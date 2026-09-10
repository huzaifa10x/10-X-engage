<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Meta webhook endpoint (App Dashboard > WhatsApp > Configuration > Callback URL).
 *
 *  GET  /webhooks/whatsapp  – verification handshake (hub.mode, hub.verify_token, hub.challenge)
 *  POST /webhooks/whatsapp  – notifications { object: "whatsapp_business_account", entry: [...] }
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        // PHP converts "hub.mode" query keys to "hub_mode"; accept both spellings.
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode === 'subscribe' && $token && hash_equals((string) config('whatsapp.webhook.verify_token'), (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (config('whatsapp.webhook.verify_signature')) {
            $this->assertValidSignature($raw, $signature);
        }

        $payload = json_decode($raw, true) ?: [];

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('Ignored', 200);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $event = WebhookEvent::create([
                    'object' => $payload['object'],
                    'waba_id' => (string) ($entry['id'] ?? ''),
                    'field' => $change['field'] ?? null,
                    'signature' => $signature,
                    'payload' => ['entry_id' => $entry['id'] ?? null, 'time' => $entry['time'] ?? null, 'change' => $change],
                ]);

                ProcessWhatsAppWebhook::dispatch($event->id);
            }
        }

        // Meta expects a 200 quickly; all processing happens in the queue.
        return response('EVENT_RECEIVED', 200);
    }

    protected function assertValidSignature(string $raw, ?string $signature): void
    {
        $secret = (string) config('whatsapp.app.secret');

        if ($secret === '' || ! $signature || ! str_starts_with($signature, 'sha256=')) {
            throw new HttpException(401, 'Missing webhook signature.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $raw, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new HttpException(401, 'Invalid webhook signature.');
        }
    }
}
