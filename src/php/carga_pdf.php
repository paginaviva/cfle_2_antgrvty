<?php
// src/php/carga_pdf.php
require_once __DIR__ . '/../Service/AuthService.php';

// Cargar configuración
$config = require __DIR__ . '/../../config/config.php';
$auth = new AuthService($config);

// Proteger ruta
$auth->requireLogin();

$uploadedFile = null;
$error = null;
$processingResult = null;

// Cargar configuración de prompts
$promptsConfig = require __DIR__ . '/../../config/prompts.php';
$promptsData = $promptsConfig['prompts'] ?? [];

// --- LÓGICA DE SUBIDA (Paso 1) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {
    if ($_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        
        $nombreTmp  = $_FILES['pdf']['tmp_name'];
        $nombreOrig = basename($_FILES['pdf']['name']);
        
        // Crear carpeta basada en el nombre del archivo (sin extensión)
        $nombreCarpeta = pathinfo($nombreOrig, PATHINFO_FILENAME);
        $nombreCarpeta = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreCarpeta);
        
        $targetDir = DOCS_PATH . '/' . $nombreCarpeta . '/';
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        
        $rutaDestino = $targetDir . $nombreOrig;

        if (move_uploaded_file($nombreTmp, $rutaDestino)) {
            $uploadedFile = [
                'path' => $rutaDestino,
                'name' => $nombreOrig,
                'initial_prompt' => $_POST['initial_prompt'] ?? null
            ];
        } else {
            $error = "Error al guardar el archivo.";
        }
    } else {
        $error = "Error en la subida: " . $_FILES['pdf']['error'];
    }
}

// --- LÓGICA DE PROCESAMIENTO (Paso 2 - Placeholder) ---
// --- LÓGICA DE PROCESAMIENTO (Paso 2 - Subida a OpenAI) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filepath']) && !isset($_FILES['pdf'])) {
    $rutaDestino = $_POST['filepath'];
    // Validación de seguridad
    $realUploadDir = realpath(DOCS_PATH);
    $realFilePath = realpath($rutaDestino);

    if ($realFilePath && strpos($realFilePath, $realUploadDir) === 0 && file_exists($realFilePath)) {
         $uploadedFile = [
            'path' => $realFilePath,
            'name' => basename($realFilePath),
            'initial_prompt' => $_POST['prompt_select'] ?? null
        ];
        
        // Configurar Logger
        $logFile = dirname($realFilePath) . '/process.log';
        $log = function($msg) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[$timestamp] $msg" . PHP_EOL, FILE_APPEND);
        };

        $log("Iniciando proceso para: " . $uploadedFile['name']);
        $log("Prompt seleccionado: " . ($uploadedFile['initial_prompt'] ?? 'None'));

        try {
            // Instanciar Cliente OpenAI
            require_once __DIR__ . '/../OpenAIClient.php';
            $client = new OpenAIClient($config['openai_api_key'] ?? OPENAI_API_KEY);

            // Subir archivo a OpenAI
            $log("Subiendo archivo a OpenAI...");
            $uploadResult = $client->uploadFile($realFilePath, 'assistants');
            
            if (!isset($uploadResult['id'])) {
                throw new \RuntimeException("La respuesta de OpenAI no contiene un ID de archivo.");
            }

            $fileId = $uploadResult['id'];
            $log("Archivo subido con éxito. ID: $fileId");

            // Guardar file_id
            file_put_contents($realFilePath . '.file_id', $fileId);
            $_SESSION['current_file_id'] = $fileId;

            // --- PASO 3: Crear Hilo ---
            $log("Creando Hilo...");
            
            // Obtener prompt inicial
            $promptKey = $uploadedFile['initial_prompt'] ?? array_key_first($promptsData);
            $promptName = $promptsData[$promptKey]['name'] ?? $promptKey;
            $log("Prompt seleccionado: $promptName"); // Loguear nombre legible
            
            $promptText = $promptsData[$promptKey]['prompt_text'] ?? "Analiza este documento.";
            
            // Mensaje inicial del usuario
            $messages = [
                [
                    "role" => "user",
                    "content" => "Analiza el archivo PDF adjunto, aplica el Proceso 1 y el Proceso 2, y devuelve un único objeto JSON con la clave Matriz.",
                    "attachments" => [
                        [
                            "file_id" => $fileId,
                            "tools" => [["type" => "code_interpreter"]]
                        ]
                    ]
                ]
            ];

            $thread = $client->createThread($messages);
            $threadId = $thread['id'];
            $log("Hilo creado: $threadId");

            // --- PASO 4: Configurar Run ---
            $model = $_POST['parametro_2'] ?? OPENAI_MODEL_ID;

            // Crear Asistente Dinámicamente
            $log("Creando Asistente: $promptName");
            $assistant = $client->createAssistant($promptName, $promptText, $model);
            $assistantId = $assistant['id'];
            $log("Asistente creado: $assistantId");

            // --- PASO 5: Ejecutar Run y Polling ---
            $log("Iniciando Run con Asistente: $assistantId y Modelo: $model");
            $run = $client->createRun($threadId, $assistantId, $model);
            $runId = $run['id'];
            $log("Run iniciado: $runId");

            // Polling Loop
            $status = $run['status'];
            $attempts = 0;
            $maxAttempts = 60; // ~2 minutos (2s delay)

            while (!in_array($status, ['completed', 'failed', 'cancelled', 'expired']) && $attempts < $maxAttempts) {
                sleep(2);
                $runCheck = $client->getRun($threadId, $runId);
                $status = $runCheck['status'];
                $attempts++;
                $log("Estado Run ($attempts): $status");
            }

            if ($status === 'completed') {
                $log("Run completado. Obteniendo mensajes...");
                $messagesList = $client->listMessages($threadId);
                $lastMessage = $messagesList['data'][0]['content'][0]['text']['value'] ?? "Sin respuesta";
                
                // Intentar extraer JSON si hay texto adicional
                if (preg_match('/```json\s*(\{.*?\})\s*```/s', $lastMessage, $matches)) {
                    $jsonOutput = $matches[1];
                } else {
                    $jsonOutput = $lastMessage;
                }

                $processingResult = $jsonOutput;
                $log("Proceso finalizado con éxito.");
            } else {
                throw new \RuntimeException("El Run no se completó. Estado final: $status");
            }

        } catch (\Exception $e) {
            $error = "Error en el proceso: " . $e->getMessage();
            $log("ERROR: " . $e->getMessage());
        }

    } else {
        $error = "Archivo no válido o no encontrado.";
    }
}

