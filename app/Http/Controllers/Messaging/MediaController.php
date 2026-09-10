<?php

namespace App\Http\Controllers\Messaging;

use App\Exceptions\GraphApiException;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\PhoneNumber;
use App\Services\Meta\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * POST /{{Phone-Number-ID}}/media — uploads a file and returns the Cloud API media ID
 * so it can be referenced as { "image": { "id": "<MEDIA_ID>" } } etc.
 */
class MediaController extends Controller
{
    public function store(Request $request, MessagingService $messaging): JsonResponse
    {
        $data = $request->validate([
            'phone_number_id' => ['required', 'integer', 'exists:phone_numbers,id'],
            'media_type' => ['required', 'in:image,video,audio,document,sticker'],
            'file' => ['required', 'file'],
        ]);

        $phone = PhoneNumber::with('account')->findOrFail($data['phone_number_id']);
        Gate::authorize('update', $phone->account);

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $type = $data['media_type'];

        $allowed = config("whatsapp.media.mime_types.{$type}", []);
        $limit = config("whatsapp.media.limits.{$type}", 0);

        if ($allowed && ! in_array($mime, $allowed, true)) {
            return response()->json(['message' => "{$mime} is not a supported {$type} type. Allowed: ".implode(', ', $allowed)], 422);
        }
        if ($limit && $file->getSize() > $limit) {
            return response()->json(['message' => ucfirst($type).' files must be smaller than '.round($limit / 1048576).' MB.'], 422);
        }

        try {
            $result = $messaging->for($phone)->uploadMedia(
                $phone->phone_number_id,
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName(),
                $mime
            );
        } catch (GraphApiException $e) {
            return response()->json(['message' => $e->displayMessage()], 422);
        }

        $asset = MediaAsset::create([
            'workspace_id' => $request->user()->workspace_id,
            'phone_number_id' => $phone->id,
            'user_id' => $request->user()->id,
            'kind' => MediaAsset::KIND_MEDIA_ID,
            'media_type' => $type,
            'meta_media_id' => $result['id'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'file_size' => $file->getSize(),
            'response' => $result,
        ]);

        return response()->json([
            'id' => $asset->id,
            'media_id' => $asset->meta_media_id,
            'file_name' => $asset->file_name,
            'mime_type' => $asset->mime_type,
        ]);
    }
}
