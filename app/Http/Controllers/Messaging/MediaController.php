<?php

namespace App\Http\Controllers\Messaging;

use App\Exceptions\GraphApiException;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\PhoneNumber;
use App\Services\Meta\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Jobs\DownloadInboundMedia;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Keep our own copy so the inbox can display what we sent (Meta media expires).
        $disk = config('whatsapp.media.disk', 'local');
        $path = sprintf('media/%d/%s/%s.%s', $request->user()->workspace_id, now()->format('Y/m'), Str::uuid(), DownloadInboundMedia::extension($mime, $file->getClientOriginalName()));
        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        $asset = MediaAsset::create([
            'workspace_id' => $request->user()->workspace_id,
            'phone_number_id' => $phone->id,
            'user_id' => $request->user()->id,
            'kind' => MediaAsset::KIND_MEDIA_ID,
            'direction' => 'outbound',
            'media_type' => $type,
            'meta_media_id' => $result['id'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'file_size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'response' => $result,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'downloaded_at' => now(),
        ]);

        return response()->json([
            'id' => $asset->id,
            'media_id' => $asset->meta_media_id,
            'file_name' => $asset->file_name,
            'mime_type' => $asset->mime_type,
            'url' => $asset->url(),
        ]);
    }

    /** Stream a stored media file to an authenticated user of the same workspace. */
    public function show(Request $request, MediaAsset $asset): StreamedResponse|Response
    {
        abort_unless($asset->workspace_id === $request->user()->workspace_id, 403);
        abort_unless($asset->isStored(), 404);

        $disk = Storage::disk($asset->storage_disk ?? config('whatsapp.media.disk', 'local'));
        abort_unless($disk->exists($asset->storage_path), 404);

        $download = $request->boolean('download') || ! in_array($asset->media_type, ['image', 'video', 'audio', 'sticker'], true);

        return $disk->response($asset->storage_path, $asset->file_name ?? basename($asset->storage_path), [
            'Content-Type' => $asset->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
        ], $download ? 'attachment' : 'inline');
    }
}
