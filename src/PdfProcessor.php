<?php
// src/PdfProcessor.php

require_once __DIR__ . '/OpenAIClient.php';

class PdfProcessor
{
    private OpenAIClient $client;

    public function __construct(OpenAIClient $client)
    {
        $this->client = $client;
    }

    /**
     * Procesa la ficha técnica usando el CGPT.
     *
     * @param string $filePath Ruta al PDF subido.
     * @param string $modo     'humano' o 'json' (tipo de salida deseada).
     */
    public function procesarFicha(string $filePath, string $modo = 'humano'): array
    {
        // 1) Opcional: subimos el archivo a OpenAI y obtenemos un file_id
        $fileInfo = $this->client->uploadFile($filePath);
        $fileId   = $fileInfo['id'] ?? null;

        // 2) Construimos un input para el CGPT.
        //    La instrucción maestra del CGPT ya sabe qué hacer con B1/B2/B3,
        //    aquí solo le damos la orden de "iniciar el proceso" y la preferencia de salida.
        $inputTexto = $this->buildInputMessage($modo);

        // 3) Preparamos el payload del endpoint /v1/responses
        //    (La estructura puede adaptarse a cómo uses el modelo; esto es un esqueleto.)
        $payload = [
            'model' => OPENAI_MODEL_ID,
            'input' => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $inputTexto,
                        ],
                        // Asociamos el archivo para que el CGPT pueda leerlo
                        [
                            'type'     => 'input_file',
                            'file_id'  => $fileId,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->client->callResponses($payload);

        return $response;
    }

    private function buildInputMessage(string $modo): string
    {
        if ($modo === 'json') {
            return <<<TXT
Inicia el proceso completo descrito en B1, B2 y B3 con la ficha técnica adjunta.

Objetivo:
1. Usa B1 para comprender el contexto del layout y del tipo de ficha.
2. Usa B2 para extraer:
   - Tabla de elementos comunes.
   - Lista de características.
   - Tabla de especificaciones técnicas.
3. Usa B3 para:
   - Detectar esquemas y otras tablas.
   - Identificar sus nombres o descripciones.
   - Contar cuántos hay en todo el PDF.

Responde exclusivamente en JSON válido, con una estructura clara:
{
  "contexto_b1": { ... },
  "elementos_comunes_b2": [ ... ],
  "caracteristicas_b2": [ ... ],
  "especificaciones_tecnicas_b2": [ ... ],
  "esquemas_y_tablas_b3": {
    "total": number,
    "detalle": [ ... ]
  }
}
TXT;
        }

        // Modo "humano": respuesta legible para persona
        return <<<TXT
Inicia el proceso completo descrito en B1, B2 y B3 con la ficha técnica adjunta.

1. Explica brevemente qué entiendes del contexto del layout y del tipo de ficha (B1).
2. Muestra en el chat:
   - Una tabla con los elementos comunes.
   - Una lista clara de características.
   - Una tabla con las especificaciones técnicas extraídas (B2).
3. A continuación:
   - Indica cuántos esquemas y otras tablas detectas en todo el PDF.
   - Nombra o describe cada esquema/tabla y su página si es posible (B3).
TXT;
    }
}
