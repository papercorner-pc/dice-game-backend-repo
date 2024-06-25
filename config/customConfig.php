<?php

return [

    'google' => [
        'firebase' => [
            'projectId' => env('FIREBASE_PROJECT_ID', ''),
            'fcm' => [
                'domainProtocol' => env('FCM_DOMAIN_PROTOCOL', ''),
                'domainUrl' => env('FCM_DOMAIN_URL', ''),
                'sendUri' => env('FCM_SEND_URI', ''),
                'sendApiUrl' => (
                    (env('FCM_DOMAIN_PROTOCOL', '') != '')
                    && (env('FCM_DOMAIN_URL', '') != '')
                    && (env('FCM_SEND_URI', '') != '')
                ) ? env('FCM_DOMAIN_PROTOCOL', '') . '://' . env('FCM_DOMAIN_URL', '') . '/' . env('FCM_SEND_URI', '') : '',
                'sendV1Prefix' => env('FCM_SEND_URL_V1_PREFIX', ''),
                'sendV1Uri' => env('FCM_SEND_URL_V1_URI', ''),
                'authKeysJsonV1' => env('FCM_AUTH_KEYS_JSON_PATH', ''),
                'sendV1ApiUrl' => (
                    (env('FCM_DOMAIN_PROTOCOL', '') != '')
                    && (env('FCM_DOMAIN_URL', '') != '')
                    && (env('FCM_SEND_URL_V1_PREFIX', '') != '')
                    && (env('FIREBASE_PROJECT_ID', '') != '')
                    && (env('FCM_SEND_URL_V1_URI', '') != '')
                ) ? env('FCM_DOMAIN_PROTOCOL', '') . '://' . env('FCM_DOMAIN_URL', '') . '/' . env('FCM_SEND_URL_V1_PREFIX', '') . '/' . env('FIREBASE_PROJECT_ID', '') . '/' . env('FCM_SEND_URL_V1_URI', '') : '',
            ],
        ]
    ],

];
