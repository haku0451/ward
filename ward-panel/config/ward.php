<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agent heartbeat
    |--------------------------------------------------------------------------
    |
    | Nodes that have not sent a heartbeat within this many seconds are shown
    | as offline. Keep this longer than the agent's heartbeat interval.
    |
    */

    'heartbeat_stale_after' => (int) env('WARD_HEARTBEAT_STALE_AFTER', 90),

    /*
    |--------------------------------------------------------------------------
    | OWASP CRS
    |--------------------------------------------------------------------------
    */

    'crs_version' => env('WARD_CRS_VERSION', '4.30.0'),
    'crs_source' => env('WARD_CRS_SOURCE', 'https://github.com/coreruleset/coreruleset'),

    /*
    |--------------------------------------------------------------------------
    | Event handling
    |--------------------------------------------------------------------------
    */

    'event_retention_days' => (int) env('WARD_EVENT_RETENTION_DAYS', 30),
    'event_batch_max' => (int) env('WARD_EVENT_BATCH_MAX', 500),
    'mask_query_keys' => [
        'password',
        'passwd',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'api_key',
        'apikey',
        'secret',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default WAF mode
    |--------------------------------------------------------------------------
    |
    | detection_only inspects and reports. blocking also denies matching
    | requests. Automatic IP bans default to off and require confirmation.
    |
    */

    'default_waf_mode' => env('WARD_DEFAULT_WAF_MODE', 'detection_only'),

    /*
    |--------------------------------------------------------------------------
    | Policy signing
    |--------------------------------------------------------------------------
    |
    | Ed25519 keys. Production must set these or generate them with
    | `php artisan ward:generate-signing-key`. Local/testing generate a
    | file under storage/app/private when missing.
    |
    */

    'policy_private_key' => env('WARD_POLICY_PRIVATE_KEY'),
    'policy_public_key' => env('WARD_POLICY_PUBLIC_KEY'),
    'policy_key_path' => env('WARD_POLICY_KEY_PATH', storage_path('app/private/ward-policy.json')),

    /*
    |--------------------------------------------------------------------------
    | Generated Agent YAML
    |--------------------------------------------------------------------------
    |
    | Values written into the agent.yaml copied from the Panel. Use Docker
    | service names so a Compose Agent can reach Panel and persist under /data.
    |
    */

    'agent_panel_url' => env('WARD_AGENT_PANEL_URL', env('APP_URL')),
    'agent_data_dir' => env('WARD_AGENT_DATA_DIR', './data'),
    'agent_listen' => env('WARD_AGENT_LISTEN', ':8080'),
    'agent_admin_listen' => env('WARD_AGENT_ADMIN_LISTEN', '127.0.0.1:9090'),

];
