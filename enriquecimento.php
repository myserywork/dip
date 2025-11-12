<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  ENRIQUECIMENTO DE DADOS - Sistema de Consulta a APIs
 * ═══════════════════════════════════════════════════════════════
 * 
 * Enriquece dados das partes extraídas com:
 * - API de Pessoa (CPF) - api_pessoa.php
 * - API de CNPJ - consulta_cnpj.php (se existir)
 */

require_once __DIR__ . '/database.php';

class EnriquecimentoService {
    private $db;
    private $apiPessoaUrl;
    private $apiCnpjUrl;
    
    public function __construct() {
        $this->db = new DatabaseManager();
        
        // URLs das APIs locais
        $baseUrl = 'http://localhost/dip';
        $this->apiPessoaUrl = $baseUrl . '/api_pessoa.php';
        $this->apiCnpjUrl = $baseUrl . '/consulta_cnpj_socios.php'; // Nova API com sócios
    }
    
    /**
     * Enriquece partes diretamente (sem buscar do banco)
     * Usado para enriquecimento em tempo real durante análise
     */
    public function enriquecerPartesEmTempoReal($partes, $analiseId = null) {
        error_log("╔════════════════════════════════════════════════════════════╗");
        error_log("║      ENRIQUECIMENTO EM TEMPO REAL                         ║");
        error_log("╚════════════════════════════════════════════════════════════╝");
        
        $total = count($partes);
        error_log("📊 Total de partes a enriquecer: {$total}");
        
        $partesEnriquecidas = [];
        $sucesso = 0;
        $falhas = 0;
        
        foreach ($partes as $index => $parte) {
            $num = $index + 1;
            $nome = $parte['name'] ?? 'N/A';
            $tipo = $parte['document_type'] ?? 'DESCONHECIDO';
            
            error_log("\n[{$num}/{$total}] Enriquecendo: {$nome} ({$tipo})");
            
            $dadosEnriquecidos = null;
            
            try {
                if ($tipo === 'CPF' && isset($parte['document']) && $parte['document'] !== 'NAOENCONTRADO') {
                    $dadosEnriquecidos = $this->consultarCPF($parte['document']);
                } else if ($tipo === 'CNPJ' && isset($parte['document']) && $parte['document'] !== 'NAOENCONTRADO') {
                    // Verificar se deve buscar sócios (vendedor, proprietário, outorgante, cedente)
                    $role = $parte['role'] ?? '';
                    error_log("     🔍 Verificando role: '{$role}'");
                    
                    $deveBuscarSocios = isset($parte['role']) && 
                                        (stripos($parte['role'], 'vendedor') !== false || 
                                         stripos($parte['role'], 'vendedora') !== false ||
                                         stripos($parte['role'], 'outorgante') !== false ||
                                         stripos($parte['role'], 'proprietári') !== false ||
                                         stripos($parte['role'], 'cedente') !== false);
                    
                    if ($deveBuscarSocios) {
                        error_log("     ✅ Role '{$role}' - Buscando sócios!");
                    } else {
                        error_log("     ⚠️ Role '{$role}' não requer busca de sócios");
                    }
                    
                    $dadosEnriquecidos = $this->consultarCNPJ($parte['document'], $deveBuscarSocios);
                }
                
                if ($dadosEnriquecidos) {
                    $parte['dados_enriquecidos'] = $dadosEnriquecidos;
                    
                    // Apenas preparar dados dos sócios - NÃO enriquecer agora (muito lento)
                    if (isset($dadosEnriquecidos['socios']) && is_array($dadosEnriquecidos['socios']) && count($dadosEnriquecidos['socios']) > 0) {
                        error_log("     👥 " . count($dadosEnriquecidos['socios']) . " sócio(s) extraídos (enriquecimento será feito depois)");
                        
                        // Apenas limpar e preparar CPFs para enriquecimento posterior
                        foreach ($dadosEnriquecidos['socios'] as $idx => &$socio) {
                            $cpfSocio = $socio['cpf_cnpj'] ?? '';
                            $cpfLimpo = preg_replace('/[^0-9]/', '', $cpfSocio);
                            
                            $socio['cpf_original'] = $cpfSocio;
                            
                            if (strlen($cpfLimpo) === 11) {
                                $socio['cpf_limpo'] = $cpfLimpo;
                            } else if (strlen($cpfLimpo) > 0) {
                                $socio['cpf_parcial'] = $cpfLimpo;
                            }
                        }
                        unset($socio);
                        
                        $parte['dados_enriquecidos']['socios'] = $dadosEnriquecidos['socios'];
                    }
                    
                    $sucesso++;
                    error_log("  ✅ Enriquecido com sucesso");
                } else {
                    error_log("  ℹ️ Nenhum dado adicional encontrado");
                }
                
            } catch (Exception $e) {
                error_log("  ❌ Erro: " . $e->getMessage());
                $falhas++;
            }
            
            $partesEnriquecidas[] = $parte;
        }
        
        error_log("\n╔════════════════════════════════════════════════════════════╗");
        error_log("║  ✅ Sucesso: {$sucesso} | ❌ Falhas: {$falhas}           ║");
        error_log("╚════════════════════════════════════════════════════════════╝");
        
        return [
            'partes' => $partesEnriquecidas,
            'stats' => [
                'total' => $total,
                'sucesso' => $sucesso,
                'falhas' => $falhas
            ]
        ];
    }
    
