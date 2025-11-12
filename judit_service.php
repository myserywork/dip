<?php
/**
 * Serviço de integração com a API Judit.io
 * Permite consulta de processos judiciais por CPF/CNPJ
 */

class JuditService {
    private $apiKey;
    private $baseUrl = 'https://requests.prod.judit.io';
    private $maxRetries = 30; // Máximo de tentativas para verificar status
    private $retryDelay = 2; // Segundos entre tentativas

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Busca processos judiciais por CPF ou CNPJ
     * @param string $document CPF ou CNPJ (com ou sem formatação)
     * @param string $name Nome da pessoa/empresa para referência
     * @return array Resultado da consulta com processos encontrados
     */
    public function searchLawsuits($document, $name = '') {
        try {
            // Limpar documento (remover pontos, traços, etc)
            $cleanDocument = preg_replace('/[^0-9]/', '', $document);

            // Determinar tipo de documento
            $searchType = strlen($cleanDocument) === 11 ? 'cpf' : 'cnpj';

            // Criar requisição
            $requestId = $this->createRequest($cleanDocument, $searchType);

            if (!$requestId) {
                return [
                    'success' => false,
                    'error' => 'Erro ao criar requisição na Judit',
                    'document' => $document,
                    'name' => $name
                ];
            }

            // Aguardar processamento
            $status = $this->waitForCompletion($requestId);

            if ($status !== 'completed') {
                return [
                    'success' => false,
                    'error' => 'Timeout ou erro no processamento',
                    'status' => $status,
                    'document' => $document,
                    'name' => $name
                ];
            }

            // Obter resultados
            $results = $this->getResults($requestId);

            return [
                'success' => true,
                'document' => $document,
                'name' => $name,
                'search_type' => $searchType,
                'total_lawsuits' => count($results['lawsuits'] ?? []),
                'lawsuits' => $this->processLawsuits($results['lawsuits'] ?? []),
                'consulted_at' => date('d/m/Y H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'document' => $document,
                'name' => $name,
                'search_type' => strlen(preg_replace('/[^0-9]/', '', $document)) === 11 ? 'cpf' : 'cnpj',
                'consulted_at' => date('d/m/Y H:i:s'),
                'total_lawsuits' => 0,
                'lawsuits' => []
            ];
        }
    }

    /**
     * Cria uma requisição na API Judit
     */
    private function createRequest($document, $searchType) {
        $url = $this->baseUrl . '/requests';

        $data = [
            'search' => [
                'search_type' => $searchType,
                'search_key' => $document,
                'cache_ttl_in_days' => 30
            ]
        ];

        $response = $this->makeRequest('POST', $url, $data);

        if (isset($response['request_id'])) {
            return $response['request_id'];
        }

        return null;
    }

    /**
     * Aguarda conclusão do processamento
     */
    private function waitForCompletion($requestId) {
        $url = $this->baseUrl . '/requests?request_id=' . urlencode($requestId);

        for ($i = 0; $i < $this->maxRetries; $i++) {
            $response = $this->makeRequest('GET', $url);

            if (isset($response['status'])) {
                $status = strtolower($response['status']);

                if ($status === 'completed' || $status === 'done') {
                    return 'completed';
                }

                if ($status === 'failed' || $status === 'error') {
                    return 'failed';
                }
            }

            // Aguardar antes da próxima tentativa
            sleep($this->retryDelay);
        }

        return 'timeout';
    }

    /**
     * Obtém os resultados da consulta
     */
    private function getResults($requestId) {
        $url = $this->baseUrl . '/responses?request_id=' . urlencode($requestId);

        $response = $this->makeRequest('GET', $url);

        return $response;
    }

    /**
     * Processa e organiza os processos encontrados
     */
    private function processLawsuits($lawsuits) {
        $processed = [
            'tj_civil' => [],
            'tj_criminal' => [],
            'trf_civil' => [],
            'trf_criminal' => [],
            'trt' => []
        ];

        foreach ($lawsuits as $lawsuit) {
            $processedLawsuit = [
                'cnj_code' => $lawsuit['cnj_code'] ?? 'N/A',
                'court' => $lawsuit['court'] ?? 'N/A',
                'tribunal' => $lawsuit['tribunal'] ?? 'N/A',
                'justice_type' => $lawsuit['justice_description'] ?? 'N/A',
                'area' => $lawsuit['area'] ?? 'N/A',
                'class' => $lawsuit['class'] ?? 'N/A',
                'subject' => $lawsuit['subject'] ?? 'N/A',
                'status' => $lawsuit['status'] ?? 'N/A',
                'situation' => $lawsuit['situation'] ?? 'N/A',
                'distribution_date' => $lawsuit['distribution_date'] ?? 'N/A',
                'last_update' => $lawsuit['last_update_date'] ?? 'N/A',
                'amount' => $lawsuit['amount'] ?? 0,
                'risk_level' => $this->assessRisk($lawsuit)
            ];

            // Classificar por tipo de justiça e área
            $justiceType = strtolower($lawsuit['justice_description'] ?? '');
            $area = strtolower($lawsuit['area'] ?? '');

            if (strpos($justiceType, 'estadual') !== false || strpos($justiceType, 'tj') !== false) {
                if (strpos($area, 'criminal') !== false || strpos($area, 'penal') !== false) {
                    $processed['tj_criminal'][] = $processedLawsuit;
                } else {
                    $processed['tj_civil'][] = $processedLawsuit;
                }
            } elseif (strpos($justiceType, 'federal') !== false || strpos($justiceType, 'trf') !== false) {
                if (strpos($area, 'criminal') !== false || strpos($area, 'penal') !== false) {
                    $processed['trf_criminal'][] = $processedLawsuit;
                } else {
                    $processed['trf_civil'][] = $processedLawsuit;
                }
            } elseif (strpos($justiceType, 'trabalh') !== false || strpos($justiceType, 'trt') !== false) {
                $processed['trt'][] = $processedLawsuit;
            } else {
                // Se não identificar, colocar em cível estadual por padrão
                $processed['tj_civil'][] = $processedLawsuit;
            }
        }

        return $processed;
    }

    /**
     * Avalia o nível de risco do processo
     */
    private function assessRisk($lawsuit) {
        $status = strtolower($lawsuit['status'] ?? '');
        $situation = strtolower($lawsuit['situation'] ?? '');
        $area = strtolower($lawsuit['area'] ?? '');
        $amount = floatval($lawsuit['amount'] ?? 0);

        // Critérios de risco alto
        if (
            strpos($status, 'execu') !== false ||
            strpos($situation, 'penhora') !== false ||
            strpos($area, 'criminal') !== false ||
            $amount > 100000
        ) {
            return 'ALTO';
        }

        // Critérios de risco médio
        if (
            strpos($status, 'ativa') !== false ||
            strpos($status, 'andamento') !== false ||
            $amount > 10000
        ) {
            return 'MÉDIO';
        }

        // Processos arquivados ou finalizados = risco baixo
        if (
            strpos($status, 'arquivado') !== false ||
            strpos($status, 'extinto') !== false ||
            strpos($status, 'finalizado') !== false
        ) {
            return 'BAIXO';
        }

        return 'MÉDIO'; // Padrão
    }

    /**
     * Faz requisição HTTP para a API Judit
     */
    private function makeRequest($method, $url, $data = null) {
        $ch = curl_init();

        $headers = [
            'api-key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

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

    /**
     * Formata resultados para incluir no prompt da IA
     */
    public static function formatForPrompt($juditResults) {
        if (empty($juditResults) || !is_array($juditResults)) {
            return "\n\n**CONSULTA JUDIT:** Nenhuma consulta foi realizada.\n";
        }

        $prompt = "\n\n========================================\n";
        $prompt .= "DADOS DA CONSULTA AUTOMÁTICA JUDIT.IO\n";
        $prompt .= "========================================\n\n";
        $prompt .= "**IMPORTANTE:** Os dados abaixo foram obtidos através de consulta automática na base de dados Judit.io.\n";
        $prompt .= "Você DEVE usar essas informações para preencher as certidões judiciais no relatório.\n\n";

        foreach ($juditResults as $idx => $result) {
            $num = $idx + 1;
            $prompt .= "--- PESSOA {$num}: {$result['name']} ---\n";
            $prompt .= "Documento: {$result['document']}\n";
            $prompt .= "Tipo: " . ($result['search_type'] ?? 'N/A') . "\n";
            $prompt .= "Consultado em: " . ($result['consulted_at'] ?? date('d/m/Y H:i:s')) . "\n\n";

            if (!$result['success']) {
                $prompt .= "❌ ERRO NA CONSULTA: {$result['error']}\n";
                $prompt .= "AÇÃO: Manter certidões como 'Pendente' para esta pessoa.\n\n";
                continue;
            }

            $total = $result['total_lawsuits'];
            $prompt .= "✅ CONSULTA REALIZADA COM SUCESSO\n";
            $prompt .= "Total de processos encontrados: {$total}\n\n";

            if ($total === 0) {
                $prompt .= "✓ CERTIDÕES NEGATIVAS - Nenhum processo encontrado\n";
                $prompt .= "AÇÃO: Marcar TODAS as certidões judiciais como:\n";
                $prompt .= "- Status: 'NEGATIVA - Consultado via Judit em {$result['consulted_at']}'\n";
                $prompt .= "- Análise: 'Sem processos identificados'\n\n";
            } else {
                $lawsuits = $result['lawsuits'];

                // TJ Cível
                $tjCivil = count($lawsuits['tj_civil']);
                $prompt .= "\n📋 CERTIDÃO DE AÇÕES CÍVEIS (TJ): ";
                if ($tjCivil > 0) {
                    $prompt .= "POSITIVA - {$tjCivil} processo(s) encontrado(s)\n";
                    foreach ($lawsuits['tj_civil'] as $i => $law) {
                        $prompt .= "  ⚠️ PROCESSO " . ($i+1) . ":\n";
                        $prompt .= "     - CNJ: {$law['cnj_code']}\n";
                        $prompt .= "     - Tribunal: {$law['tribunal']}\n";
                        $prompt .= "     - Classe: {$law['class']}\n";
                        $prompt .= "     - Assunto: {$law['subject']}\n";
                        $prompt .= "     - Status: {$law['status']}\n";
                        $prompt .= "     - Situação: {$law['situation']}\n";
                        $prompt .= "     - Risco: {$law['risk_level']}\n";
                        if ($law['amount'] > 0) {
                            $prompt .= "     - Valor: R$ " . number_format($law['amount'], 2, ',', '.') . "\n";
                        }
                        $prompt .= "\n";
                    }
                } else {
                    $prompt .= "NEGATIVA\n";
                }

                // TJ Criminal
                $tjCriminal = count($lawsuits['tj_criminal']);
                $prompt .= "\n⚖️ CERTIDÃO DE AÇÕES CRIMINAIS (TJ): ";
                if ($tjCriminal > 0) {
                    $prompt .= "POSITIVA - {$tjCriminal} processo(s) encontrado(s)\n";
                    foreach ($lawsuits['tj_criminal'] as $i => $law) {
                        $prompt .= "  🚨 PROCESSO " . ($i+1) . ":\n";
                        $prompt .= "     - CNJ: {$law['cnj_code']}\n";
                        $prompt .= "     - Tribunal: {$law['tribunal']}\n";
                        $prompt .= "     - Classe: {$law['class']}\n";
                        $prompt .= "     - Assunto: {$law['subject']}\n";
                        $prompt .= "     - Status: {$law['status']}\n";
                        $prompt .= "     - Situação: {$law['situation']}\n";
                        $prompt .= "     - Risco: {$law['risk_level']}\n\n";
                    }
                } else {
                    $prompt .= "NEGATIVA\n";
                }

                // TRF Cível
                $trfCivil = count($lawsuits['trf_civil']);
                $prompt .= "\n📋 CERTIDÃO DE AÇÕES CÍVEIS (TRF): ";
                if ($trfCivil > 0) {
                    $prompt .= "POSITIVA - {$trfCivil} processo(s) encontrado(s)\n";
                    foreach ($lawsuits['trf_civil'] as $i => $law) {
                        $prompt .= "  ⚠️ PROCESSO " . ($i+1) . ":\n";
                        $prompt .= "     - CNJ: {$law['cnj_code']}\n";
                        $prompt .= "     - Tribunal: {$law['tribunal']}\n";
                        $prompt .= "     - Classe: {$law['class']}\n";
                        $prompt .= "     - Assunto: {$law['subject']}\n";
                        $prompt .= "     - Status: {$law['status']}\n";
                        $prompt .= "     - Situação: {$law['situation']}\n";
                        $prompt .= "     - Risco: {$law['risk_level']}\n";
                        if ($law['amount'] > 0) {
                            $prompt .= "     - Valor: R$ " . number_format($law['amount'], 2, ',', '.') . "\n";
                        }
                        $prompt .= "\n";
                    }
                } else {
                    $prompt .= "NEGATIVA\n";
                }

                // TRF Criminal
                $trfCriminal = count($lawsuits['trf_criminal']);
                $prompt .= "\n⚖️ CERTIDÃO DE AÇÕES CRIMINAIS (TRF): ";
                if ($trfCriminal > 0) {
                    $prompt .= "POSITIVA - {$trfCriminal} processo(s) encontrado(s)\n";
                    foreach ($lawsuits['trf_criminal'] as $i => $law) {
                        $prompt .= "  🚨 PROCESSO " . ($i+1) . ":\n";
                        $prompt .= "     - CNJ: {$law['cnj_code']}\n";
                        $prompt .= "     - Tribunal: {$law['tribunal']}\n";
                        $prompt .= "     - Classe: {$law['class']}\n";
                        $prompt .= "     - Assunto: {$law['subject']}\n";
                        $prompt .= "     - Status: {$law['status']}\n";
                        $prompt .= "     - Situação: {$law['situation']}\n";
                        $prompt .= "     - Risco: {$law['risk_level']}\n\n";
                    }
                } else {
                    $prompt .= "NEGATIVA\n";
                }

                // TRT
                $trt = count($lawsuits['trt']);
                $prompt .= "\n👔 CERTIDÃO DE AÇÕES TRABALHISTAS (TRT): ";
                if ($trt > 0) {
                    $prompt .= "POSITIVA - {$trt} processo(s) encontrado(s)\n";
                    foreach ($lawsuits['trt'] as $i => $law) {
                        $prompt .= "  ⚠️ PROCESSO " . ($i+1) . ":\n";
                        $prompt .= "     - CNJ: {$law['cnj_code']}\n";
                        $prompt .= "     - Tribunal: {$law['tribunal']}\n";
                        $prompt .= "     - Classe: {$law['class']}\n";
                        $prompt .= "     - Assunto: {$law['subject']}\n";
                        $prompt .= "     - Status: {$law['status']}\n";
                        $prompt .= "     - Situação: {$law['situation']}\n";
                        $prompt .= "     - Risco: {$law['risk_level']}\n";
                        if ($law['amount'] > 0) {
                            $prompt .= "     - Valor: R$ " . number_format($law['amount'], 2, ',', '.') . "\n";
                        }
                        $prompt .= "\n";
                    }
                } else {
                    $prompt .= "NEGATIVA\n";
                }
            }

            $prompt .= "\n" . str_repeat("-", 60) . "\n\n";
        }

        $prompt .= "========================================\n";
        $prompt .= "INSTRUÇÕES PARA USO DOS DADOS JUDIT:\n";
        $prompt .= "========================================\n";
        $prompt .= "1. SUBSTITUA os status 'Pendente' ou 'Não recebido' pelas informações REAIS acima\n";
        $prompt .= "2. Para certidões POSITIVAS, adicione alertas detalhados com os dados dos processos\n";
        $prompt .= "3. Para certidões NEGATIVAS, marque como 'NEGATIVA - Consultado via Judit em [data]'\n";
        $prompt .= "4. Analise o RISCO de cada processo e inclua na sua análise geral\n";
        $prompt .= "5. Se houve ERRO na consulta, mantenha como 'Pendente' e mencione que a consulta automática falhou\n\n";

        return $prompt;
    }
}
