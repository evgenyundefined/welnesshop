<?php

namespace App\Actions\Admin\Site;

use App\Actions\Site\LoadSiteSettings;
use App\Models\SiteSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;

class SaveLogoImage
{
    public function __construct(
        private readonly LoadSiteSettings $loadSiteSettings,
        private readonly Filesystem $disk,
    ) {}

    public function __invoke(UploadedFile $file): SiteSetting
    {
        $settings = ($this->loadSiteSettings)();
        $previous = $settings->logo_image_path;

        $settings->forceFill(['logo_image_path' => $this->disk->putFile('site', $file)])->save();

        // Replaced, not accumulated: only one logo is ever shown.
        if ($previous !== null) {
            $this->disk->delete($previous);
        }

        return $settings;
    }
}
