<?php

namespace App\Actions\Admin\Site;

use App\Actions\Site\LoadSiteSettings;
use App\Models\SiteSetting;

class SaveSiteSettings
{
    public function __construct(private readonly LoadSiteSettings $loadSiteSettings) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): SiteSetting
    {
        $settings = ($this->loadSiteSettings)();

        $settings->fill($attributes)->save();

        return $settings;
    }
}
