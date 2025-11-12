<?php
/**
 * API de Busca de Processos - TJGO (Tribunal de Justiça de Goiás)
 * 
 * Uso: processo_tjgo.php?cpf=00000000000
 *      processo_tjgo.php?cnpj=00000000000000
 *      processo_tjgo.php?numero_processo=148032-91
 *      processo_tjgo.php?nome=NOME+DA+PARTE
 *      processo_tjgo.php?inquerito=123456
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

function buscarProcessosTJGO($params) {
    $baseUrl = 'https://projudi.tjgo.jus.br';
    $pageUrl = $baseUrl . '/BuscaProcesso?PaginaAtual=4&TipoConsultaProcesso=24';
    $postUrl = $baseUrl . '/BuscaProcesso';
    
    $cookieFile = tempnam(sys_get_temp_dir(), 'tjgo_proc_cookie_');
    
    try {
        // PASSO 1: Acessar página inicial para obter cookies
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
        
        // Extrair sitekey do Cloudflare Turnstile
        $siteKey = null;
        
        // Tentar vários padrões
        if (preg_match('/data-sitekey=["\']([^"\']+)["\']/', $html, $matches)) {
            $siteKey = $matches[1];
        }
        if (!$siteKey && preg_match('/sitekey\s*:\s*["\']([^"\']+)["\']/', $html, $matches)) {
            $siteKey = $matches[1];
        }
        if (!$siteKey && preg_match('/turnstile\.render\([^,]+,\s*\{[^}]*sitekey\s*:\s*["\']([^"\']+)["\']/', $html, $matches)) {
            $siteKey = $matches[1];
        }
        
        if (!$siteKey) {
            $debugFile = 'tjgo_proc_debug_' . time() . '.html';
            file_put_contents($debugFile, $html);
            throw new Exception("Não foi possível extrair o sitekey. HTML salvo em: $debugFile");
        }
        
        // PASSO 2: Resolver CAPTCHA
        $captchaToken = resolverCloudflareTurnstile($siteKey, $pageUrl);
        
        sleep(2);
        
        // PASSO 3: Preparar dados do POST
        $postData = array(
            'PaginaAtual' => '2',
            'PaginaAnterior' => '4',
            'TipoConsultaProcesso' => '',
            'ServletRedirect' => 'null',
            'QuantidadeRegistrosPagina' => '',
            'ProcessoNumero' => isset($params['numero_processo']) ? $params['numero_processo'] : '',
            'Inquerito' => isset($params['inquerito']) ? $params['inquerito'] : '',
            'NomeParte' => isset($params['nome']) ? $params['nome'] : '',
            'PesquisarNomeExato' => isset($params['nome_exato']) && $params['nome_exato'] ? 'true' : 'false',
            'CpfCnpjParte' => isset($params['cpf_cnpj']) ? preg_replace('/[^0-9]/', '', $params['cpf_cnpj']) : '',
            'imgSubmeter' => 'Buscar',
            'cf-turnstile-response' => $captchaToken,
            'g-recaptcha-response' => $captchaToken
        );
        
        $ch = curl_init($postUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
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
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro ao buscar processos: HTTP $httpCode");
        }
        
        // Parsear resultado HTML
        return parsearProcessos($response);
        
    } catch (Exception $e) {
        throw $e;
    } finally {
        if (isset($cookieFile) && file_exists($cookieFile)) {
            unlink($cookieFile);
        }
    }
}

function parsearProcessos($html) {
    $processos = array();
    
    // Verificar se não encontrou processos
    if (stripos($html, 'nenhum processo') !== false || 
        stripos($html, 'não encontrado') !== false ||
        stripos($html, 'não foi encontrado') !== false) {
        return array(
            'total' => 0,
            'processos' => array(),
            'mensagem' => 'Nenhum processo encontrado'
        );
    }
    
    // Tentar extrair tabela de processos
    // Padrão: <tr> com dados do processo
    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows);
    
    $count = 0;
    foreach ($rows[1] as $row) {
        // Extrair células da linha
        preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $row, $cells);
        
        if (!empty($cells[1]) && count($cells[1]) >= 3) {
            $processo = array();
            
            // Tentar extrair número do processo
            if (preg_match('/(\d{6,7}[-.]?\d{2}[.\-]?\d{4}[.\-]?\d{1}[.\-]?\d{2}[.\-]?\d{4})/', $row, $numero)) {
                $processo['numero'] = trim($numero[1]);
                
                // Extrair outras informações da linha
                foreach ($cells[1] as $cell) {
                    $text = strip_tags($cell);
                    $text = trim(preg_replace('/\s+/', ' ', $text));
                    if (!empty($text) && strlen($text) > 3) {
                        $processo['detalhes'][] = $text;
                    }
                }
                
                // Extrair links
                if (preg_match_all('/href=["\']([^"\']+)["\']/', $row, $links)) {
                    $processo['links'] = array_unique($links[1]);
                }
                
                $processos[] = $processo;
                $count++;
            }
        }
    }
    
    // Se não encontrou processos na tabela, tentar outros padrões
    if ($count === 0) {
        // Buscar qualquer número de processo no HTML
        preg_match_all('/(\d{6,7}[-.]?\d{2}[.\-]?\d{4}[.\-]?\d{1}[.\-]?\d{2}[.\-]?\d{4})/', $html, $matches);
        if (!empty($matches[1])) {
            $numeros = array_unique($matches[1]);
            foreach ($numeros as $numero) {
                $processos[] = array(
                    'numero' => $numero,
                    'detalhes' => array()
                );
                $count++;
            }
        }
    }
    
    return array(
        'total' => $count,
        'processos' => $processos,
        'mensagem' => $count > 0 ? "Encontrado(s) $count processo(s)" : 'Nenhum processo encontrado'
    );
}

// Processar requisição
try {
    // Verificar se há algum parâmetro de busca
    $params = array();
    
    if (isset($_GET['cpf'])) {
        $params['cpf_cnpj'] = $_GET['cpf'];
    } elseif (isset($_GET['cnpj'])) {
        $params['cpf_cnpj'] = $_GET['cnpj'];
    }
    
    if (isset($_GET['numero_processo'])) {
        $params['numero_processo'] = $_GET['numero_processo'];
    }
    
    if (isset($_GET['nome'])) {
        $params['nome'] = $_GET['nome'];
        if (isset($_GET['nome_exato'])) {
            $params['nome_exato'] = true;
        }
    }
    
    if (isset($_GET['inquerito'])) {
        $params['inquerito'] = $_GET['inquerito'];
    }
    
    if (empty($params)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(array(
            'erro' => 'Nenhum parâmetro de busca fornecido',
            'uso' => array(
                'Por CPF' => 'processo_tjgo.php?cpf=00000000000',
                'Por CNPJ' => 'processo_tjgo.php?cnpj=00000000000000',
                'Por Número' => 'processo_tjgo.php?numero_processo=148032-91',
                'Por Nome' => 'processo_tjgo.php?nome=NOME+DA+PARTE',
                'Por Nome Exato' => 'processo_tjgo.php?nome=NOME+DA+PARTE&nome_exato=1',
                'Por Inquérito' => 'processo_tjgo.php?inquerito=123456'
            ),
            'info' => 'Busca de Processos Judiciais - TJGO',
            'nota' => 'Usa 2Captcha (~$0.002 por busca)'
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $resultado = buscarProcessosTJGO($params);
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'sucesso' => true,
        'parametros' => $params,
        'resultado' => $resultado
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(array(
        'erro' => $e->getMessage(),
        'parametros' => isset($params) ? $params : $_GET
    ), JSON_UNESCAPED_UNICODE);
}

