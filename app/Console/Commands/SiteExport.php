<?php

namespace App\Console\Commands;

use App\Support\SiteTransfer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('site:export {file : Куда сохранить JSON} {--connection= : Подключение к базе (например source)}')]
#[Description('Сохраняет все данные сайта в JSON-файл для site:pull --file (содержит хеши паролей, не публикуйте его)')]
class SiteExport extends Command
{
    public function handle(): int
    {
        $export = SiteTransfer::export($this->option('connection'));
        file_put_contents($this->argument('file'), json_encode($export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @chmod($this->argument('file'), 0600);
        $this->info('Сохранено: '.collect($export['tables'])->map(fn ($rows, $t) => "{$t}=".count($rows))->implode(', '));

        return self::SUCCESS;
    }
}
