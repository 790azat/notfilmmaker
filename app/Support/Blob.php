<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Vercel Blob: выдача одноразовых токенов для загрузки прямо из браузера
 * (так обходится лимит Vercel в 4,5 МБ на запрос, и можно загружать видео).
 * Формат токена повторяет generateClientTokenFromReadWriteToken из пакета @vercel/blob.
 */
class Blob
{
    public static function token(): ?string
    {
        return config('services.blob.token') ?: null;
    }

    public static function enabled(): bool
    {
        return (bool) static::token();
    }

    public static function clientToken(string $pathname, array $options = []): string
    {
        $token = static::token();
        $storeId = explode('_', $token)[3] ?? '';

        $payload = base64_encode(json_encode(array_merge($options, [
            'pathname' => $pathname,
            'validUntil' => (int) (microtime(true) * 1000) + 3600 * 1000,
        ]), JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', $payload, $token);

        return 'vercel_blob_client_'.$storeId.'_'.base64_encode($signature.'.'.$payload);
    }

    public static function delete(string $url): void
    {
        Http::withToken(static::token())
            ->withHeaders(['x-api-version' => '12'])
            ->timeout(30)
            ->post('https://vercel.com/api/blob/delete', ['urls' => [$url]]);
    }
}
