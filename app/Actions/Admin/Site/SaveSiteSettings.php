<?php

namespace App\Actions\Admin\Site;

use App\Actions\Admin\Content\PurgeOrphanedContentImages;
use App\Actions\Site\LoadSiteSettings;
use App\Models\SiteSetting;

class SaveSiteSettings
{
    public function __construct(
        private readonly LoadSiteSettings $loadSiteSettings,
        private readonly PurgeOrphanedContentImages $purgeOrphanedContentImages,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): SiteSetting
    {
        $settings = ($this->loadSiteSettings)();
        $replaced = array_filter($settings->getAttributes(), is_string(...));

        $settings->fill($attributes)->save();

        ($this->purgeOrphanedContentImages)(...array_values($replaced));

        return $settings;
    }
}
