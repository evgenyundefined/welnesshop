<?php

namespace App\Actions\Admin\Site;

use App\Actions\Site\LoadSiteSettings;
use App\Models\SiteSetting;
use Illuminate\Contracts\Filesystem\Filesystem;

class RemoveLogoImage
{
    public function __construct(
        private readonly LoadSiteSettings $loadSiteSettings,
        private readonly Filesystem $disk,
    ) {}

    public function __invoke(): SiteSetting
    {
        $settings = ($this->loadSiteSettings)();
        $path = $settings->logo_image_path;

        $settings->forceFill(['logo_image_path' => null])->save();

        if ($path !== null) {
            $this->disk->delete($path);
        }

        return $settings;
    }
}
