<?php

/**
 * The API is called from a browser on a different origin than its own, so the
 * frontend's origin has to be allowed explicitly.
 *
 * FRONTEND_URL holds the deployed frontend (comma-separate several to allow
 * more than one, e.g. a custom domain alongside the vercel.app one). The dev
 * server is always allowed so a local frontend keeps working without any env
 * set, and Vercel preview deployments — which get a fresh subdomain per push —
 * are matched by pattern rather than being listed one by one.
 */
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', '')),
    ))),

    'allowed_origins_patterns' => [
        // local dev server, on either host spelling
        '#^http://(localhost|127\.0\.0\.1):5173\z#u',
        // Vercel preview builds for this project
        '#^https://[a-z0-9-]+\.vercel\.app\z#u',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Auth is a bearer token in the Authorization header, not a cookie, so
    // credentialed requests aren't needed.
    'supports_credentials' => false,

];
