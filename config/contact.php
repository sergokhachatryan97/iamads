<?php

return [
    'telegram' => env('CONTACT_TELEGRAM', ''),
    'telegram_by_locale' => [
        'en' => env('CONTACT_TELEGRAM', ''),
        'ru' => env('CONTACT_TELEGRAM_RU', 'smmtool_sup'),
        'zh' => env('CONTACT_TELEGRAM_ZH', 'smmtool_supp0rt'),
    ],
    'telegram_support_list' => [
        ['username' => env('CONTACT_TELEGRAM', ''), 'label' => 'English', 'flag' => "\u{1F1EC}\u{1F1E7}"],
        ['username' => env('CONTACT_TELEGRAM_RU', 'smmtool_sup'), 'label' => 'Russian', 'flag' => "\u{1F1F7}\u{1F1FA}"],
        ['username' => env('CONTACT_TELEGRAM_ZH', 'smmtool_supp0rt'), 'label' => 'Chinese', 'flag' => "\u{1F1E8}\u{1F1F3}"],
    ],
    'email' => env('CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', '')),
    'cooperation_email' => env('CONTACT_COOPERATION_EMAIL', ''),
    'currency' => env('CONTACT_CURRENCY', '$'),
    'support_hours' => env('CONTACT_SUPPORT_HOURS', ''),
    'company_name' => env('CONTACT_COMPANY_NAME', config('app.name')),
    'tagline' => env('CONTACT_TAGLINE', ''),
];
