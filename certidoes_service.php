<?php
/**
 * Serviço de Extração de Certidões
 * 
 * Integra com as automações de certidões para extrair documentos
 * das partes vendedoras (CNPJ) e dos sócios (CPF)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class CertidoesService {
    
    private $db;
    private $baseUrl;
    private $pastaUpload;
    private $timeout = 180; // 3 minutos para APIs com CAPTCHA
    
    public function __construct(DatabaseManager $db) {
        $this->db = $db;
        $this->baseUrl = 'http://localhost/dip/automacoes';
        $this->pastaUpload = __DIR__ . '/uploads';
        
        // Criar pasta de uploads se não existir
        if (!is_dir($this->pastaUpload)) {
            mkdir($this->pastaUpload, 0755, true);
        }
    }
    
    /**
     * Extrai todas as certidões para uma análise
     * @param int $analiseId ID da análise
     * @return array Resultado da extração
     */
    public function extrairCertidoesAnalise($analiseId) {
        error_log("\n╔════════════════════════════════════════════════════════════╗");
        error_log("║      EXTRAÇÃO DE CERTIDÕES - Análise #{$analiseId}       ║");
        error_log("╚════════════════════════════════════════════════════════════╝");
        
        $resultado = [
            'total_certidoes' => 0,
            'sucesso' => 0,
            'falhas' => 0,
            'detalhes' => []
        ];
        
        try {
            // 1. Buscar partes da análise
            $partes = $this->db->buscarPartesAnalise($analiseId);
            error_log("📊 Total de partes encontradas: " . count($partes));
            
            // 2. Extrair certidões das empresas vendedoras (CNPJ)
            foreach ($partes as $parte) {
                if ($parte['tipo_documento'] === 'CNPJ') {
                    $role = strtolower($parte['role'] ?? '');
                    
                    // Verificar se é vendedor/outorgante/proprietário/cedente
                    if (stripos($role, 'vendedor') !== false || 
                        stripos($role, 'vendedora') !== false ||
                        stripos($role, 'outorgante') !== false ||
                        stripos($role, 'proprietári') !== false ||
                        stripos($role, 'cedente') !== false) {
                        
                        error_log("\n📌 Empresa Vendedora: {$parte['nome']}");
                        error_log("   CNPJ: {$parte['documento']}");
                        error_log("   Role: {$parte['role']}");
                        
                        // Extrair certidão STJ para CNPJ
                        $resultadoSTJ = $this->extrairCertidaoSTJ_PJ($analiseId, $parte);
                        $resultado['total_certidoes']++;
                        
                        if ($resultadoSTJ['sucesso']) {
                            $resultado['sucesso']++;
                        } else {
                            $resultado['falhas']++;
                        }
                        
                        $resultado['detalhes'][] = $resultadoSTJ;
                    }
                }
            }
            
            // 3. Buscar sócios das empresas vendedoras
            $socios = $this->db->buscarSociosPorAnalise($analiseId);
            error_log("\n📊 Total de sócios encontrados: " . count($socios));
            
            // 4. Extrair certidões dos sócios (CPF)
            foreach ($socios as $socio) {
                $cpf = $socio['socio_cpf'] ?? '';
                
                // Só processar se tiver CPF completo (11 dígitos)
                if (strlen($cpf) === 11) {
                    error_log("\n👤 Sócio: {$socio['socio_nome']}");
                    error_log("   CPF: {$cpf}");
                    error_log("   Empresa: {$socio['empresa_nome']}");
                    
                    // Extrair 3 certidões para cada sócio
                    
                    // 3.1 - STJ Pessoa Física
                    $resultadoSTJ_PF = $this->extrairCertidaoSTJ_PF($analiseId, $socio);
                    $resultado['total_certidoes']++;
                    if ($resultadoSTJ_PF['sucesso']) {
                        $resultado['sucesso']++;
                    } else {
                        $resultado['falhas']++;
                    }
                    $resultado['detalhes'][] = $resultadoSTJ_PF;
                    
                    // 3.2 - TJGO Cível
                    $resultadoTJGO = $this->extrairCertidaoTJGO($analiseId, $socio);
                    $resultado['total_certidoes']++;
                    if ($resultadoTJGO['sucesso']) {
                        $resultado['sucesso']++;
                    } else {
                        $resultado['falhas']++;
                    }
                    $resultado['detalhes'][] = $resultadoTJGO;
                    
                    // 3.3 - TJGO Criminal
                    $resultadoTJGO_Criminal = $this->extrairCertidaoTJGO_Criminal($analiseId, $socio);
                    $resultado['total_certidoes']++;
                    if ($resultadoTJGO_Criminal['sucesso']) {
                        $resultado['sucesso']++;
                    } else {
                        $resultado['falhas']++;
                    }
                    $resultado['detalhes'][] = $resultadoTJGO_Criminal;
                    
                } else {
                    error_log("\n⚠️ Sócio {$socio['socio_nome']} sem CPF completo - Pulando");
                }
            }
            
            error_log("\n╔════════════════════════════════════════════════════════════╗");
            error_log("║      EXTRAÇÃO DE CERTIDÕES CONCLUÍDA                      ║");
            error_log("╠════════════════════════════════════════════════════════════╣");
            error_log("║  📄 Total: {$resultado['total_certidoes']}");
            error_log("║  ✅ Sucesso: {$resultado['sucesso']}");
            error_log("║  ❌ Falhas: {$resultado['falhas']}");
            error_log("╚════════════════════════════════════════════════════════════╝");
            
        } catch (Exception $e) {
            error_log("❌ Erro fatal: " . $e->getMessage());
            $resultado['erro'] = $e->getMessage();
        }
        
        return $resultado;
    }
    
    /**
     * Extrai certidão STJ para Pessoa Jurídica (CNPJ)
     */
    private function extrairCertidaoSTJ_PJ($analiseId, $parte) {
        $cnpj = $parte['documento'];
        $nome = $parte['nome'];
        
        error_log("   🔍 Extraindo STJ PJ...");
        
        try {
            $url = $this->baseUrl . '/certidao_stj.php?' . http_build_query(['cnpj' => $cnpj]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $conteudo = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            $isPDF = strpos($contentType, 'pdf') !== false || substr($conteudo, 0, 4) === '%PDF';
            
            if ($httpCode === 200 && $isPDF) {
                // Salvar PDF
                $nomeArquivo = 'STJ_PJ_' . $cnpj . '_' . time() . '.pdf';
                $caminhoCompleto = $this->pastaUpload . '/' . $nomeArquivo;
                file_put_contents($caminhoCompleto, $conteudo);
                
                // Salvar no banco como documento da análise
                $documentoId = $this->db->salvarDocumento(
                    $analiseId,
                    $nomeArquivo,
                    filesize($caminhoCompleto),
                    'application/pdf',
                    json_encode([
                        'tipo_certidao' => 'STJ_PJ',
                        'cnpj' => $cnpj,
                        'nome_empresa' => $nome,
                        'parte_id' => $parte['id']
                    ])
                );
                
                error_log("      ✅ STJ PJ extraída com sucesso! (Doc ID: {$documentoId})");
                
                return [
                    'tipo' => 'STJ_PJ',
                    'entidade' => $nome,
                    'documento' => $cnpj,
                    'sucesso' => true,
                    'arquivo' => $nomeArquivo,
                    'tamanho' => filesize($caminhoCompleto),
                    'documento_id' => $documentoId
                ];
            } else {
                error_log("      ❌ Erro ao extrair STJ PJ: HTTP {$httpCode}");
                
                return [
                    'tipo' => 'STJ_PJ',
                    'entidade' => $nome,
                    'documento' => $cnpj,
                    'sucesso' => false,
                    'erro' => "HTTP {$httpCode} - Resposta não é PDF"
                ];
            }
            
        } catch (Exception $e) {
            error_log("      ❌ Exceção: " . $e->getMessage());
            
            return [
                'tipo' => 'STJ_PJ',
                'entidade' => $nome,
                'documento' => $cnpj,
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extrai certidão STJ para Pessoa Física (CPF)
     */
    private function extrairCertidaoSTJ_PF($analiseId, $socio) {
        $cpf = $socio['socio_cpf'];
        $nome = $socio['socio_nome'];
        
        error_log("   🔍 Extraindo STJ PF...");
        
        try {
            $url = $this->baseUrl . '/certidao_stj_pf.php?' . http_build_query(['cpf' => $cpf]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $conteudo = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            $isPDF = strpos($contentType, 'pdf') !== false || substr($conteudo, 0, 4) === '%PDF';
            
            if ($httpCode === 200 && $isPDF) {
                $nomeArquivo = 'STJ_PF_' . $cpf . '_' . time() . '.pdf';
                $caminhoCompleto = $this->pastaUpload . '/' . $nomeArquivo;
                file_put_contents($caminhoCompleto, $conteudo);
                
                $documentoId = $this->db->salvarDocumento(
                    $analiseId,
                    $nomeArquivo,
                    filesize($caminhoCompleto),
                    'application/pdf',
                    json_encode([
                        'tipo_certidao' => 'STJ_PF',
                        'cpf' => $cpf,
                        'nome_pessoa' => $nome,
                        'socio_id' => $socio['id'],
                        'empresa' => $socio['empresa_nome']
                    ])
                );
                
                error_log("      ✅ STJ PF extraída! (Doc ID: {$documentoId})");
                
                return [
                    'tipo' => 'STJ_PF',
                    'entidade' => $nome,
                    'documento' => $cpf,
                    'sucesso' => true,
                    'arquivo' => $nomeArquivo,
                    'tamanho' => filesize($caminhoCompleto),
                    'documento_id' => $documentoId
                ];
            } else {
                error_log("      ❌ Erro: HTTP {$httpCode}");
                
                return [
                    'tipo' => 'STJ_PF',
                    'entidade' => $nome,
                    'documento' => $cpf,
                    'sucesso' => false,
                    'erro' => "HTTP {$httpCode}"
                ];
            }
            
        } catch (Exception $e) {
            error_log("      ❌ Exceção: " . $e->getMessage());
            
            return [
                'tipo' => 'STJ_PF',
                'entidade' => $nome,
                'documento' => $cpf,
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extrai certidão TJGO Cível
     */
    private function extrairCertidaoTJGO($analiseId, $socio) {
        $cpf = $socio['socio_cpf'];
        $nome = $socio['socio_nome'];
        $nomeMae = $socio['socio_nome_mae'] ?? '';
        $nascimento = $socio['socio_nascimento'] ?? '';
        
        error_log("   🔍 Extraindo TJGO Cível...");
        
        // Verificar se temos os dados necessários
        if (empty($nomeMae) || empty($nascimento)) {
            error_log("      ⚠️ Sócio não enriquecido - faltam nome da mãe ou data de nascimento");
            
            return [
                'tipo' => 'TJGO_Civel',
                'entidade' => $nome,
                'documento' => $cpf,
                'sucesso' => false,
                'erro' => 'Sócio não enriquecido - faltam dados necessários (nome da mãe e data de nascimento)'
            ];
        }
        
        try {
            // Formatar data de nascimento (YYYY-MM-DD para DD/MM/YYYY)
            $dataFormatada = date('d/m/Y', strtotime($nascimento));
            
            $url = $this->baseUrl . '/certidao_tjgo.php?' . http_build_query([
                'cpf' => $cpf,
                'nome' => $nome,
                'nome_mae' => $nomeMae,
                'data_nascimento' => $dataFormatada
            ]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $conteudo = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            $isPDF = strpos($contentType, 'pdf') !== false || substr($conteudo, 0, 4) === '%PDF';
            
            if ($httpCode === 200 && $isPDF) {
                $nomeArquivo = 'TJGO_Civel_' . $cpf . '_' . time() . '.pdf';
                $caminhoCompleto = $this->pastaUpload . '/' . $nomeArquivo;
                file_put_contents($caminhoCompleto, $conteudo);
                
                $documentoId = $this->db->salvarDocumento(
                    $analiseId,
                    $nomeArquivo,
                    filesize($caminhoCompleto),
                    'application/pdf',
                    json_encode([
                        'tipo_certidao' => 'TJGO_Civel',
                        'cpf' => $cpf,
                        'nome_pessoa' => $nome,
                        'socio_id' => $socio['id'],
                        'empresa' => $socio['empresa_nome']
                    ])
                );
                
                error_log("      ✅ TJGO Cível extraída! (Doc ID: {$documentoId})");
                
                return [
                    'tipo' => 'TJGO_Civel',
                    'entidade' => $nome,
                    'documento' => $cpf,
                    'sucesso' => true,
                    'arquivo' => $nomeArquivo,
                    'tamanho' => filesize($caminhoCompleto),
                    'documento_id' => $documentoId
                ];
            } else {
                error_log("      ❌ Erro: HTTP {$httpCode}");
                
                return [
                    'tipo' => 'TJGO_Civel',
                    'entidade' => $nome,
                    'documento' => $cpf,
                    'sucesso' => false,
                    'erro' => "HTTP {$httpCode}"
                ];
            }
            
        } catch (Exception $e) {
            error_log("      ❌ Exceção: " . $e->getMessage());
            
            return [
                'tipo' => 'TJGO_Civel',
                'entidade' => $nome,
                'documento' => $cpf,
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extrai certidão TJGO Criminal
     */
    private function extrairCertidaoTJGO_Criminal($analiseId, $socio) {
        $cpf = $socio['socio_cpf'];
        $nome = $socio['socio_nome'];
        $nomeMae = $socio['socio_nome_mae'] ?? '';
        $nascimento = $socio['socio_nascimento'] ?? '';
        
        error_log("   🔍 Extraindo TJGO Criminal...");
        
        if (empty($nomeMae) || empty($nascimento)) {
            error_log("      ⚠️ Sócio não enriquecido - faltam dados");
            
            return [
                'tipo' => 'TJGO_Criminal',
                'entidade' => $nome,
                'documento' => $cpf,
                'sucesso' => false,
                'erro' => 'Sócio não enriquecido - faltam dados necessários'
            ];
        }
        
        try {
            $dataFormatada = date('d/m/Y', strtotime($nascimento));
            
            $url = $this->baseUrl . '/certidao_tjgo_criminal.php?' . http_build_query([
                'cpf' => $cpf,
                'nome' => $nome,
                'nome_mae' => $nomeMae,
                'data_nascimento' => $dataFormatada
            ]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $conteudo = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            $isPDF = strpos($contentType, 'pdf') !== false || substr($conteudo, 0, 4) === '%PDF';
            
            if ($httpCode === 200 && $isPDF) {
                $nomeArquivo = 'TJGO_Criminal_' . $cpf . '_' . time() . '.pdf';
                $caminhoCompleto = $this->pastaUpload . '/' . $nomeArquivo;
                file_put_contents($caminhoCompleto, $conteudo);
                
                $documentoId = $this->db->salvarDocumento(
                    $analiseId,
                    $nomeArquivo,
                    filesize($caminhoCompleto),
                    'application/pdf',
                    json_encode([
                        'tipo_certidao' => 'TJGO_Criminal',
                        'cpf' => $cpf,
                        'nome_pessoa' => $nome,
                        'socio_id' => $socio['id'],
                        'empresa' => $socio['empresa_nome']
                    ])
                );
                
                error_log("      ✅ TJGO Criminal extraída! (Doc ID: {$documentoId})");
                
                return [
                    'tipo' => 'TJGO_Criminal',
                    'entidade' => $nome,
                    'documento' => $cpf,
                    'sucesso' => true,
                    'arquivo' => $nomeArquivo,
                    'tamanho' => filesize($caminhoCompleto),
                    'documento_id' => $documentoId
                ];
            } else {
                error_log("      ❌ Erro: HTTP {$httpCode}");
                
                return [
                    'tipo' => 'TJGO_Criminal',
                    'entidade' => $nome,
                    'documento' => $cpf,
                    'sucesso' => false,
                    'erro' => "HTTP {$httpCode}"
                ];
            }
            
        } catch (Exception $e) {
            error_log("      ❌ Exceção: " . $e->getMessage());
            
            return [
                'tipo' => 'TJGO_Criminal',
                'entidade' => $nome,
                'documento' => $cpf,
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
}

