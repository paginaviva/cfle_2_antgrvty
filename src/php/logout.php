<?php
// src/php/logout.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Service/AuthService.php';

$config = require __DIR__ . '/../../config/config.php';
$auth = new AuthService($config);

$auth->logout();
header('Location: login.php');
exit;
