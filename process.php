<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carregar configurações
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/judit_service.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/enriquecimento.php';

set_time_limit(PROCESS_TIMEOUT);

// Usar chave da configuração se não for fornecida no POST
if (empty($_POST['gemini_api_key'])) {
    $_POST['gemini_api_key'] = GEMINI_API_KEY;
}

// Função para ler o prompt do arquivo
function getPrompt($juditData = null, $partiesData = null) {
    // Adicionar data e hora atual no início do prompt
    date_default_timezone_set('America/Sao_Paulo');
    $dataAtual = date('d/m/Y H:i:s');
    
    $promptFile = __DIR__ . '/prompt.txt';
    $basePrompt = "";

    if (file_exists($promptFile)) {
        $basePrompt = file_get_contents($promptFile);
    } else {
        $basePrompt = "Analise os documentos imobiliários fornecidos e gere um relatório de due diligence completo.";
    }

    // Adicionar data atual e instruções específicas para um relatório mais detalhado
    $additionalInstructions = "\n\n";
    $additionalInstructions .= "═══════════════════════════════════════════════════════════════\n";
    $additionalInstructions .= "📅 DATA DA ANÁLISE: {$dataAtual}\n";
    $additionalInstructions .= "═══════════════════════════════════════════════════════════════\n\n";
    $additionalInstructions .= "═══════════════════════════════════════════════════════════════\n";
    $additionalInstructions .= "                 INSTRUÇÕES DE FORMATAÇÃO HTML                  \n";
    $additionalInstructions .= "═══════════════════════════════════════════════════════════════\n\n";
    
    $additionalInstructions .= "⚠️ ATENÇÃO CRÍTICA: Você DEVE retornar um relatório PROFISSIONAL, LIMPO e ELEGANTE em HTML PURO!\n\n";
    
    $additionalInstructions .= "🎨 PADRÃO DE DESIGN PROFISSIONAL:\n\n";
    
    $additionalInstructions .= "⚡ PRINCÍPIOS OBRIGATÓRIOS:\n";
    $additionalInstructions .= "1. SIMPLICIDADE - Evite caixas dentro de caixas\n";
    $additionalInstructions .= "2. CONSISTÊNCIA - Use sempre os mesmos estilos para elementos similares\n";
    $additionalInstructions .= "3. HIERARQUIA - Títulos grandes, conteúdo proporcional\n";
    $additionalInstructions .= "4. ESPAÇAMENTO - Respire! Use mb-6 entre seções, mb-4 entre elementos\n";
    $additionalInstructions .= "5. ELEGÂNCIA - Menos é mais. Sem exageros.\n\n";
    
    $additionalInstructions .= "🚫 NÃO FAÇA:\n";
    $additionalInstructions .= "- ❌ Caixas coloridas dentro de caixas coloridas\n";
    $additionalInstructions .= "- ❌ Muitos gradientes (apenas no sumário executivo)\n";
    $additionalInstructions .= "- ❌ Bordas e sombras em tudo\n";
    $additionalInstructions .= "- ❌ Cores conflitantes\n";
    $additionalInstructions .= "- ❌ Divs aninhadas sem necessidade\n\n";
    
    $additionalInstructions .= "✅ ESTRUTURA PADRÃO ELEGANTE:\n\n";
    
    $additionalInstructions .= "1️⃣ TÍTULO PRINCIPAL (Simples e Limpo):\n";
    $additionalInstructions .= "<h1 class=\"text-4xl font-bold text-gray-900 mb-2\">📋 RELATÓRIO DE DUE DILIGENCE IMOBILIÁRIA</h1>\n";
    $additionalInstructions .= "<div class=\"w-24 h-1 bg-blue-600 mb-8\"></div>\n\n";
    
    $additionalInstructions .= "2️⃣ SUMÁRIO EXECUTIVO (Design Especial):\n";
    $additionalInstructions .= "<div class=\"bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-lg overflow-hidden mb-8 shadow-lg\">\n";
    $additionalInstructions .= "  <div class=\"p-6\">\n";
    $additionalInstructions .= "    <h2 class=\"text-2xl font-bold mb-6 flex items-center\">\n";
    $additionalInstructions .= "      <span class=\"text-3xl mr-3\">📊</span>SUMÁRIO EXECUTIVO\n";
    $additionalInstructions .= "    </h2>\n";
    $additionalInstructions .= "    \n";
    $additionalInstructions .= "    <div class=\"grid grid-cols-1 md:grid-cols-2 gap-6 mb-6\">\n";
    $additionalInstructions .= "      <div class=\"bg-white bg-opacity-10 rounded-lg p-4\">\n";
    $additionalInstructions .= "        <p class=\"text-xs uppercase tracking-wide opacity-90 mb-2\">Classificação Geral</p>\n";
    $additionalInstructions .= "        <p class=\"text-3xl font-bold\">[🟢/🟡/🔴]</p>\n";
    $additionalInstructions .= "        <p class=\"text-sm mt-2 opacity-90\">[Baixo Risco / Atenção / Alto Risco]</p>\n";
    $additionalInstructions .= "      </div>\n";
    $additionalInstructions .= "      <div class=\"bg-white bg-opacity-10 rounded-lg p-4\">\n";
    $additionalInstructions .= "        <p class=\"text-xs uppercase tracking-wide opacity-90 mb-2\">Documentos Analisados</p>\n";
    $additionalInstructions .= "        <p class=\"text-3xl font-bold\">[NÚMERO]</p>\n";
    $additionalInstructions .= "        <p class=\"text-sm mt-2 opacity-90\">arquivos processados</p>\n";
    $additionalInstructions .= "      </div>\n";
    $additionalInstructions .= "    </div>\n";
    $additionalInstructions .= "    \n";
    $additionalInstructions .= "    <div class=\"border-t border-white border-opacity-20 pt-4\">\n";
    $additionalInstructions .= "      <p class=\"text-sm font-semibold mb-3 uppercase tracking-wide\">Principais Conclusões:</p>\n";
    $additionalInstructions .= "      <ul class=\"space-y-2\">\n";
    $additionalInstructions .= "        <li class=\"flex items-start\">\n";
    $additionalInstructions .= "          <span class=\"mr-2 mt-1\">✓</span>\n";
    $additionalInstructions .= "          <span class=\"text-sm\">[Conclusão 1]</span>\n";
    $additionalInstructions .= "        </li>\n";
    $additionalInstructions .= "        <li class=\"flex items-start\">\n";
    $additionalInstructions .= "          <span class=\"mr-2 mt-1\">✓</span>\n";
    $additionalInstructions .= "          <span class=\"text-sm\">[Conclusão 2]</span>\n";
    $additionalInstructions .= "        </li>\n";
    $additionalInstructions .= "        <li class=\"flex items-start\">\n";
    $additionalInstructions .= "          <span class=\"mr-2 mt-1\">✓</span>\n";
    $additionalInstructions .= "          <span class=\"text-sm\">[Conclusão 3]</span>\n";
    $additionalInstructions .= "        </li>\n";
    $additionalInstructions .= "      </ul>\n";
    $additionalInstructions .= "    </div>\n";
    $additionalInstructions .= "  </div>\n";
    $additionalInstructions .= "</div>\n\n";
    
    $additionalInstructions .= "3️⃣ TÍTULOS DE SEÇÃO (Sem caixas, só título):\n";
    $additionalInstructions .= "<h2 class=\"text-2xl font-bold text-gray-900 mt-8 mb-4 pb-2 border-b-2 border-gray-200\">\n";
    $additionalInstructions .= "  <span class=\"mr-2\">[EMOJI]</span>[TÍTULO]\n";
    $additionalInstructions .= "</h2>\n\n";
    
    $additionalInstructions .= "4️⃣ CONTEÚDO (Texto direto, sem caixas):\n";
    $additionalInstructions .= "<p class=\"text-gray-700 mb-4 leading-relaxed\">[Parágrafo de texto]</p>\n\n";
    
    $additionalInstructions .= "5️⃣ LISTAS (Simples e limpas):\n";
    $additionalInstructions .= "<ul class=\"space-y-2 mb-6\">\n";
    $additionalInstructions .= "  <li class=\"flex items-start\">\n";
    $additionalInstructions .= "    <span class=\"text-blue-600 mr-2\">•</span>\n";
    $additionalInstructions .= "    <span class=\"text-gray-700\">[Item]</span>\n";
    $additionalInstructions .= "  </li>\n";
    $additionalInstructions .= "</ul>\n\n";
    
    $additionalInstructions .= "6️⃣ ALERTAS (Minimalistas, só quando necessário):\n";
    $additionalInstructions .= "   - CRÍTICO: <div class=\"border-l-4 border-red-500 bg-red-50 p-3 mb-4\"><p class=\"text-red-800 text-sm\"><strong>🚨 Crítico:</strong> [texto]</p></div>\n";
    $additionalInstructions .= "   - AVISO: <div class=\"border-l-4 border-yellow-500 bg-yellow-50 p-3 mb-4\"><p class=\"text-yellow-800 text-sm\"><strong>⚠️ Atenção:</strong> [texto]</p></div>\n";
    $additionalInstructions .= "   - INFO: <div class=\"border-l-4 border-blue-500 bg-blue-50 p-3 mb-4\"><p class=\"text-blue-800 text-sm\"><strong>ℹ️ Info:</strong> [texto]</p></div>\n";
    $additionalInstructions .= "   - OK: <div class=\"border-l-4 border-green-500 bg-green-50 p-3 mb-4\"><p class=\"text-green-800 text-sm\"><strong>✅ Conforme:</strong> [texto]</p></div>\n\n";
    
    $additionalInstructions .= "7️⃣ TABELAS (Simples e elegantes):\n";
    $additionalInstructions .= "<div class=\"overflow-x-auto mb-6\">\n";
    $additionalInstructions .= "  <table class=\"min-w-full bg-white border border-gray-200\">\n";
    $additionalInstructions .= "    <thead class=\"bg-gray-800 text-white\">\n";
    $additionalInstructions .= "      <tr>\n";
    $additionalInstructions .= "        <th class=\"px-4 py-3 text-left text-sm font-semibold\">[Coluna 1]</th>\n";
    $additionalInstructions .= "        <th class=\"px-4 py-3 text-left text-sm font-semibold\">[Coluna 2]</th>\n";
    $additionalInstructions .= "      </tr>\n";
    $additionalInstructions .= "    </thead>\n";
    $additionalInstructions .= "    <tbody class=\"divide-y divide-gray-200\">\n";
    $additionalInstructions .= "      <tr class=\"hover:bg-gray-50\">\n";
    $additionalInstructions .= "        <td class=\"px-4 py-3 text-sm text-gray-900\">[Dado 1]</td>\n";
    $additionalInstructions .= "        <td class=\"px-4 py-3 text-sm text-gray-700\">[Dado 2]</td>\n";
    $additionalInstructions .= "      </tr>\n";
    $additionalInstructions .= "    </tbody>\n";
    $additionalInstructions .= "  </table>\n";
    $additionalInstructions .= "</div>\n\n";
    
    $additionalInstructions .= "8️⃣ BADGES (Pequenos e discretos):\n";
    $additionalInstructions .= "   <span class=\"px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-800\">OK</span>\n";
    $additionalInstructions .= "   <span class=\"px-2 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-800\">Atenção</span>\n";
    $additionalInstructions .= "   <span class=\"px-2 py-1 text-xs font-medium rounded bg-red-100 text-red-800\">Crítico</span>\n\n";
    
    $additionalInstructions .= "9️⃣ DADOS EM GRID (Sem caixas, só texto organizado):\n";
    $additionalInstructions .= "<div class=\"grid grid-cols-2 gap-x-8 gap-y-3 mb-6\">\n";
    $additionalInstructions .= "  <div>\n";
    $additionalInstructions .= "    <p class=\"text-sm text-gray-500\">Matrícula</p>\n";
    $additionalInstructions .= "    <p class=\"text-base font-semibold text-gray-900\">25.936</p>\n";
    $additionalInstructions .= "  </div>\n";
    $additionalInstructions .= "  <div>\n";
    $additionalInstructions .= "    <p class=\"text-sm text-gray-500\">Cartório</p>\n";
    $additionalInstructions .= "    <p class=\"text-base font-semibold text-gray-900\">5º Ofício</p>\n";
    $additionalInstructions .= "  </div>\n";
    $additionalInstructions .= "</div>\n\n";
    
    $additionalInstructions .= "🔟 SEÇÕES OBRIGATÓRIAS DO RELATÓRIO:\n";
    $additionalInstructions .= "   1. Sumário Executivo (único com gradiente)\n";
    $additionalInstructions .= "   2. Identificação do Imóvel (grid de dados)\n";
    $additionalInstructions .= "   3. Análise da Matrícula (tabela)\n";
    $additionalInstructions .= "   4. Partes Identificadas (tabela)\n";
    $additionalInstructions .= "   5. Certidões (tabela ou lista)\n";
    $additionalInstructions .= "   6. Análise de Riscos (texto + alertas se necessário)\n";
    $additionalInstructions .= "   7. Conclusões e Recomendações (texto simples)\n\n";
    
    $additionalInstructions .= "═══════════════════════════════════════════════════════════════\n";
    $additionalInstructions .= "                 INSTRUÇÕES DE CONTEÚDO                         \n";
    $additionalInstructions .= "═══════════════════════════════════════════════════════════════\n\n";
    
    $additionalInstructions .= "📋 REQUISITOS DO RELATÓRIO:\n\n";
    $additionalInstructions .= "- Este relatório deve ser MUITO DETALHADO e EXTENSO, com no mínimo 5.000 palavras\n";
    $additionalInstructions .= "- Analise CADA DOCUMENTO enviado individualmente e em detalhes\n";
    $additionalInstructions .= "- Crie tabelas HTML detalhadas para TODOS os dados encontrados\n";
    $additionalInstructions .= "- Explique TODOS os riscos encontrados com base legal completa\n";
    $additionalInstructions .= "- Inclua seções extensas de análise jurídica para cada aspecto\n";
    $additionalInstructions .= "- Detalhe TODAS as certidões, mesmo as que estão corretas\n";
    $additionalInstructions .= "- Forneça recomendações específicas e detalhadas para cada situação\n";
    $additionalInstructions .= "- Seja exaustivo na análise - quanto mais detalhado, melhor\n";
    $additionalInstructions .= "- Inclua citações legais completas com artigos e leis específicas\n";
    $additionalInstructions .= "- Analise TODOS os aspectos possíveis dos documentos fornecidos\n\n";

    // Adicionar dados das partes do processo extraídas
    if ($partiesData && !empty($partiesData)) {
        $additionalInstructions .= formatProcessParties($partiesData);
    }

    // Adicionar dados da Judit se disponíveis
    if ($juditData && !empty($juditData)) {
        $additionalInstructions .= JuditService::formatForPrompt($juditData);
    }

    return $additionalInstructions . $basePrompt;
}

