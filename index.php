<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DueBot - Análise Inteligente de Due Diligence</title>
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
            max-width: 900px;
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
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="3" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="70" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="30" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="90" r="1" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            100% { transform: translateY(-100px); }
        }

        .logo {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .logo i {
            background: linear-gradient(45deg, #f39c12, #e74c3c);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-right: 15px;
        }

        .subtitle {
            font-size: 18px;
            opacity: 0.9;
            font-weight: 300;
            position: relative;
            z-index: 1;
        }

        .main-content {
            padding: 40px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .feature-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(52, 152, 219, 0.1);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.1);
        }

        .feature-icon {
            font-size: 32px;
            color: #3498db;
            margin-bottom: 15px;
        }

        .feature-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .feature-desc {
            font-size: 14px;
            color: #7f8c8d;
            line-height: 1.5;
        }

        .form-section {
            background: #ffffff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .api-hint {
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            color: white;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 8px;
            display: flex;
            align-items: center;
        }

        .api-hint i {
            margin-right: 8px;
        }

        .api-hint a {
            color: #ddd;
            text-decoration: underline;
        }

        .dropzone {
            border: 3px dashed #bdc3c7;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .dropzone:hover {
            border-color: #3498db;
            background: linear-gradient(135deg, #ebf3fd 0%, #f8f9fa 100%);
        }

        .dropzone.dragover {
            border-color: #e74c3c;
            background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
            transform: scale(1.02);
        }

        .dropzone-content {
            position: relative;
            z-index: 1;
        }

        .dropzone-icon {
            font-size: 48px;
            color: #bdc3c7;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .dropzone:hover .dropzone-icon {
            color: #3498db;
            transform: scale(1.1);
        }

        .dropzone-text {
            font-size: 18px;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .dropzone-hint {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .file-input {
            display: none;
        }

        .file-list {
            margin-top: 20px;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 4px solid #3498db;
        }

        .file-info {
            display: flex;
            align-items: center;
        }

        .file-icon {
            color: #3498db;
            margin-right: 10px;
        }

        .file-name {
            font-weight: 500;
            color: #2c3e50;
        }

        .file-size {
            color: #7f8c8d;
            font-size: 12px;
            margin-left: 8px;
        }

        .file-remove {
            color: #e74c3c;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .file-remove:hover {
            background-color: rgba(231, 76, 60, 0.1);
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .progress-container {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .progress-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1.5s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .progress-text {
            font-size: 18px;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .progress-hint {
            color: #7f8c8d;
            font-size: 14px;
        }

        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 5px solid;
            display: flex;
            align-items: flex-start;
        }

        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-color: #2196f3;
            color: #0d47a1;
        }

        .alert-icon {
            font-size: 20px;
            margin-right: 15px;
            margin-top: 2px;
        }

        .supported-formats {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }

        .format-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .logo {
                font-size: 36px;
            }
            
            .main-content {
                padding: 30px 20px;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <i class="fas fa-robot"></i>DueBot
            </div>
            <div class="subtitle">
                Análise Inteligente de Due Diligence Imobiliária com IA
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Features -->
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <div class="feature-title">IA Avançada</div>
                    <div class="feature-desc">Powered by Google Gemini para análise jurídica profunda</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-title">Análise Completa</div>
                    <div class="feature-desc">Verificação detalhada de matrículas, certidões e documentos</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="feature-title">Relatório Profissional</div>
                    <div class="feature-desc">Documentos estruturados com base legal e classificação de riscos</div>
                </div>
            </div>

            <!-- Alert -->
            <div class="alert alert-info">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <strong>Como funciona o DueBot:</strong>
                    <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                        <li>Faça upload da matrícula imobiliária e documentos complementares</li>
                        <li>Insira sua chave API do Google Gemini para ativar a IA</li>
                        <li>O DueBot analisará todos os documentos automaticamente</li>
                        <li>Receba um relatório detalhado com classificação de riscos</li>
                    </ul>
                    <div class="supported-formats">
                        <span class="format-badge">PDF</span>
                        <span class="format-badge">JPG</span>
                        <span class="format-badge">JPEG</span>
                        <span class="format-badge">PNG</span>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <div class="form-section">
                <form id="uploadForm" action="process.php" method="POST" enctype="multipart/form-data">
                    <!-- API Key -->
                    
                    <!-- File Upload -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-upload"></i> Documentos para Análise
                        </label>
                        <div class="dropzone" id="dropzone">
                            <div class="dropzone-content">
                                <div class="dropzone-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="dropzone-text">
                                    Arraste seus documentos aqui
                                </div>
                                <div class="dropzone-hint">
                                    ou clique para selecionar arquivos
                                </div>
                                <div style="margin: 20px 0;">
                                    <i class="fas fa-file-pdf" style="color: #e74c3c; margin: 0 5px;"></i>
                                    <i class="fas fa-file-image" style="color: #f39c12; margin: 0 5px;"></i>
                                    <span style="color: #7f8c8d; font-size: 14px;">até 20MB por arquivo</span>
                                </div>
                            </div>
                        </div>
                        <input type="file" 
                               id="documents" 
                               name="documents[]" 
                               class="file-input"
                               multiple 
                               required 
                               accept=".pdf,.jpg,.jpeg,.png">
                        
                        <div id="fileList" class="file-list"></div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-rocket"></i>
                        Iniciar Análise com DueBot
                    </button>
                </form>
            </div>

            <!-- Progress -->
            <div class="progress-container" id="progressContainer">
                <div class="progress-spinner"></div>
                <div class="progress-text">DueBot está analisando seus documentos...</div>
                <div class="progress-hint">
                    <i class="fas fa-coffee"></i>
                    Isso pode levar alguns minutos. Que tal um café? ☕
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access Links -->
    <div style="max-width: 900px; margin: 40px auto 0; padding: 0 20px;">
        <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); overflow: hidden;">
            
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; padding: 30px; text-align: center;">
                <h2 style="font-size: 24px; font-weight: 600; margin: 0;">
                    <i class="fas fa-link" style="margin-right: 10px;"></i>
                    Acesso Rápido
                </h2>
                <p style="margin: 8px 0 0 0; opacity: 0.9; font-weight: 300; font-size: 14px;">Ferramentas e recursos disponíveis</p>
            </div>

            <!-- Content -->
            <div style="padding: 40px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    
                    <!-- Histórico -->
                    <a href="historico.php" class="quick-access-card">
                        <div class="quick-icon">📊</div>
                        <div class="quick-title">Histórico</div>
                        <div class="quick-desc">Ver análises anteriores</div>
                    </a>

                    <!-- Enriquecimento -->
                    <a href="enriquecimento.php?run=enriquecer&limite=50" target="_blank" class="quick-access-card">
                        <div class="quick-icon">🚀</div>
                        <div class="quick-title">Enriquecer</div>
                        <div class="quick-desc">Atualizar dados via API</div>
                    </a>

                    <!-- API Pessoa -->
                    <a href="api_pessoa.php?estrutura=1" target="_blank" class="quick-access-card">
                        <div class="quick-icon">🔍</div>
                        <div class="quick-title">Consultar CPF</div>
                        <div class="quick-desc">Buscar dados de pessoas</div>
                    </a>

                    <!-- Nova Análise -->
                    <a href="#mainForm" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;" class="quick-access-card">
                        <div class="quick-icon">➕</div>
                        <div class="quick-title">Nova Análise</div>
                        <div class="quick-desc">Iniciar análise agora</div>
                    </a>
                    
                </div>

                <!-- Footer Info -->
                <div style="margin-top: 30px; padding-top: 25px; border-top: 1px solid rgba(52, 152, 219, 0.1); text-align: center;">
                    <div style="display: flex; justify-content: center; align-items: center; gap: 20px; flex-wrap: wrap; font-size: 13px; color: #7f8c8d;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-brain" style="color: #3498db;"></i>
                            <span>IA Gemini</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-database" style="color: #3498db;"></i>
                            <span>SQLite</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-sync-alt" style="color: #3498db;"></i>
                            <span>Auto-Enriquecimento</span>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <style>
        .quick-access-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(52, 152, 219, 0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }
        
        .quick-access-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.15);
            border-color: rgba(52, 152, 219, 0.3);
        }
        
        .quick-icon {
            font-size: 40px;
            margin-bottom: 12px;
        }
        
        .quick-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 6px;
        }
        
        .quick-desc {
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 400;
        }
    </style>

    <script>
        let selectedFiles = [];

        // Dropzone functionality
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('documents');
        const fileList = document.getElementById('fileList');

        // Click to select files
        dropzone.addEventListener('click', () => {
            fileInput.click();
        });

        // Drag and drop events
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dragover');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            const files = Array.from(e.dataTransfer.files);
            handleFiles(files);
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            handleFiles(files);
        });

        function handleFiles(files) {
            const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            const maxSize = 20 * 1024 * 1024; // 20MB

            files.forEach(file => {
                const extension = file.name.split('.').pop().toLowerCase();
                
                // Validate extension
                if (!allowedExtensions.includes(extension)) {
                    showNotification(`Arquivo "${file.name}" tem formato não suportado.`, 'error');
                    return;
                }

                // Validate size
                if (file.size > maxSize) {
                    showNotification(`Arquivo "${file.name}" é muito grande (máx. 20MB).`, 'error');
                    return;
                }

                // Check if file already exists
                if (selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                    showNotification(`Arquivo "${file.name}" já foi adicionado.`, 'warning');
                    return;
                }

                selectedFiles.push(file);
            });

            updateFileList();
            updateDropzone();
        }

        function updateFileList() {
            fileList.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const fileIcon = getFileIcon(file.name);
                const fileSize = formatFileSize(file.size);
                
                fileItem.innerHTML = `
                    <div class="file-info">
                        <i class="fas ${fileIcon} file-icon"></i>
                        <span class="file-name">${file.name}</span>
                        <span class="file-size">(${fileSize})</span>
                    </div>
                    <i class="fas fa-times file-remove" onclick="removeFile(${index})"></i>
                `;
                
                fileList.appendChild(fileItem);
            });
        }

        function updateDropzone() {
            const dropzoneText = document.querySelector('.dropzone-text');
            const dropzoneHint = document.querySelector('.dropzone-hint');
            
            if (selectedFiles.length > 0) {
                dropzoneText.textContent = `${selectedFiles.length} arquivo(s) selecionado(s)`;
                dropzoneHint.textContent = 'Clique para adicionar mais arquivos';
            } else {
                dropzoneText.textContent = 'Arraste seus documentos aqui';
                dropzoneHint.textContent = 'ou clique para selecionar arquivos';
            }
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
            updateDropzone();
            updateFileInput();
        }

        function updateFileInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        function getFileIcon(filename) {
            const extension = filename.split('.').pop().toLowerCase();
            switch (extension) {
                case 'pdf': return 'fa-file-pdf';
                case 'jpg':
                case 'jpeg':
                case 'png': return 'fa-file-image';
                default: return 'fa-file';
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                animation: slideIn 0.3s ease;
                max-width: 300px;
            `;
            
            switch (type) {
                case 'error':
                    notification.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
                    break;
                case 'warning':
                    notification.style.background = 'linear-gradient(135deg, #f39c12, #e67e22)';
                    break;
                default:
                    notification.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
            }
            
            notification.textContent = message;
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
           
            if (selectedFiles.length === 0) {
                e.preventDefault();
                showNotification('Por favor, selecione pelo menos um documento.', 'error');
                return;
            }

            // Update file input with selected files
            updateFileInput();

            // Show progress
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            document.getElementById('progressContainer').style.display = 'block';
            
            // Scroll to progress
            document.getElementById('progressContainer').scrollIntoView({ behavior: 'smooth' });
            
            showNotification('Iniciando análise com DueBot...', 'info');
        });
    </script>
</body>
</html>
