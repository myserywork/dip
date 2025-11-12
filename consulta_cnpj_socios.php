<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  API DE CONSULTA CNPJ COM SÓCIOS - cnpj.ws
 * ═══════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');

// Token da API cnpj.ws
define('CNPJ_WS_TOKEN', 'eipOjD63SW0kHKevU2UXlf6GoCriYy6W3zwrpmvmcdyL');

// Parâmetros
$cnpj = $_GET['cnpj'] ?? '';

if (empty($cnpj)) {
    echo json_encode(['erro' => 'CNPJ não fornecido'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Limpar CNPJ
$cnpj = preg_replace('/[^0-9]/', '', $cnpj);

if (strlen($cnpj) !== 14) {
    echo json_encode(['erro' => 'CNPJ inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    error_log("🔍 Consultando CNPJ: {$cnpj} na cnpj.ws...");
    
    // Consultar API da cnpj.ws
    $url = "https://comercial.cnpj.ws/cnpj/{$cnpj}?token=" . CNPJ_WS_TOKEN;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        error_log("❌ Erro ao consultar cnpj.ws: HTTP {$httpCode} - {$curlError}");
        echo json_encode(['erro' => 'Erro ao consultar API: ' . $curlError], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $dados = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("❌ Erro ao decodificar JSON: " . json_last_error_msg());
        echo json_encode(['erro' => 'Erro ao processar resposta da API'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (isset($dados['erro']) || isset($dados['error'])) {
        error_log("❌ CNPJ não encontrado ou erro na API");
        echo json_encode(['erro' => $dados['erro'] ?? $dados['error'] ?? 'CNPJ não encontrado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Formatar resposta padronizada
    $estabelecimento = $dados['estabelecimento'] ?? [];
    
    $resultado = [
        'cnpj' => $estabelecimento['cnpj'] ?? $cnpj,
        'razao_social' => $dados['razao_social'] ?? 'N/A',
        'nome_fantasia' => $estabelecimento['nome_fantasia'] ?? 'N/A',
        'situacao' => $estabelecimento['situacao_cadastral'] ?? 'N/A',
        'data_situacao' => $estabelecimento['data_situacao_cadastral'] ?? 'N/A',
        'tipo' => $estabelecimento['tipo'] ?? 'N/A',
        'abertura' => $estabelecimento['data_inicio_atividade'] ?? 'N/A',
        'capital_social' => $dados['capital_social'] ?? 'N/A',
        'atividade_principal' => $estabelecimento['atividade_principal']['descricao'] ?? 'N/A',
        'natureza_juridica' => $dados['natureza_juridica']['descricao'] ?? 'N/A',
        'porte' => $dados['porte']['descricao'] ?? 'N/A',
        'telefone' => isset($estabelecimento['ddd1'], $estabelecimento['telefone1']) 
            ? "({$estabelecimento['ddd1']}) {$estabelecimento['telefone1']}" 
            : 'N/A',
        'email' => $estabelecimento['email'] ?? 'N/A',
        'endereco' => [
            'logradouro' => ($estabelecimento['tipo_logradouro'] ?? '') . ' ' . ($estabelecimento['logradouro'] ?? ''),
            'numero' => $estabelecimento['numero'] ?? 'N/A',
            'complemento' => $estabelecimento['complemento'] ?? '',
            'bairro' => $estabelecimento['bairro'] ?? 'N/A',
            'municipio' => $estabelecimento['cidade']['nome'] ?? 'N/A',
            'uf' => $estabelecimento['estado']['sigla'] ?? 'N/A',
            'cep' => $estabelecimento['cep'] ?? 'N/A'
        ],
        'socios' => []
    ];
    
    // Extrair sócios (quadro societário) - estrutura cnpj.ws
    if (isset($dados['socios']) && is_array($dados['socios'])) {
        foreach ($dados['socios'] as $socio) {
            $resultado['socios'][] = [
                'nome' => $socio['nome'] ?? 'N/A',
                'cpf_cnpj' => $socio['cpf_cnpj_socio'] ?? null,
                'qualificacao' => $socio['qualificacao_socio']['descricao'] ?? 'N/A',
                'qualificacao_id' => $socio['qualificacao_socio']['id'] ?? null,
                'data_entrada' => $socio['data_entrada'] ?? null,
                'tipo' => $socio['tipo'] ?? null,
                'faixa_etaria' => $socio['faixa_etaria'] ?? null,
                'pais_origem' => $socio['pais']['nome'] ?? null,
                'representante_legal' => $socio['nome_representante'] ?? null,
                'cpf_representante' => $socio['cpf_representante_legal'] ?? null,
                'qualificacao_representante' => $socio['qualificacao_representante'] ?? null
            ];
        }
        
        error_log("✅ CNPJ encontrado: {$resultado['razao_social']} - " . count($resultado['socios']) . " sócio(s)");
    } else {
        error_log("⚠️ CNPJ encontrado mas sem dados de sócios");
    }
    
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("❌ Erro ao consultar CNPJ: " . $e->getMessage());
    echo json_encode(['erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