include __DIR__ . '/layout_header.php';
?>

<div style="text-align: center; padding: 4rem 0;">
    <h2>Procesar Ficha Técnica</h2>
    <p style="color: #6b7280; margin-top: 1rem;">
        Sube un PDF para que el Agente IA lo analice.
    </p>
    
    <div style="margin-top: 2rem; padding: 2rem; border: 2px dashed #d1d5db; border-radius: 0.5rem; background-color: #f9fafb; max-width: 600px; margin-left: auto; margin-right: auto;">
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($uploadedFile): ?>
            <!-- Step 2: Confirmation and Process Button -->
            <div style="text-align: left; background: white; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                <h3 style="margin-top: 0; color: #059669;">¡Archivo Subido Correctamente!</h3>
                <p style="color: #374151; margin-bottom: 1.5rem;">
                    Has subido: <strong><?php echo htmlspecialchars($uploadedFile['name']); ?></strong>
                </p>

                <form action="carga_pdf.php" method="post" style="display: flex; flex-direction: column; gap: 1rem;" onsubmit="this.querySelector('button[type=submit]').disabled = true; this.querySelector('button[type=submit]').innerText = 'Procesando...';">
                    <input type="hidden" name="filepath" value="<?php echo htmlspecialchars($uploadedFile['path']); ?>">
                    
                    <!-- Prompt Seleccionado (Estático) -->
                    <?php 
                        $selectedPromptKey = $uploadedFile['initial_prompt'] ?? array_key_first($promptsData);
                        $selectedPromptName = $promptsData[$selectedPromptKey]['name'] ?? $selectedPromptKey;
                        $selectedPromptDesc = $promptsData[$selectedPromptKey]['description'] ?? '';
                    ?>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Prompt Seleccionado</label>
                        <div style="padding: 0.75rem; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 0.375rem; color: #111827;">
                            <strong><?php echo htmlspecialchars($selectedPromptName); ?></strong>
                            <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; margin-bottom: 0;"><?php echo htmlspecialchars($selectedPromptDesc); ?></p>
                        </div>
                        <input type="hidden" name="prompt_select" value="<?php echo htmlspecialchars($selectedPromptKey); ?>">
                    </div>

                    <!-- Contenedor de Parámetros Dinámicos -->
                    <div id="dynamic_parameters">
                        <!-- Se llena vía JS -->
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.75rem;">
                            Ejecutar Análisis IA
                        </button>
                        <a href="carga_pdf.php" class="btn" style="background: #9ca3af; color: white; text-decoration: none; padding: 0.75rem; border-radius: 0.375rem; text-align: center;">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>

            <script>
                // Pasar configuración de PHP a JS
                const promptsConfig = <?php echo json_encode($promptsData); ?>;
                const selectedPromptKey = "<?php echo $selectedPromptKey; ?>";
                const postData = <?php echo json_encode($_POST); ?>; // Datos POST para preservar valores
                const paramsContainer = document.getElementById('dynamic_parameters');

                function renderParameters(promptKey) {
                    paramsContainer.innerHTML = ''; // Limpiar
                    const promptData = promptsConfig[promptKey];
                    
                    if (!promptData) return;

                    // Renderizar parámetros
                    if (promptData.pprompt_parametros) {
                        for (const [paramKey, paramConfig] of Object.entries(promptData.pprompt_parametros)) {
                            const wrapper = document.createElement('div');
                            wrapper.style.marginBottom = '1rem';

                            const label = document.createElement('label');
                            label.htmlFor = paramKey;
                            label.style.display = 'block';
                            label.style.fontWeight = '600';
                            label.style.marginBottom = '0.5rem';
                            label.style.color = '#374151';
                            label.textContent = (paramConfig.Etiqueta && paramConfig.Etiqueta[0]) ? paramConfig.Etiqueta[0] : paramKey;
                            wrapper.appendChild(label);

                            const type = (paramConfig.Tipo && paramConfig.Tipo[0]) ? paramConfig.Tipo[0] : 'text';
                            // Priorizar valor POST, luego valor por defecto
                            const defaultValue = postData[paramKey] !== undefined ? postData[paramKey] : (paramConfig.valor_defecto || '');

                            if (type === 'select' && paramConfig.opciones) {
                                const select = document.createElement('select');
                                select.name = paramKey;
                                select.id = paramKey;
                                select.style.width = '100%';
                                select.style.padding = '0.5rem';
                                select.style.border = '1px solid #d1d5db';
                                select.style.borderRadius = '0.375rem';
                                select.style.background = 'white';

                                paramConfig.opciones.forEach(opt => {
                                    const option = document.createElement('option');
                                    option.value = opt;
                                    option.textContent = opt;
                                    if (String(opt) === String(defaultValue)) {
                                        option.selected = true;
                                    }
                                    select.appendChild(option);
                                });
                                wrapper.appendChild(select);
                            } else {
                                const input = document.createElement('input');
                                input.type = 'text';
                                input.name = paramKey;
                                input.id = paramKey;
                                input.value = defaultValue;
                                input.style.width = '100%';
                                input.style.padding = '0.5rem';
                                input.style.border = '1px solid #d1d5db';
                                input.style.borderRadius = '0.375rem';
                                wrapper.appendChild(input);
                            }

                            paramsContainer.appendChild(wrapper);
                        }
                    }
                }

                // Render inicial
                if (selectedPromptKey) {
                    renderParameters(selectedPromptKey);
                }
            </script>

        <?php else: ?>
            <!-- Step 1: Upload Form -->
            <form action="carga_pdf.php" method="post" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div style="text-align: left;">
                    <label for="pdf" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Archivo PDF</label>
                    <input type="file" name="pdf" id="pdf" accept="application/pdf" required 
                           style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; background: white;">
                </div>

                <!-- Selector de Prompt en Paso 1 -->
                <div style="text-align: left;">
                    <label for="initial_prompt" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Seleccionar Prompt</label>
                    <select name="initial_prompt" id="initial_prompt" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; background: white;">
                        <?php foreach ($promptsData as $key => $data): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>">
                                <?php echo htmlspecialchars($data['name'] ?? $key); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.75rem; font-size: 1rem; margin-top: 1rem;">
                    Subir Archivo
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>
