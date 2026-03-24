<?php

return [
    /*
    |--------------------------------------------------------------------------
    | App Service Templates
    |--------------------------------------------------------------------------
    |
    | Templates for app store services (Apple App Store, Google Play).
    | Used for app download/install and app review/feedback tasks.
    |
    */

    // ---- Service 1: App Download + Positive Review (App Store / Google Play) ----
    'app_download_positive_review_star' => [
        'label' => 'App Download + Positive Review + Star (1-5)',
        'action' => 'download_positive_review_star',
        'policy_key' => 'download_positive_review',
        'allowed_link_kinds' => ['app'],
        'mode' => 'combo',
        'steps' => ['download', 'positive_review'],
        'accepts_star_rating' => true,
        'default_priority' => 50,
    ],

    // Alias for app_download_positive_review_star (used by seeder)
    'app_download_positive_review' => [
        'label' => 'App Download + Positive Review',
        'action' => 'download_positive_review_star',
        'policy_key' => 'download_positive_review',
        'allowed_link_kinds' => ['app'],
        'mode' => 'combo',
        'steps' => ['download', 'positive_review'],
        'accepts_star_rating' => true,
        'default_priority' => 50,
    ],

    // ---- Service 2: App Download + Custom Review + Star (1-5) ----
    'app_download_custom_review_star' => [
        'label' => 'App Download + Custom Review + Star (1-5)',
        'action' => 'download_custom_review_star',
        'policy_key' => 'download_custom_review_star',
        'allowed_link_kinds' => ['app'],
        'mode' => 'combo',
        'steps' => ['download', 'custom_review'],
        'accepts_review_comments' => true,
        'accepts_star_rating' => true,
        'default_priority' => 50,
    ],
];