// Função para formatar partes do processo para o prompt
function formatProcessParties($parties) {
    if (empty($parties) || !is_array($parties)) {
        return "\n\n**PARTES DO PROCESSO:** Nenhuma parte processual foi encontrada nos documentos.\n";
    }

    $formatted = "\n\n";
    $formatted .= "╔═══════════════════════════════════════════════════════════════╗\n";
    $formatted .= "║        PARTES IDENTIFICADAS EM PROCESSOS JUDICIAIS          ║\n";
    $formatted .= "║           (Extraído Automaticamente via IA)                 ║\n";
    $formatted .= "╚═══════════════════════════════════════════════════════════════╝\n\n";
    
    $formatted .= "🎯 **ATENÇÃO:** O sistema identificou automaticamente as seguintes partes em processos judiciais.\n";
    $formatted .= "Você DEVE incluir estas informações em uma seção especial do relatório.\n\n";

    // Agrupar partes por fonte/origem
    $bySource = [];
    foreach ($parties as $party) {
        $source = $party['source'] ?? 'Documento';
        if (!isset($bySource[$source])) {
            $bySource[$source] = [];
        }
        $bySource[$source][] = $party;
    }

    $formatted .= "📊 **RESUMO GERAL:**\n";
    $formatted .= "- Total de partes identificadas: " . count($parties) . "\n";
    $formatted .= "- Total de fontes/documentos: " . count($bySource) . "\n\n";

    // Contar por tipo de documento
    $cpfCount = 0;
    $cnpjCount = 0;
    $cpfEncontrado = 0;
    $cnpjEncontrado = 0;
    
    foreach ($parties as $party) {
        if ($party['document_type'] === 'CPF') {
            $cpfCount++;
            if ($party['document'] !== 'NAOENCONTRADO') $cpfEncontrado++;
        } else if ($party['document_type'] === 'CNPJ') {
            $cnpjCount++;
            if ($party['document'] !== 'NAOENCONTRADO') $cnpjEncontrado++;
        }
    }

    $formatted .= "📋 **CLASSIFICAÇÃO:**\n";
    $formatted .= "- Pessoas Físicas (CPF): {$cpfCount} ({$cpfEncontrado} com documento identificado)\n";
    $formatted .= "- Pessoas Jurídicas (CNPJ): {$cnpjCount} ({$cnpjEncontrado} com documento identificado)\n\n";

    // Contar por qualificação
    $byRole = [];
    foreach ($parties as $party) {
        $role = $party['role'];
        if (!isset($byRole[$role])) $byRole[$role] = 0;
        $byRole[$role]++;
    }
    
    $formatted .= "👥 **POR QUALIFICAÇÃO:**\n";
    foreach ($byRole as $role => $count) {
        $formatted .= "- {$role}: {$count}\n";
    }
    $formatted .= "\n";

    $formatted .= "═══════════════════════════════════════════════════════════════\n\n";

    // Detalhar por fonte
    $sourceIndex = 1;
    foreach ($bySource as $sourceName => $sourceParties) {
        $formatted .= "📄 **FONTE #{$sourceIndex}: {$sourceName}**\n";
        $formatted .= str_repeat("-", 60) . "\n\n";

        foreach ($sourceParties as $idx => $party) {
            $num = $idx + 1;
            $formatted .= "   👤 PARTE {$num}:\n";
            $formatted .= "      • Nome: {$party['name']}\n";
            $formatted .= "      • Tipo: " . ($party['document_type'] === 'CPF' ? 'Pessoa Física' : 'Pessoa Jurídica') . "\n";
            
            // Formatar documento para exibição
            $docFormatted = $party['document'];
            if ($docFormatted !== 'NAOENCONTRADO') {
                if ($party['document_type'] === 'CPF' && strlen($docFormatted) === 11) {
                    $docFormatted = substr($docFormatted, 0, 3) . '.' . 
                                    substr($docFormatted, 3, 3) . '.' . 
                                    substr($docFormatted, 6, 3) . '-' . 
                                    substr($docFormatted, 9, 2);
                    $formatted .= "      • CPF: {$docFormatted}\n";
                } else if ($party['document_type'] === 'CNPJ' && strlen($docFormatted) === 14) {
                    $docFormatted = substr($docFormatted, 0, 2) . '.' . 
                                    substr($docFormatted, 2, 3) . '.' . 
                                    substr($docFormatted, 5, 3) . '/' . 
                                    substr($docFormatted, 8, 4) . '-' . 
                                    substr($docFormatted, 12, 2);
                    $formatted .= "      • CNPJ: {$docFormatted}\n";
                } else {
                    $formatted .= "      • Documento: {$docFormatted}\n";
                }
            } else {
                $formatted .= "      • Documento: NÃO IDENTIFICADO NO TEXTO\n";
            }
            
            $formatted .= "      • Qualificação: {$party['role']}\n";
            
            if (!empty($party['additional_info'])) {
                $formatted .= "      • Info adicional: {$party['additional_info']}\n";
            }
            
            // Incluir dados enriquecidos se disponíveis
            if (isset($party['dados_enriquecidos']) && !empty($party['dados_enriquecidos'])) {
                $formatted .= "\n      🔍 **DADOS ENRIQUECIDOS (API):**\n";
                $enriched = $party['dados_enriquecidos'];
                
                if ($party['document_type'] === 'CPF') {
                    // Dados de Pessoa Física
                    if (isset($enriched['nome'])) $formatted .= "         • Nome Completo: {$enriched['nome']}\n";
                    if (isset($enriched['nome_mae'])) $formatted .= "         • Nome da Mãe: {$enriched['nome_mae']}\n";
                    if (isset($enriched['nascimento'])) $formatted .= "         • Data Nascimento: {$enriched['nascimento']}\n";
                    if (isset($enriched['sexo'])) $formatted .= "         • Sexo: {$enriched['sexo']}\n";
                    if (isset($enriched['rg'])) $formatted .= "         • RG: {$enriched['rg']}\n";
                    
                } else if ($party['document_type'] === 'CNPJ') {
                    // Dados de Pessoa Jurídica
                    if (isset($enriched['razao_social'])) $formatted .= "         • Razão Social: {$enriched['razao_social']}\n";
                    if (isset($enriched['nome_fantasia']) && $enriched['nome_fantasia'] !== 'N/A') {
                        $formatted .= "         • Nome Fantasia: {$enriched['nome_fantasia']}\n";
                    }
                    if (isset($enriched['situacao'])) $formatted .= "         • Situação: {$enriched['situacao']}\n";
                    if (isset($enriched['abertura'])) $formatted .= "         • Data Abertura: {$enriched['abertura']}\n";
                    if (isset($enriched['capital_social'])) $formatted .= "         • Capital Social: {$enriched['capital_social']}\n";
                    if (isset($enriched['atividade_principal'])) $formatted .= "         • Atividade Principal: {$enriched['atividade_principal']}\n";
                    
                    // SÓCIOS - Adicionar se existir e for vendedor
                    if (isset($enriched['socios']) && !empty($enriched['socios'])) {
                        $isVendedor = stripos($party['role'], 'vendedor') !== false || 
                                     stripos($party['role'], 'vendedora') !== false ||
                                     stripos($party['role'], 'outorgante') !== false;
                        
                        if ($isVendedor) {
                            $formatted .= "\n         👥 **QUADRO SOCIETÁRIO (VENDEDOR) - " . count($enriched['socios']) . " SÓCIO(S):**\n";
                            foreach ($enriched['socios'] as $i => $socio) {
                                $num = $i + 1;
                                $formatted .= "            {$num}. {$socio['nome']}\n";
                                $formatted .= "               Qualificação: {$socio['qualificacao']}\n";
                                
                                // Mostrar CPF se disponível
                                $cpfSocio = $socio['cpf_limpo'] ?? '';
                                $cpfParcial = $socio['cpf_parcial'] ?? '';
                                $cpfOriginal = $socio['cpf_original'] ?? $socio['cpf_cnpj'] ?? '';
                                
                                if (!empty($cpfSocio) && strlen($cpfSocio) === 11) {
                                    // CPF completo - formatar
                                    $cpfFormatado = substr($cpfSocio, 0, 3) . '.' . 
                                                   substr($cpfSocio, 3, 3) . '.' . 
                                                   substr($cpfSocio, 6, 3) . '-' . 
                                                   substr($cpfSocio, 9, 2);
                                    $formatted .= "               CPF: {$cpfFormatado}\n";
                                } else if (!empty($cpfOriginal)) {
                                    // CPF mascarado - mostrar conforme veio da API
                                    $formatted .= "               CPF: {$cpfOriginal} (parcial/mascarado pela API)\n";
                                }
                                
                                // Mostrar dados enriquecidos do sócio
                                if (isset($socio['dados_enriquecidos']) && !empty($socio['dados_enriquecidos'])) {
                                    $dadosSocio = $socio['dados_enriquecidos'];
                                    $formatted .= "               📊 Dados adicionais:\n";
                                    if (isset($dadosSocio['nome_mae'])) $formatted .= "                  • Nome da Mãe: {$dadosSocio['nome_mae']}\n";
                                    if (isset($dadosSocio['nascimento'])) $formatted .= "                  • Nascimento: {$dadosSocio['nascimento']}\n";
                                    if (isset($dadosSocio['sexo'])) $formatted .= "                  • Sexo: {$dadosSocio['sexo']}\n";
                                }
                                
                                if (!empty($socio['representante_legal'])) {
                                    $formatted .= "               Representante Legal: {$socio['representante_legal']}\n";
                                }
                                
                                if (isset($socio['data_entrada'])) {
                                    $formatted .= "               Data Entrada: {$socio['data_entrada']}\n";
                                }
                                
                                $formatted .= "\n";
                            }
                            $formatted .= "         ⚠️ IMPORTANTE: Estes sócios devem constar como outorgantes/vendedores no documento!\n";
                        }
                    }
                }
            }
            
            $formatted .= "\n";
        }

        $formatted .= "\n";
        $sourceIndex++;
    }

    $formatted .= "═══════════════════════════════════════════════════════════════\n\n";
    
    // Preparar totais para usar no HTML
    $totalParties = count($parties);
    
    $formatted .= "📝 **INSTRUÇÕES OBRIGATÓRIAS PARA A SEÇÃO DE PARTES:**\n\n";
    $formatted .= "🚨 CRIE UMA SEÇÃO ELEGANTE E LIMPA SEGUINDO O PADRÃO:\n\n";
    
    $formatted .= "<h2 class=\"text-2xl font-bold text-gray-900 mt-8 mb-4 pb-2 border-b-2 border-gray-200\">\n";
    $formatted .= "  <span class=\"mr-2\">🏛️</span>PARTES PROCESSUAIS IDENTIFICADAS\n";
    $formatted .= "</h2>\n\n";
    
    $formatted .= "<div class=\"border-l-4 border-blue-500 bg-blue-50 p-3 mb-6\">\n";
    $formatted .= "  <p class=\"text-blue-800 text-sm\"><strong>ℹ️ Info:</strong> As partes abaixo foram identificadas automaticamente via IA nos documentos.</p>\n";
    $formatted .= "</div>\n\n";
    
    $formatted .= "<div class=\"grid grid-cols-3 gap-x-8 gap-y-3 mb-6\">\n";
    $formatted .= "  <div>\n";
    $formatted .= "    <p class=\"text-sm text-gray-500\">Total Identificado</p>\n";
    $formatted .= "    <p class=\"text-2xl font-bold text-gray-900\">{$totalParties}</p>\n";
    $formatted .= "  </div>\n";
    $formatted .= "  <div>\n";
    $formatted .= "    <p class=\"text-sm text-gray-500\">Pessoas Físicas</p>\n";
    $formatted .= "    <p class=\"text-2xl font-bold text-blue-600\">{$cpfCount}</p>\n";
    $formatted .= "  </div>\n";
    $formatted .= "  <div>\n";
    $formatted .= "    <p class=\"text-sm text-gray-500\">Pessoas Jurídicas</p>\n";
    $formatted .= "    <p class=\"text-2xl font-bold text-purple-600\">{$cnpjCount}</p>\n";
    $formatted .= "  </div>\n";
    $formatted .= "</div>\n\n";
    
    $formatted .= "<div class=\"overflow-x-auto mb-6\">\n";
    $formatted .= "  <table class=\"min-w-full bg-white border border-gray-200\">\n";
    $formatted .= "    <thead class=\"bg-gray-800 text-white\">\n";
    $formatted .= "      <tr>\n";
    $formatted .= "        <th class=\"px-4 py-3 text-left text-sm font-semibold\">Nome</th>\n";
    $formatted .= "        <th class=\"px-4 py-3 text-left text-sm font-semibold\">CPF/CNPJ</th>\n";
    $formatted .= "        <th class=\"px-4 py-3 text-left text-sm font-semibold\">Tipo</th>\n";
    $formatted .= "        <th class=\"px-4 py-3 text-left text-sm font-semibold\">Qualificação</th>\n";
    $formatted .= "        <th class=\"px-4 py-3 text-left text-sm font-semibold\">Processo</th>\n";
    $formatted .= "      </tr>\n";
    $formatted .= "    </thead>\n";
    $formatted .= "    <tbody class=\"divide-y divide-gray-200\">\n";
    $formatted .= "      <!-- PREENCHER COM AS PARTES LISTADAS ABAIXO -->\n";
    $formatted .= "    </tbody>\n";
    $formatted .= "  </table>\n";
    $formatted .= "</div>\n\n";
    
    $formatted .= "💡 **COMO PREENCHER A TABELA:**\n\n";
    $formatted .= "Para cada parte listada abaixo, adicione uma linha <tr> assim:\n\n";
    $formatted .= "<tr class=\"hover:bg-gray-50\">\n";
    $formatted .= "  <td class=\"px-4 py-3 text-sm text-gray-900\">[Nome da Parte]</td>\n";
    $formatted .= "  <td class=\"px-4 py-3 text-sm text-gray-700\">[CPF ou CNPJ formatado]</td>\n";
    $formatted .= "  <td class=\"px-4 py-3 text-sm\"><span class=\"px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800\">[PF/PJ]</span></td>\n";
    $formatted .= "  <td class=\"px-4 py-3 text-sm text-gray-700\">[Autor/Réu/Executado/etc]</td>\n";
    $formatted .= "  <td class=\"px-4 py-3 text-sm text-gray-600\">[Número do processo ou fonte]</td>\n";
    $formatted .= "</tr>\n\n";
    
    $formatted .= "🔍 **DADOS ENRIQUECIDOS - INCLUIR OBRIGATORIAMENTE:**\n\n";
    $formatted .= "⚠️ ATENÇÃO: Abaixo de cada tabela de partes, VOCÊ DEVE incluir os DADOS ENRIQUECIDOS que foram consultados via API!\n\n";
    $formatted .= "Para partes que têm '🔍 DADOS ENRIQUECIDOS (API)' listados abaixo, crie cards assim:\n\n";
    $formatted .= "<h3 class=\"text-lg font-semibold text-gray-900 mt-6 mb-3\">🔍 Dados Complementares (APIs)</h3>\n\n";
    $formatted .= "Para CADA parte com dados enriquecidos:\n\n";
    $formatted .= "<div class=\"border-l-4 border-green-500 bg-green-50 p-4 mb-4\">\n";
    $formatted .= "  <h4 class=\"font-semibold text-gray-900 mb-2\">[NOME DA PARTE]</h4>\n";
    $formatted .= "  <div class=\"text-sm text-gray-700 space-y-1\">\n";
    $formatted .= "    <p><strong>Campo:</strong> Valor</p>\n";
    $formatted .= "    <!-- Incluir TODOS os dados enriquecidos listados -->\n";
    $formatted .= "  </div>\n";
    $formatted .= "</div>\n\n";
    $formatted .= "🚨 **ATENÇÃO ESPECIAL - SE A PARTE FOR VENDEDOR/OUTORGANTE E TIVER SÓCIOS:**\n";
    $formatted .= "VOCÊ DEVE CRIAR UMA SEÇÃO DESTACADA PARA OS SÓCIOS!\n\n";
    $formatted .= "Exemplo de como exibir:\n\n";
    $formatted .= "<div class=\"border-l-4 border-orange-500 bg-orange-50 p-4 mb-4\">\n";
    $formatted .= "  <h4 class=\"font-semibold text-gray-900 mb-2\">👥 Quadro Societário - [NOME EMPRESA VENDEDOR]</h4>\n";
    $formatted .= "  <div class=\"text-sm text-gray-700\">\n";
    $formatted .= "    <p class=\"mb-3 text-orange-800\"><strong>⚠️ IMPORTANTE: Estes sócios devem constar como outorgantes/vendedores no documento!</strong></p>\n";
    $formatted .= "    <div class=\"space-y-3\">\n";
    $formatted .= "      <!-- Para CADA sócio listado acima, crie um bloco assim: -->\n";
    $formatted .= "      <div class=\"bg-white p-3 rounded\">\n";
    $formatted .= "        <p class=\"font-semibold text-gray-900\">[NOME DO SÓCIO]</p>\n";
    $formatted .= "        <p class=\"text-sm\"><strong>Qualificação:</strong> [Diretor/Sócio-Administrador/etc]</p>\n";
    $formatted .= "        <p class=\"text-sm\"><strong>CPF:</strong> [CPF se disponível - pode estar parcialmente mascarado]</p>\n";
    $formatted .= "        <!-- Se houver dados enriquecidos do sócio (nome da mãe, nascimento, etc), incluir aqui: -->\n";
    $formatted .= "        <!-- <p class=\"text-sm\"><strong>Mãe:</strong> [nome se disponível]</p> -->\n";
    $formatted .= "        <p class=\"text-sm\"><strong>Data Entrada:</strong> [data se disponível]</p>\n";
    $formatted .= "      </div>\n";
    $formatted .= "    </div>\n";
    $formatted .= "  </div>\n";
    $formatted .= "</div>\n\n";
    $formatted .= "⚠️ **REGRA CRÍTICA:** Se você vir no texto acima \"QUADRO SOCIETÁRIO (VENDEDOR)\", você DEVE incluir TODOS os sócios listados!\n\n";
    
    $formatted .= "🎯 **ANÁLISE E OBSERVAÇÕES:**\n\n";
    $formatted .= "Após a tabela, adicione um parágrafo com análise:\n\n";
    $formatted .= "<h3 class=\"text-lg font-semibold text-gray-900 mt-6 mb-3\">📊 Análise das Partes</h3>\n";
    $formatted .= "<p class=\"text-gray-700 mb-4 leading-relaxed\">\n";
    $formatted .= "[Descreva aqui: perfil das partes, qualificações predominantes, possíveis riscos]\n";
    $formatted .= "</p>\n\n";
    
    $formatted .= "⚠️ **REGRAS IMPORTANTES:**\n";
    $formatted .= "1. Use APENAS os dados fornecidos abaixo - não invente\n";
    $formatted .= "2. Mantenha o padrão LIMPO - sem caixas dentro de caixas\n";
    $formatted .= "3. Cross-reference com as certidões quando possível\n";
    $formatted .= "4. Se houver restrições, mencione na análise (não crie alerta separado)\n\n";

    $formatted .= "═══════════════════════════════════════════════════════════════\n\n";

    return $formatted;
}

