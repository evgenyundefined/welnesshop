<?php

namespace App\Actions\Admin\Site;

use App\Actions\Site\LoadSiteSettings;
use App\Models\SiteSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;

class SaveBannerImage
{
    public function __construct(
        private readonly LoadSiteSettings $loadSiteSettings,
        private readonly Filesystem $disk,
    ) {}

    public function __invoke(UploadedFile $file): SiteSetting
    {
        $settings = ($this->loadSiteSettings)();
        $previous = $settings->banner_image_path;

        $settings->forceFill(['banner_image_path' => $this->disk->putFile('site', $file)])->save();

        // Replaced, not accumulated: only one banner is ever shown.
        if ($previous !== null) {
            $this->disk->delete($previous);
        }

        return $settings;
    }
}
