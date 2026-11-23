<?php

use Waldhacker\Oauth2Client\Controller\Backend\ManageProvidersController;
use Waldhacker\Oauth2Client\Controller\Backend\Registration\AuthorizeController;
use Waldhacker\Oauth2Client\Controller\Backend\Registration\VerifyController;

return [
    'oauth2_registration_authorize' => [
        'path' => '/oauth2/callback/handle',
        'access' => 'public',
        'redirect' => [
            'enable' => true,
            'parameters' => [
                'oauth2-provider' => true,
                'action' => true,
                'code' => true,
                'state' => true,
            ],
        ],
        'target' => AuthorizeController::class . '::handleRequest',
    ],
    'oauth2_registration_verify' => [
        'path' => '/oauth2/callback/verify',
        'target' => VerifyController::class,
    ],
    'oauth2_manage_providers' => [
        'path' => '/oauth2/manage/providers',
        'target' => ManageProvidersController::class,
    ],
];
