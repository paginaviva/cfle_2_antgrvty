<?php
// src/php/login.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../Service/AuthService.php';

$config = require __DIR__ . '/../../config/config.php';
$auth = new AuthService($config);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($auth->login($username, $password)) {
        header('Location: ../../index.php');
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_TITLE; ?></title>
    <link rel="stylesheet" href="../../assets/style.css">
</head>
<body style="display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f3f4f6;">

<div style="background: white; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); width: 100%; max-width: 400px;">
    <h2 style="text-align: center; color: #111827; margin-bottom: 1.5rem;">Iniciar Sesión</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" style="display: flex; flex-direction: column; gap: 1rem;">
        <div>
            <label for="username" style="display: block; margin-bottom: 0.5rem; color: #374151;">Usuario</label>
            <input type="text" name="username" id="username" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
        </div>
        
        <div>
            <label for="password" style="display: block; margin-bottom: 0.5rem; color: #374151;">Contraseña</label>
            <input type="password" name="password" id="password" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 0.75rem; margin-top: 0.5rem;">Entrar</button>
    </form>
</div>

</body>
</html>
