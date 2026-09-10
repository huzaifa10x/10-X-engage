<?php

namespace App\Services\Meta;

use App\Models\PhoneNumber;

/**
 * Cloud API collection — "Messages" and "Media" folders.
 * All sends go to POST /{{Phone-Number-ID}}/messages with a Messages Object.
 */
class MessagingService
{
    public function __construct(protected GraphClient $client) {}

    public function for(PhoneNumber $phone): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withToken($phone->account->apiToken());

        return $clone;
    }

    /**
     * Send any assembled Messages Object.
     *
     * @return array{messaging_product:string, contacts:array, messages:array}
     */
    public function send(string $phoneNumberId, array $message): array
    {
        return $this->client->post("{$phoneNumberId}/messages", $message);
    }

    /** "Mark Message As Read" — PUT/POST /{{Phone-Number-ID}}/messages { status: read, message_id } */
    public function markAsRead(string $phoneNumberId, string $wamid, bool $typingIndicator = false): array
    {
        $body = ['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => $wamid];

        if ($typingIndicator) {
            $body['typing_indicator'] = ['type' => 'text'];
        }

        return $this->client->post("{$phoneNumberId}/messages", $body);
    }

    /**
     * "Upload Image / Sticker / Audio" — POST /{{Phone-Number-ID}}/media (multipart form-data)
     * fields: messaging_product=whatsapp, file=@... => { "id": "<MEDIA_ID>" }
     */
    public function uploadMedia(string $phoneNumberId, string $contents, string $fileName, string $mime): array
    {
        return $this->client->postMultipart(
            "{$phoneNumberId}/media",
            ['messaging_product' => 'whatsapp', 'type' => $mime],
            'file',
            $contents,
            $fileName,
            $mime,
        );
    }

    /** "Retrieve Media URL" — GET /{{Media-ID}}?phone_number_id= */
    public function mediaUrl(string $mediaId, ?string $phoneNumberId = null): array
    {
        return $this->client->get($mediaId, array_filter(['phone_number_id' => $phoneNumberId]));
    }

    /** "Delete Media" — DELETE /{{Media-ID}}?phone_number_id= */
    public function deleteMedia(string $mediaId, ?string $phoneNumberId = null): array
    {
        return $this->client->delete($mediaId, array_filter(['phone_number_id' => $phoneNumberId]));
    }
}
