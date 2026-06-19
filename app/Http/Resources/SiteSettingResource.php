<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_title' => $this->site_title,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_url,
            'favicon_path' => $this->favicon_path,
            'favicon_url' => $this->favicon_url,
            'contact_numbers' => $this->contact_numbers ?? [],
            'email' => $this->email,
            'address' => $this->address,
            'social_links' => [
                'instagram' => data_get($this->social_links, 'instagram'),
                'facebook' => data_get($this->social_links, 'facebook'),
                'tiktok' => data_get($this->social_links, 'tiktok'),
            ],
            'forms_recipient_email' => $this->forms_recipient_email,
            'meta' => [
                'title' => data_get($this->meta, 'title'),
                'description' => data_get($this->meta, 'description'),
                'keywords' => data_get($this->meta, 'keywords', []),
            ],
            'google_analytics_pixel' => $this->google_analytics_pixel,
            'meta_pixel' => $this->meta_pixel,
            'loyalty' => [
                'first_purchase_discount_enabled' => (bool) data_get($this->loyalty, 'first_purchase_discount_enabled', false),
                'first_purchase_discount_percentage' => (float) data_get($this->loyalty, 'first_purchase_discount_percentage', 0),
                'cashback_enabled' => (bool) data_get($this->loyalty, 'cashback_enabled', false),
                'cashback_earn_percentage' => (float) data_get($this->loyalty, 'cashback_earn_percentage', 0),
                'cashback_redeem_enabled' => (bool) data_get($this->loyalty, 'cashback_redeem_enabled', false),
                'cashback_max_redeem_percentage' => (float) data_get($this->loyalty, 'cashback_max_redeem_percentage', 100),
            ],
            'og_image_path' => $this->og_image_path,
            'og_image_url' => $this->og_image_url,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
