#!/usr/bin/env php
<?php
/**
 * Script para testar todas as APIs de Certidões PHP
 * 
 * Uso: php testar_certidoes.php
 */

// Carregar configurações
if (!file_exists(__DIR__ . '/config_teste.php')) {
    die("❌ Arquivo config_teste.php não encontrado!\n" . 
        "Crie o arquivo config_teste.php com seus dados de teste.\n");
}

$config = require __DIR__ . '/config_teste.php';

// Configurações
define('PASTA_CERTIDOES', $config['pasta_certidoes']);
define('BASE_URL', $config['base_url']);

$dadosTeste = $config;
$apisAtivas = $config['apis_ativas'];

// Criar pasta para certidões se não existir
if (!is_dir(PASTA_CERTIDOES)) {
    mkdir(PASTA_CERTIDOES, 0755, true);
    echo "✓ Pasta de certidões criada: " . PASTA_CERTIDOES . "\n\n";
}

// Cores para terminal
class Cores {
    const VERDE = "\033[32m";
    const VERMELHO = "\033[31m";
    const AMARELO = "\033[33m";
    const AZUL = "\033[34m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

// Função para fazer requisição HTTP com retry
function fazerRequisicao($url, $timeout = 180, $maxTentativas = 2) {
    $tentativa = 0;
    
    while ($tentativa < $maxTentativas) {
        $tentativa++;
        
        if ($tentativa > 1) {
            echo Cores::AMARELO . "  ⟳ Tentativa " . $tentativa . " de " . $maxTentativas . "...\n" . Cores::RESET;
            sleep(3); // Aguarda 3 segundos antes de tentar novamente
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $resultado = [
            'success' => $httpCode === 200,
            'code' => $httpCode,
            'content_type' => $contentType,
            'data' => $response,
            'error' => $error,
            'is_pdf' => strpos($contentType, 'pdf') !== false || substr($response, 0, 4) === '%PDF',
            'tentativas' => $tentativa
        ];
        
        // Se teve sucesso e é PDF, retorna imediatamente
        if ($resultado['success'] && $resultado['is_pdf']) {
            if ($tentativa > 1) {
                echo Cores::VERDE . "  ✓ Sucesso na tentativa " . $tentativa . "!\n" . Cores::RESET;
            }
            return $resultado;
        }
        
        // Se falhou mas ainda tem tentativas, continua o loop
        if ($tentativa < $maxTentativas) {
            echo Cores::AMARELO . "  ⚠ Erro (HTTP " . $httpCode . ") - Tentando novamente...\n" . Cores::RESET;
        }
    }
    
    // Retorna o último resultado após todas as tentativas
    return $resultado;
}

// Função para salvar PDF
function salvarPDF($conteudo, $nomeArquivo) {
    $caminhoCompleto = PASTA_CERTIDOES . '/' . $nomeArquivo;
    $bytes = file_put_contents($caminhoCompleto, $conteudo);
    return [
        'sucesso' => $bytes !== false,
        'bytes' => $bytes,
        'caminho' => $caminhoCompleto
    ];
}

// Função para exibir resultado
function exibirResultado($nome, $sucesso, $mensagem, $detalhes = '', $tentativas = 1) {
    $cor = $sucesso ? Cores::VERDE : Cores::VERMELHO;
    $simbolo = $sucesso ? '✓' : '✗';
    
    echo $cor . $simbolo . " " . Cores::BOLD . $nome . Cores::RESET;
    
    // Se teve retry, mostrar
    if ($tentativas > 1) {
        echo Cores::AMARELO . " (após " . $tentativas . " tentativas)" . Cores::RESET;
    }
    
    echo "\n";
    echo "  " . $mensagem . "\n";
    if ($detalhes) {
        echo Cores::AMARELO . "  " . $detalhes . Cores::RESET . "\n";
    }
    echo "\n";
}

// Banner
echo Cores::AZUL . Cores::BOLD;
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║     TESTE DE APIS DE CERTIDÕES - PHP                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo Cores::RESET . "\n";

// Contador de resultados
$resultados = [
    'total' => 0,
    'sucesso' => 0,
    'falha' => 0
];

// =============================================================================
// 1. TJGO - Certidão Cível
// =============================================================================
if ($apisAtivas['tjgo_civel']) {
    echo Cores::BOLD . "🏛️  TJGO - Certidão Cível (Nada Consta)\n" . Cores::RESET;
    echo "----------------------------------------\n";

    $url = BASE_URL . '/certidao_tjgo.php?' . http_build_query([
        'cpf' => $dadosTeste['cpf'],
        'nome' => $dadosTeste['nome'],
        'nome_mae' => $dadosTeste['nome_mae'],
        'data_nascimento' => $dadosTeste['data_nascimento']
    ]);

    echo "URL: " . $url . "\n";
    echo "Aguardando resposta (pode demorar até 3 minutos devido ao CAPTCHA)...\n\n";

    $resultado = fazerRequisicao($url, $config['timeout_tjgo']);
    $resultados['total']++;

    if ($resultado['success'] && $resultado['is_pdf']) {
        $arquivo = salvarPDF($resultado['data'], 'TJGO_Civel_' . $dadosTeste['cpf'] . '.pdf');
        if ($arquivo['sucesso']) {
            exibirResultado(
                'TJGO Cível',
                true,
                'PDF gerado e salvo com sucesso!',
                'Tamanho: ' . number_format($arquivo['bytes'] / 1024, 2) . ' KB | Local: ' . basename($arquivo['caminho']),
                $resultado['tentativas']
            );
            $resultados['sucesso']++;
        }
    } else {
        $mensagem = 'Falha: HTTP ' . $resultado['code'];
        if (!$resultado['is_pdf']) {
            $mensagem .= ' (Resposta não é PDF)';
            // Tentar decodificar JSON de erro
            $json = json_decode($resultado['data'], true);
            if ($json && isset($json['erro'])) {
                $mensagem .= ' - ' . $json['erro'];
            }
        }
        exibirResultado('TJGO Cível', false, $mensagem, '', $resultado['tentativas']);
        $resultados['falha']++;
    }
} else {
    echo Cores::AMARELO . "⊘ TJGO Cível - IGNORADO (desativado no config)\n\n" . Cores::RESET;
}

// =============================================================================
// 2. TJGO - Certidão Criminal
// =============================================================================
if ($apisAtivas['tjgo_criminal']) {
    echo Cores::BOLD . "🏛️  TJGO - Certidão Criminal (Nada Consta)\n" . Cores::RESET;
    echo "----------------------------------------\n";

    $url = BASE_URL . '/certidao_tjgo_criminal.php?' . http_build_query([
        'cpf' => $dadosTeste['cpf'],
        'nome' => $dadosTeste['nome'],
        'nome_mae' => $dadosTeste['nome_mae'],
        'data_nascimento' => $dadosTeste['data_nascimento']
    ]);

    echo "URL: " . $url . "\n";
    echo "Aguardando resposta (pode demorar até 3 minutos devido ao CAPTCHA)...\n\n";

    $resultado = fazerRequisicao($url, $config['timeout_tjgo']);
    $resultados['total']++;

    if ($resultado['success'] && $resultado['is_pdf']) {
        $arquivo = salvarPDF($resultado['data'], 'TJGO_Criminal_' . $dadosTeste['cpf'] . '.pdf');
        if ($arquivo['sucesso']) {
            exibirResultado(
                'TJGO Criminal',
                true,
                'PDF gerado e salvo com sucesso!',
                'Tamanho: ' . number_format($arquivo['bytes'] / 1024, 2) . ' KB | Local: ' . basename($arquivo['caminho']),
                $resultado['tentativas']
            );
            $resultados['sucesso']++;
        }
    } else {
        $mensagem = 'Falha: HTTP ' . $resultado['code'];
        if (!$resultado['is_pdf']) {
            $mensagem .= ' (Resposta não é PDF)';
            $json = json_decode($resultado['data'], true);
            if ($json && isset($json['erro'])) {
                $mensagem .= ' - ' . $json['erro'];
            }
        }
        exibirResultado('TJGO Criminal', false, $mensagem, '', $resultado['tentativas']);
        $resultados['falha']++;
    }
} else {
    echo Cores::AMARELO . "⊘ TJGO Criminal - IGNORADO (desativado no config)\n\n" . Cores::RESET;
}

// =============================================================================
// 3. STJ - Certidão Pessoa Jurídica (CNPJ)
// =============================================================================
if ($apisAtivas['stj_pj']) {
    echo Cores::BOLD . "⚖️  STJ - Certidão Pessoa Jurídica (CNPJ)\n" . Cores::RESET;
    echo "----------------------------------------\n";

    $url = BASE_URL . '/certidao_stj.php?' . http_build_query([
        'cnpj' => $dadosTeste['cnpj']
    ]);

    echo "URL: " . $url . "\n";
    echo "Aguardando resposta...\n\n";

    $resultado = fazerRequisicao($url, $config['timeout_stj']);
    $resultados['total']++;

    if ($resultado['success'] && $resultado['is_pdf']) {
        $arquivo = salvarPDF($resultado['data'], 'STJ_PJ_' . $dadosTeste['cnpj'] . '.pdf');
        if ($arquivo['sucesso']) {
            exibirResultado(
                'STJ Pessoa Jurídica',
                true,
                'PDF gerado e salvo com sucesso!',
                'Tamanho: ' . number_format($arquivo['bytes'] / 1024, 2) . ' KB | Local: ' . basename($arquivo['caminho']),
                $resultado['tentativas']
            );
            $resultados['sucesso']++;
        }
    } else {
        $mensagem = 'Falha: HTTP ' . $resultado['code'];
        if (!$resultado['is_pdf']) {
            $mensagem .= ' (Resposta não é PDF)';
            $json = json_decode($resultado['data'], true);
            if ($json && isset($json['erro'])) {
                $mensagem .= ' - ' . $json['erro'];
            }
        }
        exibirResultado('STJ Pessoa Jurídica', false, $mensagem, '', $resultado['tentativas']);
        $resultados['falha']++;
    }
} else {
    echo Cores::AMARELO . "⊘ STJ Pessoa Jurídica - IGNORADO (desativado no config)\n\n" . Cores::RESET;
}

// =============================================================================
// 4. STJ - Certidão Pessoa Física (CPF)
// =============================================================================
if ($apisAtivas['stj_pf']) {
    echo Cores::BOLD . "⚖️  STJ - Certidão Pessoa Física (CPF)\n" . Cores::RESET;
    echo "----------------------------------------\n";

    $url = BASE_URL . '/certidao_stj_pf.php?' . http_build_query([
        'cpf' => $dadosTeste['cpf']
    ]);

    echo "URL: " . $url . "\n";
    echo "Aguardando resposta...\n\n";

    $resultado = fazerRequisicao($url, $config['timeout_stj']);
    $resultados['total']++;

    if ($resultado['success'] && $resultado['is_pdf']) {
        $arquivo = salvarPDF($resultado['data'], 'STJ_PF_' . $dadosTeste['cpf'] . '.pdf');
        if ($arquivo['sucesso']) {
            exibirResultado(
                'STJ Pessoa Física',
                true,
                'PDF gerado e salvo com sucesso!',
                'Tamanho: ' . number_format($arquivo['bytes'] / 1024, 2) . ' KB | Local: ' . basename($arquivo['caminho']),
                $resultado['tentativas']
            );
            $resultados['sucesso']++;
        }
    } else {
        $mensagem = 'Falha: HTTP ' . $resultado['code'];
        if (!$resultado['is_pdf']) {
            $mensagem .= ' (Resposta não é PDF)';
            $json = json_decode($resultado['data'], true);
            if ($json && isset($json['erro'])) {
                $mensagem .= ' - ' . $json['erro'];
            }
        }
        exibirResultado('STJ Pessoa Física', false, $mensagem, '', $resultado['tentativas']);
        $resultados['falha']++;
    }
} else {
    echo Cores::AMARELO . "⊘ STJ Pessoa Física - IGNORADO (desativado no config)\n\n" . Cores::RESET;
}

// =============================================================================
// RESUMO FINAL
// =============================================================================
echo Cores::AZUL . Cores::BOLD;
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMO FINAL                        ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo Cores::RESET . "\n";

echo "Total de testes: " . Cores::BOLD . $resultados['total'] . Cores::RESET . "\n";
echo Cores::VERDE . "✓ Sucessos: " . $resultados['sucesso'] . Cores::RESET . "\n";
echo Cores::VERMELHO . "✗ Falhas: " . $resultados['falha'] . Cores::RESET . "\n";
echo "\n";

if ($resultados['sucesso'] > 0) {
    echo Cores::AMARELO . "📁 Certidões salvas em: " . PASTA_CERTIDOES . Cores::RESET . "\n";
    
    // Listar arquivos salvos
    $arquivos = glob(PASTA_CERTIDOES . '/*.pdf');
    if ($arquivos) {
        echo "\nArquivos salvos:\n";
        foreach ($arquivos as $arquivo) {
            $tamanho = filesize($arquivo);
            echo "  • " . basename($arquivo) . " (" . number_format($tamanho / 1024, 2) . " KB)\n";
        }
    }
}

echo "\n";

// Taxa de sucesso
$taxaSucesso = $resultados['total'] > 0 ? ($resultados['sucesso'] / $resultados['total']) * 100 : 0;
$corTaxa = $taxaSucesso >= 75 ? Cores::VERDE : ($taxaSucesso >= 50 ? Cores::AMARELO : Cores::VERMELHO);
echo $corTaxa . "Taxa de sucesso: " . number_format($taxaSucesso, 1) . "%" . Cores::RESET . "\n\n";

// Código de saída
exit($resultados['falha'] > 0 ? 1 : 0);

