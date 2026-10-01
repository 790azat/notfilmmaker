<?php

namespace App\Console\Commands;

use App\Support\SiteTransfer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('site:pull {--file= : Взять данные из JSON-файла site:export вместо базы SOURCE_DATABASE_URL} {--media : Сразу скачать файлы из Vercel Blob} {--force : Не спрашивать подтверждение}')]
#[Description('Переносит работы, настройки, пользователей и чаты из старой базы в эту')]
class SitePull extends Command
{
    public function handle(): int
    {
        $file = $this->option('file');
        if (! $file && ! config('database.connections.source.url')) {
            $this->error('Укажите SOURCE_DATABASE_URL (строка подключения к старой базе Neon) или --file=выгрузка.json');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Все данные в этой базе будут заменены данными из старой. Продолжить?')) {
            return self::FAILURE;
        }

        $export = $file ? json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR) : SiteTransfer::export('source');
        $counts = SiteTransfer::import($export);
        $this->table(['Таблица', 'Строк'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all());

        if ($this->option('media')) {
            return $this->call('site:media');
        }

        return self::SUCCESS;
    }
}
