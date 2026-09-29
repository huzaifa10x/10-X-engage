<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\Meta\MessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Meta only keeps inbound media for a short time and its download URLs expire in minutes,
 * so we copy every inbound file (and our own uploads) into our storage and serve it from there.
 */
class DownloadInboundMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $assetId) {}

    public function handle(MessagingService $messaging): void
    {
        $asset = MediaAsset::with('phoneNumber.account')->find($this->assetId);
        if (! $asset || $asset->isStored() || ! $asset->meta_media_id || ! $asset->phoneNumber) {
            return;
        }

        try {
            $result = $messaging->for($asset->phoneNumber)->downloadMedia($asset->meta_media_id, $asset->phoneNumber->phone_number_id);
        } catch (\Throwable $e) {
            $asset->update(['download_error' => $e->getMessage()]);
            throw $e;
        }

        $mime = $result['meta']['mime_type'] ?? $asset->mime_type ?? 'application/octet-stream';
        $ext = self::extension($mime, $asset->file_name);
        $path = sprintf('media/%d/%s/%s.%s', $asset->workspace_id, now()->format('Y/m'), Str::uuid(), $ext);
        $disk = config('whatsapp.media.disk', 'local');

        Storage::disk($disk)->put($path, $result['contents']);

        $asset->update([
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime_type' => $mime,
            'file_size' => $result['meta']['file_size'] ?? strlen($result['contents']),
            'sha256' => $result['meta']['sha256'] ?? hash('sha256', $result['contents']),
            'downloaded_at' => now(),
            'download_error' => null,
            'response' => $result['meta'],
        ]);
    }

    public static function extension(string $mime, ?string $fileName = null): string
    {
        if ($fileName && ($ext = pathinfo($fileName, PATHINFO_EXTENSION))) {
            return strtolower(preg_replace('/[^a-z0-9]/i', '', $ext));
        }

        return match (strtolower(explode(';', $mime)[0])) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
            'video/mp4' => 'mp4', 'video/3gpp' => '3gp',
            'audio/ogg', 'audio/ogg; codecs=opus' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/aac' => 'aac', 'audio/amr' => 'amr',
            'application/pdf' => 'pdf', 'text/plain' => 'txt',
            'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            default => 'bin',
        };
    }
}
