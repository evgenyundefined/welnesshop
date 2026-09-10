<?php

namespace App\Actions\Admin\Site;

use App\Actions\Site\LoadSiteSettings;
use App\Models\SiteSetting;
use Illuminate\Contracts\Filesystem\Filesystem;

class RemoveBannerImage
{
    public function __construct(
        private readonly LoadSiteSettings $loadSiteSettings,
        private readonly Filesystem $disk,
    ) {}

    public function __invoke(): SiteSetting
    {
        $settings = ($this->loadSiteSettings)();
        $path = $settings->banner_image_path;

        // Without a picture the banner has nothing to stand on, so it is
        // switched off rather than left half-configured.
        $settings->forceFill(['banner_image_path' => null, 'banner_enabled' => false])->save();

        if ($path !== null) {
            $this->disk->delete($path);
        }

        return $settings;
    }
}
