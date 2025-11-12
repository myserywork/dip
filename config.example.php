<?php
/**
 * Arquivo de configuração do DueBot
 *
 * INSTRUÇÕES:
 * 1. Copie este arquivo para "config.php"
 * 2. Preencha suas chaves de API abaixo
 * 3. Nunca compartilhe o arquivo config.php com suas chaves
 */

// Chave API do Google Gemini
// Obtenha em: https://makersuite.google.com/app/apikey
define('GEMINI_API_KEY', 'sua-chave-gemini-aqui');

// Chave API da Judit.io
// Obtenha entrando em contato: https://judit.io
define('JUDIT_API_KEY', 'sua-chave-judit-aqui');

// Timeout para processamento (em segundos)
define('PROCESS_TIMEOUT', 600); // 10 minutos

// Número máximo de proprietários para consultar na Judit
define('MAX_OWNERS_TO_QUERY', 3);
