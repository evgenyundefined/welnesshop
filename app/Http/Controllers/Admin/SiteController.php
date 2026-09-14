<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Site\DescribeIntegrations;
use App\Actions\Admin\Site\RemoveBannerImage;
use App\Actions\Admin\Site\RemoveLogoImage;
use App\Actions\Admin\Site\SaveBannerImage;
use App\Actions\Admin\Site\SaveLogoImage;
use App\Actions\Admin\Site\SaveSiteSettings;
use App\Actions\Site\LoadSiteSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSiteSettingsRequest;
use App\Http\Requests\Admin\UploadBannerImageRequest;
use App\Http\Requests\Admin\UploadLogoImageRequest;
use App\Http\Resources\Admin\SiteSettingResource;

class SiteController extends Controller
{
    public function show(LoadSiteSettings $loadSiteSettings, DescribeIntegrations $describeIntegrations): SiteSettingResource
    {
        return (new SiteSettingResource($loadSiteSettings()))->additional(['integrations' => $describeIntegrations()]);
    }

    public function update(SaveSiteSettingsRequest $request, SaveSiteSettings $saveSiteSettings): SiteSettingResource
    {
        return new SiteSettingResource($saveSiteSettings($request->settings()));
    }

    public function storeBanner(UploadBannerImageRequest $request, SaveBannerImage $saveBannerImage): SiteSettingResource
    {
        return new SiteSettingResource($saveBannerImage($request->bannerImage()));
    }

    public function destroyBanner(RemoveBannerImage $removeBannerImage): SiteSettingResource
    {
        return new SiteSettingResource($removeBannerImage());
    }

    public function storeLogo(UploadLogoImageRequest $request, SaveLogoImage $saveLogoImage): SiteSettingResource
    {
        return new SiteSettingResource($saveLogoImage($request->logoImage()));
    }

    public function destroyLogo(RemoveLogoImage $removeLogoImage): SiteSettingResource
    {
        return new SiteSettingResource($removeLogoImage());
    }
}
