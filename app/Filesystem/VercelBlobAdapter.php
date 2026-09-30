<?php

namespace App\Filesystem;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;

/**
 * Flysystem-адаптер для Vercel Blob (публичное или приватное хранилище) поверх его HTTP API,
 * того же, что использует официальный пакет @vercel/blob.
 */
class VercelBlobAdapter implements FilesystemAdapter
{
    private const API_VERSION = '12';

    /**
     * @param  string  $access  public или private (как выбрано при создании хранилища)
     * @param  string|null  $mediaUrl  адрес, через который сайт отдаёт файлы приватного хранилища
     */
    public function __construct(
        private readonly string $token,
        private readonly string $access = 'public',
        private readonly ?string $mediaUrl = null,
        private readonly string $apiUrl = 'https://vercel.com/api/blob',
    ) {}

    /**
     * Прямой адрес файла в хранилище, например https://abc123.private.blob.vercel-storage.com/posters/a.jpg.
     */
    public function blobUrl(string $path): string
    {
        $storeId = strtolower(explode('_', $this->token)[3] ?? '');

        return 'https://'.$storeId.'.'.$this->access.'.blob.vercel-storage.com/'.ltrim($path, '/');
    }

    /**
     * Скачивает файл потоком (для приватного хранилища файлы отдаются через сайт).
     */
    public function fetch(string $path, ?string $range = null): Response
    {
        return Http::withToken($this->token)
            ->withHeaders(array_filter(['Range' => $range]))
            ->withOptions(['stream' => true])
            ->timeout(60)
            ->get($this->blobUrl($path));
    }

    public function fileExists(string $path): bool
    {
        $response = $this->head($path);

        if ($response->successful()) {
            return true;
        }

        if ($response->status() === 404) {
            return false;
        }

        throw UnableToCheckExistence::forLocation($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->list(rtrim($path, '/').'/', 1)['blobs'] !== [];
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->put($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->put($path, stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        $response = Http::withToken($this->token)->timeout(60)->get($this->blobUrl($path));

        if (! $response->successful()) {
            throw UnableToReadFile::fromLocation($path, 'HTTP '.$response->status());
        }

        return $response->body();
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->deleteUrls([$this->blobUrl($path)], $path);
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = rtrim($path, '/').'/';
        $cursor = null;

        do {
            $page = $this->list($prefix, 1000, $cursor);
            $urls = array_column($page['blobs'], 'url');

            if ($urls !== []) {
                $this->deleteUrls($urls, $path);
            }

            $cursor = $page['cursor'] ?? null;
        } while (($page['hasMore'] ?? false) && $cursor);
    }

    public function createDirectory(string $path, Config $config): void
    {
        // В Blob нет папок: они появляются вместе с файлами.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Доступ задаётся для всего хранилища, а не для отдельных файлов.
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, Visibility::PUBLIC);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = trim($path, '/') === '' ? '' : trim($path, '/').'/';
        $cursor = null;

        do {
            $page = $this->list($prefix, 1000, $cursor, $deep ? null : 'folded');

            foreach ($page['folders'] ?? [] as $folder) {
                yield new DirectoryAttributes(rtrim($folder, '/'));
            }

            foreach ($page['blobs'] as $blob) {
                yield $this->attributes($blob);
            }

            $cursor = $page['cursor'] ?? null;
        } while (($page['hasMore'] ?? false) && $cursor);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->put($destination, $this->read($source), $config);
        } catch (\Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function getUrl(string $path): string
    {
        if ($this->access === 'private' && $this->mediaUrl) {
            return rtrim($this->mediaUrl, '/').'/'.ltrim($path, '/');
        }

        return $this->blobUrl($path);
    }

    private function put(string $path, string $contents, Config $config): void
    {
        $mime = $config->get('mimetype')
            ?? (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents)
            ?: 'application/octet-stream';

        $response = $this->request()
            ->withHeaders([
                'x-vercel-blob-access' => $this->access,
                'x-add-random-suffix' => '0',
                'x-allow-overwrite' => '1',
                'x-content-type' => $mime,
            ])
            ->withBody($contents, $mime)
            ->put($this->apiUrl.'/?'.http_build_query(['pathname' => ltrim($path, '/')]));

        if (! $response->successful()) {
            throw UnableToWriteFile::atLocation($path, 'HTTP '.$response->status().': '.$response->body());
        }
    }

    private function head(string $path): Response
    {
        return $this->request()->get($this->apiUrl.'/', ['url' => $this->blobUrl($path)]);
    }

    private function metadata(string $path): FileAttributes
    {
        $response = $this->head($path);

        if (! $response->successful()) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', 'HTTP '.$response->status());
        }

        return $this->attributes($response->json() + ['pathname' => $path]);
    }

    private function attributes(array $blob): FileAttributes
    {
        return new FileAttributes(
            $blob['pathname'],
            $blob['size'] ?? null,
            Visibility::PUBLIC,
            isset($blob['uploadedAt']) ? strtotime($blob['uploadedAt']) : null,
            $blob['contentType'] ?? null,
        );
    }

    private function list(string $prefix, int $limit, ?string $cursor = null, ?string $mode = null): array
    {
        $response = $this->request()->get($this->apiUrl.'/', array_filter([
            'prefix' => $prefix,
            'limit' => $limit,
            'cursor' => $cursor,
            'mode' => $mode,
        ]));

        if (! $response->successful()) {
            throw UnableToRetrieveMetadata::create($prefix, 'list', 'HTTP '.$response->status());
        }

        return $response->json() + ['blobs' => []];
    }

    private function deleteUrls(array $urls, string $path): void
    {
        $response = $this->request()->post($this->apiUrl.'/delete', ['urls' => $urls]);

        if (! $response->successful()) {
            throw UnableToDeleteFile::atLocation($path, 'HTTP '.$response->status());
        }
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->token)
            ->withHeaders(['x-api-version' => self::API_VERSION])
            ->timeout(60);
    }
}
