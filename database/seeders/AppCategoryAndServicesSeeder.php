<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Seeds the App category with two services:
 * 1. App Download + Positive Review (App Store / Google Play)
 * 2. App Download + Custom Review + Star (1-5)
 */
class AppCategoryAndServicesSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(
            ['name' => 'App'],
            ['link_driver' => 'app', 'status' => true]
        );
        $category->update(['link_driver' => 'app', 'status' => true]);

        Service::firstOrCreate(
            [
                'category_id' => $category->id,
                'template_key' => 'app_download_positive_review',
            ],
            [
                'name' => 'App Download + Positive Review',
                'description' => 'Download the app and leave a positive review (App Store / Google Play)',
                'mode' => 'manual',
                'service_type' => 'default',
                'target_type' => 'app',
                'rate_per_1000' => 5.00,
                'min_quantity' => 1,
                'max_quantity' => 10000,
                'is_active' => true,
                'priority' => 50,
            ]
        );

        Service::firstOrCreate(
            [
                'category_id' => $category->id,
                'template_key' => 'app_download_custom_review_star',
            ],
            [
                'name' => 'App Download + Custom Review + Star (1-5)',
                'description' => 'Download the app, leave a custom review, and set a star rating (1-5)',
                'mode' => 'manual',
                'service_type' => 'default',
                'target_type' => 'app',
                'rate_per_1000' => 7.00,
                'min_quantity' => 1,
                'max_quantity' => 10000,
                'is_active' => true,
                'priority' => 50,
            ]
        );
    }
}
