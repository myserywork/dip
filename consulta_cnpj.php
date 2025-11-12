<?php
/**
 * API de Consulta e Enriquecimento de CNPJ
 * 
 * Endpoints:
 * - GET consulta_cnpj.php?cnpj=12345678000199
 * - POST consulta_cnpj.php (body: {"cnpjs": ["...", "..."]})
 * 
 * Retorna dados enriquecidos da empresa incluindo sócios
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações da API
define('API_URL', 'https://comercial.cnpj.ws/cnpj');
define('API_KEY', 'eipOjD63SW0kHKevU2UXlf6GoCriYy6W3zwrpmvmcdyL');
define('TIMEOUT', 15);
define('RETRY_ATTEMPTS', 2);
define('RETRY_DELAY', 1);

// Classe de serviço de enriquecimento de CNPJ
class CNPJEnrichmentService {
    
    private $apiUrl;
    private $apiKey;
    private $timeout;
    private $retryAttempts;
    private $retryDelay;
    
    public function __construct() {
        $this->apiUrl = API_URL;
        $this->apiKey = API_KEY;
        $this->timeout = TIMEOUT;
        $this->retryAttempts = RETRY_ATTEMPTS;
        $this->retryDelay = RETRY_DELAY;
    }
    
    // Valida se um CNPJ é válido
    public function isValidCNPJ($cnpj) {
        if (!$cnpj) return false;
        
        $cleaned = preg_replace('/\D/', '', $cnpj);
        if (strlen($cleaned) !== 14) return false;
        if (preg_match('/^(\d)\1{13}$/', $cleaned)) return false;
        
        // Primeiro dígito verificador
        $sum = 0;
        $weight = 2;
        for ($i = 11; $i >= 0; $i--) {
            $sum += intval($cleaned[$i]) * $weight;
            $weight = $weight === 9 ? 2 : $weight + 1;
        }
        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;
        if ($digit1 !== intval($cleaned[12])) return false;
        
        // Segundo dígito verificador
        $sum = 0;
        $weight = 2;
        for ($i = 12; $i >= 0; $i--) {
            $sum += intval($cleaned[$i]) * $weight;
            $weight = $weight === 9 ? 2 : $weight + 1;
        }
        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;
        if ($digit2 !== intval($cleaned[13])) return false;
        
        return true;
    }
    
    // Limpa e formata CNPJ
    public function cleanCNPJ($cnpj) {
        if (!$cnpj) return null;
        $cleaned = preg_replace('/\D/', '', $cnpj);
        return strlen($cleaned) === 14 ? $cleaned : null;
    }
    
    // Formata CNPJ para exibição
    public function formatCNPJ($cnpj) {
        $cleaned = $this->cleanCNPJ($cnpj);
        if (!$cleaned) return $cnpj;
        
        return substr($cleaned, 0, 2) . '.' . 
               substr($cleaned, 2, 3) . '.' . 
               substr($cleaned, 5, 3) . '/' . 
               substr($cleaned, 8, 4) . '-' . 
               substr($cleaned, 12, 2);
    }
    
    // Consulta dados enriquecidos na API
    public function enrichCNPJData($cnpj) {
        $cleanCnpj = $this->cleanCNPJ($cnpj);
        if (!$cleanCnpj || !$this->isValidCNPJ($cleanCnpj)) {
            throw new Exception("CNPJ inválido: {$cnpj}");
        }
        
        error_log("✅ Consultando CNPJ: " . $this->formatCNPJ($cleanCnpj));
        
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $url = "{$this->apiUrl}/{$cleanCnpj}";
                
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_HTTPHEADER => [
                        'x_api_token: ' . $this->apiKey,
                        'Accept: */*',
                        'User-Agent: DueBot-CNPJ-Enrichment-PHP/1.0'
                    ],
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    throw new Exception("Erro cURL: {$error}");
                }
                
                if ($httpCode !== 200) {
                    throw new Exception("Erro HTTP: {$httpCode}");
                }
                
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
                }
                
                $enrichedData = $this->processEnrichedData($data, $cleanCnpj);
                error_log("✅ Dados enriquecidos obtidos para CNPJ: " . $this->formatCNPJ($cleanCnpj));
                
                return $enrichedData;
                
            } catch (Exception $e) {
                error_log("❌ Tentativa {$attempt}/{$this->retryAttempts} falhou: " . $e->getMessage());
                
                if ($attempt === $this->retryAttempts) {
                    throw new Exception("Falha após {$this->retryAttempts} tentativas: " . $e->getMessage());
                }
                
                sleep($this->retryDelay * $attempt);
            }
        }
    }
    
    // Processa e padroniza os dados retornados pela API
    private function processEnrichedData($apiData, $cnpj) {
        $enrichedData = [
            'cnpj' => $cnpj,
            'cnpj_formatado' => $this->formatCNPJ($cnpj),
            'enriched_at' => date('Y-m-d H:i:s'),
            'source' => 'cnpj_ws_api',
            'data' => [
                // Dados básicos da empresa
                'razao_social' => $apiData['razao_social'] ?? null,
                'nome_fantasia' => $apiData['estabelecimento']['nome_fantasia'] ?? null,
                'capital_social' => $apiData['capital_social'] ?? null,
                'porte' => $apiData['porte']['descricao'] ?? null,
                'natureza_juridica' => $apiData['natureza_juridica']['descricao'] ?? null,
                
                // Dados do estabelecimento
                'situacao_cadastral' => $apiData['estabelecimento']['situacao_cadastral'] ?? null,
                'data_situacao_cadastral' => $this->formatDate($apiData['estabelecimento']['data_situacao_cadastral'] ?? null),
                'data_inicio_atividade' => $this->formatDate($apiData['estabelecimento']['data_inicio_atividade'] ?? null),
                
                // Endereço
                'endereco' => $this->formatAddress($apiData['estabelecimento'] ?? null),
                
                // Contato
                'telefone' => $this->formatPhone($apiData['estabelecimento'] ?? null),
                'email' => $apiData['estabelecimento']['email'] ?? null,
                
                // Atividades
                'atividade_principal' => $apiData['estabelecimento']['atividade_principal']['descricao'] ?? null,
                'atividades_secundarias' => $this->extractAtividadesSecundarias($apiData['estabelecimento'] ?? null),
                
                // Simples Nacional
                'simples_nacional' => [
                    'optante' => ($apiData['simples']['simples'] ?? 'N') === 'S',
                    'data_opcao' => $this->formatDate($apiData['simples']['data_opcao_simples'] ?? null),
                    'data_exclusao' => $this->formatDate($apiData['simples']['data_exclusao_simples'] ?? null),
                    'mei' => ($apiData['simples']['mei'] ?? 'N') === 'S',
                    'data_opcao_mei' => $this->formatDate($apiData['simples']['data_opcao_mei'] ?? null)
                ],
                
                // Sócios - PARTE MAIS IMPORTANTE
                'socios' => $this->processSocios($apiData['socios'] ?? []),
                
                // Metadados
                'processed_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        return $enrichedData;
    }
    
    // Processa dados dos sócios (ENRIQUECIDOS)
    private function processSocios($socios) {
        if (!$socios || !is_array($socios)) return [];
        
        $sociosProcessados = [];
        
        foreach ($socios as $socio) {
            $cpfCnpj = $socio['cpf_cnpj_socio'] ?? null;
            $cleaned = $cpfCnpj ? preg_replace('/\D/', '', $cpfCnpj) : null;
            
            $tipo = 'DESCONHECIDO';
            if ($cleaned) {
                if (strlen($cleaned) === 11) {
                    $tipo = 'PESSOA_FISICA';
                } elseif (strlen($cleaned) === 14) {
                    $tipo = 'PESSOA_JURIDICA';
                }
            }
            
            $sociosProcessados[] = [
                'cpf_cnpj' => $cpfCnpj,
                'cpf_cnpj_limpo' => $cleaned,
                'tipo_documento' => $tipo,
                'nome' => $socio['nome'] ?? null,
                'tipo' => $socio['tipo'] ?? null,
                'data_entrada' => $this->formatDate($socio['data_entrada'] ?? null),
                'qualificacao' => $socio['qualificacao_socio']['descricao'] ?? null,
                'qualificacao_codigo' => $socio['qualificacao_socio']['codigo'] ?? null,
                'representante_legal' => [
                    'cpf' => $socio['cpf_representante_legal'] ?? null,
                    'nome' => $socio['nome_representante'] ?? null,
                    'qualificacao' => $socio['qualificacao_representante'] ?? null
                ],
                'faixa_etaria' => $socio['faixa_etaria'] ?? null,
                'pais' => $socio['pais']['nome'] ?? 'BRASIL'
            ];
        }
        
        return $sociosProcessados;
    }
    
    // Extrai atividades secundárias
    private function extractAtividadesSecundarias($estabelecimento) {
        if (!$estabelecimento || !isset($estabelecimento['atividades_secundarias'])) {
            return [];
        }
        
        $atividades = [];
        foreach ($estabelecimento['atividades_secundarias'] as $atividade) {
            if (isset($atividade['descricao'])) {
                $atividades[] = $atividade['descricao'];
            }
        }
        
        return $atividades;
    }
    
    // Formata endereço completo
    private function formatAddress($estabelecimento) {
        if (!$estabelecimento) return null;
        
        $parts = [];
        if (!empty($estabelecimento['tipo_logradouro'])) $parts[] = $estabelecimento['tipo_logradouro'];
        if (!empty($estabelecimento['logradouro'])) $parts[] = $estabelecimento['logradouro'];
        if (!empty($estabelecimento['numero'])) $parts[] = $estabelecimento['numero'];
        if (!empty($estabelecimento['complemento'])) $parts[] = $estabelecimento['complemento'];
        if (!empty($estabelecimento['bairro'])) $parts[] = $estabelecimento['bairro'];
        if (!empty($estabelecimento['cidade']['nome'])) $parts[] = $estabelecimento['cidade']['nome'];
        if (!empty($estabelecimento['estado']['sigla'])) $parts[] = $estabelecimento['estado']['sigla'];
        if (!empty($estabelecimento['cep'])) $parts[] = "CEP: {$estabelecimento['cep']}";
        
        return count($parts) > 0 ? implode(', ', $parts) : null;
    }
    
    // Formata telefone
    private function formatPhone($estabelecimento) {
        if (!$estabelecimento) return null;
        
        $phones = [];
        if (!empty($estabelecimento['ddd1']) && !empty($estabelecimento['telefone1'])) {
            $phones[] = "({$estabelecimento['ddd1']}) {$estabelecimento['telefone1']}";
        }
        if (!empty($estabelecimento['ddd2']) && !empty($estabelecimento['telefone2'])) {
            $phones[] = "({$estabelecimento['ddd2']}) {$estabelecimento['telefone2']}";
        }
        
        return count($phones) > 0 ? implode(' / ', $phones) : null;
    }
    
    // Formata data para YYYY-MM-DD
    private function formatDate($dateInput) {
        if (!$dateInput) return null;
        
        try {
            $date = new DateTime($dateInput);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
    
    // Enriquece múltiplos CNPJs
    public function enrichMultipleCNPJs($cnpjs) {
        error_log("✅ Iniciando enriquecimento de " . count($cnpjs) . " CNPJs");
        
        $results = [
            'enriched' => [],
            'failed' => [],
            'summary' => [
                'total' => count($cnpjs),
                'success' => 0,
                'failed' => 0,
                'start_time' => date('Y-m-d H:i:s')
            ]
        ];
        
        foreach ($cnpjs as $cnpj) {
            try {
                $enrichedData = $this->enrichCNPJData($cnpj);
                $results['enriched'][] = $enrichedData;
                $results['summary']['success']++;
                
                // Pausa entre requisições
                sleep(1);
                
            } catch (Exception $e) {
                $results['failed'][] = [
                    'cnpj' => $cnpj,
                    'error' => $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $results['summary']['failed']++;
            }
        }
        
        $results['summary']['end_time'] = date('Y-m-d H:i:s');
        
        error_log("✅ Enriquecimento concluído: {$results['summary']['success']}/{$results['summary']['total']} sucessos");
        
        return $results;
    }
}

// ========================================
// Processar requisição
// ========================================

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    $service = new CNPJEnrichmentService();
    
    // POST - Múltiplos CNPJs
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['cnpjs']) || !is_array($input['cnpjs'])) {
            throw new Exception('Forneça um array de CNPJs no body: {"cnpjs": ["...", "..."]}');
        }
        
        $resultado = $service->enrichMultipleCNPJs($input['cnpjs']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    // GET - CNPJ único
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        if (isset($_GET['cnpj']) && !empty($_GET['cnpj'])) {
            $resultado = $service->enrichCNPJData($_GET['cnpj']);
            echo json_encode([
                'sucesso' => true,
                'dados' => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
        } else {
            // Mostrar ajuda
            http_response_code(400);
            echo json_encode([
                'erro' => 'Parâmetro não fornecido',
                'uso' => [
                    'GET - CNPJ único' => 'consulta_cnpj.php?cnpj=12345678000199',
                    'POST - Múltiplos CNPJs' => 'POST /consulta_cnpj.php com body: {"cnpjs": ["...", "..."]}'
                ],
                'exemplos' => [
                    'http://localhost/dip/consulta_cnpj.php?cnpj=04126474000135',
                    'http://localhost/dip/consulta_cnpj.php?cnpj=05.520.899/0001-97'
                ],
                'notas' => [
                    'CNPJ pode ter pontos, traços e barras - serão removidos automaticamente',
                    'Retorna dados enriquecidos incluindo sócios',
                    'API: https://comercial.cnpj.ws'
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    } else {
        throw new Exception('Método não permitido. Use GET ou POST.');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

