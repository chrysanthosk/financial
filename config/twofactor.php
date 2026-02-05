<?php

return [

    // How many days to trust a device when user checks "remember this device"
    'trust_days' => (int) env('TWOFA_TRUST_DAYS', 30),

    // Cookie settings
    'cookie' => [
        'name' => env('TWOFA_TRUST_COOKIE', 'tfa_trusted_device'),
    ],

];
