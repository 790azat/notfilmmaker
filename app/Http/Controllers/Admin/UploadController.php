<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Blob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    private const FOLDERS = ['works', 'gallery', 'covers', 'site', 'videos'];

    private const TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif',
        'video/mp4', 'video/webm', 'video/quicktime',
    ];

    /** Локальная загрузка (когда Vercel Blob не подключён). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:'.implode(',', self::TYPES), 'max:512000'],
            'folder' => ['required', 'in:'.implode(',', self::FOLDERS)],
        ]);

        $path = $data['file']->storePublicly($data['folder'], 'public');

        return response()->json(['path' => $path]);
    }

    /**
     * Токен для прямой загрузки из браузера в Vercel Blob (протокол handleUpload из @vercel/blob).
     */
    public function blobToken(Request $request): JsonResponse
    {
        abort_unless(Blob::enabled(), 404);

        $type = $request->input('type');
        abort_unless($type === 'blob.generate-client-token', 400);

        $pathname = (string) $request->input('payload.pathname');
        $folder = Str::before($pathname, '/');
        abort_unless(in_array($folder, self::FOLDERS, true) && preg_match('~^[a-z]+/[a-z0-9-]+\.[a-z0-9]{2,5}$~', $pathname), 422);

        return response()->json([
            'type' => $type,
            'clientToken' => Blob::clientToken($pathname, [
                'allowedContentTypes' => self::TYPES,
                'maximumSizeInBytes' => 500 * 1024 * 1024,
                'addRandomSuffix' => true,
            ]),
        ]);
    }
}
