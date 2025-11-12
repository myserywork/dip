<?php
/**
 * API de Certidão STJ - Pessoa Física (CPF)
 * 
 * Uso: certidao_stj_pf.php?cpf=00000000000
 */

set_time_limit(120);

function obterCertidaoSTJ_PF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        throw new Exception('CPF inválido - deve ter 11 dígitos');
    }
    
    // Formatar CPF: 000.000.000-00
    $cpfFormatado = substr($cpf, 0, 3) . '.' . 
                    substr($cpf, 3, 3) . '.' . 
                    substr($cpf, 6, 3) . '-' . 
                    substr($cpf, 9, 2);
    
    $baseUrl = 'https://processo.stj.jus.br';
    $url = $baseUrl . '/processo/certidao/emitir';
    
    // Criar arquivo temporário para cookies
    $cookieFile = tempnam(sys_get_temp_dir(), 'stj_pf_cookie_');
    
    try {
        // PASSO 1: Acessar página inicial para OBTER COOKIES
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_HTTPHEADER => array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.6',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'
            ),
            CURLOPT_TIMEOUT => 30
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro ao acessar página STJ: HTTP $httpCode");
        }
        
        sleep(1);
        
        // PASSO 2: Enviar requisição para emitir certidão de PESSOA FÍSICA
        $postData = http_build_query(array(
            'certidaoTipo' => 'pessoafisicaconstanadaconsta',
            'classe' => '',
            'num_processo' => '',
            'num_registro' => '',
            'certidaoEleitoralPublicaParteCPF' => '',
            'parteNome' => '',
            'parteCPF' => $cpfFormatado,
            'parteCNPJ' => '',
            'advogado.cpf' => '',
            'certidaoProcessosEmTramite' => 'TRUE',
            'aplicacao' => 'certidao',
            'acao' => 'emitir'
        ));
        
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_HTTPHEADER => array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.6',
                'Cache-Control: max-age=0',
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $baseUrl,
                'Referer: ' . $url,
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: same-origin',
                'Sec-Fetch-User: ?1',
                'Upgrade-Insecure-Requests: 1',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
                'sec-ch-ua: "Chromium";v="142", "Brave";v="142", "Not_A Brand";v="99"',
                'sec-ch-ua-mobile: ?0',
                'sec-ch-ua-platform: "Windows"',
                'sec-gpc: 1'
            ),
            CURLOPT_TIMEOUT => 30
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro ao emitir certidão STJ PF: HTTP $httpCode");
        }
        
        // Verificar se retornou PDF
        if (strpos($contentType, 'pdf') !== false) {
            return $response;
        }
        
        // Verificar se tem PDF no response
        if (strlen($response) > 1000 && substr($response, 0, 4) === '%PDF') {
            return $response;
        }
        
        // Se chegou aqui, não é PDF
        throw new Exception('Resposta não é um PDF. Tamanho: ' . strlen($response) . ' bytes. Content-Type: ' . $contentType);
        
    } catch (Exception $e) {
        throw $e;
    } finally {
        // Limpar arquivo de cookies
        if (isset($cookieFile) && file_exists($cookieFile)) {
            unlink($cookieFile);
        }
    }
}

// Processar requisição
try {
    if (empty($_GET['cpf'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(array(
            'erro' => 'Parâmetro cpf não informado',
            'uso' => 'certidao_stj_pf.php?cpf=00000000000',
            'info' => 'Certidão de processos no STJ para Pessoa Física (CPF)',
            'nota' => 'Os cookies são obtidos automaticamente'
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $pdf = obterCertidaoSTJ_PF($_GET['cpf']);
    
    $cpf = preg_replace('/[^0-9]/', '', $_GET['cpf']);
    $nomeArquivo = "Certidao_STJ_PF_{$cpf}_" . date('Y-m-d') . ".pdf";
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nomeArquivo . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $pdf;
    
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(array(
        'erro' => $e->getMessage(),
        'cpf' => isset($_GET['cpf']) ? $_GET['cpf'] : null
    ), JSON_UNESCAPED_UNICODE);
}

