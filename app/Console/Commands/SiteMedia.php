<?php

namespace App\Console\Commands;

use App\Support\SiteTransfer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('site:media')]
#[Description('Скачивает файлы из Vercel Blob на локальный диск и меняет ссылки в базе')]
class SiteMedia extends Command
{
    public function handle(): int
    {
        $stats = SiteTransfer::localizeMedia(fn (string $line) => $this->line('  '.$line));
        $this->info("Скачано файлов: {$stats['files']}, обновлено записей: {$stats['rows']}, ошибок: {$stats['failed']}");

        return $stats['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
