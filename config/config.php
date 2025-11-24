<?php
// config/config.php

// Definir rutas base
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
if (!defined('DOCS_PATH')) define('DOCS_PATH', BASE_PATH . '/docs');
if (!defined('LOGS_PATH')) define('LOGS_PATH', BASE_PATH . '/logs');

// Configuracion de la App
if (!defined('APP_TITLE')) define('APP_TITLE', 'Gestor de Fichas Tecnicas');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

// OpenAI Configuration
if (!defined('OPENAI_API_KEY')) define('OPENAI_API_KEY', 'TU_API_KEY_AQUI');
if (!defined('OPENAI_API_KEY_DEFAULT')) define('OPENAI_API_KEY_DEFAULT', 'TU_API_KEY_AQUI');
if (!defined('OPENAI_ASSISTANT_ID')) define('OPENAI_ASSISTANT_ID', 'asst_...'); // Reemplazar con ID real
if (!defined('OPENAI_MODEL_ID')) define('OPENAI_MODEL_ID', 'gpt-5.1-chat-latest');
if (!defined('OPENAI_RESPONSES_URL')) define('OPENAI_RESPONSES_URL', 'https://api.openai.com/v1/responses');
if (!defined('OPENAI_FILES_URL')) define('OPENAI_FILES_URL', 'https://api.openai.com/v1/files');
if (!defined('OPENAI_THREADS_URL')) define('OPENAI_THREADS_URL', 'https://api.openai.com/v1/threads');

// Upload Configuration
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', DOCS_PATH . '/');

// Configuracion de Usuarios
$users_config = [
    'admin' => '$2y$10$8.h.y.t.e.s.t.h.a.s.h.e.r.e.p.l.a.c.e.w.i.t.h.r.e.a.l.h.a.s.h', // Reemplazar con hash real
];

// Si existe users.json, cargarlo (preferido)
$users_file = __DIR__ . '/users.json';
if (file_exists($users_file)) {
    $json_users = json_decode(file_get_contents($users_file), true);
    if ($json_users) {
        $users_config = $json_users;
    }
}

return [
    'users' => $users_config,
    'paths' => [
        'docs' => DOCS_PATH,
        'logs' => LOGS_PATH
    ],
    'openai' => [
        'api_key' => OPENAI_API_KEY,
        'assistant_id' => OPENAI_ASSISTANT_ID,
        'model_id' => OPENAI_MODEL_ID
    ]
];
