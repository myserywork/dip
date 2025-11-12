<?php
/**
 * API de Certidão STJ (Superior Tribunal de Justiça)
 * 
 * Uso: certidao_stj.php?cnpj=00000000000000
 */

set_time_limit(120);

function obterCertidaoSTJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) {
        throw new Exception('CNPJ inválido - deve ter 14 dígitos');
    }
    
    // Formatar CNPJ: 00.000.000/0000-00
    $cnpjFormatado = substr($cnpj, 0, 2) . '.' . 
                     substr($cnpj, 2, 3) . '.' . 
                     substr($cnpj, 5, 3) . '/' . 
                     substr($cnpj, 8, 4) . '-' . 
                     substr($cnpj, 12, 2);
    
    $baseUrl = 'https://processo.stj.jus.br';
    $url = $baseUrl . '/processo/certidao/emitir';
    
    // Criar arquivo temporário para cookies
    $cookieFile = tempnam(sys_get_temp_dir(), 'stj_cookie_');
    
    try {
        // PASSO 1: Acessar página inicial para OBTER COOKIES
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR => $cookieFile,  // Salvar cookies
            CURLOPT_COOKIEFILE => $cookieFile, // Usar cookies
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
        
        sleep(1); // Aguardar
        
        // PASSO 2: Enviar requisição para emitir certidão
        $postData = http_build_query(array(
            'certidaoTipo' => 'pessoajuridicaconstanadaconsta',
            'classe' => '',
            'num_processo' => '',
            'num_registro' => '',
            'certidaoEleitoralPublicaParteCPF' => '',
            'parteNome' => '',
            'parteCPF' => '',
            'parteCNPJ' => $cnpjFormatado,
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
            CURLOPT_COOKIEJAR => $cookieFile,  // Usar os mesmos cookies
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
                'sec-ch-ua-platform: "Windows"'
            ),
            CURLOPT_TIMEOUT => 30
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro ao emitir certidão STJ: HTTP $httpCode");
        }
        
        // Verificar se retornou PDF
        if (strpos($contentType, 'pdf') !== false) {
            return $response; // Retorna o PDF direto
        }
        
        // Se não for PDF, pode ser HTML com link ou mensagem
        // Verificar se tem PDF no response
        if (strlen($response) > 1000 && substr($response, 0, 4) === '%PDF') {
            return $response;
        }
        
        // Se chegou aqui, não é PDF - verificar o que retornou
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
    if (empty($_GET['cnpj'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(array(
            'erro' => 'Parâmetro cnpj não informado',
            'uso' => 'certidao_stj.php?cnpj=00000000000000',
            'info' => 'Certidão de processos no STJ (Superior Tribunal de Justiça)',
            'nota' => 'Os cookies são obtidos automaticamente ao acessar a página'
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $pdf = obterCertidaoSTJ($_GET['cnpj']);
    
    $cnpj = preg_replace('/[^0-9]/', '', $_GET['cnpj']);
    $nomeArquivo = "Certidao_STJ_{$cnpj}_" . date('Y-m-d') . ".pdf";
    
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
        'cnpj' => isset($_GET['cnpj']) ? $_GET['cnpj'] : null
    ), JSON_UNESCAPED_UNICODE);
}

