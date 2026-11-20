<?php

return [
    'cache_dir'      => __DIR__ . '/cache',
    'cache_ttl'      => 3600, // 1 hora em segundos
    'profile_dir'    => __DIR__ . '/chrome_profile',
    'chrome_binary'  => __DIR__ . '/chrome_wrapper.sh',
    'headless'       => true, // Mude para false apenas na 1ª configuração manual
    'limite_itens'   => 5,    // Máximo de produtos retornados por fornecedor
    'user_agent'     => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
];


