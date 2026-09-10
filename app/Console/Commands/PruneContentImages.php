<?php

namespace App\Console\Commands;

use App\Actions\Admin\Content\FindUnreferencedContentImages;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;

class PruneContentImages extends Command
{
    protected $signature = 'content:prune {--force : Delete the files instead of listing them}';

    protected $description = 'Найти картинки редактора, на которые не ссылается ни один текст';

    public function handle(FindUnreferencedContentImages $findUnreferencedContentImages, Filesystem $disk): int
    {
        $orphans = $findUnreferencedContentImages();

        if ($orphans->isEmpty()) {
            $this->info('Лишних картинок нет.');

            return self::SUCCESS;
        }

        $this->table(
            ['Файл', 'Размер'],
            $orphans->map(fn (string $path): array => [$path, $this->humanSize($disk->size($path))])->all(),
        );

        if (! $this->option('force')) {
            $this->comment('Ничего не удалено. Проверьте список и повторите с --force.');
            $this->comment('Картинка, загруженная в черновик и ещё не сохранённая, тоже попадает сюда.');

            return self::SUCCESS;
        }

        $disk->delete($orphans->all());

        $this->info("Удалено файлов: {$orphans->count()}.");

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        return $bytes < 1024 * 1024
            ? round($bytes / 1024).' КБ'
            : round($bytes / 1024 / 1024, 1).' МБ';
    }
}
