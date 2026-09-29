<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meta "Data Deletion Request" callback (App Settings → Basic → Data deletion request URL).
 * Verifies the signed_request with the app secret and answers with a status URL + confirmation code.
 * Scope: the Facebook-login account holder (our client), not their customers' WhatsApp history.
 */
class DataDeletionController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        $signed = (string) $request->input('signed_request', '');
        [$sig, $payload] = array_pad(explode('.', $signed, 2), 2, '');

        $expected = hash_hmac('sha256', $payload, (string) config('whatsapp.app.secret'), true);
        $given = base64_decode(strtr($sig, '-_', '+/'));

        if ($payload === '' || ! hash_equals($expected, (string) $given)) {
            return response()->json(['message' => 'Invalid signed_request'], 400);
        }

        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true) ?: [];
        $userId = (string) ($data['user_id'] ?? '');
        $code = Str::lower(Str::random(12));

        Log::info('Meta data deletion request received', ['user_id' => $userId, 'confirmation_code' => $code]);
        // Actual erasure of Facebook-login-linked data is a manual/ops step today; the code is logged for follow-up.

        return response()->json([
            'url' => route('deletion.status', ['code' => $code]),
            'confirmation_code' => $code,
        ]);
    }

    public function status(Request $request): Response
    {
        return Inertia::render('user-data-deletion', ['code' => $request->query('code')]);
    }
}
