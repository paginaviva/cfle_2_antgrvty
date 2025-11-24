<?php
// src/OpenAIClient.php

require_once __DIR__ . '/../config/config.php';

class OpenAIClient
{
    private $apiKey;

    public function __construct(string $apiKey = OPENAI_API_KEY)
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException("La clave API de OpenAI no está configurada.");
        }
        $this->apiKey = $apiKey;
    }

    /**
     * Llama al endpoint /v1/responses con un cuerpo JSON arbitrario.
     */
    public function callResponses(array $payload): array
    {
        $ch = curl_init(OPENAI_RESPONSES_URL);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Error al llamar a OpenAI: $error");
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException("Respuesta HTTP $statusCode de OpenAI: $result");
        }

        return json_decode($result, true);
    }

    /**
     * (Opcional) Sube un archivo PDF a /v1/files para referenciarlo después.
     */
    public function uploadFile(string $filePath, string $purpose = 'assistants'): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("El archivo no existe: $filePath");
        }

        $ch = curl_init(OPENAI_FILES_URL);

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $postFields = [
            'file'    => new \CURLFile($filePath, 'application/pdf', basename($filePath)),
            'purpose' => $purpose,
        ];

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Error al subir archivo a OpenAI: $error");
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException("Respuesta HTTP $statusCode al subir archivo: $result");
        }

        return json_decode($result, true);
    }
    /**
     * Helper genérico para peticiones CURL.
     */
    private function makeRequest(string $url, string $method = 'GET', array $data = []): array
    {
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'OpenAI-Beta: assistants=v2' // Importante para Assistants API v2
        ];

        $options = [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if (!empty($data)) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        }

        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Error CURL ($url): $error");
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $msg = $response['error']['message'] ?? $result;
            throw new \RuntimeException("OpenAI Error ($statusCode): $msg");
        }

        return $response;
    }

    public function createAssistant(string $name, string $instructions, string $model): array
    {
        $payload = [
            'name' => $name,
            'instructions' => $instructions,
            'model' => $model,
            'tools' => [['type' => 'code_interpreter']]
        ];
        return $this->makeRequest('https://api.openai.com/v1/assistants', 'POST', $payload);
    }

    public function createThread(array $messages = []): array
    {
        $payload = [];
        if (!empty($messages)) {
            $payload['messages'] = $messages;
        }
        return $this->makeRequest('https://api.openai.com/v1/threads', 'POST', $payload);
    }

    public function createRun(string $threadId, string $assistantId, string $model = null): array
    {
        $payload = [
            'assistant_id' => $assistantId,
        ];
        if ($model) {
            $payload['model'] = $model;
        }
        return $this->makeRequest("https://api.openai.com/v1/threads/$threadId/runs", 'POST', $payload);
    }

    public function getRun(string $threadId, string $runId): array
    {
        return $this->makeRequest("https://api.openai.com/v1/threads/$threadId/runs/$runId", 'GET');
    }

    public function listMessages(string $threadId): array
    {
        return $this->makeRequest("https://api.openai.com/v1/threads/$threadId/messages?order=desc&limit=1", 'GET');
    }
}
