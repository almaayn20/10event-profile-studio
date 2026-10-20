<?php

return [
    /*
    | One shared password for the team. STUDIO_PASSWORD is only the first one: once somebody sets another from the
    | studio's settings page, that one is used.
    */
    'password' => env('STUDIO_PASSWORD'),

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
