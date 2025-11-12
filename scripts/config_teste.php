<?php
/**
 * Arquivo de Configuração para Testes de Certidões
 * 
 * Edite os dados abaixo com informações reais para testar as APIs
 */

return [
    // Dados de Pessoa Física para testes
    'cpf' => '05434961129',
    'nome' => 'Pedro Henrique Pontes de Noronha',
    'nome_mae' => 'Alessandra Pontes de Sampaio',
    'data_nascimento' => '02/04/1996', // Formato: DD/MM/AAAA
    
    // Dados de Pessoa Jurídica para testes
    'cnpj' => '00000000000191', // CNPJ do Banco do Brasil (exemplo)
    
    // URL Base (ajuste se necessário)
    'base_url' => 'http://localhost/dip/automacoes',
    
    // Pasta onde serão salvas as certidões
    'pasta_certidoes' => __DIR__ . '/certidoes_teste',
    
    // Timeouts (em segundos)
    'timeout_tjgo' => 180,  // TJGO pode demorar devido ao CAPTCHA
    'timeout_stj' => 60,    // STJ é mais rápido
    
    // APIs para testar (true = ativo, false = ignorar)
    'apis_ativas' => [
        'tjgo_civel' => true,
        'tjgo_criminal' => true,
        'stj_pj' => true,
        'stj_pf' => true,
    ]
];

