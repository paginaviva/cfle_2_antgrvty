<?php
// config/config.example.php

// Definir rutas base
define('BASE_PATH', dirname(__DIR__));
define('DOCS_PATH', BASE_PATH . '/docs');
define('LOGS_PATH', BASE_PATH . '/logs');

// Configuración de la App
define('APP_TITLE', 'Gestor de Fichas Técnicas');

// Configuración de Usuarios
// Copiar este archivo a config.php y ajustar valores
return [
    'users' => [
        'admin' => 'HASH_DE_PASSWORD_AQUI'
    ],
    'paths' => [
        'docs' => DOCS_PATH,
        'logs' => LOGS_PATH
    ]
];
