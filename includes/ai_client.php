<?php
/**
 * RealEstateAI — Flask AI prediction API client.
 */
declare(strict_types=1);

if (!defined('AI_API_BASE_URL')) {
    define('AI_API_BASE_URL', 'http://127.0.0.1:5000');
}

if (!function_exists('ai_api_predict')) {
    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   ok: bool,
     *   status: string,
     *   predicted_price_lkr?: float,
     *   predicted_price_formatted?: string,
     *   model_version?: string,
     *   error?: string,
     *   details?: list<string>
     * }
     */
    function ai_api_predict(array $payload): array
    {
        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'status' => 'unavailable',
                'error' => 'The AI prediction service is temporarily unavailable. Please try again later.',
            ];
        }

        $url = rtrim(AI_API_BASE_URL, '/') . '/predict';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return [
                'ok' => false,
                'status' => 'invalid_request',
                'error' => 'Invalid input data.',
            ];
        }

        $handle = curl_init($url);
        if ($handle === false) {
            return [
                'ok' => false,
                'status' => 'unavailable',
                'error' => 'The AI prediction service is temporarily unavailable. Please try again later.',
            ];
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 15,
        ]);

        $responseBody = curl_exec($handle);
        $curlError = curl_error($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($responseBody === false || $curlError !== '') {
            return [
                'ok' => false,
                'status' => 'unavailable',
                'error' => 'The AI prediction service is temporarily unavailable. Please try again later.',
            ];
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'status' => 'invalid_response',
                'error' => 'The AI prediction service returned an unexpected response. Please try again later.',
            ];
        }

        if ($httpCode === 400) {
            $details = $decoded['details'] ?? [];
            if (!is_array($details)) {
                $details = [];
            }
            return [
                'ok' => false,
                'status' => 'validation',
                'error' => (string) ($decoded['error'] ?? 'Invalid input data.'),
                'details' => array_values(array_map('strval', $details)),
            ];
        }

        if ($httpCode !== 200 || ($decoded['success'] ?? false) !== true) {
            return [
                'ok' => false,
                'status' => 'error',
                'error' => 'The AI prediction service is temporarily unavailable. Please try again later.',
            ];
        }

        $price = $decoded['predicted_price_lkr'] ?? null;
        if (!is_numeric($price)) {
            return [
                'ok' => false,
                'status' => 'invalid_response',
                'error' => 'The AI prediction service returned an unexpected response. Please try again later.',
            ];
        }

        return [
            'ok' => true,
            'status' => 'success',
            'predicted_price_lkr' => (float) $price,
            'predicted_price_formatted' => (string) ($decoded['predicted_price_formatted'] ?? ''),
            'model_version' => (string) ($decoded['model_version'] ?? ''),
        ];
    }
}
