<?php

namespace App\Actions\Admin\Content;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class PurgeOrphanedContentImages
{
    private const DIRECTORY = 'content';

    private const SAFE_NAME = '/^[A-Za-z0-9._-]+$/';

    public function __construct(private readonly Filesystem $disk) {}

    /**
     * Pictures uploaded from the editor belong to the text they were pasted
     * into, so they are dropped once no saved text mentions them any more.
     * Only the texts that just changed are examined: an upload that has not
     * been saved into a body yet is invisible here and survives.
     */
    public function __invoke(string ...$replacedHtml): void
    {
        $orphans = $this->pathsIn(...$replacedHtml)->diff($this->referencedPaths());

        foreach ($orphans as $path) {
            $this->disk->delete($path);
        }
    }

    /** @return Collection<int, string> */
    private function referencedPaths(): Collection
    {
        $texts = Page::query()->pluck('body')->all();

        foreach (SiteSetting::query()->get() as $settings) {
            $texts = [...$texts, ...array_values(array_filter($settings->getAttributes(), is_string(...)))];
        }

        return $this->pathsIn(...$texts);
    }

    /** @return Collection<int, string> */
    private function pathsIn(?string ...$html): Collection
    {
        $prefix = preg_quote($this->disk->url(self::DIRECTORY.'/'), '~');

        preg_match_all('~'.$prefix.'([^"\'\s<>?#]+)~', implode(' ', array_filter($html)), $matches);

        return collect($matches[1])
            ->filter(fn (string $name): bool => preg_match(self::SAFE_NAME, $name) === 1)
            ->map(fn (string $name): string => self::DIRECTORY.'/'.$name)
            ->unique()
            ->values();
    }
}
