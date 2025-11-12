<?php
/**
 * Endpoint para extrair certidões de uma análise
 * 
 * GET: /extrair_certidoes.php?analise_id=123
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/certidoes_service.php';

header('Content-Type: application/json; charset=utf-8');

// Aumentar tempo de execução (pode demorar bastante)
set_time_limit(600); // 10 minutos

$analiseId = $_GET['analise_id'] ?? null;

if (!$analiseId) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID da análise não fornecido.']);
    exit;
}

try {
    $db = new DatabaseManager();
    $certidoesService = new CertidoesService($db);
    
    error_log("\n🚀 Iniciando extração de certidões para análise #{$analiseId}");
    
    $resultado = $certidoesService->extrairCertidoesAnalise($analiseId);
    
    if (isset($resultado['erro'])) {
        echo json_encode(['sucesso' => false, 'erro' => $resultado['erro']]);
    } else {
        echo json_encode([
            'sucesso' => true,
            'total_certidoes' => $resultado['total_certidoes'],
            'sucesso_count' => $resultado['sucesso'],
            'falhas_count' => $resultado['falhas'],
            'detalhes' => $resultado['detalhes'],
            'mensagem' => "Extração concluída!\n" .
                         "Total: {$resultado['total_certidoes']}\n" .
                         "Sucesso: {$resultado['sucesso']}\n" .
                         "Falhas: {$resultado['falhas']}"
        ]);
    }
    
    $db->close();

} catch (Exception $e) {
    error_log("❌ Erro fatal em extrair_certidoes.php: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno do servidor: ' . $e->getMessage()]);
}

