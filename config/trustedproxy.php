<?php

return [
    /* Only Nginx instances directly controlled by this deployment. */
    'proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))
    ))),
];
