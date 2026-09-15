<?php

namespace App\Seo;

final readonly class PageMeta
{
    /** @param ?array<string, mixed> $structuredData */
    public function __construct(
        public string $title,
        public string $siteTitle,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $imageUrl = null,
        public bool $indexable = true,
        public ?array $structuredData = null,
    ) {}
}
