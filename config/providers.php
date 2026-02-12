<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Socpanel Provider
    |--------------------------------------------------------------------------
    |
    | Configuration for polling Socpanel privateApi/getOrders and ingesting
    | active orders into the local orders table.
    |
    */

    'socpanel' => [
        'name' => 'adtag',
        'base_url' => env('SOCPANEL_BASE_URL', 'https://socpanel.com/privateApi'),
        'token' => env('SOCPANEL_TOKEN'),
        // HTTP timeout in seconds for API calls (default 60; increase if getOrders/completed often times out)
        'timeout' => (int) env('SOCPANEL_TIMEOUT', 60),
        // Comma-separated list of provider service IDs to poll (e.g. "123,456,789")
        'allowed_service_ids' => array_filter(
            array_map('trim', explode(',', env('SOCPANEL_ALLOWED_SERVICE_IDS', '')))
        ),
        // Fallback client_id for ingested orders until real mapping exists
        'fallback_client_id' => env('SOCPANEL_FALLBACK_CLIENT_ID'),
        // Cancel invalid orders job: cursor-based pagination
        'cancel_cursor_cache_key' => env('SOCPANEL_CANCEL_CURSOR_KEY', 'socpanel:cancel:cursor'),
        'cancel_batch_size' => (int) env('SOCPANEL_CANCEL_BATCH_SIZE', 50),
        'cancel_delay_ms' => (int) env('SOCPANEL_CANCEL_DELAY_MS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Adtag Provider
    |--------------------------------------------------------------------------
    |
    | Configuration for syncing Adtag service list into the local services table.
    |
    */

    'adtag' => [
        'name' => 'adtag',
        'base_url' => env('ADTAG_BASE_URL', 'https://adtag.pro/api/v2'),
        'api_key' => env('ADTAG_API_KEY'),
        'unmapped_category_id' => env('ADTAG_UNMAPPED_CATEGORY_ID', 1),
        'auto_map_templates' => env('ADTAG_AUTO_MAP_TEMPLATES', true),
    ],
];
