<?php
/**
 * ETAPA 2: Enriquecimento de Sócios
 * Enriquece CPFs dos sócios em background
 */

require_once __DIR__ . '/enriquecimento.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $service = new EnriquecimentoService();
    
    // Parâmetros
    $analiseId = isset($_GET['analise_id']) ? (int)$_GET['analise_id'] : null;
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
    
    // Executar enriquecimento
    $resultado = $service->enriquecerSocios($analiseId, $limite);
    
    echo json_encode([
        'sucesso' => true,
        'resultado' => $resultado,
        'mensagem' => "Enriquecimento concluído: {$resultado['sucesso']} sócios enriquecidos"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

