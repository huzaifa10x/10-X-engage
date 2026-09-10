<?php

namespace App\Services\Meta;

use App\Exceptions\GraphApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for https://graph.facebook.com/{{Version}}/...
 *
 * Every request is authenticated with a Bearer token exactly like the Postman
 * collections ("Authorization: Bearer {{User-Access-Token}}").
 */
class GraphClient
{
    public function __construct(
        protected ?string $token = null,
        protected ?string $version = null,
    ) {
        $this->version ??= config('whatsapp.graph.version');
    }

    public function withToken(?string $token): static
    {
        $clone = clone $this;
        $clone->token = $token;

        return $clone;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function url(string $path): string
    {
        $base = rtrim(config('whatsapp.graph.base_url'), '/');
        $path = ltrim($path, '/');

        // Paths that already start with a version (e.g. "v25.0/...") are used verbatim.
        if (preg_match('/^v\d+\.\d+\//', $path)) {
            return "{$base}/{$path}";
        }

        return "{$base}/{$this->version}/{$path}";
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    public function post(string $path, array $json = [], array $query = []): array
    {
        return $this->send('POST', $path, ['json' => $json, 'query' => $query]);
    }

    public function delete(string $path, array $query = []): array
    {
        return $this->send('DELETE', $path, ['query' => $query]);
    }

    /** Send multipart form-data (used by POST /{phone-number-id}/media). */
    public function postMultipart(string $path, array $fields, string $fileField, string $contents, string $fileName, ?string $mime = null): array
    {
        $request = $this->request();
        $request = $request->attach($fileField, $contents, $fileName, $mime ? ['Content-Type' => $mime] : []);

        $response = $request->post($this->url($path), $fields);

        return $this->handle($response, 'POST', $path);
    }

    /** Send a raw binary body (used by the Resumable Upload API step 2). */
    public function postRaw(string $path, string $contents, array $headers = []): array
    {
        $response = $this->request()
            ->withHeaders($headers)
            ->withBody($contents, 'application/octet-stream')
            ->post($this->url($path));

        return $this->handle($response, 'POST', $path);
    }

    protected function send(string $method, string $path, array $options): array
    {
        $request = $this->request();

        if (! empty($options['query'])) {
            $request = $request->withQueryParameters($options['query']);
        }

        $response = match ($method) {
            'GET' => $request->get($this->url($path)),
            'DELETE' => $request->delete($this->url($path)),
            default => ! empty($options['json'])
                ? $request->asJson()->post($this->url($path), $options['json'])
                : $request->post($this->url($path)),
        };

        return $this->handle($response, $method, $path);
    }

    protected function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout(config('whatsapp.graph.timeout', 30))
            ->retry(2, 500, fn ($e, $req) => $e instanceof \Illuminate\Http\Client\ConnectionException, throw: false);

        if ($this->token) {
            $request = $request->withToken($this->token);
        }

        return $request;
    }

    protected function handle(Response $response, string $method, string $path): array
    {
        if ($response->failed()) {
            $exception = GraphApiException::fromResponse($response);

            Log::warning('Graph API error', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'error' => $exception->toArray(),
            ]);

            throw $exception;
        }

        $json = $response->json();

        // Some endpoints (e.g. resumable upload step 2) return a plain JSON object; keep arrays as is.
        return is_array($json) ? $json : ['raw' => $response->body()];
    }
}
