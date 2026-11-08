<?php

return [
    /*
    | These are exact hostnames, not regular expressions or provider-wide
    | wildcards. The hostname from APP_URL is included automatically.
    */
    'trusted_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'TRUSTED_HOSTS',
            'localhost,127.0.0.1,::1,lms.smkn1cerme.test'
        ))
    ))),

    /*
    | Quick-tunnel hostnames can be registered at runtime with the
    | `lms:trusted-host` command. Keep this file outside the public directory.
    */
    'host_registry' => env(
        'TRUSTED_HOSTS_FILE',
        storage_path('app/private/trusted-hosts.json')
    ),
];
