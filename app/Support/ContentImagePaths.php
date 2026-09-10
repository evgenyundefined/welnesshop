<?php

namespace App\Support;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;

final readonly class ContentImagePaths
{
    public const DIRECTORY = 'content';

    private const SAFE_NAME = '/^[A-Za-z0-9._-]+$/';

    public function __construct(private Filesystem $disk) {}

    /**
     * Only a plain file name is accepted, so a body crafted with ".." cannot
     * name a file outside the directory the editor uploads into.
     *
     * @return Collection<int, string>
     */
    public function in(?string ...$html): Collection
    {
        $prefix = preg_quote($this->disk->url(self::DIRECTORY.'/'), '~');

        preg_match_all('~'.$prefix.'([^"\'\s<>?#]+)~', implode(' ', array_filter($html)), $matches);

        return collect($matches[1])
            ->filter(fn (string $name): bool => preg_match(self::SAFE_NAME, $name) === 1)
            ->map(fn (string $name): string => self::DIRECTORY.'/'.$name)
            ->unique()
            ->values();
    }

    /** @return Collection<int, string> */
    public function referenced(): Collection
    {
        $texts = Page::query()->pluck('body')->all();

        foreach (SiteSetting::query()->get() as $settings) {
            $texts = [...$texts, ...array_values(array_filter($settings->getAttributes(), is_string(...)))];
        }

        return $this->in(...$texts);
    }

    /** @return Collection<int, string> */
    public function stored(): Collection
    {
        return collect($this->disk->files(self::DIRECTORY))->values();
    }
}
