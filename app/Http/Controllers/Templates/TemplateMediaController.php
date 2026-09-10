<?php

namespace App\Http\Controllers\Templates;

use App\Exceptions\GraphApiException;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\WhatsAppAccount;
use App\Services\Meta\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Resumable Upload API — returns the header_handle used in
 * { "type": "HEADER", "format": "IMAGE|VIDEO|DOCUMENT", "example": { "header_handle": ["4::..."] } }
 */
class TemplateMediaController extends Controller
{
    public function store(Request $request, TemplateService $templates): JsonResponse
    {
        $data = $request->validate([
            'whatsapp_account_id' => ['required', 'integer', 'exists:whatsapp_accounts,id'],
            'format' => ['required', 'in:IMAGE,VIDEO,DOCUMENT'],
            'file' => ['required', 'file', 'max:16384'],
        ]);

        $account = WhatsAppAccount::findOrFail($data['whatsapp_account_id']);
        Gate::authorize('update', $account);

        $file = $request->file('file');
        $mime = $file->getMimeType();

        $allowed = match ($data['format']) {
            'IMAGE' => ['image/jpeg', 'image/png'],
            'VIDEO' => ['video/mp4'],
            'DOCUMENT' => ['application/pdf'],
        };

        if (! in_array($mime, $allowed, true)) {
            return response()->json(['message' => "{$mime} is not allowed for a {$data['format']} header. Allowed: ".implode(', ', $allowed)], 422);
        }

        try {
            $handle = $templates->for($account)->uploadHeaderMedia(
                file_get_contents($file->getRealPath()),
                $mime,
                $file->getClientOriginalName()
            );
        } catch (GraphApiException $e) {
            return response()->json(['message' => $e->displayMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        MediaAsset::create([
            'workspace_id' => $request->user()->workspace_id,
            'user_id' => $request->user()->id,
            'kind' => MediaAsset::KIND_UPLOAD_HANDLE,
            'media_type' => strtolower($data['format']),
            'upload_handle' => $handle,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'file_size' => $file->getSize(),
        ]);

        return response()->json(['handle' => $handle, 'file_name' => $file->getClientOriginalName()]);
    }
}
