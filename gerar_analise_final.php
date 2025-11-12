<?php
/**
 * Endpoint para gerar análise final consolidada
 * 
 * GET: /gerar_analise_final.php?analise_id=123
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/analise_final_service.php';

header('Content-Type: application/json; charset=utf-8');

// Aumentar tempo de execução (pode demorar bastante com muitos documentos)
set_time_limit(600); // 10 minutos

$analiseId = $_GET['analise_id'] ?? null;

if (!$analiseId) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID da análise não fornecido.']);
    exit;
}

try {
    $db = new DatabaseManager();
    
    // Verificar se a análise existe
    $analise = $db->buscarAnalisePorId($analiseId);
    if (!$analise) {
        echo json_encode(['sucesso' => false, 'erro' => 'Análise não encontrada.']);
        exit;
    }
    
    // Usar a API key da análise ou a padrão
    $geminiApiKey = GEMINI_API_KEY;
    
    $analiseFinalService = new AnaliseFinalService($db, $geminiApiKey);
    
    error_log("\n🚀 Iniciando análise final consolidada para análise #{$analiseId}");
    
    $resultado = $analiseFinalService->gerarAnaliseFinal($analiseId);
    
    if ($resultado['sucesso']) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Análise final gerada com sucesso!',
            'total_documentos' => $resultado['total_documentos'],
            'total_certidoes' => $resultado['total_certidoes'],
            'total_partes' => $resultado['total_partes'],
            'total_socios' => $resultado['total_socios'],
            'tamanho_relatorio' => number_format($resultado['tamanho_relatorio'] / 1024, 2) . ' KB'
        ]);
    } else {
        echo json_encode([
            'sucesso' => false,
            'erro' => $resultado['erro'] ?? 'Erro desconhecido ao gerar análise final.'
        ]);
    }
    
    $db->close();

} catch (Exception $e) {
    error_log("❌ Erro fatal em gerar_analise_final.php: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}

