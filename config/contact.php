<?php

return [
    'telegram' => env('CONTACT_TELEGRAM', ''),
    'email' => env('CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', '')),
    'cooperation_email' => env('CONTACT_COOPERATION_EMAIL', ''),
    'currency' => env('CONTACT_CURRENCY', '$'),
    'support_hours' => env('CONTACT_SUPPORT_HOURS', ''),
    'company_name' => env('CONTACT_COMPANY_NAME', config('app.name')),
    'tagline' => env('CONTACT_TAGLINE', ''),
];
