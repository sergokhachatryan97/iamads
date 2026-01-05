<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Export Modules Configuration
    |--------------------------------------------------------------------------
    |
    | Each module defines:
    | - allowed_filters: List of filter keys that can be used
    | - allowed_columns: Array of column keys => labels
    | - default_columns: Array of column keys to include by default
    | - query_builder_class: Class that implements ExportQueryBuilderInterface
    | - max_rows: Maximum number of rows allowed per export (null = unlimited)
    |
    */

    'modules' => [
        'orders' => [
            'allowed_filters' => [
                'date_from',
                'date_to',
                'client_id',
                'staff_id', // manager_id
                'service_id',
                'category_id',
                'status',
                'provider_id',
                'mode',
            ],
            'allowed_columns' => [
                'id' => 'ID',
                'created_at' => 'Date',
                'client_name' => 'Client',
                'staff_name' => 'Manager',
                'link' => 'Link',
                'charge' => 'Charge',
                'cost' => 'Cost',
                'start_count' => 'Start Count',
                'quantity' => 'Quantity',
                'delivered' => 'Delivered',
                'remains' => 'Remains',
                'service_name' => 'Service',
                'category_name' => 'Category',
                'status' => 'Status',
                'mode' => 'Mode',
                'provider_order_id' => 'Provider Order ID',
            ],
            'default_columns' => [
                'id',
                'created_at',
                'client_name',
                'link',
                'charge',
                'quantity',
                'delivered',
                'remains',
                'service_name',
                'status',
            ],
            'query_builder_class' => \App\Exports\OrdersExportQueryBuilder::class,
            'max_rows' => 200000,
        ],

        'subscriptions' => [
            'allowed_filters' => [
                'date_from',
                'date_to',
                'expires_from',
                'expires_to',
                'client_id',
                'staff_id', // manager_id
                'subscription_id',
                'service_id',
                'auto_renew',
                'status', // active/expired
            ],
            'allowed_columns' => [
                'id' => 'ID',
                'created_at' => 'Created At',
                'client_name' => 'Client',
                'staff_name' => 'Manager',
                'subscription_name' => 'Subscription Plan',
                'service_name' => 'Service',
                'orders_left' => 'Orders Left',
                'quantity_left' => 'Quantity Left',
                'link' => 'Link',
                'auto_renew' => 'Auto Renew',
                'expires_at' => 'Expires At',
                'status' => 'Status',
            ],
            'default_columns' => [
                'id',
                'created_at',
                'client_name',
                'subscription_name',
                'service_name',
                'orders_left',
                'quantity_left',
                'expires_at',
                'status',
            ],
            'query_builder_class' => \App\Exports\SubscriptionsExportQueryBuilder::class,
            'max_rows' => 100000,
        ],

//        'services' => [
//            'allowed_filters' => [
//                'category_id',
//                'provider_id',
//                'is_active',
//            ],
//            'allowed_columns' => [
//                'id' => 'ID',
//                'name' => 'Name',
//                'category_name' => 'Category',
//                'min_quantity' => 'Min Quantity',
//                'max_quantity' => 'Max Quantity',
//                'increment' => 'Increment',
//                'rate_per_1000' => 'Rate per 1000',
//                'is_active' => 'Active',
//            ],
//            'default_columns' => [
//                'id',
//                'name',
//                'category_name',
//                'min_quantity',
//                'max_quantity',
//                'rate_per_1000',
//                'is_active',
//            ],
//            'query_builder_class' => \App\Exports\ServicesExportQueryBuilder::class,
//            'max_rows' => 100000,
//        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    */
    'default_format' => 'csv',
    'allowed_formats' => ['csv'],
    'storage_disk' => 'local',
    'storage_path' => 'exports',
];

