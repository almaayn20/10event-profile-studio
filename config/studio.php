<?php

return [
    /*
    | Which profile this studio makes: «event» (10 Event, 960 × 540 pt) or «gift» (10 Gift, 566.93 × 311.81 pt).
    | One code base; each edition runs on its own with its own data.
    */
    'edition' => env('STUDIO_EDITION', 'event'),

    /*
    | One shared password for the team. STUDIO_PASSWORD is only the first one: once somebody sets another from the
    | studio's settings page, that one is used.
    */
    'password' => env('STUDIO_PASSWORD'),

    /*
    | Signing in from the 10concept platforms portal (apps.bayn-ai.net) without the password: the portal signs a
    | one-minute, one-time link with this studio's own secret. The app key is «profile» or «giftprofile».
    */
    'portal_secret' => env('PORTAL_SSO_SECRET'),
    'portal_app' => env('PORTAL_SSO_APP', env('STUDIO_EDITION', 'event') === 'gift' ? 'giftprofile' : 'profile'),
    'portal_url' => env('PORTAL_URL', 'https://apps.bayn-ai.net'),

    /*
    | Printing a page and editing the profile PDF are done by engine/studio_pdf.py: Chrome prints the page,
    | pypdf edits the book. Point these at the server's binaries if they are not on the PATH.
    */
    'python' => env('STUDIO_PYTHON', 'python3'),
    'chrome' => env('CHROME_BIN', 'chromium'),
    'engine' => env('STUDIO_ENGINE', base_path('engine/studio_pdf.py')),
    'timeout' => (int) env('STUDIO_ENGINE_TIMEOUT', 300),

    // a profile PDF is large (20 MB+); nginx and PHP must allow at least this much too
    'profile_max_mb' => (int) env('STUDIO_PROFILE_MAX_MB', 150),
    'image_max_mb' => 25,
];
