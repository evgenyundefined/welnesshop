<?php

namespace App\Actions\Site;

use App\Models\SiteSetting;

class LoadSiteSettings
{
    /**
     * The settings are a single row created by the migration, so this never
     * has to decide what an absent one would mean.
     */
    public function __invoke(): SiteSetting
    {
        return SiteSetting::query()->sole();
    }
}
