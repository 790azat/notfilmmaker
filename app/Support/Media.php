<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class Media
{
    /** Файлы могут лежать в хранилище (относительный путь) или по полной ссылке (Vercel Blob, внешняя картинка). */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public static function delete(?string $path): void
    {
        if (! $path) {
            return;
        }
        try {
            if (Str::startsWith($path, ['http://', 'https://'])) {
                if (Blob::enabled() && str_contains($path, '.blob.vercel-storage.com/')) {
                    Blob::delete($path);
                }

                return;
            }
            Storage::disk('public')->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function isVideo(string $path): bool
    {
        return (bool) preg_match('/\.(mp4|webm|mov|m4v)(\?.*)?$/i', $path);
    }
}
