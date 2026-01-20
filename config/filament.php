<?php

return [
    /**
     * Authentication guard Filament should use.
     * Set FILAMENT_AUTH_GUARD in .env to override.
     */
    'auth' => [
        'guard' => env('FILAMENT_AUTH_GUARD', 'admin'),
    ],

    // Minimal config — package defaults will fill the rest.
];
