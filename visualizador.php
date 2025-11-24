<?php
// visualizador.php
// Herramienta temporal para visualizar tablas HTML desde un JSON.

$resultado = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonInput = $_POST['json_data'] ?? '';
    
    // Limpiamos las barras invertidas que a veces añade PHP magic quotes (aunque obsoleto, por si acaso)
    if (get_magic_quotes_gpc()) {
        $jsonInput = stripslashes($jsonInput);
    }

    if (!empty($jsonInput)) {
        $data = json_decode($jsonInput, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // Extraer datos asumiendo las claves mencionadas
            // Se aceptan variaciones comunes por flexibilidad
            $titulo = $data['titulo_tabla'] ?? $data['titulo'] ?? 'Título no encontrado';
            $tablaHtml = $data['html_tabla'] ?? $data['tabla_html'] ?? $data['tabla'] ?? $data['html'] ?? '<p>No se encontró contenido HTML de tabla.</p>';
            
            $resultado = [
                'titulo' => $titulo,
                'html' => $tablaHtml
            ];
        } else {
            $error = "Error al leer el JSON: " . json_last_error_msg();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador de Tablas JSON</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            margin: 0;
            padding: 2rem;
            line-height: 1.5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        h1, h2 { color: #2563eb; margin-top: 0; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        textarea {
            width: 100%;
            height: 150px;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-family: monospace;
            font-size: 0.9rem;
            box-sizing: border-box;
            margin-bottom: 1rem;
        }
        textarea:focus { outline: 2px solid #2563eb; border-color: transparent; }
        button {
            background-color: #2563eb;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 600;
        }
        button:hover { background-color: #1d4ed8; }
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            border: 1px solid #fecaca;
        }
        
        /* Estilos para la tabla renderizada */
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 0.75rem;
            text-align: left;
        }
        th {
            background-color: #f9fafb;
            font-weight: 600;
        }
        tr:nth-child(even) { background-color: #f9fafb; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h1>Visualizador de Tablas JSON</h1>
        <p>Pega tu JSON abajo. Debe tener el formato: <code>{"titulo_tabla": "...", "tabla_html": "..."}</code></p>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="json_data">Pegar JSON aquí:</label>
                <!-- El textarea se muestra vacío siempre al recargar, como se pidió -->
                <textarea name="json_data" id="json_data" placeholder='{
    "titulo_tabla": "Mi Tabla de Ejemplo",
    "tabla_html": "<table><tr><th>Col1</th></tr><tr><td>Dato</td></tr></table>"
}' required></textarea>
            </div>
            <button type="submit">Visualizar Tabla</button>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($resultado): ?>
        <div class="card">
            <h2><?php echo htmlspecialchars($resultado['titulo']); ?></h2>
            <div class="table-container">
                <!-- Se imprime el HTML tal cual (raw) para que se renderice la tabla -->
                <?php echo $resultado['html']; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