    /**
     * Consulta CPF diretamente (retorna dados ou null)
     */
    private function consultarCPF($cpf) {
        try {
            $url = $this->apiPessoaUrl . '?cpf=' . urlencode($cpf);
            $response = @file_get_contents($url);
            
            if ($response === false) {
                return null;
            }
            
            $dados = json_decode($response, true);
            
            if (isset($dados['erro']) || !isset($dados['dados'])) {
                return null;
            }
            
            // Retornar apenas o array interno 'dados' que contém nome_mae, nascimento, etc
            error_log("     → Nome: " . ($dados['dados']['nome'] ?? 'N/A'));
            return $dados['dados'];
            
        } catch (Exception $e) {
            error_log("     Erro CPF: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Consulta CNPJ diretamente (retorna dados ou null)
     */
    private function consultarCNPJ($cnpj, $buscarSocios = false) {
        try {
            error_log("     📞 Consultando CNPJ: {$cnpj} (buscar sócios: " . ($buscarSocios ? 'SIM' : 'NÃO') . ")");
            
            $url = $this->apiCnpjUrl . '?cnpj=' . urlencode($cnpj);
            error_log("     🌐 URL: {$url}");
            
            $response = @file_get_contents($url);
            
            if ($response === false) {
                error_log("     ❌ Erro ao consultar CNPJ - file_get_contents retornou false");
                error_log("     💡 Dica: Verifique se o Apache está rodando e a API está acessível");
                return null;
            }
            
            error_log("     ✅ Resposta recebida: " . substr($response, 0, 200) . "...");
            
            $dados = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("     ❌ Erro ao decodificar JSON: " . json_last_error_msg());
                error_log("     📄 Resposta: " . $response);
                return null;
            }
            
            if (isset($dados['erro'])) {
                error_log("     ⚠️ API retornou erro: " . $dados['erro']);
                return null;
            }
            
            $razaoSocial = $dados['razao_social'] ?? 'N/A';
            $totalSocios = isset($dados['socios']) ? count($dados['socios']) : 0;
            
            error_log("     → Razão Social: {$razaoSocial}");
            error_log("     → Total de sócios no array: {$totalSocios}");
            
            if ($buscarSocios) {
                if ($totalSocios > 0) {
                    error_log("     👥 SÓCIOS ENCONTRADOS: {$totalSocios}");
                    foreach ($dados['socios'] as $i => $socio) {
                        $num = $i + 1;
                        $nomeSocio = $socio['nome'] ?? 'N/A';
                        $qualSocio = $socio['qualificacao'] ?? 'N/A';
                        error_log("        {$num}. {$nomeSocio} - {$qualSocio}");
                    }
                } else {
                    error_log("     ⚠️ Array de sócios está VAZIO ou não existe!");
                    if (isset($dados['socios'])) {
                        error_log("        dados['socios'] = " . json_encode($dados['socios']));
                    } else {
                        error_log("        dados['socios'] NÃO EXISTE na resposta");
                    }
                }
            }
            
            return $dados;
            
        } catch (Exception $e) {
            error_log("     ❌ Exceção ao consultar CNPJ: " . $e->getMessage());
            error_log("     Stack: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Enriquece todas as partes pendentes
     */
    public function enriquecerPendentes($limite = 50) {
        error_log("╔════════════════════════════════════════════════════════════╗");
        error_log("║      INICIANDO ENRIQUECIMENTO DE DADOS                    ║");
        error_log("╚════════════════════════════════════════════════════════════╝");
        
        $partes = $this->db->buscarPartesNaoEnriquecidas($limite);
        $total = count($partes);
        
        error_log("📊 Total de partes pendentes: {$total}");
        
        $sucesso = 0;
        $falhas = 0;
        
        foreach ($partes as $index => $parte) {
            $num = $index + 1;
            error_log("\n[{$num}/{$total}] Processando: {$parte['nome']} ({$parte['tipo_documento']})");
            
            if ($parte['tipo_documento'] === 'CPF') {
                $resultado = $this->enriquecerCPF($parte);
            } else if ($parte['tipo_documento'] === 'CNPJ') {
                $resultado = $this->enriquecerCNPJ($parte);
            } else {
                error_log("  ⚠️ Tipo desconhecido: {$parte['tipo_documento']}");
                continue;
            }
            
            if ($resultado) {
                $sucesso++;
            } else {
                $falhas++;
            }
        }
        
        error_log("\n╔════════════════════════════════════════════════════════════╗");
        error_log("║      ENRIQUECIMENTO CONCLUÍDO                             ║");
        error_log("╠════════════════════════════════════════════════════════════╣");
        error_log("║  ✅ Sucesso: {$sucesso}");
        error_log("║  ❌ Falhas:  {$falhas}");
        error_log("╚════════════════════════════════════════════════════════════╝");
        
        return [
            'total' => $total,
            'sucesso' => $sucesso,
            'falhas' => $falhas
        ];
    }
    
    /**
     * Enriquece dados de CPF via API local
     */
    private function enriquecerCPF($parte) {
        $cpf = $parte['documento'];
        
        try {
            error_log("  🔍 Consultando API de Pessoa (CPF: {$cpf})...");
            
            $url = $this->apiPessoaUrl . '?cpf=' . urlencode($cpf);
            $response = @file_get_contents($url);
            
            if ($response === false) {
                error_log("  ❌ Erro ao consultar API de Pessoa");
                $this->db->salvarEnriquecimento(
                    $parte['id'],
                    'cpf_api_pessoa',
                    null,
                    false,
                    'Erro ao consultar API'
                );
                return false;
            }
            
            $dados = json_decode($response, true);
            
            if (isset($dados['erro'])) {
                error_log("  ℹ️ CPF não encontrado na base");
                $this->db->salvarEnriquecimento(
                    $parte['id'],
                    'cpf_api_pessoa',
                    json_encode(['status' => 'nao_encontrado']),
                    true,
                    null
                );
                return true;
            }
            
            // Sucesso!
            error_log("  ✅ Dados encontrados!");
            error_log("     Nome: " . ($dados['nome'] ?? 'N/A'));
            error_log("     Nome Mãe: " . ($dados['nome_mae'] ?? 'N/A'));
            error_log("     Data Nascimento: " . ($dados['nascimento'] ?? 'N/A'));
            
            $this->db->salvarEnriquecimento(
                $parte['id'],
                'cpf_api_pessoa',
                json_encode($dados),
                true,
                null
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("  ❌ Exceção: " . $e->getMessage());
            $this->db->salvarEnriquecimento(
                $parte['id'],
                'cpf_api_pessoa',
                null,
                false,
                $e->getMessage()
            );
            return false;
        }
    }
    
    /**
     * Enriquece dados de CNPJ via API local (se disponível)
     */
    private function enriquecerCNPJ($parte) {
        $cnpj = $parte['documento'];
        
        try {
            error_log("  🔍 Consultando API de CNPJ ({$cnpj})...");
            
            // Verificar se arquivo existe
            if (!file_exists(__DIR__ . '/consulta_cnpj.php')) {
                error_log("  ⚠️ API de CNPJ não disponível");
                $this->db->salvarEnriquecimento(
                    $parte['id'],
                    'cnpj_api',
                    json_encode(['status' => 'api_nao_disponivel']),
                    true,
                    null
                );
                return true;
            }
            
            $url = $this->apiCnpjUrl . '?cnpj=' . urlencode($cnpj);
            $response = @file_get_contents($url);
            
            if ($response === false) {
                error_log("  ❌ Erro ao consultar API de CNPJ");
                $this->db->salvarEnriquecimento(
                    $parte['id'],
                    'cnpj_api',
                    null,
                    false,
                    'Erro ao consultar API'
                );
                return false;
            }
            
            $dados = json_decode($response, true);
            
            if (isset($dados['erro'])) {
                error_log("  ℹ️ CNPJ não encontrado ou erro na consulta");
                $this->db->salvarEnriquecimento(
                    $parte['id'],
                    'cnpj_api',
                    json_encode(['status' => 'nao_encontrado']),
                    true,
                    null
                );
                return true;
            }
            
            // Sucesso!
            error_log("  ✅ Dados encontrados!");
            if (isset($dados['razao_social'])) {
                error_log("     Razão Social: " . $dados['razao_social']);
            }
            if (isset($dados['nome_fantasia'])) {
                error_log("     Nome Fantasia: " . $dados['nome_fantasia']);
            }
            
            $this->db->salvarEnriquecimento(
                $parte['id'],
                'cnpj_api',
                json_encode($dados),
                true,
                null
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("  ❌ Exceção: " . $e->getMessage());
            $this->db->salvarEnriquecimento(
                $parte['id'],
                'cnpj_api',
                null,
                false,
                $e->getMessage()
            );
            return false;
        }
    }
    
    /**
     * Enriquece uma parte específica por ID
     */
    public function enriquecerParte($parteId) {
        $parte = $this->db->buscarPartePorId($parteId);
        
        if (!$parte) {
            error_log("❌ Parte {$parteId} não encontrada");
            return false;
        }
        
        if ($parte['tipo_documento'] === 'CPF') {
            return $this->enriquecerCPF($parte);
        } else if ($parte['tipo_documento'] === 'CNPJ') {
            return $this->enriquecerCNPJ($parte);
        }
        
        return false;
    }
    
    /**
     * Busca dados enriquecidos de uma parte
     */
    public function buscarDadosEnriquecidos($parteId) {
        return $this->db->buscarHistoricoParte($parteId);
    }
    
    /**
     * Enriquece sócios salvos no banco (ETAPA 2)
     * Busca sócios não enriquecidos e tenta completar dados
     */
    public function enriquecerSocios($analiseId = null, $limite = 50) {
        error_log("\n╔════════════════════════════════════════════════════════════╗");
        error_log("║      ETAPA 2: ENRIQUECIMENTO DE SÓCIOS                    ║");
        error_log("╚════════════════════════════════════════════════════════════╝");
        
        try {
            // Buscar sócios não enriquecidos usando método público
            $socios = $this->db->buscarSociosParaEnriquecimento($analiseId, $limite);
            $total = count($socios);
            
            error_log("📊 Total de sócios a enriquecer: {$total}");
            
            $sucesso = 0;
            $jaEnriquecidos = 0;
            $falhas = 0;
            
            foreach ($socios as $index => $socio) {
                $num = $index + 1;
                $nome = $socio['socio_nome'] ?? $socio['nome'] ?? 'N/A';
                $cpf = $socio['socio_cpf'] ?? $socio['cpf'] ?? '';
                
                error_log("\n[{$num}/{$total}] {$nome}");
                error_log("   Empresa: {$socio['empresa_nome']}");
                error_log("   CPF atual: {$cpf}");
                
                // Se CPF tem 11 dígitos, tentar enriquecer direto
                if (strlen($cpf) === 11) {
                    error_log("   🔍 CPF completo - consultando API...");
                    $dados = $this->consultarCPF($cpf);
                    
                    if ($dados) {
                        error_log("   ✅ Dados encontrados!");
                        
                        // Salvar dados enriquecidos no banco
                        $dadosEnriquecidos = [
                            'cpf' => $cpf,
                            'nome_mae' => $dados['nome_mae'] ?? null,
                            'nascimento' => $dados['nascimento'] ?? null,
                            'rg' => $dados['rg'] ?? null,
                            'sexo' => $dados['sexo'] ?? null
                        ];
                        
                        if ($this->db->atualizarDadosEnriquecidosSocio($socio['id'], $dadosEnriquecidos)) {
                            error_log("   💾 Dados salvos no banco!");
                            $sucesso++;
                        } else {
                            error_log("   ⚠️ Erro ao salvar no banco");
                            $falhas++;
                        }
                    } else {
                        error_log("   ⚠️ CPF não encontrado na API");
                        $falhas++;
                    }
                } else {
                    // CPF parcial - tentar buscar por nome
                    error_log("   ⚠️ CPF parcial ({$cpf}) - buscando por nome...");
                    $cpfCompleto = $this->buscarCPFPorNome($nome, $cpf);
                    
                    if ($cpfCompleto) {
                        error_log("   ✅ CPF completo encontrado: {$cpfCompleto}");
                        
                        // Enriquecer com dados completos
                        $dados = $this->consultarCPF($cpfCompleto);
                        if ($dados) {
                            error_log("   ✅ Sócio enriquecido!");
                            
                            // Salvar TODOS os dados enriquecidos no banco
                            $dadosEnriquecidos = [
                                'cpf' => $cpfCompleto,
                                'nome_mae' => $dados['nome_mae'] ?? null,
                                'nascimento' => $dados['nascimento'] ?? null,
                                'rg' => $dados['rg'] ?? null,
                                'sexo' => $dados['sexo'] ?? null
                            ];
                            
                            if ($this->db->atualizarDadosEnriquecidosSocio($socio['id'], $dadosEnriquecidos)) {
                                error_log("   💾 Dados completos salvos no banco!");
                                $sucesso++;
                            } else {
                                error_log("   ⚠️ Erro ao salvar no banco");
                                $falhas++;
                            }
                        } else {
                            // Se não encontrou dados, pelo menos atualiza o CPF
                            if ($this->db->atualizarCpfSocio($socio['id'], $cpfCompleto)) {
                                error_log("   💾 CPF atualizado no banco");
                            }
                            error_log("   ⚠️ CPF atualizado mas dados não encontrados na API");
                            $falhas++;
                        }
                    } else {
                        error_log("   ❌ CPF completo não encontrado");
                        $falhas++;
                    }
                }
            }
            
            error_log("\n╔════════════════════════════════════════════════════════════╗");
            error_log("║      ENRIQUECIMENTO DE SÓCIOS CONCLUÍDO                   ║");
            error_log("╠════════════════════════════════════════════════════════════╣");
            error_log("║  ✅ Sucesso: {$sucesso}");
            error_log("║  ⚠️ Já enriquecidos: {$jaEnriquecidos}");
            error_log("║  ❌ Falhas: {$falhas}");
            error_log("╚════════════════════════════════════════════════════════════╝");
            
            return [
                'total' => $total,
                'sucesso' => $sucesso,
                'ja_enriquecidos' => $jaEnriquecidos,
                'falhas' => $falhas
            ];
            
        } catch (Exception $e) {
            error_log("❌ Erro ao enriquecer sócios: " . $e->getMessage());
            return [
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca CPF completo pelo nome quando CPF estiver mascarado
     * Faz match pelo CPF parcial para confirmar
     */
    private function buscarCPFPorNome($nome, $cpfParcial = '') {
        try {
            error_log("           🔎 Buscando '{$nome}' na API de pessoas...");
            
            $url = $this->apiPessoaUrl . '?nome=' . urlencode($nome);
            $response = @file_get_contents($url);
            
            if ($response === false) {
                error_log("           ❌ Erro ao consultar API de Pessoa");
                return null;
            }
            
            $dados = json_decode($response, true);
            
            // Verificar se encontrou resultados
            if (!isset($dados['sucesso']) || !$dados['sucesso'] || !$dados['encontrado']) {
                error_log("           ⚠️ Nenhum resultado encontrado");
                return null;
            }
            
            $resultados = $dados['dados'] ?? [];
            $totalResultados = count($resultados);
            error_log("           📋 Encontrados {$totalResultados} resultado(s)");
            
            // Se tem CPF parcial, tentar fazer match
            if (!empty($cpfParcial) && strlen($cpfParcial) > 0) {
                error_log("           🎯 Procurando match com CPF parcial: {$cpfParcial}");
                
                foreach ($resultados as $resultado) {
                    $cpfCompleto = $resultado['cpf'] ?? '';
                    
                    // Verificar se o CPF completo contém os dígitos parciais
                    // CPF mascarado ***610997** vira 610997
                    // Devemos verificar se 610997 está no CPF completo
                    if (strpos($cpfCompleto, $cpfParcial) !== false) {
                        error_log("           ✅ MATCH! CPF encontrado: {$cpfCompleto}");
                        error_log("              Nome no banco: {$resultado['nome']}");
                        return $cpfCompleto;
                    }
                }
                
                error_log("           ⚠️ Nenhum CPF com match encontrado para '{$cpfParcial}'");
                
                // Se não encontrou match mas tem apenas 1 resultado, retornar ele
                if ($totalResultados === 1) {
                    $cpfCompleto = $resultados[0]['cpf'] ?? '';
                    if (!empty($cpfCompleto) && strlen($cpfCompleto) === 11) {
                        error_log("           💡 Único resultado - assumindo que é o correto: {$cpfCompleto}");
                        return $cpfCompleto;
                    }
                }
                
            } else {
                // Sem CPF parcial, retornar o primeiro resultado se tiver apenas 1
                if ($totalResultados === 1) {
                    $cpfCompleto = $resultados[0]['cpf'] ?? '';
                    if (!empty($cpfCompleto) && strlen($cpfCompleto) === 11) {
                        error_log("           ✅ Único resultado encontrado: {$cpfCompleto}");
                        return $cpfCompleto;
                    }
                } else {
                    error_log("           ⚠️ Múltiplos resultados sem CPF parcial para match");
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("           ❌ Erro ao buscar por nome: " . $e->getMessage());
            return null;
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// EXECUÇÃO DIRETA (para rodar via CLI ou Cron)
// ═══════════════════════════════════════════════════════════════

if (php_sapi_name() === 'cli' || (isset($_GET['run']) && $_GET['run'] === 'enriquecer')) {
    $service = new EnriquecimentoService();
    
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
    $resultado = $service->enriquecerPendentes($limite);
    
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode($resultado, JSON_PRETTY_PRINT);
    }
}

