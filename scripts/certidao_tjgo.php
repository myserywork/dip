<?php
/**
 * API de Certidão TJGO - Tribunal de Justiça de Goiás
 * Pessoa Física - Cível (Nada Consta)
 * 
 * Uso: certidao_tjgo.php?cpf=00000000000&nome=NOME+COMPLETO&nome_mae=NOME+DA+MAE&data_nascimento=DD/MM/AAAA
 * 
 * Requer 2Captcha para resolver Cloudflare Turnstile
 */

set_time_limit(180);

// ⚠️ CONFIGURE SUA API KEY DO 2CAPTCHA AQUI:
define('API_KEY_2CAPTCHA', 'c73f67ffb4424733b464ee5cf07f3bae');

function resolverCloudflareTurnstile($siteKey, $pageUrl) {
    $apiKey = API_KEY_2CAPTCHA;
    
    // Enviar CAPTCHA para resolução
    $ch = curl_init('http://2captcha.com/in.php');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array(
            'key' => $apiKey,
            'method' => 'turnstile',
            'sitekey' => $siteKey,
            'pageurl' => $pageUrl
        ))
    ));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response || strpos($response, 'OK|') !== 0) {
        throw new Exception("Erro ao enviar CAPTCHA: $response");
    }
    
    $captchaId = explode('|', $response)[1];
    
    // Aguardar resolução (max 60 segundos)
    for ($i = 0; $i < 24; $i++) {
        sleep(5);
        
        $ch = curl_init("http://2captcha.com/res.php?key=$apiKey&action=get&id=$captchaId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        
        if (strpos($result, 'OK|') === 0) {
            return explode('|', $result)[1];
        }
        
        if (strpos($result, 'CAPCHA_NOT_READY') === false) {
            throw new Exception("Erro ao resolver CAPTCHA: $result");
        }
    }
    
    throw new Exception('Timeout ao resolver CAPTCHA');
}

function obterCertidaoTJGO($cpf, $nome, $nomeMae, $dataNascimento, $comarca = '') {
    // Validar CPF
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11) {
        throw new Exception('CPF inválido - deve ter 11 dígitos');
    }
    
    // Validar data de nascimento (DD/MM/AAAA)
    if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataNascimento)) {
        throw new Exception('Data de nascimento inválida. Use o formato DD/MM/AAAA');
    }
    
    $baseUrl = 'https://projudi.tjgo.jus.br';
    $pageUrl = $baseUrl . '/CertidaoNegativaPositivaPublica?PaginaAtual=1&TipoArea=1&InteressePessoal=&Territorio=&Finalidade=';
    $postUrl = $baseUrl . '/CertidaoNegativaPositivaPublica';
    
    $cookieFile = tempnam(sys_get_temp_dir(), 'tjgo_cookie_');
    
    try {
        // PASSO 1: Acessar página para obter cookies
        $ch = curl_init($pageUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_HTTPHEADER => array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9',
                'Connection: keep-alive',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36'
            ),
            CURLOPT_TIMEOUT => 30
        ));
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro ao acessar página TJGO: HTTP $httpCode");
        }
        
        // Salvar HTML para debug (opcional - descomente para ver o HTML)
        // file_put_contents('tjgo_debug.html', $html);
        
        // Extrair sitekey do Cloudflare Turnstile (tentar vários padrões)
        $siteKey = null;
        
        // Padrão 1: data-sitekey="..."
        if (preg_match('/data-sitekey=["\']([^"\']+)["\']/', $html, $matches)) {
            $siteKey = $matches[1];
        }
        
        // Padrão 2: sitekey: "..."
        if (!$siteKey && preg_match('/sitekey\s*:\s*["\']([^"\']+)["\']/', $html, $matches)) {
            $siteKey = $matches[1];
        }
        
        // Padrão 3: turnstile.render com sitekey
        if (!$siteKey && preg_match('/turnstile\.render\([^,]+,\s*\{[^}]*sitekey\s*:\s*["\']([^"\']+)["\']/', $html, $matches)) {
            $siteKey = $matches[1];
        }
        
        // Padrão 4: cf-turnstile com data-sitekey
        if (!$siteKey && preg_match('/<div[^>]*class=["\'][^"\']*cf-turnstile[^"\']*["\'][^>]*data-sitekey=["\']([^"\']+)["\']/', $html, $matches)) {
            $siteKey = $matches[1];
        }
        
        if (!$siteKey) {
            // Salvar HTML automaticamente para debug
            $debugFile = 'tjgo_debug_' . time() . '.html';
            file_put_contents($debugFile, $html);
            throw new Exception("Não foi possível extrair o sitekey do Cloudflare Turnstile. HTML salvo em: $debugFile");
        }
        
        // PASSO 2: Resolver CAPTCHA
        $captchaToken = resolverCloudflareTurnstile($siteKey, $pageUrl);
        
        sleep(2);
        
        // PASSO 3: Enviar requisição com CAPTCHA resolvido
        $postData = http_build_query(array(
            'PaginaAtual' => '3',
            'PaginaAnterior' => 'null',
            'TituloPagina' => 'null',
            'TipoArea' => '1',
            'Nome' => $nome,
            'Cpf' => $cpf,
            'NomeMae' => $nomeMae,
            'DataNascimento' => $dataNascimento,
            'Id_Comarca' => '',
            'Comarca' => $comarca,
            'imgSubmeter' => 'Gerar Certidão',
            'cf-turnstile-response' => $captchaToken,
            'g-recaptcha-response' => $captchaToken
        ));
        
        $ch = curl_init($postUrl);
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
                'Accept-Language: pt-BR,pt;q=0.9',
                'Cache-Control: max-age=0',
                'Connection: keep-alive',
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $baseUrl,
                'Referer: ' . $pageUrl,
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
            CURLOPT_TIMEOUT => 60
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro ao emitir certidão TJGO: HTTP $httpCode");
        }
        
        // Verificar se retornou PDF
        if (strpos($contentType, 'pdf') !== false || substr($response, 0, 4) === '%PDF') {
            return $response;
        }
        
        // Se não for PDF, pode ser HTML com a certidão ou erro
        if (strpos($response, 'certidão') !== false || strpos($response, 'certidao') !== false) {
            // Se contém HTML, tentar encontrar link do PDF
            if (preg_match('/href=["\']([^"\']*\.pdf[^"\']*)["\']/i', $response, $matches)) {
                $pdfUrl = $matches[1];
                if (strpos($pdfUrl, 'http') !== 0) {
                    $pdfUrl = $baseUrl . $pdfUrl;
                }
                
                // Baixar PDF
                $ch = curl_init($pdfUrl);
                curl_setopt_array($ch, array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_COOKIEFILE => $cookieFile,
                    CURLOPT_TIMEOUT => 30
                ));
                
                $pdf = curl_exec($ch);
                curl_close($ch);
                
                if (substr($pdf, 0, 4) === '%PDF') {
                    return $pdf;
                }
            }
        }
        
        throw new Exception('Resposta não contém PDF. Tamanho: ' . strlen($response) . ' bytes');
        
    } catch (Exception $e) {
        throw $e;
    } finally {
        if (isset($cookieFile) && file_exists($cookieFile)) {
            unlink($cookieFile);
        }
    }
}

