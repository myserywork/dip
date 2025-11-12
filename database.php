<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  DATABASE MANAGER - Sistema de Persistência de Análises
 * ═══════════════════════════════════════════════════════════════
 * 
 * Gerencia o banco SQLite com:
 * - Histórico de análises/relatórios
 * - Partes processuais extraídas
 * - Documentos analisados
 * - Enriquecimento de dados via APIs
 */

class DatabaseManager {
    private $pdo;
    private $dbPath;
    
    public function __construct($dbPath = null) {
        $this->dbPath = $dbPath ?? __DIR__ . '/dip_analytics.db';
        $this->connect();
        $this->initializeTables();
    }
    
    /**
     * Conecta ao banco SQLite
     */
    private function connect() {
        try {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            error_log("✅ Database conectado: " . $this->dbPath);
        } catch (Exception $e) {
            error_log("❌ Erro ao conectar database: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Inicializa as tabelas do banco
     */
    private function initializeTables() {
        try {
            // Tabela de análises/relatórios
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS analises (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                    status TEXT DEFAULT 'concluida',
                    html_relatorio TEXT,
                    resumo_json TEXT,
                    total_documentos INTEGER DEFAULT 0,
                    total_partes INTEGER DEFAULT 0,
                    classificacao_risco TEXT,
                    observacoes TEXT
                )
            ");
            
            // Tabela de documentos analisados
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS documentos_analisados (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    analise_id INTEGER NOT NULL,
                    nome_arquivo TEXT NOT NULL,
                    tipo_arquivo TEXT,
                    tamanho_bytes INTEGER,
                    hash_md5 TEXT,
                    metadata TEXT,
                    data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (analise_id) REFERENCES analises(id) ON DELETE CASCADE
                )
            ");
            
            // Tabela de partes extraídas
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS partes_extraidas (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    analise_id INTEGER NOT NULL,
                    nome TEXT NOT NULL,
                    documento TEXT,
                    tipo_documento TEXT,
                    role TEXT,
                    fonte TEXT,
                    dados_adicionais TEXT,
                    data_extracao DATETIME DEFAULT CURRENT_TIMESTAMP,
                    enriquecido INTEGER DEFAULT 0,
                    FOREIGN KEY (analise_id) REFERENCES analises(id) ON DELETE CASCADE
                )
            ");
            
            // Índice para buscar por documento
            $this->pdo->exec("
                CREATE INDEX IF NOT EXISTS idx_partes_documento 
                ON partes_extraidas(documento, tipo_documento)
            ");
            
            // Tabela de enriquecimento de dados
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS enriquecimento_dados (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    parte_id INTEGER NOT NULL,
                    tipo_enriquecimento TEXT NOT NULL,
                    dados_json TEXT,
                    data_consulta DATETIME DEFAULT CURRENT_TIMESTAMP,
                    sucesso INTEGER DEFAULT 1,
                    erro TEXT,
                    FOREIGN KEY (parte_id) REFERENCES partes_extraidas(id) ON DELETE CASCADE
                )
            ");
            
            // Índice para consultas rápidas de enriquecimento
            $this->pdo->exec("
                CREATE INDEX IF NOT EXISTS idx_enriquecimento_parte 
                ON enriquecimento_dados(parte_id, tipo_enriquecimento)
            ");
            
            // Tabela de sócios (para CNPJs)
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS socios (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    parte_id INTEGER NOT NULL,
                    analise_id INTEGER NOT NULL,
                    nome TEXT NOT NULL,
                    qualificacao TEXT,
                    cpf TEXT,
                    data_entrada TEXT,
                    representante_legal TEXT,
                    qualificacao_representante TEXT,
                    pais_origem TEXT,
                    nome_mae TEXT,
                    nascimento TEXT,
                    rg TEXT,
                    sexo TEXT,
                    enriquecido BOOLEAN DEFAULT 0,
                    data_enriquecimento DATETIME,
                    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (parte_id) REFERENCES partes_extraidas(id) ON DELETE CASCADE,
                    FOREIGN KEY (analise_id) REFERENCES analises(id) ON DELETE CASCADE
                )
            ");
            
            // Índice para buscar sócios por parte
            $this->pdo->exec("
                CREATE INDEX IF NOT EXISTS idx_socios_parte 
                ON socios(parte_id, analise_id)
            ");
            
            error_log("✅ Tabelas do banco inicializadas");
        } catch (Exception $e) {
            error_log("❌ Erro ao criar tabelas: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Inicia uma nova análise
     */
    public function criarAnalise($totalDocumentos = 0, $observacoes = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO analises (total_documentos, observacoes, status)
                VALUES (:total_documentos, :observacoes, 'processando')
            ");
            
            $stmt->execute([
                'total_documentos' => $totalDocumentos,
                'observacoes' => $observacoes
            ]);
            
            $analiseId = $this->pdo->lastInsertId();
            error_log("📝 Nova análise criada: ID {$analiseId}");
            
            return $analiseId;
        } catch (Exception $e) {
            error_log("❌ Erro ao criar análise: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva um documento analisado
     */
    public function salvarDocumento($analiseId, $nomeArquivo, $tamanhoBytes, $tipoArquivo = 'application/pdf', $metadata = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO documentos_analisados 
                (analise_id, nome_arquivo, tipo_arquivo, tamanho_bytes, metadata)
                VALUES (:analise_id, :nome_arquivo, :tipo_arquivo, :tamanho_bytes, :metadata)
            ");
            
            $stmt->execute([
                'analise_id' => $analiseId,
                'nome_arquivo' => $nomeArquivo,
                'tipo_arquivo' => $tipoArquivo,
                'tamanho_bytes' => $tamanhoBytes,
                'metadata' => $metadata
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("❌ Erro ao salvar documento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva uma parte extraída
     */
    public function salvarParte($analiseId, $parte) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO partes_extraidas 
                (analise_id, nome, documento, tipo_documento, role, fonte, dados_adicionais)
                VALUES (:analise_id, :nome, :documento, :tipo_documento, :role, :fonte, :dados_adicionais)
            ");
            
            $dadosAdicionais = isset($parte['additional_info']) ? $parte['additional_info'] : null;
            
            $stmt->execute([
                'analise_id' => $analiseId,
                'nome' => $parte['name'] ?? '',
                'documento' => $parte['document'] ?? 'NAOENCONTRADO',
                'tipo_documento' => $parte['document_type'] ?? 'DESCONHECIDO',
                'role' => $parte['role'] ?? 'Não especificado',
                'fonte' => $parte['source'] ?? 'Desconhecida',
                'dados_adicionais' => $dadosAdicionais
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("❌ Erro ao salvar parte: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva múltiplas partes de uma vez
     */
    public function salvarPartes($analiseId, $partes) {
        $savedCount = 0;
        
        foreach ($partes as $parte) {
            if ($this->salvarParte($analiseId, $parte)) {
                $savedCount++;
            }
        }
        
        error_log("💾 {$savedCount} partes salvas para análise {$analiseId}");
        return $savedCount;
    }
    
    /**
     * Finaliza análise salvando o relatório HTML
     */
    public function finalizarAnalise($analiseId, $htmlRelatorio, $resumoJson = null, $totalPartes = 0, $classificacaoRisco = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE analises 
                SET html_relatorio = :html_relatorio,
                    resumo_json = :resumo_json,
                    total_partes = :total_partes,
                    classificacao_risco = :classificacao_risco,
                    status = 'concluida',
                    data_atualizacao = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            
            $stmt->execute([
                'html_relatorio' => $htmlRelatorio,
                'resumo_json' => $resumoJson,
                'total_partes' => $totalPartes,
                'classificacao_risco' => $classificacaoRisco,
                'id' => $analiseId
            ]);
            
            error_log("✅ Análise {$analiseId} finalizada");
            return true;
        } catch (Exception $e) {
            error_log("❌ Erro ao finalizar análise: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca partes por documento (CPF ou CNPJ)
     */
    public function buscarPartesPorDocumento($documento) {
        try {
            // Limpar documento (remover formatação)
            $documentoLimpo = preg_replace('/[^0-9]/', '', $documento);
            
            $stmt = $this->pdo->prepare("
                SELECT p.*, a.data_criacao, a.classificacao_risco
                FROM partes_extraidas p
                INNER JOIN analises a ON p.analise_id = a.id
                WHERE p.documento = :documento
                ORDER BY p.data_extracao DESC
            ");
            
            $stmt->execute(['documento' => $documentoLimpo]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar partes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca partes não enriquecidas
     */
    public function buscarPartesNaoEnriquecidas($limite = 100) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM partes_extraidas
                WHERE enriquecido = 0 
                  AND documento != 'NAOENCONTRADO'
                  AND documento IS NOT NULL
                ORDER BY data_extracao DESC
                LIMIT :limite
            ");
            
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar partes não enriquecidas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Salva dados de enriquecimento
     */
    public function salvarEnriquecimento($parteId, $tipoEnriquecimento, $dadosJson, $sucesso = true, $erro = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO enriquecimento_dados 
                (parte_id, tipo_enriquecimento, dados_json, sucesso, erro)
                VALUES (:parte_id, :tipo_enriquecimento, :dados_json, :sucesso, :erro)
            ");
            
            $stmt->execute([
                'parte_id' => $parteId,
                'tipo_enriquecimento' => $tipoEnriquecimento,
                'dados_json' => $dadosJson,
                'sucesso' => $sucesso ? 1 : 0,
                'erro' => $erro
            ]);
            
            // Marcar parte como enriquecida
            if ($sucesso) {
                $this->pdo->exec("UPDATE partes_extraidas SET enriquecido = 1 WHERE id = {$parteId}");
            }
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("❌ Erro ao salvar enriquecimento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca histórico de uma parte específica
     */
    public function buscarHistoricoParte($parteId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT e.*, p.nome, p.documento
                FROM enriquecimento_dados e
                INNER JOIN partes_extraidas p ON e.parte_id = p.id
                WHERE e.parte_id = :parte_id
                ORDER BY e.data_consulta DESC
            ");
            
            $stmt->execute(['parte_id' => $parteId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar histórico: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca últimas análises
     */
    public function buscarUltimasAnalises($limite = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT a.*,
                       COUNT(DISTINCT d.id) as total_docs,
                       COUNT(DISTINCT p.id) as total_parts
                FROM analises a
                LEFT JOIN documentos_analisados d ON a.id = d.analise_id
                LEFT JOIN partes_extraidas p ON a.id = p.analise_id
                GROUP BY a.id
                ORDER BY a.data_criacao DESC
                LIMIT :limite
            ");
            
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar análises: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca uma análise específica por ID
     */
    public function buscarAnalisePorId($analiseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM analises 
                WHERE id = :id
            ");
            
            $stmt->execute(['id' => $analiseId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar análise por ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Busca análise completa por ID
     */
    public function buscarAnalise($analiseId) {
        try {
            // Buscar análise
            $stmt = $this->pdo->prepare("SELECT * FROM analises WHERE id = :id");
            $stmt->execute(['id' => $analiseId]);
            $analise = $stmt->fetch();
            
            if (!$analise) return null;
            
            // Buscar documentos
            $stmt = $this->pdo->prepare("
                SELECT * FROM documentos_analisados 
                WHERE analise_id = :analise_id
                ORDER BY data_upload ASC
            ");
            $stmt->execute(['analise_id' => $analiseId]);
            $analise['documentos'] = $stmt->fetchAll();
            
            // Buscar partes
            $stmt = $this->pdo->prepare("
                SELECT * FROM partes_extraidas 
                WHERE analise_id = :analise_id
                ORDER BY data_extracao ASC
            ");
            $stmt->execute(['analise_id' => $analiseId]);
            $analise['partes'] = $stmt->fetchAll();
            
            return $analise;
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar análise: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Estatísticas gerais
     */
    public function getEstatisticas() {
        try {
            $stats = [];
            
            // Total de análises
            $stats['total_analises'] = $this->pdo->query("SELECT COUNT(*) FROM analises")->fetchColumn();
            
            // Total de partes extraídas
            $stats['total_partes'] = $this->pdo->query("SELECT COUNT(*) FROM partes_extraidas")->fetchColumn();
            
            // Total de documentos únicos (por hash)
            $stats['total_docs_unicos'] = $this->pdo->query("
                SELECT COUNT(DISTINCT hash_md5) FROM documentos_analisados
            ")->fetchColumn();
            
            // Partes enriquecidas
            $stats['partes_enriquecidas'] = $this->pdo->query("
                SELECT COUNT(*) FROM partes_extraidas WHERE enriquecido = 1
            ")->fetchColumn();
            
            // Partes pendentes de enriquecimento
            $stats['partes_pendentes'] = $this->pdo->query("
                SELECT COUNT(*) FROM partes_extraidas 
                WHERE enriquecido = 0 AND documento != 'NAOENCONTRADO'
            ")->fetchColumn();
            
            // CPFs únicos
            $stats['cpfs_unicos'] = $this->pdo->query("
                SELECT COUNT(DISTINCT documento) FROM partes_extraidas 
                WHERE tipo_documento = 'CPF' AND documento != 'NAOENCONTRADO'
            ")->fetchColumn();
            
            // CNPJs únicos
            $stats['cnpjs_unicos'] = $this->pdo->query("
                SELECT COUNT(DISTINCT documento) FROM partes_extraidas 
                WHERE tipo_documento = 'CNPJ' AND documento != 'NAOENCONTRADO'
            ")->fetchColumn();
            
            return $stats;
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar estatísticas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Salva um sócio de uma parte
     */
    public function salvarSocio($parteId, $analiseId, $socio) {
        try {
            // Tentar pegar CPF em ordem de preferência
            $cpf = $socio['cpf_limpo'] ?? $socio['cpf_parcial'] ?? $socio['cpf'] ?? null;
            
            // Se não tiver nenhum mas tiver cpf_cnpj ou cpf_original, tentar limpar
            if (!$cpf) {
                $cpfOriginal = $socio['cpf_original'] ?? $socio['cpf_cnpj'] ?? '';
                $cpfLimpo = preg_replace('/[^0-9]/', '', $cpfOriginal);
                if (strlen($cpfLimpo) >= 6) { // Aceitar pelo menos parcial
                    $cpf = $cpfLimpo;
                }
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO socios 
                (parte_id, analise_id, nome, qualificacao, cpf, data_entrada, 
                 representante_legal, qualificacao_representante, pais_origem)
                VALUES (:parte_id, :analise_id, :nome, :qualificacao, :cpf, :data_entrada,
                        :representante_legal, :qualificacao_representante, :pais_origem)
            ");
            
            $stmt->execute([
                'parte_id' => $parteId,
                'analise_id' => $analiseId,
                'nome' => $socio['nome'] ?? '',
                'qualificacao' => $socio['qualificacao'] ?? '',
                'cpf' => $cpf,
                'data_entrada' => $socio['data_entrada'] ?? null,
                'representante_legal' => $socio['representante_legal'] ?? null,
                'qualificacao_representante' => $socio['qualificacao_representante'] ?? null,
                'pais_origem' => $socio['pais_origem'] ?? null
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("❌ Erro ao salvar sócio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva múltiplos sócios de uma vez
     */
    public function salvarSocios($parteId, $analiseId, $socios) {
        $savedCount = 0;
        
        foreach ($socios as $socio) {
            if ($this->salvarSocio($parteId, $analiseId, $socio)) {
                $savedCount++;
            }
        }
        
        error_log("👥 {$savedCount} sócios salvos para parte {$parteId}");
        return $savedCount;
    }
    
    /**
     * Busca sócios de uma parte
     */
    public function buscarSociosPorParte($parteId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM socios
                WHERE parte_id = :parte_id
                ORDER BY id ASC
            ");
            
            $stmt->execute(['parte_id' => $parteId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar sócios: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca uma parte por ID
     */
    public function buscarPartePorId($parteId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM partes_extraidas WHERE id = :id");
            $stmt->execute(['id' => $parteId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar parte por ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Busca sócios para enriquecimento (com filtro opcional por análise)
     */
    public function buscarSociosParaEnriquecimento($analiseId = null, $limite = 50) {
        try {
            $sql = "SELECT s.*, p.nome as empresa_nome, p.documento as empresa_cnpj 
                    FROM socios s
                    INNER JOIN partes_extraidas p ON s.parte_id = p.id
                    WHERE s.cpf IS NOT NULL";
            
            if ($analiseId) {
                $sql .= " AND s.analise_id = :analise_id";
            }
            
            $sql .= " ORDER BY s.id ASC LIMIT :limite";
            
            $stmt = $this->pdo->prepare($sql);
            if ($analiseId) {
                $stmt->bindValue(':analise_id', $analiseId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar sócios para enriquecimento: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Atualiza CPF de um sócio
     */
    public function atualizarCpfSocio($socioId, $cpf) {
        try {
            $stmt = $this->pdo->prepare("UPDATE socios SET cpf = :cpf WHERE id = :id");
            $stmt->execute(['cpf' => $cpf, 'id' => $socioId]);
            return true;
        } catch (Exception $e) {
            error_log("❌ Erro ao atualizar CPF do sócio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualiza dados enriquecidos de um sócio
     */
    public function atualizarDadosEnriquecidosSocio($socioId, $dados) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE socios SET 
                    cpf = :cpf,
                    nome_mae = :nome_mae,
                    nascimento = :nascimento,
                    rg = :rg,
                    sexo = :sexo,
                    enriquecido = 1,
                    data_enriquecimento = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            
            $stmt->execute([
                'id' => $socioId,
                'cpf' => $dados['cpf'] ?? null,
                'nome_mae' => $dados['nome_mae'] ?? null,
                'nascimento' => $dados['nascimento'] ?? null,
                'rg' => $dados['rg'] ?? null,
                'sexo' => $dados['sexo'] ?? null
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("❌ Erro ao atualizar dados enriquecidos do sócio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca sócios de uma análise
     */
    public function buscarSociosPorAnalise($analiseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    s.id,
                    s.nome as socio_nome,
                    s.qualificacao as socio_qualificacao,
                    s.cpf as socio_cpf,
                    s.data_entrada as socio_data_entrada,
                    s.representante_legal as socio_representante,
                    s.qualificacao_representante as socio_qualificacao_rep,
                    s.pais_origem as socio_pais,
                    s.nome_mae as socio_nome_mae,
                    s.nascimento as socio_nascimento,
                    s.rg as socio_rg,
                    s.sexo as socio_sexo,
                    s.enriquecido as socio_enriquecido,
                    s.data_enriquecimento as socio_data_enriquecimento,
                    s.data_criacao,
                    p.nome as empresa_nome,
                    p.documento as empresa_cnpj,
                    p.role as empresa_role
                FROM socios s
                INNER JOIN partes_extraidas p ON s.parte_id = p.id
                WHERE s.analise_id = :analise_id
                ORDER BY p.nome, s.id ASC
            ");
            
            $stmt->execute(['analise_id' => $analiseId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar sócios da análise: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca partes extraídas de uma análise
     */
    public function buscarPartesAnalise($analiseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM partes_extraidas
                WHERE analise_id = :analise_id
                ORDER BY id ASC
            ");
            
            $stmt->execute(['analise_id' => $analiseId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar partes da análise: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca certidões (documentos com metadata) de uma análise
     */
    public function buscarCertidoesAnalise($analiseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM documentos_analisados 
                WHERE analise_id = :analise_id 
                AND metadata IS NOT NULL
                ORDER BY data_upload DESC
            ");
            
            $stmt->execute(['analise_id' => $analiseId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar certidões da análise: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca documentos originais (sem metadata) de uma análise
     */
    public function buscarDocumentosOriginais($analiseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM documentos_analisados 
                WHERE analise_id = :analise_id 
                AND (metadata IS NULL OR metadata = '')
                ORDER BY data_upload ASC
            ");
            
            $stmt->execute(['analise_id' => $analiseId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar documentos originais: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Atualiza o relatório HTML de uma análise
     */
    public function atualizarRelatorio($analiseId, $htmlRelatorio) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE analises 
                SET html_relatorio = :html_relatorio,
                    data_finalizacao = CURRENT_TIMESTAMP,
                    status = 'concluída'
                WHERE id = :id
            ");
            
            $stmt->execute([
                'id' => $analiseId,
                'html_relatorio' => $htmlRelatorio
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("❌ Erro ao atualizar relatório: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reseta o status de enriquecimento dos sócios de uma análise (para testes)
     */
    public function resetarEnriquecimentoSocios($analiseId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE socios SET 
                    enriquecido = 0,
                    nome_mae = NULL,
                    nascimento = NULL,
                    rg = NULL,
                    sexo = NULL,
                    data_enriquecimento = NULL
                WHERE analise_id = :analise_id
            ");
            
            $stmt->execute(['analise_id' => $analiseId]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("❌ Erro ao resetar enriquecimento: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Fecha conexão
     */
    public function close() {
        $this->pdo = null;
    }
}

