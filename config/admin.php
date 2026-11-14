<?php

/*
| The admin panel and its sign-in protection. Everything here is read from .env, so it can be
| changed on the server without touching the code.
*/

$path = trim((string) env('ADMIN_PATH', 'admin'), '/ ');

return [
    // address of the admin panel: https://domain/{path}. Anything else than "admin" makes /admin a 404.
    'path' => preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-\/]{0,60}$/', $path) ? $path : 'admin',

    // signed out after this many minutes without any request
    'idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 120),

    // Content-Security-Policy: "enforce", "report" (only reports problems in the browser console) or "off"
    'csp' => env('CSP_MODE', 'enforce'),
];