// Função para converter arquivo em base64
function fileToBase64($filePath) {
    $fileContent = file_get_contents($filePath);
    return base64_encode($fileContent);
}

// Função para determinar o tipo MIME
function getMimeType($originalFileName) {
    $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf':
            return 'application/pdf';
        case 'jpg':
        case 'jpeg':
            return 'image/jpeg';
        case 'png':
            return 'image/png';
        default:
            throw new Exception("Tipo de arquivo não suportado: {$extension}. A API do Gemini suporta apenas PDF, JPG, JPEG e PNG.");
    }
}

// Função para chamar a API do Gemini
function callGeminiAPI($apiKey, $prompt, $files) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=' . $apiKey;
    
    // Preparar o conteúdo da requisição
    $parts = [];
    
    // Adicionar o prompt
    $parts[] = [
        'text' => $prompt
    ];
    
    // Adicionar os arquivos
    foreach ($files as $file) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $file['mime_type'],
                'data' => $file['data']
            ]
        ];
    }
    
    $requestData = [
        'contents' => [
            [
                'parts' => $parts
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 100000,
        ]
    ];
    
    $headers = [
        'Content-Type: application/json',
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Aumentar timeout para 5 minutos
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Erro cURL: " . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Erro HTTP: " . $httpCode . " - " . $response);
    }
    
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar resposta JSON: " . json_last_error_msg());
    }
    
    return $responseData;
}

