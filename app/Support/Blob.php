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

    /** Загрузка с сервера (для импорта). Возвращает публичную ссылку. */
    public static function put(string $pathname, string $contents, string $mime): string
    {
        $response = Http::withToken(static::token())
            ->withHeaders([
                'x-api-version' => '12',
                'x-vercel-blob-access' => 'public',
                'x-add-random-suffix' => '0',
                'x-content-type' => $mime,
            ])
            ->timeout(60)
            ->withBody($contents, $mime)
            ->put('https://vercel.com/api/blob/?'.http_build_query(['pathname' => $pathname]));

        if (! $response->successful() || ! $response->json('url')) {
            throw new \RuntimeException('Blob upload failed: HTTP '.$response->status());
        }

        return $response->json('url');
    }
}