// Processar requisição
try {
    // Validar parâmetros obrigatórios
    $erros = array();
    
    if (empty($_GET['cpf'])) {
        $erros[] = 'cpf é obrigatório';
    }
    if (empty($_GET['nome'])) {
        $erros[] = 'nome é obrigatório';
    }
    if (empty($_GET['nome_mae'])) {
        $erros[] = 'nome_mae é obrigatório';
    }
    if (empty($_GET['data_nascimento'])) {
        $erros[] = 'data_nascimento é obrigatório';
    }
    
    if (!empty($erros)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(array(
            'erro' => 'Parâmetros obrigatórios faltando',
            'parametros_faltando' => $erros,
            'uso' => 'certidao_tjgo.php?cpf=00000000000&nome=NOME+COMPLETO&nome_mae=NOME+DA+MAE&data_nascimento=DD/MM/AAAA',
            'exemplo' => 'certidao_tjgo.php?cpf=05434961129&nome=Pedro+Henrique+Pontes+de+Noronha&nome_mae=Alessandra+Pontes+de+Sampaio&data_nascimento=02/04/1996',
            'info' => 'Certidão Nada Consta (Cível) - TJGO',
            'nota' => 'Usa 2Captcha para resolver Cloudflare Turnstile (~$0.002 por certidão)'
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $comarca = isset($_GET['comarca']) ? $_GET['comarca'] : '';
    
    $pdf = obterCertidaoTJGO(
        $_GET['cpf'],
        $_GET['nome'],
        $_GET['nome_mae'],
        $_GET['data_nascimento'],
        $comarca
    );
    
    $cpf = preg_replace('/[^0-9]/', '', $_GET['cpf']);
    $nomeArquivo = "Certidao_TJGO_{$cpf}_" . date('Y-m-d') . ".pdf";
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nomeArquivo . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $pdf;
    
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(array(
        'erro' => $e->getMessage(),
        'parametros' => $_GET
    ), JSON_UNESCAPED_UNICODE);
}

