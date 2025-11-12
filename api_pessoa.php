<?php
/**
 * API Simples de Consulta de Pessoas
 * 
 * Endpoints:
 * - GET api_pessoa.php?cpf=12345678900
 * - GET api_pessoa.php?nome=JOAO
 * - GET api_pessoa.php?id=123
 * 
 * Retorna JSON com os dados da pessoa
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configuração do banco de dados
define('DB_PATH', 'E:\contatos_reduzido.db');

// Cache em memória (válido por 5 minutos)
$CACHE = [];
$CACHE_TTL = 300; // 5 minutos

function getCacheKey($tipo, $valor) {
    return md5($tipo . '_' . $valor);
}

function getFromCache($tipo, $valor) {
    global $CACHE, $CACHE_TTL;
    $key = getCacheKey($tipo, $valor);
    
    if (isset($CACHE[$key])) {
        $cached = $CACHE[$key];
        if (time() - $cached['time'] < $CACHE_TTL) {
            return $cached['data'];
        }
    }
    return null;
}

function setCache($tipo, $valor, $data) {
    global $CACHE;
    $key = getCacheKey($tipo, $valor);
    $CACHE[$key] = [
        'data' => $data,
        'time' => time()
    ];
}

// Função para conectar ao SQLite
function conectarBanco() {
    try {
        if (!file_exists(DB_PATH)) {
            throw new Exception("Banco de dados não encontrado: " . DB_PATH);
        }
        
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Timeout de 5 segundos para queries
        $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
        
        // Otimizações SQLite para leitura rápida
        $db->exec("PRAGMA cache_size = 10000");
        $db->exec("PRAGMA temp_store = MEMORY");
        
        return $db;
    } catch (Exception $e) {
        return null;
    }
}

// Função para buscar por CPF
function buscarPorCPF($cpf) {
    // Limpar CPF (remover pontos, traços, etc)
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verificar cache primeiro
    $cached = getFromCache('cpf', $cpf);
    if ($cached !== null) {
        return $cached;
    }
    
    $db = conectarBanco();
    if (!$db) {
        return ['erro' => 'Erro ao conectar ao banco de dados'];
    }
    
    try {
        
        $stmt = $db->prepare("SELECT * FROM SRS_CONTATOS_REDUZIDO WHERE CPF = :cpf LIMIT 1");
        $stmt->execute(['cpf' => $cpf]);
        
        $resultado = $stmt->fetch();
        
        if ($resultado) {
            $dados = [
                'sucesso' => true,
                'encontrado' => true,
                'dados' => [
                    'id' => $resultado['ID'],
                    'cpf' => $resultado['CPF'],
                    'nome' => $resultado['NOME'],
                    'sexo' => $resultado['SEXO'],
                    'nascimento' => $resultado['NASCIMENTO'],
                    'nome_mae' => $resultado['NOME_MAE'],
                    'nome_pai' => $resultado['NOME_PAI'],
                    'rg' => $resultado['RG'],
                    'orgao_emissor' => $resultado['ORGAO_EMISSOR']
                ]
            ];
            setCache('cpf', $cpf, $dados);
            return $dados;
        } else {
            $dados = [
                'sucesso' => true,
                'encontrado' => false,
                'mensagem' => 'CPF não encontrado'
            ];
            setCache('cpf', $cpf, $dados);
            return $dados;
        }
    } catch (Exception $e) {
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

// Função para buscar por nome
function buscarPorNome($nome) {
    $nome = strtoupper(trim($nome));
    
    // Verificar cache primeiro
    $cached = getFromCache('nome', $nome);
    if ($cached !== null) {
        return $cached;
    }
    
    $db = conectarBanco();
    if (!$db) {
        return ['erro' => 'Erro ao conectar ao banco de dados'];
    }
    
    try {
        // Busca otimizada: nome começa com (muito mais rápido que LIKE '%nome%')
        
        // Primeira tentativa: busca exata (super rápido)
        $stmt = $db->prepare("SELECT * FROM SRS_CONTATOS_REDUZIDO WHERE NOME = :nome LIMIT 10");
        $stmt->execute(['nome' => $nome]);
        $resultados = $stmt->fetchAll();
        
        // Se não encontrou, busca por "começa com" (rápido, usa índice)
        if (empty($resultados)) {
            $stmt = $db->prepare("SELECT * FROM SRS_CONTATOS_REDUZIDO WHERE NOME LIKE :nome LIMIT 10");
            $stmt->execute(['nome' => $nome . '%']);
            $resultados = $stmt->fetchAll();
        }
        
        // Se ainda não encontrou E nome tem mais de 3 palavras, busca só primeiro e último
        if (empty($resultados) && str_word_count($nome) >= 3) {
            $palavras = explode(' ', $nome);
            $primeiro = $palavras[0];
            $ultimo = end($palavras);
            
            $stmt = $db->prepare("
                SELECT * FROM SRS_CONTATOS_REDUZIDO 
                WHERE NOME LIKE :primeiro 
                  AND NOME LIKE :ultimo 
                LIMIT 10
            ");
            $stmt->execute([
                'primeiro' => $primeiro . '%',
                'ultimo' => '%' . $ultimo
            ]);
            $resultados = $stmt->fetchAll();
        }
        
        if ($resultados) {
            $dados = [];
            foreach($resultados as $row) {
                $dados[] = [
                    'id' => $row['ID'],
                    'cpf' => $row['CPF'],
                    'nome' => $row['NOME'],
                    'sexo' => $row['SEXO'],
                    'nascimento' => $row['NASCIMENTO'],
                    'nome_mae' => $row['NOME_MAE'],
                    'nome_pai' => $row['NOME_PAI']
                ];
            }
            
            $resultado = [
                'sucesso' => true,
                'encontrado' => true,
                'total' => count($dados),
                'dados' => $dados
            ];
            
            // Salvar no cache
            setCache('nome', $nome, $resultado);
            return $resultado;
        } else {
            $resultado = [
                'sucesso' => true,
                'encontrado' => false,
                'mensagem' => 'Nenhuma pessoa encontrada com esse nome'
            ];
            
            // Salvar no cache
            setCache('nome', $nome, $resultado);
            return $resultado;
        }
    } catch (Exception $e) {
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

// Função para buscar por ID
function buscarPorID($id) {
    $db = conectarBanco();
    if (!$db) {
        return ['erro' => 'Erro ao conectar ao banco de dados'];
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM SRS_CONTATOS_REDUZIDO WHERE ID = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        
        $resultado = $stmt->fetch();
        
        if ($resultado) {
            return [
                'sucesso' => true,
                'encontrado' => true,
                'dados' => [
                    'id' => $resultado['ID'],
                    'cpf' => $resultado['CPF'],
                    'nome' => $resultado['NOME'],
                    'sexo' => $resultado['SEXO'],
                    'nascimento' => $resultado['NASCIMENTO'],
                    'nome_mae' => $resultado['NOME_MAE'],
                    'nome_pai' => $resultado['NOME_PAI'],
                    'rg' => $resultado['RG'],
                    'orgao_emissor' => $resultado['ORGAO_EMISSOR']
                ]
            ];
        } else {
            return [
                'sucesso' => true,
                'encontrado' => false,
                'mensagem' => 'ID não encontrado'
            ];
        }
    } catch (Exception $e) {
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

// Função para listar estrutura da tabela
function listarEstrutura() {
    $db = conectarBanco();
    if (!$db) {
        return ['erro' => 'Erro ao conectar ao banco de dados'];
    }
    
    try {
        $stmt = $db->query("PRAGMA table_info(SRS_CONTATOS_REDUZIDO)");
        $colunas = $stmt->fetchAll();
        
        $stmt2 = $db->query("SELECT COUNT(*) as total FROM SRS_CONTATOS_REDUZIDO");
        $total = $stmt2->fetch();
        
        return [
            'sucesso' => true,
            'banco' => DB_PATH,
            'tabela' => 'SRS_CONTATOS_REDUZIDO',
            'total_registros' => number_format($total['total'], 0, ',', '.'),
            'colunas' => $colunas
        ];
    } catch (Exception $e) {
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

// Processar requisição
try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido. Use GET.');
    }
    
    // Verificar parâmetros
    if (isset($_GET['cpf']) && !empty($_GET['cpf'])) {
        // Buscar por CPF
        $resultado = buscarPorCPF($_GET['cpf']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } elseif (isset($_GET['nome']) && !empty($_GET['nome'])) {
        // Buscar por nome
        $resultado = buscarPorNome($_GET['nome']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } elseif (isset($_GET['id']) && !empty($_GET['id'])) {
        // Buscar por ID
        $resultado = buscarPorID($_GET['id']);
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } elseif (isset($_GET['estrutura'])) {
        // Listar estrutura da tabela
        $resultado = listarEstrutura();
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } else {
        // Nenhum parâmetro fornecido - mostrar ajuda
        http_response_code(400);
        echo json_encode([
            'erro' => 'Parâmetro não fornecido',
            'uso' => [
                'Buscar por CPF' => 'api_pessoa.php?cpf=12345678900',
                'Buscar por nome' => 'api_pessoa.php?nome=JOAO',
                'Buscar por ID' => 'api_pessoa.php?id=123',
                'Ver estrutura' => 'api_pessoa.php?estrutura=1'
            ],
            'exemplos' => [
                'http://localhost/dip/api_pessoa.php?cpf=12345678900',
                'http://localhost/dip/api_pessoa.php?nome=MARIA',
                'http://localhost/dip/api_pessoa.php?estrutura=1'
            ],
            'notas' => [
                'CPF pode ter pontos e traços, serão removidos automaticamente',
                'Busca por nome é parcial (LIKE)',
                'Retorna até 50 resultados na busca por nome',
                'Banco: ' . DB_PATH
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

