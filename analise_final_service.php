<?php
/**
 * Serviço de Análise Final Consolidada
 * 
 * Integra todos os dados coletados (certidões, partes, sócios, enriquecimentos)
 * e gera um relatório final completo usando o Gemini
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class AnaliseFinalService {
    
    private $db;
    private $geminiApiKey;
    private $pastaUpload;
    
    public function __construct(DatabaseManager $db, $geminiApiKey) {
        $this->db = $db;
        $this->geminiApiKey = $geminiApiKey;
        $this->pastaUpload = __DIR__ . '/uploads';
    }
    
    /**
     * Gera análise final consolidada
     */
    public function gerarAnaliseFinal($analiseId) {
        error_log("\n╔════════════════════════════════════════════════════════════╗");
        error_log("║      ANÁLISE FINAL CONSOLIDADA - ID #{$analiseId}        ║");
        error_log("╚════════════════════════════════════════════════════════════╝");
        
        try {
            // 1. Buscar análise
            $analise = $this->db->buscarAnalisePorId($analiseId);
            if (!$analise) {
                throw new Exception("Análise não encontrada");
            }
            
            error_log("✅ Análise encontrada: " . $analise['data_criacao']);
            
            // 2. Buscar documentos originais
            $documentosOriginais = $this->db->buscarDocumentosOriginais($analiseId);
            error_log("📄 Documentos originais: " . count($documentosOriginais));
            
            // 3. Buscar certidões
            $certidoes = $this->db->buscarCertidoesAnalise($analiseId);
            error_log("📜 Certidões: " . count($certidoes));
            
            // 4. Buscar partes extraídas
            $partes = $this->db->buscarPartesAnalise($analiseId);
            error_log("👥 Partes: " . count($partes));
            
            // 5. Buscar sócios
            $socios = $this->db->buscarSociosPorAnalise($analiseId);
            error_log("🏢 Sócios: " . count($socios));
            
            // 6. ETAPA 1: Analisar cada certidão individualmente
            error_log("\n═══════════════════════════════════════════════════════════");
            error_log("ETAPA 1: Análise Individual de Certidões");
            error_log("═══════════════════════════════════════════════════════════");
            
            $resultadosCertidoes = [];
            foreach ($certidoes as $index => $certidao) {
                $num = $index + 1;
                $meta = json_decode($certidao['metadata'], true);
                $tipoCertidao = $meta['tipo_certidao'] ?? 'Desconhecido';
                
                error_log("\n[{$num}/" . count($certidoes) . "] Analisando: {$tipoCertidao}");
                error_log("   Arquivo: {$certidao['nome_arquivo']}");
                
                $resultadoCertidao = $this->analisarCertidaoIndividual($certidao);
                $resultadosCertidoes[] = $resultadoCertidao;
                
                if ($resultadoCertidao['sucesso']) {
                    error_log("   ✅ Análise concluída");
                } else {
                    error_log("   ⚠️ Falha na análise: " . ($resultadoCertidao['erro'] ?? 'Erro desconhecido'));
                }
            }
            
            error_log("\n✅ Análise individual de certidões concluída!");
            error_log("   Sucessos: " . count(array_filter($resultadosCertidoes, fn($r) => $r['sucesso'])));
            error_log("   Falhas: " . count(array_filter($resultadosCertidoes, fn($r) => !$r['sucesso'])));
            
            // 7. ETAPA 2: Gerar relatório final consolidado
            error_log("\n═══════════════════════════════════════════════════════════");
            error_log("ETAPA 2: Geração de Relatório Final Consolidado");
            error_log("═══════════════════════════════════════════════════════════");
            
            $prompt = $this->montarPromptConsolidado($analise, $partes, $socios, $certidoes, $resultadosCertidoes);
            
            // 8. Chamar Gemini apenas com documentos originais (certidões já foram analisadas)
            error_log("📦 Enviando documentos originais para análise final...");
            $htmlRelatorio = $this->chamarGeminiFinal($prompt, $documentosOriginais, $resultadosCertidoes);
            
            // 9. Atualizar relatório na análise
            $this->db->atualizarRelatorio($analiseId, $htmlRelatorio);
            
            error_log("✅ Relatório final gerado e salvo com sucesso!");
            
            return [
                'sucesso' => true,
                'total_documentos' => count($documentosOriginais),
                'total_certidoes' => count($certidoes),
                'total_partes' => count($partes),
                'total_socios' => count($socios),
                'tamanho_relatorio' => strlen($htmlRelatorio)
            ];
            
        } catch (Exception $e) {
            error_log("❌ Erro: " . $e->getMessage());
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analisa uma certidão individualmente com o Gemini
     */
    private function analisarCertidaoIndividual($certidao) {
        $meta = json_decode($certidao['metadata'], true);
        $caminhoArquivo = $this->pastaUpload . '/' . $certidao['nome_arquivo'];
        
        if (!file_exists($caminhoArquivo)) {
            return [
                'sucesso' => false,
                'erro' => 'Arquivo não encontrado',
                'certidao' => $certidao['nome_arquivo']
            ];
        }
        
        try {
            // Montar prompt específico para esta certidão
            $prompt = "╔═══════════════════════════════════════════════════════════════════════╗\n";
            $prompt .= "║           ANÁLISE INDIVIDUAL DE CERTIDÃO JUDICIAL                     ║\n";
            $prompt .= "╚═══════════════════════════════════════════════════════════════════════╝\n\n";
            
            $prompt .= "🎯 TAREFA: Analisar o PDF da certidão anexado e extrair informações.\n\n";
            
            $prompt .= "📋 DADOS DA CERTIDÃO:\n";
            $prompt .= "Tipo: " . ($meta['tipo_certidao'] ?? 'N/A') . "\n";
            if (isset($meta['nome_empresa'])) {
                $prompt .= "Empresa: " . $meta['nome_empresa'] . "\n";
                $prompt .= "CNPJ: " . $meta['cnpj'] . "\n";
            } else if (isset($meta['nome_pessoa'])) {
                $prompt .= "Pessoa: " . $meta['nome_pessoa'] . "\n";
                $prompt .= "CPF: " . $meta['cpf'] . "\n";
            }
            $prompt .= "\n";
            
            $prompt .= "🔍 O QUE EXTRAIR DO PDF:\n\n";
            $prompt .= "1. **Resultado Principal**: A certidão indica 'NADA CONSTA' ou 'NEGATIVA'?\n";
            $prompt .= "2. **Processos**: Se houver processos, liste:\n";
            $prompt .= "   - Número do processo\n";
            $prompt .= "   - Tipo de ação\n";
            $prompt .= "   - Situação atual (ativo/arquivado/baixado)\n";
            $prompt .= "   - Valor (se mencionado)\n";
            $prompt .= "3. **Data de Emissão**: Data em que a certidão foi emitida\n";
            $prompt .= "4. **Validade**: Data de validade da certidão\n";
            $prompt .= "5. **Observações**: Qualquer informação relevante\n\n";
            
            $prompt .= "📤 FORMATO DE RESPOSTA (JSON):\n\n";
            $prompt .= "Retorne APENAS um objeto JSON (sem ```json ou qualquer formatação markdown):\n\n";
            $prompt .= "{\n";
            $prompt .= '  "resultado": "NADA_CONSTA" ou "CONSTA_PROCESSO",'."\n";
            $prompt .= '  "total_processos": 0,'."\n";
            $prompt .= '  "processos": [],'."\n";
            $prompt .= '  "data_emissao": "DD/MM/YYYY",'."\n";
            $prompt .= '  "data_validade": "DD/MM/YYYY",'."\n";
            $prompt .= '  "observacoes": "texto",'."\n";
            $prompt .= '  "risco": "BAIXO" ou "MÉDIO" ou "ALTO"'."\n";
            $prompt .= "}\n\n";
            
            $prompt .= "⚠️ IMPORTANTE: Retorne APENAS o JSON, sem texto antes ou depois!\n";
            
            // Preparar arquivo
            $conteudoBase64 = base64_encode(file_get_contents($caminhoArquivo));
            
            $parts = [
                ['text' => $prompt],
                [
                    'inline_data' => [
                        'mime_type' => 'application/pdf',
                        'data' => $conteudoBase64
                    ]
                ]
            ];
            
            // Chamar Gemini
            $requestBody = [
                'contents' => [['parts' => $parts]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 2048
                ]
            ];
            
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=" . $this->geminiApiKey;
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($requestBody),
                CURLOPT_TIMEOUT => 60
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return [
                    'sucesso' => false,
                    'erro' => "API Error: HTTP {$httpCode}",
                    'certidao' => $certidao['nome_arquivo'],
                    'metadata' => $meta
                ];
            }
            
            $responseData = json_decode($response, true);
            
            if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                return [
                    'sucesso' => false,
                    'erro' => 'Resposta inválida do Gemini',
                    'certidao' => $certidao['nome_arquivo'],
                    'metadata' => $meta
                ];
            }
            
            $analiseTexto = $responseData['candidates'][0]['content']['parts'][0]['text'];
            
            // Limpar e parsear JSON
            $analiseTexto = trim($analiseTexto);
            $analiseTexto = preg_replace('/```json\s*/i', '', $analiseTexto);
            $analiseTexto = preg_replace('/```\s*$/i', '', $analiseTexto);
            $analiseTexto = trim($analiseTexto);
            
            $analiseData = json_decode($analiseTexto, true);
            
            if (!$analiseData) {
                // Se não conseguiu parsear JSON, retornar o texto mesmo
                $analiseData = [
                    'resultado' => 'ERRO_PARSE',
                    'texto_bruto' => $analiseTexto
                ];
            }
            
            return [
                'sucesso' => true,
                'certidao' => $certidao['nome_arquivo'],
                'metadata' => $meta,
                'analise' => $analiseData
            ];
            
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'certidao' => $certidao['nome_arquivo'],
                'metadata' => $meta
            ];
        }
    }
    
    /**
     * Monta prompt consolidado com todos os dados
     */
    private function montarPromptConsolidado($analise, $partes, $socios, $certidoes, $resultadosCertidoes = []) {
        date_default_timezone_set('America/Sao_Paulo');
        $dataAtual = date('d/m/Y H:i:s');
        
        $prompt = "";
        
        $prompt .= "╔═══════════════════════════════════════════════════════════════════════╗\n";
        $prompt .= "║              ANÁLISE FINAL CONSOLIDADA - DUE DILIGENCE               ║\n";
        $prompt .= "║                    Sistema de Análise Jurídica                        ║\n";
        $prompt .= "╚═══════════════════════════════════════════════════════════════════════╝\n\n";
        
        $prompt .= "🚨 INSTRUÇÕES CRÍTICAS:\n\n";
        $prompt .= "✅ As " . count($resultadosCertidoes) . " certidões JÁ FORAM ANALISADAS INDIVIDUALMENTE!\n";
        $prompt .= "   Os resultados completos estão na seção 'CERTIDÕES OFICIAIS JÁ ANALISADAS' abaixo.\n\n";
        
        $prompt .= "🎯 SUA TAREFA:\n";
        $prompt .= "- Criar relatório final consolidado usando os RESULTADOS JÁ FORNECIDOS\n";
        $prompt .= "- Analisar o documento original anexado (matrícula/escritura)\n";
        $prompt .= "- INCLUIR os resultados das certidões no relatório (não marcar como Pendente!)\n";
        $prompt .= "- Cada certidão já tem: resultado, total_processos, risco, observações\n\n";
        
        $prompt .= "❌ NÃO FAÇA:\n";
        $prompt .= "- Ignorar os resultados das certidões fornecidos abaixo\n";
        $prompt .= "- Marcar certidões como 'Pendente' (elas já foram analisadas!)\n";
        $prompt .= "- Criar checklist de documentos 'não apresentados'\n";
        $prompt .= "- Gerar relatório genérico sem usar os dados fornecidos\n\n";
        
        $prompt .= "📅 DATA DA ANÁLISE FINAL: {$dataAtual}\n";
        $prompt .= "🔢 ID DA ANÁLISE: #{$analise['id']}\n";
        $prompt .= "📆 ANÁLISE INICIAL: {$analise['data_criacao']}\n\n";
        
        $prompt .= "═══════════════════════════════════════════════════════════════════════\n";
        $prompt .= "                        CONTEXTO DA ANÁLISE                            \n";
        $prompt .= "═══════════════════════════════════════════════════════════════════════\n\n";
        
        $prompt .= "Esta é uma ANÁLISE FINAL CONSOLIDADA que integra:\n\n";
        $prompt .= "✅ " . count($partes) . " PARTE(S) PROCESSUAL(IS) identificada(s) e enriquecida(s)\n";
        $prompt .= "✅ " . count($socios) . " SÓCIO(S) extraído(s) e enriquecido(s)\n";
        $prompt .= "✅ " . count($certidoes) . " CERTIDÃO(ÕES) oficial(is) anexada(s)\n";
        $prompt .= "✅ Dados de enriquecimento de CPF e CNPJ\n";
        $prompt .= "✅ Todos os documentos originais fornecidos\n\n";
        
        // ==================================================================
        // PARTES PROCESSUAIS
        // ==================================================================
        if (!empty($partes)) {
            $prompt .= "═══════════════════════════════════════════════════════════════════════\n";
            $prompt .= "                    PARTES PROCESSUAIS IDENTIFICADAS                   \n";
            $prompt .= "═══════════════════════════════════════════════════════════════════════\n\n";
            
            foreach ($partes as $index => $parte) {
                $num = $index + 1;
                $prompt .= "PARTE #{$num}:\n";
                $prompt .= "   Nome/Razão Social: {$parte['nome']}\n";
                $prompt .= "   Tipo: {$parte['tipo_documento']}\n";
                $prompt .= "   Documento: {$parte['documento']}\n";
                $prompt .= "   Qualificação: {$parte['role']}\n";
                
                // Buscar dados de enriquecimento
                $enriquecimento = $this->db->buscarHistoricoParte($parte['id']);
                if (!empty($enriquecimento)) {
                    $prompt .= "   \n   📊 DADOS ENRIQUECIDOS:\n";
                    
                    foreach ($enriquecimento as $enr) {
                        if ($enr['sucesso']) {
                            $dados = json_decode($enr['dados_json'], true);
                            
                            if ($parte['tipo_documento'] === 'CPF' && isset($dados['dados'])) {
                                $d = $dados['dados'];
                                if (isset($d['nome_mae'])) $prompt .= "      • Nome da Mãe: {$d['nome_mae']}\n";
                                if (isset($d['nascimento'])) $prompt .= "      • Nascimento: {$d['nascimento']}\n";
                                if (isset($d['sexo'])) $prompt .= "      • Sexo: {$d['sexo']}\n";
                            }
                            
                            if ($parte['tipo_documento'] === 'CNPJ' && isset($dados['razao_social'])) {
                                if (isset($dados['razao_social'])) $prompt .= "      • Razão Social: {$dados['razao_social']}\n";
                                if (isset($dados['nome_fantasia'])) $prompt .= "      • Nome Fantasia: {$dados['nome_fantasia']}\n";
                                if (isset($dados['situacao_cadastral'])) $prompt .= "      • Situação: {$dados['situacao_cadastral']}\n";
                                if (isset($dados['capital_social'])) $prompt .= "      • Capital Social: R$ {$dados['capital_social']}\n";
                            }
                        }
                    }
                }
                
                $prompt .= "\n";
            }
        }
        
        // ==================================================================
        // SÓCIOS
        // ==================================================================
        if (!empty($socios)) {
            $prompt .= "═══════════════════════════════════════════════════════════════════════\n";
            $prompt .= "                      SÓCIOS IDENTIFICADOS                             \n";
            $prompt .= "═══════════════════════════════════════════════════════════════════════\n\n";
            
            $sociosPorEmpresa = [];
            foreach ($socios as $socio) {
                $sociosPorEmpresa[$socio['empresa_nome']][] = $socio;
            }
            
            foreach ($sociosPorEmpresa as $empresa => $sociosDaEmpresa) {
                $prompt .= "🏢 EMPRESA: {$empresa}\n\n";
                
                foreach ($sociosDaEmpresa as $index => $socio) {
                    $num = $index + 1;
                    $prompt .= "   SÓCIO #{$num}:\n";
                    $prompt .= "      Nome: {$socio['socio_nome']}\n";
                    $prompt .= "      CPF: " . ($socio['socio_cpf'] ?? 'N/A') . "\n";
                    $prompt .= "      Qualificação: {$socio['socio_qualificacao']}\n";
                    
                    if ($socio['socio_enriquecido']) {
                        $prompt .= "      \n      📊 DADOS ENRIQUECIDOS:\n";
                        if ($socio['socio_nome_mae']) $prompt .= "         • Nome da Mãe: {$socio['socio_nome_mae']}\n";
                        if ($socio['socio_nascimento']) $prompt .= "         • Nascimento: {$socio['socio_nascimento']}\n";
                        if ($socio['socio_rg']) $prompt .= "         • RG: {$socio['socio_rg']}\n";
                        if ($socio['socio_sexo']) $prompt .= "         • Sexo: {$socio['socio_sexo']}\n";
                    }
                    
                    $prompt .= "\n";
                }
                
                $prompt .= "\n";
            }
        }
        
        // ==================================================================
        // CERTIDÕES JÁ ANALISADAS
        // ==================================================================
        if (!empty($resultadosCertidoes)) {
            $prompt .= "═══════════════════════════════════════════════════════════════════════\n";
            $prompt .= "           CERTIDÕES OFICIAIS JÁ ANALISADAS INDIVIDUALMENTE           \n";
            $prompt .= "═══════════════════════════════════════════════════════════════════════\n\n";
            
            $prompt .= "✅ As certidões abaixo JÁ FORAM ANALISADAS. Use os resultados fornecidos!\n\n";
            
            foreach ($resultadosCertidoes as $index => $resultado) {
                $num = $index + 1;
                $meta = $resultado['metadata'] ?? [];
                $analise = $resultado['analise'] ?? [];
                
                $prompt .= "───────────────────────────────────────────────────────────────────────\n";
                $prompt .= "CERTIDÃO #{$num}: " . ($meta['tipo_certidao'] ?? 'N/A') . "\n";
                $prompt .= "───────────────────────────────────────────────────────────────────────\n";
                
                if (isset($meta['nome_empresa'])) {
                    $prompt .= "Empresa: {$meta['nome_empresa']}\n";
                    $prompt .= "CNPJ: {$meta['cnpj']}\n";
                } else if (isset($meta['nome_pessoa'])) {
                    $prompt .= "Pessoa: {$meta['nome_pessoa']}\n";
                    $prompt .= "CPF: {$meta['cpf']}\n";
                }
                
                if ($resultado['sucesso']) {
                    $prompt .= "\n📊 RESULTADO DA ANÁLISE:\n";
                    $prompt .= "Resultado: " . ($analise['resultado'] ?? 'N/A') . "\n";
                    $prompt .= "Total de Processos: " . ($analise['total_processos'] ?? 0) . "\n";
                    $prompt .= "Risco: " . ($analise['risco'] ?? 'N/A') . "\n";
                    
                    if (!empty($analise['processos'])) {
                        $prompt .= "\nProcessos encontrados:\n";
                        foreach ($analise['processos'] as $proc) {
                            $prompt .= "  • " . json_encode($proc, JSON_UNESCAPED_UNICODE) . "\n";
                        }
                    }
                    
                    if (!empty($analise['data_emissao'])) {
                        $prompt .= "Data de Emissão: {$analise['data_emissao']}\n";
                    }
                    
                    if (!empty($analise['observacoes'])) {
                        $prompt .= "Observações: {$analise['observacoes']}\n";
                    }
                } else {
                    $prompt .= "\n❌ ERRO NA ANÁLISE: " . ($resultado['erro'] ?? 'Desconhecido') . "\n";
                }
                
                $prompt .= "\n";
            }
            
            $prompt .= "═══════════════════════════════════════════════════════════════════════\n";
            $prompt .= "TOTAL DE CERTIDÕES ANALISADAS: " . count($resultadosCertidoes) . "\n";
            $prompt .= "═══════════════════════════════════════════════════════════════════════\n\n";
        }
        
        // ==================================================================
        // INSTRUÇÕES FINAIS
        // ==================================================================
        $prompt .= "═══════════════════════════════════════════════════════════════════════\n";
        $prompt .= "                        INSTRUÇÕES DE ANÁLISE                          \n";
        $prompt .= "═══════════════════════════════════════════════════════════════════════\n\n";
        
        $prompt .= "🎯 SUA MISSÃO:\n\n";
        $prompt .= "Você deve gerar um RELATÓRIO FINAL DE DUE DILIGENCE CONSOLIDADO que:\n\n";
        
        $prompt .= "1. ✅ INTEGRE todos os dados acima com os documentos fornecidos\n";
        $prompt .= "2. ✅ USE OS RESULTADOS DAS CERTIDÕES JÁ FORNECIDOS (elas já foram analisadas!)\n";
        $prompt .= "3. ✅ ANALISE o documento original anexado (matrícula/escritura)\n";
        $prompt .= "4. ✅ IDENTIFIQUE riscos, inconsistências e alertas\n";
        $prompt .= "5. ✅ VALIDE se os sócios extraídos constam como vendedores/outorgantes\n";
        $prompt .= "6. ✅ INCLUA os resultados das certidões no relatório (veja seção acima)\n";
        $prompt .= "7. ✅ CORRELACIONE dados enriquecidos com informações dos documentos\n";
        $prompt .= "8. ✅ APRESENTE conclusões e recomendações profissionais\n\n";
        
        $prompt .= "📋 ESTRUTURA OBRIGATÓRIA DO RELATÓRIO:\n\n";
        
        $prompt .= "O relatório DEVE conter estas seções (nesta ordem):\n\n";
        
        $prompt .= "1. SUMÁRIO EXECUTIVO\n";
        $prompt .= "   - Resumo geral da análise\n";
        $prompt .= "   - Status: APROVADO / APROVADO COM RESSALVAS / REPROVADO\n";
        $prompt .= "   - Principais alertas e riscos\n";
        $prompt .= "   - Métricas: total de partes, sócios, certidões, documentos\n\n";
        
        $prompt .= "2. PARTES PROCESSUAIS IDENTIFICADAS\n";
        $prompt .= "   - Lista de todas as partes com dados completos\n";
        $prompt .= "   - Dados de enriquecimento (CPF/CNPJ)\n";
        $prompt .= "   - Sócios das empresas vendedoras\n\n";
        
        $prompt .= "3. ANÁLISE DE CERTIDÕES ⚠️ SEÇÃO OBRIGATÓRIA - USE OS DADOS JÁ FORNECIDOS!\n\n";
        $prompt .= "   🚨 ATENÇÃO: As " . count($resultadosCertidoes) . " certidões JÁ FORAM ANALISADAS!\n";
        $prompt .= "   Todos os resultados estão na seção 'CERTIDÕES OFICIAIS JÁ ANALISADAS' acima.\n\n";
        
        $prompt .= "   🎯 SUA TAREFA OBRIGATÓRIA:\n";
        $prompt .= "   Copiar os resultados fornecidos acima e formatá-los como cards HTML.\n";
        $prompt .= "   NÃO analise PDFs novamente! USE OS DADOS QUE JÁ ESTÃO NO PROMPT!\n\n";
        
        $prompt .= "   Para CADA uma das " . count($resultadosCertidoes) . " certidões listadas acima:\n";
        $prompt .= "   a) Pegue o nome da certidão (ex: 'TJGO_Criminal') e identificação (CPF/CNPJ)\n";
        $prompt .= "   b) Copie o campo 'Resultado' (ex: 'NADA_CONSTA')\n";
        $prompt .= "   c) Copie o campo 'Total de Processos' (ex: 0)\n";
        $prompt .= "   d) Copie o campo 'Risco' (ex: 'BAIXO')\n";
        $prompt .= "   e) Se houver 'Observações', inclua-as também\n";
        $prompt .= "   f) Use cor VERDE para NADA_CONSTA/BAIXO, AMARELO para MÉDIO, VERMELHO para ALTO\n\n";
        
        $prompt .= "   📋 EXEMPLO (use exatamente os dados fornecidos acima):\n\n";
        $prompt .= "   <div class='bg-green-50 border-l-4 border-green-500 p-4 mb-3'>\n";
        $prompt .= "     <h4 class='font-bold text-green-900'>✅ TJGO Criminal - LUIZA SPENGLER COELHO</h4>\n";
        $prompt .= "     <p><strong>CPF:</strong> 01006423141</p>\n";
        $prompt .= "     <p><strong>Resultado:</strong> NADA_CONSTA</p>\n";
        $prompt .= "     <p><strong>Processos:</strong> 0 (zero)</p>\n";
        $prompt .= "     <p><strong>Data de Emissão:</strong> 04/11/2025</p>\n";
        $prompt .= "     <p class='text-green-700 font-semibold'>🛡️ Risco: BAIXO</p>\n";
        $prompt .= "     <p class='text-sm mt-2'><em>Obs: Certidão Negativa de Ações Criminais...</em></p>\n";
        $prompt .= "   </div>\n\n";
        
        $prompt .= "   🚨 CRÍTICO: Crie um card para CADA uma das " . count($resultadosCertidoes) . " certidões!\n";
        $prompt .= "   NÃO marque como 'Pendente'! Todos os resultados já estão disponíveis acima!\n\n";
        
        $prompt .= "4. ANÁLISE DE DOCUMENTOS ORIGINAIS\n";
        $prompt .= "   - Análise de cada documento fornecido\n";
        $prompt .= "   - Validade, autenticidade, conformidade\n";
        $prompt .= "   - Correlação com certidões e partes\n\n";
        
        $prompt .= "5. VALIDAÇÕES CRUZADAS\n";
        $prompt .= "   - Conferência de dados entre documentos\n";
        $prompt .= "   - Verificação de consistência\n";
        $prompt .= "   - Identificação de divergências\n\n";
        
        $prompt .= "6. RISCOS E ALERTAS\n";
        $prompt .= "   - Lista consolidada de todos os riscos\n";
        $prompt .= "   - Classificação por gravidade (Alto/Médio/Baixo)\n";
        $prompt .= "   - Impacto potencial\n\n";
        
        $prompt .= "7. CONCLUSÃO E RECOMENDAÇÕES\n";
        $prompt .= "   - Parecer final sobre a operação\n";
        $prompt .= "   - Recomendações específicas\n";
        $prompt .= "   - Próximos passos sugeridos\n\n";
        
        $prompt .= "🎨 FORMATAÇÃO HTML:\n\n";
        $prompt .= "Use Tailwind CSS para estilização.\n";
        $prompt .= "Mantenha o padrão elegante e profissional estabelecido.\n";
        $prompt .= "Use cards coloridos para diferentes seções (azul, verde, laranja, vermelho).\n";
        $prompt .= "Destaque riscos em vermelho, alertas em amarelo, positivos em verde.\n\n";
        
        $prompt .= "⚠️ IMPORTANTE:\n";
        $prompt .= "- Retorne APENAS o HTML completo do relatório\n";
        $prompt .= "- NÃO inclua tags <html>, <head> ou <body>\n";
        $prompt .= "- Comece direto com os elementos de conteúdo\n";
        $prompt .= "- Use classes Tailwind CSS para estilização\n\n";
        
        $prompt .= "═══════════════════════════════════════════════════════════════════════\n";
        $prompt .= "                    ✅ CHECKLIST OBRIGATÓRIO                           \n";
        $prompt .= "═══════════════════════════════════════════════════════════════════════\n\n";
        
        $prompt .= "Antes de finalizar o relatório, VERIFIQUE OBRIGATORIAMENTE:\n\n";
        $prompt .= "□ Sumário Executivo com status e métricas\n";
        $prompt .= "□ Seção 'PARTES PROCESSUAIS' com todas as partes listadas\n";
        $prompt .= "□ Seção 'ANÁLISE DE CERTIDÕES' com " . count($resultadosCertidoes) . " CARDS (um para cada certidão)\n";
        $prompt .= "□ TODOS os " . count($resultadosCertidoes) . " cards de certidões criados (usando os dados fornecidos acima)\n";
        $prompt .= "□ NENHUMA certidão marcada como 'Pendente' (todas já foram analisadas!)\n";
        $prompt .= "□ Cada card com: Nome, CPF/CNPJ, Resultado, Processos, Risco, Observações\n";
        $prompt .= "□ Cards coloridos: Verde (NADA_CONSTA/BAIXO), Amarelo (MÉDIO), Vermelho (ALTO)\n";
        $prompt .= "□ Análise dos documentos originais\n";
        $prompt .= "□ Validações cruzadas\n";
        $prompt .= "□ Seção de riscos e alertas consolidados\n";
        $prompt .= "□ Conclusão e recomendações\n\n";
        
        $prompt .= "🚨 VERIFICAÇÃO FINAL OBRIGATÓRIA:\n";
        $prompt .= "Antes de retornar o HTML, conte quantos cards de certidões você criou.\n";
        $prompt .= "Se não criou EXATAMENTE " . count($resultadosCertidoes) . " cards, VOCÊ FALHOU!\n";
        $prompt .= "Volte e crie um card para cada certidão listada na seção acima.\n\n";
        
        $prompt .= "✅ Os dados das certidões estão na seção 'CERTIDÕES OFICIAIS JÁ ANALISADAS'.\n";
        $prompt .= "✅ NÃO crie sua própria análise! COPIE os resultados fornecidos!\n";
        $prompt .= "✅ Se marcar alguma certidão como 'Pendente', você IGNOROU as instruções!\n\n";
        
        return $prompt;
    }
    
    /**
     * Chama Gemini para relatório final (com resultados de certidões já prontos)
     */
    private function chamarGeminiFinal($prompt, $documentosOriginais, $resultadosCertidoes) {
        error_log("🤖 Gerando relatório final consolidado...");
        error_log("   📄 Documentos originais: " . count($documentosOriginais));
        error_log("   📜 Certidões analisadas: " . count($resultadosCertidoes));
        
        // Salvar prompt para debug
        $debugPromptFile = __DIR__ . '/debug_prompt_relatorio_final_' . time() . '.txt';
        file_put_contents($debugPromptFile, $prompt);
        error_log("💾 Prompt salvo em: " . basename($debugPromptFile));
        
        $parts = [];
        $parts[] = ['text' => $prompt];
        
        // Adicionar apenas documentos originais (certidões já foram analisadas)
        foreach ($documentosOriginais as $arquivo) {
            $caminhoArquivo = $this->pastaUpload . '/' . $arquivo['nome_arquivo'];
            
            if (file_exists($caminhoArquivo)) {
                $conteudoBase64 = base64_encode(file_get_contents($caminhoArquivo));
                $mimeType = $arquivo['tipo_arquivo'] ?? 'application/pdf';
                
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => $conteudoBase64
                    ]
                ];
                
                error_log("   📄 Anexado: " . $arquivo['nome_arquivo']);
            }
        }
        
        $requestBody = [
            'contents' => [
                [
                    'parts' => $parts
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'topK' => 32,
                'topP' => 1,
                'maxOutputTokens' => 16384
            ]
        ];
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=" . $this->geminiApiKey;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_TIMEOUT => 300
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro na API Gemini: HTTP {$httpCode}");
        }
        
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Resposta inválida do Gemini");
        }
        
        $htmlRelatorio = $responseData['candidates'][0]['content']['parts'][0]['text'];
        
        // Salvar resposta bruta para debug
        $debugResponseFile = __DIR__ . '/debug_response_relatorio_final_' . time() . '.html';
        file_put_contents($debugResponseFile, $htmlRelatorio);
        error_log("💾 Resposta do Gemini salva em: " . basename($debugResponseFile));
        
        $htmlRelatorio = $this->limparHtml($htmlRelatorio);
        
        error_log("✅ Relatório final gerado: " . strlen($htmlRelatorio) . " caracteres");
        
        // Verificar se menciona certidões
        if (stripos($htmlRelatorio, 'certidão') === false && stripos($htmlRelatorio, 'certidao') === false) {
            error_log("⚠️ ALERTA: Relatório não menciona 'certidão'!");
        }
        
        return $htmlRelatorio;
    }
    
    /**
     * Chama Gemini com todos os documentos (método antigo - deprecado)
     */
    private function chamarGemini($prompt, $arquivos) {
        error_log("🤖 Chamando Gemini com " . count($arquivos) . " arquivo(s)...");
        
        // Salvar prompt para debug
        $debugPromptFile = __DIR__ . '/debug_prompt_analise_final_' . time() . '.txt';
        file_put_contents($debugPromptFile, $prompt);
        error_log("💾 Prompt salvo em: " . basename($debugPromptFile));
        
        $parts = [];
        $parts[] = ['text' => $prompt];
        
        $certidoesAnexadas = 0;
        $documentosOriginais = 0;
        
        // Adicionar todos os arquivos
        foreach ($arquivos as $arquivo) {
            $caminhoArquivo = $this->pastaUpload . '/' . $arquivo['nome_arquivo'];
            
            if (file_exists($caminhoArquivo)) {
                $conteudoBase64 = base64_encode(file_get_contents($caminhoArquivo));
                $mimeType = $arquivo['tipo_arquivo'] ?? 'application/pdf';
                
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => $conteudoBase64
                    ]
                ];
                
                // Contar certidões vs documentos
                if (isset($arquivo['metadata']) && !empty($arquivo['metadata'])) {
                    $certidoesAnexadas++;
                    error_log("   📜 Certidão anexada: " . $arquivo['nome_arquivo']);
                } else {
                    $documentosOriginais++;
                    error_log("   📄 Documento original anexado: " . $arquivo['nome_arquivo']);
                }
            } else {
                error_log("   ⚠️ Arquivo não encontrado: " . $caminhoArquivo);
            }
        }
        
        error_log("📊 Resumo de anexos:");
        error_log("   📜 Certidões: {$certidoesAnexadas}");
        error_log("   📄 Documentos originais: {$documentosOriginais}");
        
        $requestBody = [
            'contents' => [
                [
                    'parts' => $parts
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'topK' => 32,
                'topP' => 1,
                'maxOutputTokens' => 16384  // Aumentado para permitir análise completa
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_NONE'
                ]
            ]
        ];
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=" . $this->geminiApiKey;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_TIMEOUT => 300
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro na API Gemini: HTTP {$httpCode}");
        }
        
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Resposta inválida do Gemini");
        }
        
        $htmlRelatorio = $responseData['candidates'][0]['content']['parts'][0]['text'];
        
        // Salvar resposta bruta para debug
        $debugResponseFile = __DIR__ . '/debug_response_analise_final_' . time() . '.html';
        file_put_contents($debugResponseFile, $htmlRelatorio);
        error_log("💾 Resposta do Gemini salva em: " . basename($debugResponseFile));
        
        $htmlRelatorio = $this->limparHtml($htmlRelatorio);
        
        error_log("✅ Relatório gerado: " . strlen($htmlRelatorio) . " caracteres");
        
        // Verificar se menciona certidões
        if (stripos($htmlRelatorio, 'certidão') === false && stripos($htmlRelatorio, 'certidao') === false) {
            error_log("⚠️ ALERTA: Relatório não menciona 'certidão'! Pode ter ignorado as certidões.");
        }
        
        return $htmlRelatorio;
    }
    
    /**
     * Limpa HTML de marcações markdown
     */
    private function limparHtml($html) {
        $html = preg_replace('/```html\s*/i', '', $html);
        $html = preg_replace('/```\s*$/i', '', $html);
        $html = trim($html);
        return $html;
    }
}