// Função para extrair texto da resposta do Gemini
function extractTextFromResponse($response) {
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        return $response['candidates'][0]['content']['parts'][0]['text'];
    }
    throw new Exception("Resposta inválida da API do Gemini");
}

// Função para processar e formatar o HTML do relatório
function formatReportHTML($text) {
    error_log("=== FORMATAÇÃO DO RELATÓRIO ===");
    error_log("Tamanho do texto recebido: " . strlen($text) . " caracteres");
    error_log("Primeiros 500 caracteres: " . substr($text, 0, 500));
    
    // Remover possíveis marcadores de código markdown
    $html = preg_replace('/```html\s*/i', '', $text);
    $html = preg_replace('/```\s*/', '', $html);
    $html = trim($html);
    
    // Se já contém HTML estruturado, retornar direto
    if (strpos($html, '<h1>') !== false || strpos($html, '<h2>') !== false) {
        error_log("HTML estruturado detectado, retornando direto");
        
        // Garantir que alertas tenham classes corretas
        $html = preg_replace('/<div class="alert">/i', '<div class="alert alert-info">', $html);
        
        return $html;
    }
    
    error_log("HTML não estruturado, aplicando conversões...");
    
    // Converter markdown para HTML
    // Títulos com #
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    
    // Negrito e itálico
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
    
    // Listas não ordenadas
    $html = preg_replace('/^[\-\*\•] (.+)$/m', '<li>$1</li>', $html);
    
    // Listas ordenadas
    $html = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $html);
    
    // Envolver listas consecutivas
    $html = preg_replace('/(<li>.*<\/li>\s*)+/s', '<ul>$0</ul>', $html);
    
    // Parágrafos (linhas em branco duplas)
    $html = preg_replace('/\n\n+/', '</p><p>', $html);
    $html = '<p>' . $html . '</p>';
    
    // Limpar parágrafos vazios
    $html = preg_replace('/<p>\s*<\/p>/', '', $html);
    $html = preg_replace('/<p>(<h[1-6]>)/i', '$1', $html);
    $html = preg_replace('/(<\/h[1-6]>)<\/p>/i', '$1', $html);
    $html = preg_replace('/<p>(<table)/i', '$1', $html);
    $html = preg_replace('/(<\/table>)<\/p>/i', '$1', $html);
    $html = preg_replace('/<p>(<ul)/i', '$1', $html);
    $html = preg_replace('/(<\/ul>)<\/p>/i', '$1', $html);
    $html = preg_replace('/<p>(<div)/i', '$1', $html);
    $html = preg_replace('/(<\/div>)<\/p>/i', '$1', $html);
    
    // Destacar palavras-chave de risco
    $html = preg_replace('/\b(RISCO ALTO)\b/i', '<span class="risk-high">RISCO ALTO</span>', $html);
    $html = preg_replace('/\b(RISCO MÉDIO)\b/i', '<span class="risk-medium">RISCO MÉDIO</span>', $html);
    $html = preg_replace('/\b(RISCO BAIXO)\b/i', '<span class="risk-low">RISCO BAIXO</span>', $html);
    
    // Converter alertas em texto para divs
    $html = preg_replace('/⚠️\s*ALERTA:(.+?)(?=<[hp]|$)/is', '<div class="alert alert-danger">⚠️ ALERTA:$1</div>', $html);
    $html = preg_replace('/🚨\s*ATENÇÃO:(.+?)(?=<[hp]|$)/is', '<div class="alert alert-warning">🚨 ATENÇÃO:$1</div>', $html);
    $html = preg_replace('/💡\s*RECOMENDAÇÃO:(.+?)(?=<[hp]|$)/is', '<div class="alert alert-info">💡 RECOMENDAÇÃO:$1</div>', $html);
    
    error_log("HTML formatado, tamanho final: " . strlen($html) . " caracteres");
    
    return $html;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    // Verificar se a chave API foi fornecida
    if (empty($_POST['gemini_api_key'])) {
        throw new Exception("Chave API do Gemini não foi fornecida.");
    }
    
    $apiKey = trim($_POST['gemini_api_key']);
    
    // Verificar se arquivos foram enviados
    if (empty($_FILES['documents']['tmp_name'][0])) {
        throw new Exception("Nenhum arquivo foi enviado.");
    }
    
    $uploadedFiles = [];
    $totalFiles = count($_FILES['documents']['tmp_name']);
    
    // Processar cada arquivo enviado
    for ($i = 0; $i < $totalFiles; $i++) {
        if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['documents']['tmp_name'][$i];
            $fileName = $_FILES['documents']['name'][$i];
            $fileSize = $_FILES['documents']['size'][$i];
            
            // Verificar tamanho do arquivo (20MB max)
            if ($fileSize > 20 * 1024 * 1024) {
                throw new Exception("O arquivo '{$fileName}' é muito grande. Tamanho máximo: 20MB");
            }
            
            // Verificar extensão do arquivo
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception("Formato de arquivo não suportado: {$extension}. Formatos aceitos: " . implode(', ', $allowedExtensions));
            }
            
            // Converter arquivo para base64
            $fileData = fileToBase64($tmpPath);
            $mimeType = getMimeType($fileName);
            
            $uploadedFiles[] = [
                'name' => $fileName,
                'data' => $fileData,
                'mime_type' => $mimeType
            ];
        } else {
            throw new Exception("Erro no upload do arquivo: " . $_FILES['documents']['error'][$i]);
        }
    }
    
    if (empty($uploadedFiles)) {
        throw new Exception("Nenhum arquivo válido foi processado.");
    }

    // ═══════════════════════════════════════════════════════════════
    // INICIALIZAR BANCO DE DADOS E CRIAR ANÁLISE
    // ═══════════════════════════════════════════════════════════════
    $db = new DatabaseManager();
    $analiseId = $db->criarAnalise($totalFiles, "Análise via interface web");
    
    error_log("╔════════════════════════════════════════════════════════════╗");
    error_log("║  ANÁLISE CRIADA NO BANCO - ID: {$analiseId}                ║");
    error_log("╚════════════════════════════════════════════════════════════╝");
    
    // Salvar documentos no banco
    foreach ($uploadedFiles as $file) {
        $db->salvarDocumento(
            $analiseId,
            $file['name'],
            $file['mime_type'],
            strlen($file['data']),
            $file['data']
        );
    }
    
    error_log("💾 {$totalFiles} documentos salvos no banco");

    // ═══════════════════════════════════════════════════════════════
    // ETAPA 1: EXTRAÇÃO DEDICADA DE PARTES (CHAMADA INDIVIDUAL)
    // ═══════════════════════════════════════════════════════════════
    $processParties = [];
    
    error_log("╔════════════════════════════════════════════════════════════╗");
    error_log("║  ETAPA 1: EXTRAÇÃO INDIVIDUAL DE PARTES DO PROCESSO      ║");
    error_log("╚════════════════════════════════════════════════════════════╝");
    
    try {
        date_default_timezone_set('America/Sao_Paulo');
        $dataAtual = date('d/m/Y H:i:s');
        
        $partiesPrompt = "";
        $partiesPrompt .= "╔═══════════════════════════════════════════════════════════════════════╗\n";
        $partiesPrompt .= "║                    EXTRAÇÃO DE DADOS ESTRUTURADOS                     ║\n";
        $partiesPrompt .= "║                  Sistema de Análise Jurídica - DueBot                 ║\n";
        $partiesPrompt .= "╚═══════════════════════════════════════════════════════════════════════╝\n\n";
        $partiesPrompt .= "📅 DATA DA EXTRAÇÃO: {$dataAtual}\n\n";
        
        $partiesPrompt .= "🎯 MISSÃO CRÍTICA:\n";
        $partiesPrompt .= "Você é um assistente especializado em extração de dados jurídicos.\n";
        $partiesPrompt .= "Sua ÚNICA tarefa é identificar e extrair TODAS as pessoas (físicas e jurídicas) mencionadas nos documentos.\n\n";
        
        $partiesPrompt .= "📋 O QUE EXTRAIR:\n\n";
        $partiesPrompt .= "1. PESSOAS FÍSICAS:\n";
        $partiesPrompt .= "   - Nome completo\n";
        $partiesPrompt .= "   - CPF (11 dígitos)\n";
        $partiesPrompt .= "   - Qualificação no documento (proprietário, autor, réu, testemunha, etc)\n\n";
        
        $partiesPrompt .= "2. PESSOAS JURÍDICAS:\n";
        $partiesPrompt .= "   - Razão social completa\n";
        $partiesPrompt .= "   - CNPJ (14 dígitos)\n";
        $partiesPrompt .= "   - Qualificação no documento (parte, interveniente, credor, etc)\n\n";
        
        $partiesPrompt .= "🔍 ONDE BUSCAR:\n";
        $partiesPrompt .= "- Matrículas imobiliárias (proprietários, cessionários, credores)\n";
        $partiesPrompt .= "- Certidões de distribuição (autores, réus, executados, exequentes)\n";
        $partiesPrompt .= "- Contratos (partes contratantes, testemunhas, intervenientes)\n";
        $partiesPrompt .= "- Petições judiciais (todas as partes mencionadas)\n";
        $partiesPrompt .= "- Mandados (executados, credores)\n";
        $partiesPrompt .= "- Escrituras públicas (outorgantes, outorgados)\n";
        $partiesPrompt .= "- Qualquer outro documento jurídico\n\n";
        
        $partiesPrompt .= "📤 FORMATO DE SAÍDA OBRIGATÓRIO:\n";
        $partiesPrompt .= "Retorne APENAS um array JSON válido, sem texto antes ou depois.\n";
        $partiesPrompt .= "NÃO use ```json ou qualquer marcação.\n";
        $partiesPrompt .= "Estrutura EXATA:\n\n";
        
        $partiesPrompt .= "[\n";
        $partiesPrompt .= "  {\n";
        $partiesPrompt .= "    \"name\": \"NOME COMPLETO EM MAIÚSCULAS\",\n";
        $partiesPrompt .= "    \"document\": \"12345678900\",\n";
        $partiesPrompt .= "    \"document_type\": \"CPF\",\n";
        $partiesPrompt .= "    \"role\": \"PROPRIETÁRIO\",\n";
        $partiesPrompt .= "    \"source\": \"Matrícula 12345\",\n";
        $partiesPrompt .= "    \"additional_info\": \"Informações complementares relevantes\"\n";
        $partiesPrompt .= "  }\n";
        $partiesPrompt .= "]\n\n";
        
        $partiesPrompt .= "⚠️ REGRAS ABSOLUTAS:\n\n";
        $partiesPrompt .= "1. DOCUMENTO:\n";
        $partiesPrompt .= "   - CPF: SEMPRE 11 dígitos numéricos (ex: 12345678900)\n";
        $partiesPrompt .= "   - CNPJ: SEMPRE 14 dígitos numéricos (ex: 12345678000199)\n";
        $partiesPrompt .= "   - REMOVA pontos, traços, barras e espaços\n";
        $partiesPrompt .= "   - Se não encontrar, use \"NAOENCONTRADO\" como valor\n\n";
        
        $partiesPrompt .= "2. DOCUMENT_TYPE:\n";
        $partiesPrompt .= "   - Use APENAS \"CPF\" ou \"CNPJ\"\n";
        $partiesPrompt .= "   - CPF = Pessoa Física (nomes de pessoas)\n";
        $partiesPrompt .= "   - CNPJ = Pessoa Jurídica (empresas, bancos, etc)\n\n";
        
        $partiesPrompt .= "3. ROLE (Qualificação):\n";
        $partiesPrompt .= "   - PROPRIETÁRIO (atual dono do imóvel)\n";
        $partiesPrompt .= "   - AUTOR (parte ativa em processo)\n";
        $partiesPrompt .= "   - RÉU (parte passiva em processo)\n";
        $partiesPrompt .= "   - EXECUTADO (devedor em execução)\n";
        $partiesPrompt .= "   - EXEQUENTE (credor em execução)\n";
        $partiesPrompt .= "   - CREDOR (credor hipotecário, fiduciário)\n";
        $partiesPrompt .= "   - DEVEDOR (devedor hipotecário)\n";
        $partiesPrompt .= "   - TERCEIRO (terceiro interessado)\n";
        $partiesPrompt .= "   - TESTEMUNHA (testemunha)\n";
        $partiesPrompt .= "   - Outros: descreva claramente\n\n";
        
        $partiesPrompt .= "4. SOURCE:\n";
        $partiesPrompt .= "   - Indique de onde extraiu (ex: \"Matrícula 12345\", \"Processo 123-45.2023\", \"Certidão STJ\")\n\n";
        
        $partiesPrompt .= "5. NAME:\n";
        $partiesPrompt .= "   - SEMPRE em MAIÚSCULAS\n";
        $partiesPrompt .= "   - Nome completo conforme documento\n";
        $partiesPrompt .= "   - Sem abreviações quando possível\n\n";
        
        $partiesPrompt .= "💡 EXEMPLOS PRÁTICOS:\n\n";
        
        $partiesPrompt .= "📄 Exemplo 1 - Matrícula:\n";
        $partiesPrompt .= "Texto: \"Proprietário: JOÃO DA SILVA SANTOS, CPF 123.456.789-00\"\n";
        $partiesPrompt .= "JSON:\n";
        $partiesPrompt .= "[{\"name\": \"JOÃO DA SILVA SANTOS\", \"document\": \"12345678900\", \"document_type\": \"CPF\", \"role\": \"PROPRIETÁRIO\", \"source\": \"Matrícula\", \"additional_info\": \"Proprietário atual\"}]\n\n";
        
        $partiesPrompt .= "📄 Exemplo 2 - Processo:\n";
        $partiesPrompt .= "Texto: \"Processo 5012345-67.2023 - AUTOR: BANCO ABC S.A., CNPJ 00.000.000/0001-91 vs RÉU: MARIA PEREIRA\"\n";
        $partiesPrompt .= "JSON:\n";
        $partiesPrompt .= "[\n";
        $partiesPrompt .= "  {\"name\": \"BANCO ABC S.A.\", \"document\": \"00000000000191\", \"document_type\": \"CNPJ\", \"role\": \"AUTOR\", \"source\": \"Processo 5012345-67.2023\", \"additional_info\": \"Autor da ação\"},\n";
        $partiesPrompt .= "  {\"name\": \"MARIA PEREIRA\", \"document\": \"NAOENCONTRADO\", \"document_type\": \"CPF\", \"role\": \"RÉU\", \"source\": \"Processo 5012345-67.2023\", \"additional_info\": \"Réu no processo\"}\n";
        $partiesPrompt .= "]\n\n";
        
        $partiesPrompt .= "📄 Exemplo 3 - Múltiplas partes:\n";
        $partiesPrompt .= "Texto: \"Credores: CAIXA ECONÔMICA FEDERAL (CNPJ 00.360.305/0001-04) e CARLOS ALBERTO SOUZA (CPF 111.222.333-44)\"\n";
        $partiesPrompt .= "JSON:\n";
        $partiesPrompt .= "[\n";
        $partiesPrompt .= "  {\"name\": \"CAIXA ECONÔMICA FEDERAL\", \"document\": \"00360305000104\", \"document_type\": \"CNPJ\", \"role\": \"CREDOR\", \"source\": \"Documento\", \"additional_info\": \"Credor hipotecário\"},\n";
        $partiesPrompt .= "  {\"name\": \"CARLOS ALBERTO SOUZA\", \"document\": \"11122233344\", \"document_type\": \"CPF\", \"role\": \"CREDOR\", \"source\": \"Documento\", \"additional_info\": \"Credor\"}\n";
        $partiesPrompt .= "]\n\n";
        
        $partiesPrompt .= "🚨 CASOS ESPECIAIS:\n\n";
        $partiesPrompt .= "- Se encontrar \"e outros\", \"et al\", \"e cônjuge\": tente extrair todos os nomes\n";
        $partiesPrompt .= "- Se o CPF/CNPJ não estiver explícito, coloque \"NAOENCONTRADO\"\n";
        $partiesPrompt .= "- Se encontrar siglas (ex: CEF, BB), expanda para nome completo quando possível\n";
        $partiesPrompt .= "- Ignore cabeçalhos, rodapés e textos genéricos\n";
        $partiesPrompt .= "- Se NÃO encontrar NENHUMA pessoa, retorne: []\n\n";
        
        $partiesPrompt .= "🎯 SUA TAREFA AGORA:\n";
        $partiesPrompt .= "Analise METICULOSAMENTE todos os documentos fornecidos.\n";
        $partiesPrompt .= "Extraia TODAS as pessoas físicas e jurídicas mencionadas.\n";
        $partiesPrompt .= "Retorne APENAS o array JSON limpo.\n";
        $partiesPrompt .= "Seja PRECISO e COMPLETO!\n\n";
        
        $partiesPrompt .= "ATENÇÃO: Sua resposta deve começar com [ e terminar com ]\n";
        $partiesPrompt .= "NÃO adicione texto explicativo. APENAS o JSON puro.\n\n";

        error_log("Enviando prompt de extração para Gemini...");
        error_log("Tamanho do prompt: " . strlen($partiesPrompt) . " caracteres");
        
        $partiesResponse = callGeminiAPI($apiKey, $partiesPrompt, $uploadedFiles);
        $partiesText = extractTextFromResponse($partiesResponse);

        error_log("═══ RESPOSTA DA EXTRAÇÃO ═══");
        error_log("Tamanho: " . strlen($partiesText) . " caracteres");
        error_log("Primeiros 1000 caracteres: " . substr($partiesText, 0, 1000));

        // Limpeza agressiva da resposta
        $partiesText = trim($partiesText);
        
        // Remover qualquer texto antes do primeiro [
        if (preg_match('/\[/', $partiesText)) {
            $partiesText = substr($partiesText, strpos($partiesText, '['));
        }
        
        // Remover qualquer texto depois do último ]
        if (preg_match('/\]/', $partiesText)) {
            $partiesText = substr($partiesText, 0, strrpos($partiesText, ']') + 1);
        }
        
        // Remover marcadores de código
        $partiesText = preg_replace('/```json\s*/i', '', $partiesText);
        $partiesText = preg_replace('/```\s*/', '', $partiesText);
        $partiesText = trim($partiesText);

        error_log("JSON limpo: " . $partiesText);

        $processParties = json_decode($partiesText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("❌ ERRO JSON: " . json_last_error_msg());
            error_log("JSON problemático: " . $partiesText);
            throw new Exception("Falha ao decodificar JSON de partes: " . json_last_error_msg());
        }

        if (!is_array($processParties)) {
            error_log("⚠️ WARNING: Resposta não é array!");
            $processParties = [];
        }

        error_log("✓ Partes extraídas com sucesso: " . count($processParties));
        
        // Validar e enriquecer dados
        if (!empty($processParties)) {
            $validParties = [];
            $partCount = 0;
            
            foreach ($processParties as $party) {
                if (!isset($party['name']) || empty(trim($party['name']))) {
                    error_log("⚠️ Parte sem nome, pulando...");
                    continue;
                }
                
                $cleanDoc = '';
                if (isset($party['document']) && $party['document'] !== 'NAOENCONTRADO') {
                    $cleanDoc = preg_replace('/[^0-9]/', '', $party['document']);
                }
                
                // Validar tamanho do documento
                $isValidDoc = (strlen($cleanDoc) === 11 || strlen($cleanDoc) === 14);
                
                // Determinar tipo baseado no documento ou nome
                $docType = $party['document_type'] ?? 'CPF';
                if (!$isValidDoc) {
                    // Tentar inferir pelo nome
                    $name = strtoupper(trim($party['name']));
                    if (preg_match('/(LTDA|S\.A\.|S\/A|EIRELI|MEI|BANCO|CAIXA|EMPRESA|CONSTRUTORA|INCORPORADORA)/i', $name)) {
                        $docType = 'CNPJ';
                        $cleanDoc = 'NAOENCONTRADO';
                    } else {
                        $docType = 'CPF';
                        $cleanDoc = 'NAOENCONTRADO';
                    }
                }
                
                $validParties[] = [
                    'name' => strtoupper(trim($party['name'])),
                    'document' => $cleanDoc ?: 'NAOENCONTRADO',
                    'document_type' => $docType,
                    'role' => strtoupper($party['role'] ?? 'NÃO ESPECIFICADO'),
                    'source' => $party['source'] ?? 'Documento',
                    'additional_info' => $party['additional_info'] ?? ''
                ];
                
                $partCount++;
                error_log("✓ Parte {$partCount}: {$validParties[$partCount-1]['name']} ({$docType})");
            }
            
            $processParties = $validParties;
            error_log("═══ RESULTADO FINAL ═══");
            error_log("Total de partes válidas: " . count($processParties));
        } else {
            error_log("ℹ️ Nenhuma parte encontrada nos documentos");
        }

    } catch (Exception $e) {
        error_log("❌ ERRO CRÍTICO NA EXTRAÇÃO: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $processParties = [];
    }
    
    // Salvar JSON para debug
    $jsonFile = __DIR__ . '/debug_partes_' . time() . '.json';
    file_put_contents($jsonFile, json_encode($processParties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    error_log("📁 JSON salvo em: " . $jsonFile);
    
    // ═══════════════════════════════════════════════════════════════
    // SALVAR PARTES NO BANCO DE DADOS
    // ═══════════════════════════════════════════════════════════════
    $partidasComId = [];
    if (!empty($processParties)) {
        foreach ($processParties as $parte) {
            $parteId = $db->salvarParte($analiseId, $parte);
            if ($parteId) {
                $parte['db_id'] = $parteId; // Adicionar ID do banco
                $partidasComId[] = $parte;
            }
        }
        error_log("💾 " . count($partidasComId) . " partes salvas no banco de dados");
        $processParties = $partidasComId; // Atualizar array com IDs
    }
    
    error_log("╚════════════════════════════════════════════════════════════╝");
    
    // ═══════════════════════════════════════════════════════════════
    // ETAPA 1.5: AUTO-ENRIQUECIMENTO DAS PARTES
    // ═══════════════════════════════════════════════════════════════
    if (!empty($processParties)) {
        error_log("\n╔════════════════════════════════════════════════════════════╗");
        error_log("║  ETAPA 1.5: AUTO-ENRIQUECIMENTO DAS PARTES               ║");
        error_log("╚════════════════════════════════════════════════════════════╝");
        
        try {
            $enrichmentService = new EnriquecimentoService();
            $resultadoEnriquecimento = $enrichmentService->enriquecerPartesEmTempoReal($processParties, $analiseId);
            
            // Atualizar array com partes enriquecidas
            $processParties = $resultadoEnriquecimento['partes'];
            $stats = $resultadoEnriquecimento['stats'];
            
            error_log("📊 Resultado do enriquecimento:");
            error_log("   ✅ Enriquecidas: {$stats['sucesso']}");
            error_log("   ❌ Falhas: {$stats['falhas']}");
            
            // Salvar JSON enriquecido para debug
            $jsonEnriquecidoFile = __DIR__ . '/debug_partes_enriquecidas_' . time() . '.json';
            file_put_contents($jsonEnriquecidoFile, json_encode($processParties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            error_log("📁 JSON enriquecido salvo em: " . $jsonEnriquecidoFile);
            
            // Verificar quantas têm dados enriquecidos e salvar sócios
            $comEnriquecimento = 0;
            $totalSociosSalvos = 0;
            
            error_log("\n🔍 VERIFICANDO DADOS ENRIQUECIDOS E SALVANDO SÓCIOS:");
            
            foreach ($processParties as $parte) {
                $nomeParte = $parte['name'] ?? 'N/A';
                
                if (isset($parte['dados_enriquecidos']) && !empty($parte['dados_enriquecidos'])) {
                    $comEnriquecimento++;
                    error_log("   🔍 {$nomeParte} - Enriquecido com " . count($parte['dados_enriquecidos']) . " campos");
                    
                    // Verificar se tem ID do banco
                    if (!isset($parte['db_id'])) {
                        error_log("      ⚠️ ATENÇÃO: Parte NÃO tem db_id! Não pode salvar sócios.");
                        continue;
                    }
                    
                    error_log("      ✅ db_id existe: {$parte['db_id']}");
                    
                    // Verificar se tem sócios
                    if (isset($parte['dados_enriquecidos']['socios'])) {
                        $qtdSocios = count($parte['dados_enriquecidos']['socios']);
                        error_log("      📋 Array de sócios existe com {$qtdSocios} sócio(s)");
                        
                        if ($qtdSocios > 0) {
                            // Verificar se é vendedor/proprietário
                            $role = $parte['role'] ?? '';
                            error_log("      🔍 Role: '{$role}'");
                            
                            // Salvar sócios se for: vendedor, outorgante, proprietário ou cedente
                            $deveExtrairSocios = isset($parte['role']) && 
                                                 (stripos($parte['role'], 'vendedor') !== false || 
                                                  stripos($parte['role'], 'vendedora') !== false ||
                                                  stripos($parte['role'], 'outorgante') !== false ||
                                                  stripos($parte['role'], 'proprietári') !== false || // pega proprietário/proprietária
                                                  stripos($parte['role'], 'cedente') !== false);
                            
                            if ($deveExtrairSocios) {
                                error_log("      ✅ Salvando {$qtdSocios} sócio(s) no banco... (role: {$role})");
                                
                                $sociosSalvos = $db->salvarSocios(
                                    $parte['db_id'], 
                                    $analiseId, 
                                    $parte['dados_enriquecidos']['socios']
                                );
                                
                                error_log("      💾 {$sociosSalvos} sócio(s) salvos com sucesso!");
                                $totalSociosSalvos += $sociosSalvos;
                            } else {
                                error_log("      ⚠️ Role '{$role}' não requer extração de sócios");
                            }
                        } else {
                            error_log("      ⚠️ Array de sócios está VAZIO");
                        }
                    } else {
                        error_log("      ⚠️ Parte NÃO tem array 'socios' nos dados enriquecidos");
                    }
                } else {
                    error_log("   ⚠️ {$nomeParte} - SEM dados enriquecidos");
                }
            }
            
            error_log("\n📊 RESUMO DO SALVAMENTO:");
            error_log("   💎 Total com dados enriquecidos: {$comEnriquecimento}/" . count($processParties));
            error_log("   👥 Total de sócios salvos no banco: {$totalSociosSalvos}");
            
            if ($totalSociosSalvos === 0) {
                error_log("\n⚠️ NENHUM SÓCIO FOI SALVO! Possíveis causas:");
                error_log("   1. API de CNPJ (cnpj.ws) não retornou sócios");
                error_log("   2. Nenhuma parte foi identificada como VENDEDOR/OUTORGANTE");
                error_log("   3. Problema na consulta da API cnpj.ws");
                error_log("   4. Token da API pode estar inválido ou com limite excedido");
            }
            
        } catch (Exception $e) {
            error_log("⚠️ Erro no enriquecimento automático: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            // Continua a análise mesmo se o enriquecimento falhar
        }
        
        error_log("╚════════════════════════════════════════════════════════════╝\n");
    }

    // ETAPA 2: Primeira análise com Gemini para extrair CPF/CNPJ dos proprietários
    $juditResults = [];

    // ⚠️ JUDIT TEMPORARIAMENTE DESABILITADA
    $JUDIT_ENABLED = false;
    
    if ($JUDIT_ENABLED && !empty(JUDIT_API_KEY)) {
        error_log("=== JUDIT INTEGRATION ENABLED ===");
        error_log("API Key configured: " . substr(JUDIT_API_KEY, 0, 10) . "...");
        try {
            $extractionPrompt = "TAREFA: Extrair dados dos proprietários atuais do imóvel para consulta judicial.\n\n";
            $extractionPrompt .= "INSTRUÇÕES:\n";
            $extractionPrompt .= "1. Localize a MATRÍCULA IMOBILIÁRIA nos documentos\n";
            $extractionPrompt .= "2. Encontre o ÚLTIMO registro R-XX (registro mais recente)\n";
            $extractionPrompt .= "3. Identifique os proprietários mencionados neste último registro\n";
            $extractionPrompt .= "4. Extraia: Nome completo e CPF/CNPJ de cada proprietário\n\n";

            $extractionPrompt .= "FORMATO DE SAÍDA (retorne APENAS este JSON, nada mais):\n";
            $extractionPrompt .= "[\n";
            $extractionPrompt .= "  {\"name\": \"NOME COMPLETO\", \"document\": \"12345678900\"},\n";
            $extractionPrompt .= "  {\"name\": \"OUTRO NOME\", \"document\": \"98765432100\"}\n";
            $extractionPrompt .= "]\n\n";

            $extractionPrompt .= "REGRAS IMPORTANTES:\n";
            $extractionPrompt .= "- CPF: 11 dígitos sem formatação (ex: 12345678900)\n";
            $extractionPrompt .= "- CNPJ: 14 dígitos sem formatação (ex: 12345678000199)\n";
            $extractionPrompt .= "- Máximo 3 proprietários\n";
            $extractionPrompt .= "- Se NÃO encontrar matrícula ou proprietários, retorne: []\n";
            $extractionPrompt .= "- Retorne SOMENTE o array JSON, sem texto antes ou depois\n\n";

            $extractionPrompt .= "EXEMPLOS:\n";
            $extractionPrompt .= "Se encontrar 'JOÃO DA SILVA, CPF 123.456.789-00':\n";
            $extractionPrompt .= "[{\"name\": \"JOÃO DA SILVA\", \"document\": \"12345678900\"}]\n\n";

            $extractionPrompt .= "Se encontrar 'EMPRESA XYZ LTDA, CNPJ 12.345.678/0001-99':\n";
            $extractionPrompt .= "[{\"name\": \"EMPRESA XYZ LTDA\", \"document\": \"12345678000199\"}]\n\n";

            $extractionPrompt .= "Agora analise os documentos e extraia os dados:\n";

            $extractionResponse = callGeminiAPI($apiKey, $extractionPrompt, $uploadedFiles);
            $extractionText = extractTextFromResponse($extractionResponse);

            // Log para debug
            error_log("=== JUDIT EXTRACTION DEBUG ===");
            error_log("Raw extraction response: " . substr($extractionText, 0, 500));

            // Limpar resposta
            $extractionText = trim($extractionText);
            // Remover marcadores de código
            $extractionText = preg_replace('/```json\s*/i', '', $extractionText);
            $extractionText = preg_replace('/```\s*/', '', $extractionText);
            $extractionText = trim($extractionText);

            // Tentar encontrar o JSON na resposta
            if (preg_match('/(\[.*\])/s', $extractionText, $matches)) {
                $extractionText = $matches[1];
            }

            error_log("Cleaned extraction text: " . $extractionText);

            $owners = json_decode($extractionText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                throw new Exception("Falha ao decodificar JSON de extração: " . json_last_error_msg());
            }

            error_log("Owners extracted: " . print_r($owners, true));

            if (is_array($owners) && !empty($owners)) {
                // Limitar ao número máximo configurado
                $owners = array_slice($owners, 0, MAX_OWNERS_TO_QUERY);

                // Validar e limpar documentos
                $validOwners = [];
                foreach ($owners as $owner) {
                    if (isset($owner['name']) && isset($owner['document'])) {
                        // Limpar documento (remover formatação se houver)
                        $cleanDoc = preg_replace('/[^0-9]/', '', $owner['document']);

                        // Validar tamanho (CPF=11 ou CNPJ=14)
                        if (strlen($cleanDoc) === 11 || strlen($cleanDoc) === 14) {
                            $validOwners[] = [
                                'name' => trim($owner['name']),
                                'document' => $cleanDoc
                            ];
                            error_log("Valid owner added: " . $owner['name'] . " - " . $cleanDoc);
                        } else {
                            error_log("Invalid document length for " . $owner['name'] . ": " . $cleanDoc);
                        }
                    }
                }

                if (!empty($validOwners)) {
                    // Consultar Judit para cada proprietário
                    $juditService = new JuditService(JUDIT_API_KEY);

                    foreach ($validOwners as $owner) {
                        error_log("Consulting Judit for: " . $owner['name'] . " - " . $owner['document']);
                        $result = $juditService->searchLawsuits($owner['document'], $owner['name']);
                        $juditResults[] = $result;
                        error_log("Judit result: " . print_r($result, true));

                        // Aguardar 2 segundos entre consultas
                        sleep(2);
                    }
                } else {
                    error_log("No valid owners found after validation");
                }
            } else {
                error_log("Owners array is empty or invalid");
            }
        } catch (Exception $e) {
            // Se falhar a extração ou consulta Judit, continuar sem os dados
            error_log("ERRO JUDIT: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    } else {
        error_log("╔════════════════════════════════════════════════════════════╗");
        error_log("║           JUDIT INTEGRATION DISABLED                      ║");
        error_log("╚════════════════════════════════════════════════════════════╝");
        
        if (!$JUDIT_ENABLED) {
            error_log("⚠️ Judit está temporariamente DESABILITADA por configuração");
            error_log("💡 Para reabilitar: Altere \$JUDIT_ENABLED = true em process.php");
        } else {
            error_log("⚠️ JUDIT_API_KEY não configurada em config.php");
            error_log("💡 Para habilitar: Configure sua chave em config.php");
            error_log("📖 Veja instruções em: COMO_CONFIGURAR_JUDIT.txt");
        }
        
        error_log("ℹ️ Certidões judiciais ficarão como 'Pendente' no relatório");
    }

    // ETAPA 3: Análise completa com dados da Judit + Partes do Processo
    error_log("=== GERANDO RELATÓRIO FINAL ===");
    error_log("Partes extraídas para relatório: " . count($processParties));
    error_log("Dados Judit disponíveis: " . count($juditResults));
    
    $prompt = getPrompt($juditResults, $processParties);
    
    error_log("Tamanho do prompt final: " . strlen($prompt) . " caracteres");

    // Chamar a API do Gemini
    error_log("Chamando Gemini para análise final...");
    $response = callGeminiAPI($apiKey, $prompt, $uploadedFiles);
    $rawResult = extractTextFromResponse($response);
    
    error_log("Resposta recebida do Gemini, tamanho: " . strlen($rawResult) . " caracteres");
    
    $analysisResult = formatReportHTML($rawResult);
    
    error_log("Relatório formatado, tamanho final: " . strlen($analysisResult) . " caracteres");
    
    // ═══════════════════════════════════════════════════════════════
    // FINALIZAR ANÁLISE NO BANCO DE DADOS
    // ═══════════════════════════════════════════════════════════════
    $resumoJson = json_encode([
        'total_documentos' => $totalFiles,
        'total_partes' => count($processParties),
        'data_processamento' => date('Y-m-d H:i:s'),
        'arquivos' => array_map(function($f) { return $f['name']; }, $uploadedFiles)
    ]);
    
    $db->finalizarAnalise(
        $analiseId,
        $analysisResult,
        $resumoJson,
        count($processParties),
        null // Classificação de risco pode ser extraída posteriormente
    );
    
    error_log("✅ Análise {$analiseId} finalizada e salva no banco");
    
    // Gerar nome do arquivo de relatório
    $reportFileName = 'relatorio_due_diligence_' . date('Y-m-d_H-i-s') . '.html';
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($error) ? 'Erro na Análise' : 'Relatório de Due Diligence'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007bff;
        }
        .header h1 {
            color: #333;
            margin: 0;
        }
        .header .date {
            color: #666;
            margin-top: 10px;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .file-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .file-info h3 {
            margin-top: 0;
            color: #495057;
        }
        .analysis-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.8;
            font-size: 15px;
            color: #333;
            border: 1px solid #e9ecef;
        }
        .analysis-content h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 25px;
            font-size: 24px;
        }
        .analysis-content h2 {
            color: #34495e;
            border-bottom: 2px solid #bdc3c7;
            padding-bottom: 8px;
            margin-top: 30px;
            margin-bottom: 20px;
            font-size: 20px;
        }
        .analysis-content h3 {
            color: #555;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .analysis-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .analysis-content table th {
            background-color: #3498db;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        .analysis-content table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        .analysis-content table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .analysis-content table tr:hover {
            background-color: #e8f4f8;
        }
        .analysis-content ul, .analysis-content ol {
            margin: 15px 0;
            padding-left: 30px;
        }
        .analysis-content li {
            margin-bottom: 8px;
            line-height: 1.6;
        }
        .analysis-content .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .analysis-content .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .analysis-content .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .analysis-content .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .analysis-content .risk-high {
            background-color: #dc3545;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        .analysis-content .risk-medium {
            background-color: #ffc107;
            color: #212529;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        .analysis-content .risk-low {
            background-color: #28a745;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        .analysis-content blockquote {
            border-left: 4px solid #3498db;
            margin: 20px 0;
            padding: 15px 20px;
            background-color: #f8f9fa;
            font-style: italic;
        }
        .analysis-content .section {
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background-color: #fdfdfd;
        }
        .analysis-content .disclaimer {
            background-color: #f1f3f4;
            border: 2px solid #dadce0;
            padding: 20px;
            margin-top: 30px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.5;
        }
        .analysis-content .toc {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        .analysis-content .toc h3 {
            margin-top: 0;
            color: #495057;
        }
        .analysis-content .toc ul {
            margin-bottom: 0;
        }
        .analysis-content .highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .risk-indicator-VERDE {
            color: #28a745 !important;
            font-weight: bold;
            font-size: 18px;
        }
        .risk-indicator-AMARELO {
            color: #ffc107 !important;
            font-weight: bold;
            font-size: 18px;
        }
        .risk-indicator-VERMELHO {
            color: #dc3545 !important;
            font-weight: bold;
            font-size: 18px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .actions {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        @media print {
            .actions, .btn { display: none; }
            body { background-color: white; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo isset($error) ? 'Erro na Análise' : 'Relatório de Due Diligence Imobiliária'; ?></h1>
            <div class="date">Gerado em: <?php echo date('d/m/Y H:i:s'); ?></div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error">
                <h3>Erro durante o processamento:</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
            <div class="actions">
                <a href="index.php" class="btn btn-primary">Voltar ao Formulário</a>
            </div>
        <?php else: ?>
            <div class="success">
                <strong>Análise concluída com sucesso!</strong>
                <p>A análise dos documentos foi processada pela IA e o relatório foi gerado.</p>
            </div>

            <div class="file-info">
                <h3>Arquivos Analisados:</h3>
                <ul>
                    <?php foreach ($uploadedFiles as $file): ?>
                        <li><?php echo htmlspecialchars($file['name']); ?> (<?php echo $file['mime_type']; ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="analysis-content">
                <?php echo $analysisResult; ?>
            </div>

            <div class="actions">
                <button onclick="window.print()" class="btn btn-success">Imprimir Relatório</button>
                <button onclick="downloadReport()" class="btn btn-primary">Baixar Relatório</button>
                <a href="index.php" class="btn btn-secondary">Nova Análise</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function downloadReport() {
            const content = document.querySelector('.analysis-content').innerHTML;
            const fileName = '<?php echo isset($reportFileName) ? str_replace('.html', '', $reportFileName) : 'relatorio_due_diligence'; ?>.html';
            
            const htmlContent = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Due Diligence Imobiliária</title>
    <style>
        body { font-family: Georgia, serif; line-height: 1.8; margin: 40px; color: #333; }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; border-bottom: 2px solid #bdc3c7; padding-bottom: 8px; margin-top: 30px; }
        h3 { color: #555; margin-top: 25px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background-color: #3498db; color: white; padding: 12px; text-align: left; }
        td { padding: 10px 12px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; font-weight: bold; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .risk-high { background-color: #dc3545; color: white; padding: 8px 12px; border-radius: 4px; }
        .risk-medium { background-color: #ffc107; color: #212529; padding: 8px 12px; border-radius: 4px; }
        .risk-low { background-color: #28a745; color: white; padding: 8px 12px; border-radius: 4px; }
        @media print { body { margin: 20px; } }
    </style>
</head>
<body>
    <h1>Relatório de Due Diligence Imobiliária</h1>
    <p><strong>Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
    ${content}
</body>
</html>`;
            
            const element = document.createElement('a');
            const file = new Blob([htmlContent], {type: 'text/html'});
            element.href = URL.createObjectURL(file);
            element.download = fileName;
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        }

        // Auto scroll para mostrar o relatório quando carregado
        <?php if (!isset($error)): ?>
        window.onload = function() {
            document.querySelector('.analysis-content').scrollIntoView({behavior: 'smooth'});
        };
        <?php endif; ?>
    </script>
</body>
</html>