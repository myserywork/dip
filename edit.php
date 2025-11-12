<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de Prompt - DueBot</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .back-btn {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .main-content {
            padding: 40px;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }

        textarea {
            width: 100%;
            min-height: 600px;
            padding: 20px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            resize: vertical;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }

        .button-container {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .file-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            color: #0c5460;
        }

        .file-info i {
            margin-right: 8px;
        }

        .char-counter {
            text-align: right;
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-container {
            animation: fadeIn 0.6s ease-out;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-edit"></i>
                Editor de Prompt
            </h1>
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Voltar
            </a>
        </div>

        <div class="main-content">
            <?php
            $promptFile = 'prompt.txt';
            $message = '';
            $messageType = '';

            // Processar o formulário quando enviado
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prompt'])) {
                $newContent = $_POST['prompt_content'];
                
                // Salvar o arquivo em UTF-8
                if (file_put_contents($promptFile, $newContent, LOCK_EX) !== false) {
                    $message = 'Prompt salvo com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao salvar o prompt. Verifique as permissões do arquivo.';
                    $messageType = 'error';
                }
            }

            // Ler o conteúdo atual do prompt
            $currentContent = '';
            if (file_exists($promptFile)) {
                $currentContent = file_get_contents($promptFile);
                // Garantir que seja lido como UTF-8
                if (!mb_check_encoding($currentContent, 'UTF-8')) {
                    $currentContent = mb_convert_encoding($currentContent, 'UTF-8', mb_detect_encoding($currentContent));
                }
            } else {
                $message = 'Arquivo prompt.txt não encontrado.';
                $messageType = 'error';
            }
            ?>

            <div class="form-container">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="file-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Arquivo:</strong> <?php echo $promptFile; ?> | 
                    <strong>Codificação:</strong> UTF-8 | 
                    <strong>Última modificação:</strong> <?php echo file_exists($promptFile) ? date('d/m/Y H:i:s', filemtime($promptFile)) : 'N/A'; ?>
                </div>

                <form method="POST" id="promptForm">
                    <div class="form-group">
                        <label for="prompt_content">
                            <i class="fas fa-file-alt"></i>
                            Conteúdo do Prompt
                        </label>
                        <textarea 
                            name="prompt_content" 
                            id="prompt_content" 
                            placeholder="Digite o conteúdo do prompt aqui..."
                            required
                        ><?php echo htmlspecialchars($currentContent); ?></textarea>
                        <div class="char-counter">
                            <span id="charCount">0</span> caracteres
                        </div>
                    </div>

                    <div class="button-container">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </a>
                        <button type="submit" name="save_prompt" class="btn btn-primary" id="saveBtn">
                            <i class="fas fa-save"></i>
                            Salvar Prompt
                        </button>
                    </div>
                </form>

                <div class="loading" id="loadingDiv">
                    <div class="spinner"></div>
                    <p>Salvando prompt...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Contador de caracteres
        const textarea = document.getElementById('prompt_content');
        const charCount = document.getElementById('charCount');
        
        function updateCharCount() {
            const count = textarea.value.length;
            charCount.textContent = count.toLocaleString();
        }
        
        textarea.addEventListener('input', updateCharCount);
        updateCharCount(); // Atualizar na carga da página

        // Efeito de loading ao salvar
        const form = document.getElementById('promptForm');
        const saveBtn = document.getElementById('saveBtn');
        const loadingDiv = document.getElementById('loadingDiv');
        
        form.addEventListener('submit', function() {
            saveBtn.style.display = 'none';
            loadingDiv.style.display = 'block';
        });

        // Auto-salvar (opcional - comentado por segurança)
        /*
        let autoSaveTimeout;
        textarea.addEventListener('input', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(function() {
                // Auto-save logic here if needed
            }, 5000); // 5 segundos
        });
        */

        // Atalho de teclado para salvar (Ctrl+S)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                form.submit();
            }
        });

        // Aviso ao sair sem salvar
        let originalContent = textarea.value;
        window.addEventListener('beforeunload', function(e) {
            if (textarea.value !== originalContent) {
                e.preventDefault();
                e.returnValue = '';
                return 'Você tem alterações não salvas. Deseja realmente sair?';
            }
        });

        // Resetar aviso quando salvar
        form.addEventListener('submit', function() {
            originalContent = textarea.value;
        });
    </script>
</body>
</html>
