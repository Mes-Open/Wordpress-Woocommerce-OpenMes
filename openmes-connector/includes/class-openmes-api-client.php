<?php
/**
 * HTTP client for the OpenMES REST API.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OpenMES_API_Client
{
    private string $base_url;
    private string $token;

    public function __construct(string $base_url, string $token)
    {
        $this->base_url = rtrim($base_url, '/');
        $this->token    = $token;
    }

    public static function from_settings(): ?self
    {
        $opts  = get_option(OpenMES_Settings::OPTION_NAME, []);
        $url   = isset($opts['api_url']) ? rtrim((string) $opts['api_url'], '/') : '';
        $token = isset($opts['api_token']) ? (string) $opts['api_token'] : '';

        if ($url === '' || $token === '') {
            return null;
        }

        return new self($url, $token);
    }

    /**
     * @return array|null Decoded JSON body or null on failure
     */
    public function create_work_order(array $payload): ?array
    {
        return $this->post('/api/v1/work-orders', $payload);
    }

    /**
     * Fetch production lines from OpenMES.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function fetch_lines(): array
    {
        $response = $this->get('/api/v1/lines');

        if (!is_array($response) || !isset($response['data']) || !is_array($response['data'])) {
            return [];
        }

        $lines = [];
        foreach ($response['data'] as $line) {
            if (!isset($line['id'], $line['name'])) {
                continue;
            }
            $lines[] = [
                'id'   => (int) $line['id'],
                'name' => (string) $line['name'],
            ];
        }
        return $lines;
    }

    private function post(string $path, array $payload): ?array
    {
        $url      = $this->base_url . $path;
        $response = wp_remote_post($url, [
            'timeout' => 10,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ],
            'body'    => wp_json_encode($payload),
        ]);

        return $this->handle_response($response, 'POST', $url);
    }

    private function get(string $path): ?array
    {
        $url      = $this->base_url . $path;
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ]);

        return $this->handle_response($response, 'GET', $url);
    }

    /**
     * @param array|WP_Error $response
     */
    private function handle_response($response, string $method, string $url): ?array
    {
        if (is_wp_error($response)) {
            OpenMES_Logger::error(
                sprintf('HTTP %s error for %s: %s', $method, $url, $response->get_error_message())
            );
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($code >= 400) {
            OpenMES_Logger::error(sprintf(
                'HTTP %s %s — %d — response: %s',
                $method,
                $url,
                $code,
                OpenMES_Logger::truncate($body)
            ));

            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $decoded['_http_code'] = $code;
                return $decoded;
            }
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
