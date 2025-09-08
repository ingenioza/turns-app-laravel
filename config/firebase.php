<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration - Turns Project
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase services including Authentication and Analytics
    | Project: turns-ccc9e
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID', 'turns-ccc9e'),
    
    'credentials' => [
        'type' => 'service_account',
        'project_id' => env('FIREBASE_PROJECT_ID', 'turns-ccc9e'),
        'private_key_id' => env('FIREBASE_PRIVATE_KEY_ID'),
        'private_key' => env('FIREBASE_PRIVATE_KEY'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'client_id' => env('FIREBASE_CLIENT_ID'),
        'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
        'client_x509_cert_url' => env('FIREBASE_CLIENT_CERT_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Web App Configuration
    |--------------------------------------------------------------------------
    */
    'web' => [
        'api_key' => env('FIREBASE_API_KEY', 'AIzaSyCOBASqcrJ2CJmhOxxIULkWIJc1hP1yiB4'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN', 'turns-ccc9e.firebaseapp.com'),
        'project_id' => env('FIREBASE_PROJECT_ID', 'turns-ccc9e'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', 'turns-ccc9e.firebasestorage.app'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID', '204340310004'),
        'app_id' => env('FIREBASE_WEB_APP_ID', '1:204340310004:web:a5cbf9cbf1805ef7a913a1'),
        'measurement_id' => env('FIREBASE_MEASUREMENT_ID', 'G-71XJ4P3X63'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase App Identifiers (Correct)
    |--------------------------------------------------------------------------
    */
    'apps' => [
        'android' => [
            'package_name' => env('FIREBASE_ANDROID_PACKAGE', 'za.co.ingenio.turns'),
            'app_name' => 'Turns Android',
        ],
        'web' => [
            'app_name' => env('FIREBASE_WEB_APP_NAME', 'turns_web'),
            'app_id' => env('FIREBASE_WEB_APP_ID', '1:204340310004:web:a5cbf9cbf1805ef7a913a1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Analytics
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'enabled' => env('FIREBASE_ANALYTICS_ENABLED', true),
        'debug' => env('FIREBASE_ANALYTICS_DEBUG', false),
        'measurement_id' => env('FIREBASE_MEASUREMENT_ID', 'G-71XJ4P3X63'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Settings
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'verify_email' => env('FIREBASE_VERIFY_EMAIL', true),
        'create_user_if_not_exists' => env('FIREBASE_CREATE_USER', true),
    ],
];
