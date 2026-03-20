<?php

namespace App\Services\Hubspot;

use App\Models\Event;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class HubspotFilePropertyService
{
    public function hydrateFileProperties(Event $event, array $payload): array
    {
        $fileProperties = $event->properties()
            ->where('type', 'file')
            ->get(['key', 'name']);

        if ($fileProperties->isEmpty()) {
            return $payload;
        }

        $attachments = [];
        $errors = [];

        foreach ($fileProperties as $property) {
            $source = Arr::get($payload, $property->key);
            if (! is_string($source) || $source === '') {
                continue;
            }

            if (! str_starts_with($source, 'http://') && ! str_starts_with($source, 'https://')) {
                continue;
            }

            try {
                $response = Http::timeout(15)->get($source);
                if (! $response->ok()) {
                    $errors[] = [
                        'property_key' => $property->key,
                        'url' => $source,
                        'status_code' => $response->status(),
                    ];
                    continue;
                }

                $binary = $response->body();
                $filename = basename(parse_url($source, PHP_URL_PATH) ?: $property->key);

                $attachments[$property->key] = [
                    'filename' => $filename,
                    'mime_type' => $response->header('content-type'),
                    'content_base64' => base64_encode($binary),
                    'size_bytes' => strlen($binary),
                    'source_url' => $source,
                ];
            } catch (\Throwable $exception) {
                $errors[] = [
                    'property_key' => $property->key,
                    'url' => $source,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        if (! empty($attachments)) {
            $payload['_file_attachments'] = $attachments;
        }

        if (! empty($errors)) {
            $payload['_file_attachment_errors'] = $errors;
        }

        return $payload;
    }
}
