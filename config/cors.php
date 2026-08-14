<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | IMPORTANT — exposed_headers:
    | Without listing ETag and X-MFG-API-Version here, browsers cannot read
    | those headers from JS even though they are present on the wire. CORS
    | strips any response header not explicitly exposed, silently defeating
    | Arena's ETag-based cache-revalidation design. Get this right even though
    | the ETag middleware itself does not exist until step 8.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'OPTIONS'],

    // array_map('trim', ...) so "https://a.example, https://b.example" (a
    // naturally-written list with a space after the comma) doesn't leave a
    // leading space baked into every origin after the first — Laravel's
    // CORS origin check is an exact match, so an untrimmed entry would
    // silently reject that origin with no error, just a missing
    // Access-Control-Allow-Origin header on the client side.
    'allowed_origins' => array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['ETag', 'X-MFG-API-Version'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
