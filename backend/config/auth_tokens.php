<?php

return [
    'jwt_secret' => env('JWT_SECRET'),
    'issuer' => env('JWT_ISSUER', 'h-smart-api'),
    'audience' => env('JWT_AUDIENCE', 'h-smart-frontend'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];
