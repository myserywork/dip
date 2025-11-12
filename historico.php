<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  HISTÓRICO DE ANÁLISES - Visualização e Estatísticas
 * ═══════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/database.php';

$db = new DatabaseManager();

// Buscar dados
$stats = $db->getEstatisticas();
$analises = $db->buscarUltimasAnalises(50);
$partesNaoEnriquecidas = $db->buscarPartesNaoEnriquecidas(10);

// Verificar se é requisição de detalhes de uma análise
$analiseDetalhada = null;
if (isset($_GET['analise_id'])) {
    $analiseDetalhada = $db->buscarAnalise((int)$_GET['analise_id']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Análises - DIP Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white py-6 shadow-lg">
        <div class="container mx-auto px-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold flex items-center">
                        <span class="text-4xl mr-3">📊</span>
                        HISTÓRICO DE ANÁLISES
                    </h1>
                    <p class="mt-2 opacity-90">Sistema de persistência e enriquecimento de dados</p>
                </div>
                <a href="index.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 px-6 py-3 rounded-lg font-semibold transition flex items-center">
                    <span class="mr-2">🏠</span>
                    Voltar ao Início
                </a>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-6 py-8">
        
        <!-- Estatísticas Gerais -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                <span class="mr-2">📈</span>Estatísticas Gerais
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 uppercase tracking-wide">Total Análises</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['total_analises']; ?></p>
                        </div>
                        <div class="text-4xl">📋</div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 uppercase tracking-wide">Partes Extraídas</p>
                            <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $stats['total_partes']; ?></p>
                        </div>
                        <div class="text-4xl">👥</div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 uppercase tracking-wide">CPFs Únicos</p>
                            <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $stats['cpfs_unicos']; ?></p>
                        </div>
                        <div class="text-4xl">🆔</div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 uppercase tracking-wide">CNPJs Únicos</p>
                            <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo $stats['cnpjs_unicos']; ?></p>
                        </div>
                        <div class="text-4xl">🏢</div>
                    </div>
                </div>
            </div>

            <!-- Enriquecimento -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500 uppercase tracking-wide mb-2">Partes Enriquecidas</p>
                    <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-green-600"><?php echo $stats['partes_enriquecidas']; ?></p>
                        <a href="enriquecimento.php?run=enriquecer&limite=10" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                            🚀 Enriquecer Agora
                        </a>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-500 uppercase tracking-wide mb-2">Pendentes Enriquecimento</p>
                    <p class="text-3xl font-bold text-yellow-600"><?php echo $stats['partes_pendentes']; ?></p>
                </div>
            </div>
        </div>

        <!-- Últimas Análises -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                    <span class="mr-2">📝</span>Últimas Análises
                </h2>
                <a href="index.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    ➕ Nova Análise
                </a>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase">Docs</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase">Partes</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($analises)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    Nenhuma análise realizada ainda
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($analises as $analise): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        #<?php echo $analise['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <?php echo date('d/m/Y H:i', strtotime($analise['data_criacao'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php if ($analise['status'] === 'concluida'): ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-800">
                                                ✅ Concluída
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-800">
                                                ⏳ <?php echo ucfirst($analise['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-center font-semibold text-gray-900">
                                        <?php echo $analise['total_docs']; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-center font-semibold text-blue-600">
                                        <?php echo $analise['total_parts']; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-center">
                                        <a href="?analise_id=<?php echo $analise['id']; ?>" class="text-blue-600 hover:underline">
                                            Ver Detalhes
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Partes Pendentes de Enriquecimento -->
        <?php if (!empty($partesNaoEnriquecidas)): ?>
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                <span class="mr-2">⏳</span>Partes Pendentes de Enriquecimento
            </h2>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">Nome</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">Documento</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">Qualificação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($partesNaoEnriquecidas as $parte): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($parte['nome']); ?></td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-700"><?php echo $parte['documento']; ?></td>
                                <td class="px-6 py-4 text-sm">
                                    <?php if ($parte['tipo_documento'] === 'CPF'): ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">PF</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded bg-purple-100 text-purple-800">PJ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($parte['role']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detalhes da Análise (Modal-like) -->
        <?php if ($analiseDetalhada): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" onclick="window.location.href='historico.php'">
            <div class="bg-white rounded-lg shadow-2xl max-w-6xl w-full max-h-screen overflow-auto" onclick="event.stopPropagation()">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-6 sticky top-0">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold">Análise #<?php echo $analiseDetalhada['id']; ?></h2>
                        <a href="historico.php" class="text-white hover:text-gray-200 text-3xl">&times;</a>
                    </div>
                    <p class="text-sm opacity-90 mt-1">
                        <?php echo date('d/m/Y H:i:s', strtotime($analiseDetalhada['data_criacao'])); ?>
                    </p>
                </div>

                <div class="p-6">
                    <!-- Documentos -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">📄 Documentos Analisados (<?php echo count($analiseDetalhada['documentos']); ?>)</h3>
                        <ul class="space-y-2">
                            <?php foreach ($analiseDetalhada['documentos'] as $doc): ?>
                                <li class="flex items-center text-sm text-gray-700">
                                    <span class="mr-2">•</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($doc['nome_arquivo']); ?></span>
                                    <span class="ml-2 text-gray-500">(<?php echo number_format($doc['tamanho_bytes']/1024, 1); ?> KB)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Partes -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">👥 Partes Identificadas (<?php echo count($analiseDetalhada['partes']); ?>)</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Nome</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Documento</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Tipo</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Qualificação</th>
                                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-700">Enriquecido</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($analiseDetalhada['partes'] as $parte): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($parte['nome']); ?></td>
                                            <td class="px-4 py-2 text-sm font-mono"><?php echo $parte['documento']; ?></td>
                                            <td class="px-4 py-2 text-sm">
                                                <?php if ($parte['tipo_documento'] === 'CPF'): ?>
                                                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">PF</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 text-xs rounded bg-purple-100 text-purple-800">PJ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($parte['role']); ?></td>
                                            <td class="px-4 py-2 text-center">
                                                <?php if ($parte['enriquecido']): ?>
                                                    <span class="text-green-600">✅</span>
                                                <?php else: ?>
                                                    <span class="text-gray-400">⏳</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sócios -->
                    <?php 
                    $socios = $db->buscarSociosPorAnalise($analiseDetalhada['id']);
                    if (!empty($socios)): 
                    ?>
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-lg font-semibold text-gray-900">🏢 Sócios Identificados (<?php echo count($socios); ?>)</h3>
                            <button onclick="enriquecerSocios(<?php echo $analiseDetalhada['id']; ?>)" 
                                    class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded transition-colors"
                                    id="btnEnriquecerSocios">
                                ✨ Enriquecer Sócios
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200">
                                <thead class="bg-orange-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Empresa</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Nome do Sócio</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Qualificação</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">CPF</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Dados Enriquecidos</th>
                                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-700">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($socios as $socio): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm">
                                                <div class="font-medium"><?php echo htmlspecialchars($socio['empresa_nome']); ?></div>
                                                <div class="text-xs text-gray-500 font-mono"><?php echo $socio['empresa_cnpj']; ?></div>
                                            </td>
                                            <td class="px-4 py-2 text-sm font-medium"><?php echo htmlspecialchars($socio['socio_nome'] ?? 'N/A'); ?></td>
                                            <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($socio['socio_qualificacao'] ?? '-'); ?></td>
                                            <td class="px-4 py-2 text-sm font-mono">
                                                <?php 
                                                $cpf = $socio['socio_cpf'] ?? '-';
                                                if ($cpf !== '-' && strlen($cpf) === 11) {
                                                    // Formatar CPF
                                                    $cpf = substr($cpf, 0, 3) . '.' . 
                                                           substr($cpf, 3, 3) . '.' . 
                                                           substr($cpf, 6, 3) . '-' . 
                                                           substr($cpf, 9, 2);
                                                }
                                                echo $cpf;
                                                ?>
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                <?php if ($socio['socio_enriquecido']): ?>
                                                    <div class="text-xs space-y-1">
                                                        <?php if ($socio['socio_nome_mae']): ?>
                                                            <div><strong>Mãe:</strong> <?php echo htmlspecialchars($socio['socio_nome_mae']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if ($socio['socio_nascimento']): ?>
                                                            <div><strong>Nasc:</strong> <?php echo htmlspecialchars($socio['socio_nascimento']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if ($socio['socio_rg']): ?>
                                                            <div><strong>RG:</strong> <?php echo htmlspecialchars($socio['socio_rg']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if ($socio['socio_sexo']): ?>
                                                            <div><strong>Sexo:</strong> <?php echo htmlspecialchars($socio['socio_sexo']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">Não enriquecido</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <?php if ($socio['socio_enriquecido']): ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        ✓ Enriquecido
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                                                        ⏳ Pendente
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 p-3 bg-orange-50 border-l-4 border-orange-500 text-sm">
                            <p class="text-orange-800"><strong>⚠️ Atenção:</strong> Estes sócios devem constar como outorgantes/vendedores nos documentos.</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Certidões -->
                    <?php 
                    // Buscar certidões (documentos com metadata tipo_certidao)
                    $certidoes = $db->buscarCertidoesAnalise($analiseDetalhada['id']);
                    
                    // Buscar partes para o card de análise final
                    $partes = $analiseDetalhada['partes'];
                    
                    // Agrupar certidões por tipo
                    $certidoesPorTipo = [];
                    foreach ($certidoes as $cert) {
                        $meta = json_decode($cert['metadata'], true);
                        if (isset($meta['tipo_certidao'])) {
                            $certidoesPorTipo[$meta['tipo_certidao']][] = [
                                'arquivo' => $cert['nome_arquivo'],
                                'tamanho' => $cert['tamanho_bytes'],
                                'data' => $cert['data_upload'],
                                'metadata' => $meta
                            ];
                        }
                    }
                    ?>
                    
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-lg font-semibold text-gray-900">📜 Certidões Extraídas (<?php echo count($certidoes); ?>)</h3>
                            <button onclick="extrairCertidoes(<?php echo $analiseDetalhada['id']; ?>)" 
                                    class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white text-sm rounded transition-colors"
                                    id="btnExtrairCertidoes">
                                📥 Extrair Certidões
                            </button>
                        </div>
                        
                        <?php if (!empty($certidoes)): ?>
                        <div class="space-y-4">
                            <!-- STJ Pessoa Jurídica -->
                            <?php if (isset($certidoesPorTipo['STJ_PJ'])): ?>
                            <div class="border border-purple-200 rounded-lg p-4 bg-purple-50">
                                <h4 class="font-semibold text-purple-900 mb-2">⚖️ STJ - Certidão Pessoa Jurídica (<?php echo count($certidoesPorTipo['STJ_PJ']); ?>)</h4>
                                <div class="space-y-2">
                                    <?php foreach ($certidoesPorTipo['STJ_PJ'] as $cert): ?>
                                    <div class="flex items-center justify-between bg-white p-2 rounded border border-purple-100">
                                        <div class="flex-1">
                                            <div class="font-medium text-sm"><?php echo htmlspecialchars($cert['metadata']['nome_empresa'] ?? 'N/A'); ?></div>
                                            <div class="text-xs text-gray-600">CNPJ: <?php echo htmlspecialchars($cert['metadata']['cnpj'] ?? 'N/A'); ?></div>
                                        </div>
                                        <a href="uploads/<?php echo htmlspecialchars($cert['arquivo']); ?>" 
                                           target="_blank"
                                           class="px-3 py-1 bg-purple-500 hover:bg-purple-600 text-white text-xs rounded">
                                            📄 Ver PDF
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- STJ Pessoa Física -->
                            <?php if (isset($certidoesPorTipo['STJ_PF'])): ?>
                            <div class="border border-blue-200 rounded-lg p-4 bg-blue-50">
                                <h4 class="font-semibold text-blue-900 mb-2">⚖️ STJ - Certidão Pessoa Física (<?php echo count($certidoesPorTipo['STJ_PF']); ?>)</h4>
                                <div class="space-y-2">
                                    <?php foreach ($certidoesPorTipo['STJ_PF'] as $cert): ?>
                                    <div class="flex items-center justify-between bg-white p-2 rounded border border-blue-100">
                                        <div class="flex-1">
                                            <div class="font-medium text-sm"><?php echo htmlspecialchars($cert['metadata']['nome_pessoa'] ?? 'N/A'); ?></div>
                                            <div class="text-xs text-gray-600">CPF: <?php echo htmlspecialchars($cert['metadata']['cpf'] ?? 'N/A'); ?></div>
                                        </div>
                                        <a href="uploads/<?php echo htmlspecialchars($cert['arquivo']); ?>" 
                                           target="_blank"
                                           class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs rounded">
                                            📄 Ver PDF
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- TJGO Cível -->
                            <?php if (isset($certidoesPorTipo['TJGO_Civel'])): ?>
                            <div class="border border-green-200 rounded-lg p-4 bg-green-50">
                                <h4 class="font-semibold text-green-900 mb-2">🏛️ TJGO - Certidão Cível (<?php echo count($certidoesPorTipo['TJGO_Civel']); ?>)</h4>
                                <div class="space-y-2">
                                    <?php foreach ($certidoesPorTipo['TJGO_Civel'] as $cert): ?>
                                    <div class="flex items-center justify-between bg-white p-2 rounded border border-green-100">
                                        <div class="flex-1">
                                            <div class="font-medium text-sm"><?php echo htmlspecialchars($cert['metadata']['nome_pessoa'] ?? 'N/A'); ?></div>
                                            <div class="text-xs text-gray-600">CPF: <?php echo htmlspecialchars($cert['metadata']['cpf'] ?? 'N/A'); ?></div>
                                        </div>
                                        <a href="uploads/<?php echo htmlspecialchars($cert['arquivo']); ?>" 
                                           target="_blank"
                                           class="px-3 py-1 bg-green-500 hover:bg-green-600 text-white text-xs rounded">
                                            📄 Ver PDF
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- TJGO Criminal -->
                            <?php if (isset($certidoesPorTipo['TJGO_Criminal'])): ?>
                            <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                                <h4 class="font-semibold text-red-900 mb-2">🏛️ TJGO - Certidão Criminal (<?php echo count($certidoesPorTipo['TJGO_Criminal']); ?>)</h4>
                                <div class="space-y-2">
                                    <?php foreach ($certidoesPorTipo['TJGO_Criminal'] as $cert): ?>
                                    <div class="flex items-center justify-between bg-white p-2 rounded border border-red-100">
                                        <div class="flex-1">
                                            <div class="font-medium text-sm"><?php echo htmlspecialchars($cert['metadata']['nome_pessoa'] ?? 'N/A'); ?></div>
                                            <div class="text-xs text-gray-600">CPF: <?php echo htmlspecialchars($cert['metadata']['cpf'] ?? 'N/A'); ?></div>
                                        </div>
                                        <a href="uploads/<?php echo htmlspecialchars($cert['arquivo']); ?>" 
                                           target="_blank"
                                           class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white text-xs rounded">
                                            📄 Ver PDF
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3 p-3 bg-purple-50 border-l-4 border-purple-500 text-sm">
                            <p class="text-purple-800"><strong>ℹ️ Info:</strong> Certidões são extraídas automaticamente para empresas vendedoras (CNPJ) e seus sócios (CPF).</p>
                        </div>
                        
                        <?php else: ?>
                        <div class="p-8 text-center bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg">
                            <p class="text-gray-600 mb-2">📜 Nenhuma certidão extraída ainda</p>
                            <p class="text-sm text-gray-500">Clique em "Extrair Certidões" para gerar certidões STJ e TJGO</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Análise Final Consolidada -->
                    <div class="mb-6 p-6 bg-gradient-to-r from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-indigo-900 mb-2">🎯 Análise Final Consolidada</h3>
                                <p class="text-sm text-indigo-700">
                                    Gere um relatório final completo que integra TODOS os dados: documentos originais, 
                                    certidões extraídas, partes identificadas, sócios enriquecidos e validações cruzadas.
                                </p>
                                <div class="mt-3 flex items-center gap-2 text-xs text-indigo-600">
                                    <span class="inline-flex items-center px-2 py-1 bg-white rounded">
                                        📄 <?php echo count($db->buscarDocumentosOriginais($analiseDetalhada['id'])); ?> Documentos
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 bg-white rounded">
                                        📜 <?php echo count($certidoes); ?> Certidões
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 bg-white rounded">
                                        👥 <?php echo count($partes); ?> Partes
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 bg-white rounded">
                                        🏢 <?php echo count($socios); ?> Sócios
                                    </span>
                                </div>
                            </div>
                            <div class="ml-6">
                                <button onclick="gerarAnaliseFinal(<?php echo $analiseDetalhada['id']; ?>)" 
                                        class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-lg shadow-lg transition-all transform hover:scale-105"
                                        id="btnAnaliseFinal">
                                    🚀 Gerar Análise Final
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 p-3 bg-white bg-opacity-50 rounded border border-indigo-200 text-xs text-indigo-700">
                            <strong>💡 Dica:</strong> A análise final pode demorar alguns minutos dependendo da quantidade de documentos. 
                            O sistema enviará tudo para o Gemini para gerar um relatório profissional e completo.
                        </div>
                    </div>

                    <!-- Relatório HTML -->
                    <?php if ($analiseDetalhada['html_relatorio']): ?>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">📊 Relatório Completo</h3>
                        <div class="border border-gray-200 rounded p-4 bg-gray-50 max-h-96 overflow-auto">
                            <?php echo $analiseDetalhada['html_relatorio']; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

<script>
function enriquecerSocios(analiseId) {
    const btn = document.getElementById('btnEnriquecerSocios');
    const originalText = btn.innerHTML;
    
    // Desabilitar botão
    btn.disabled = true;
    btn.innerHTML = '⏳ Enriquecendo...';
    btn.classList.add('opacity-50', 'cursor-wait');
    
    // Fazer requisição
    fetch(`enriquecer_socios.php?analise_id=${analiseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                alert(`✅ Enriquecimento concluído!\n\n${data.mensagem}`);
                // Recarregar página para mostrar dados atualizados
                location.reload();
            } else {
                alert(`❌ Erro: ${data.erro || 'Erro desconhecido'}`);
                btn.disabled = false;
                btn.innerHTML = originalText;
                btn.classList.remove('opacity-50', 'cursor-wait');
            }
        })
        .catch(error => {
            alert(`❌ Erro ao enriquecer sócios: ${error.message}`);
            btn.disabled = false;
            btn.innerHTML = originalText;
            btn.classList.remove('opacity-50', 'cursor-wait');
        });
}

function extrairCertidoes(analiseId) {
    const btn = document.getElementById('btnExtrairCertidoes');
    const originalText = btn.innerHTML;
    
    if (!confirm('⚠️ Atenção: Extrair certidões pode demorar vários minutos (até 10 minutos).\n\n' +
                 'Certidões que serão extraídas:\n' +
                 '• STJ Pessoa Jurídica (empresas vendedoras)\n' +
                 '• STJ Pessoa Física (sócios)\n' +
                 '• TJGO Cível (sócios)\n' +
                 '• TJGO Criminal (sócios)\n\n' +
                 'Deseja continuar?')) {
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '⏳ Extraindo... (pode demorar)';
    btn.classList.add('opacity-50', 'cursor-wait');
    
    fetch(`extrair_certidoes.php?analise_id=${analiseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                alert('✅ Certidões extraídas com sucesso!\n\n' + 
                      'Total: ' + data.total_certidoes + '\n' +
                      'Sucesso: ' + data.sucesso_count + '\n' +
                      'Falhas: ' + data.falhas_count);
                location.reload();
            } else {
                alert('❌ Erro: ' + (data.erro || 'Erro desconhecido'));
                btn.disabled = false;
                btn.innerHTML = originalText;
                btn.classList.remove('opacity-50', 'cursor-wait');
            }
        })
        .catch(error => {
            alert('❌ Erro ao extrair certidões: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
            btn.classList.remove('opacity-50', 'cursor-wait');
        });
}

function gerarAnaliseFinal(analiseId) {
    const btn = document.getElementById('btnAnaliseFinal');
    const originalText = btn.innerHTML;
    
    if (!confirm('🎯 ANÁLISE FINAL CONSOLIDADA\n\n' +
                 'Esta função irá:\n' +
                 '✅ Integrar todos os documentos originais\n' +
                 '✅ Incluir todas as certidões extraídas\n' +
                 '✅ Validar partes e sócios identificados\n' +
                 '✅ Realizar análise cruzada de dados\n' +
                 '✅ Gerar relatório profissional completo\n\n' +
                 '⏱️ Tempo estimado: 3-5 minutos\n\n' +
                 'O relatório atual será SUBSTITUÍDO pelo novo.\n\n' +
                 'Deseja continuar?')) {
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '🤖 Gerando análise... (aguarde)';
    btn.classList.add('opacity-50', 'cursor-wait');
    
    // Mostrar mensagem de progresso
    const progressMsg = document.createElement('div');
    progressMsg.id = 'progressMsg';
    progressMsg.className = 'mt-4 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700 text-sm';
    progressMsg.innerHTML = '<strong>🔄 Processando...</strong><br>Enviando documentos para análise. Isso pode demorar alguns minutos.';
    btn.parentElement.parentElement.appendChild(progressMsg);
    
    fetch(`gerar_analise_final.php?analise_id=${analiseId}`)
        .then(response => response.json())
        .then(data => {
            if (progressMsg) progressMsg.remove();
            
            if (data.sucesso) {
                alert('✅ Análise Final gerada com sucesso!\n\n' + 
                      '📄 Documentos: ' + data.total_documentos + '\n' +
                      '📜 Certidões: ' + data.total_certidoes + '\n' +
                      '👥 Partes: ' + data.total_partes + '\n' +
                      '🏢 Sócios: ' + data.total_socios + '\n' +
                      '📊 Relatório: ' + data.tamanho_relatorio + '\n\n' +
                      'Recarregando página...');
                location.reload();
            } else {
                alert('❌ Erro ao gerar análise final:\n\n' + (data.erro || 'Erro desconhecido'));
                btn.disabled = false;
                btn.innerHTML = originalText;
                btn.classList.remove('opacity-50', 'cursor-wait');
            }
        })
        .catch(error => {
            if (progressMsg) progressMsg.remove();
            alert('❌ Erro ao gerar análise final:\n\n' + error.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
            btn.classList.remove('opacity-50', 'cursor-wait');
        });
}
</script>

</body>
</html>

