<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * Normalised representation of a Graph API error response:
 * { "error": { "message", "type", "code", "error_subcode", "error_user_title", "error_user_msg", "fbtrace_id", "error_data": { "details" } } }
 */
class GraphApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $graphCode = 0,
        public readonly ?int $subcode = null,
        public readonly ?string $type = null,
        public readonly ?string $userTitle = null,
        public readonly ?string $userMessage = null,
        public readonly ?string $details = null,
        public readonly ?string $fbTraceId = null,
        public readonly int $httpStatus = 0,
        public readonly array $raw = [],
    ) {
        parent::__construct($message, $graphCode);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json() ?? [];
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];

        $message = $error['message'] ?? $response->body() ?: "Graph API request failed with HTTP {$response->status()}";

        return new self(
            message: $message,
            graphCode: (int) ($error['code'] ?? 0),
            subcode: isset($error['error_subcode']) ? (int) $error['error_subcode'] : null,
            type: $error['type'] ?? null,
            userTitle: $error['error_user_title'] ?? null,
            userMessage: $error['error_user_msg'] ?? null,
            details: $error['error_data']['details'] ?? null,
            fbTraceId: $error['fbtrace_id'] ?? null,
            httpStatus: $response->status(),
            raw: is_array($body) ? $body : [],
        );
    }

    /** Message safe to show in the UI. */
    public function displayMessage(): string
    {
        $parts = array_filter([
            $this->userTitle,
            $this->userMessage ?: $this->getMessage(),
            $this->details,
        ]);

        $text = implode(' — ', array_unique($parts));

        return $this->graphCode ? "{$text} (code {$this->graphCode}".($this->subcode ? "/{$this->subcode}" : '').')' : $text;
    }

    public function isTokenError(): bool
    {
        return in_array($this->graphCode, [190, 102, 104], true) || $this->type === 'OAuthException';
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->graphCode,
            'error_subcode' => $this->subcode,
            'type' => $this->type,
            'error_user_title' => $this->userTitle,
            'error_user_msg' => $this->userMessage,
            'details' => $this->details,
            'fbtrace_id' => $this->fbTraceId,
            'http_status' => $this->httpStatus,
        ];
    }
}
