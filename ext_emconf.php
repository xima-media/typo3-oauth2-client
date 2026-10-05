<?php

$EM_CONF[$_EXTKEY] = [
    'title'            => 'OAuth2 Client',
    'description'      => 'TYPO3 OAuth2 Login Client (backend and frontend)',
    'category'         => 'auth',
    'author'           => 'Oliver Eglseder; waldhacker',
    'author_email'     => 'support@co-stack.com',
    'author_company'   => 'co-stack.com, Inh. Oliver Eglseder; waldhacker UG (haftungsbeschränkt)',
    'state'            => 'stable',
    'uploadfolder'     => '0',
    'clearCacheOnLoad' => 1,
    'version'          => '5.0.0',
    'constraints'      => [
        'depends' => [
            'backend' => '13.4.0-14.4.99',
            'fluid' => '13.4.0-14.4.99',
            'setup' => '13.4.0-14.4.99',
            'typo3' => '13.4.0-14.4.99',
        ]
    ],
    'autoload' => [
        'psr-4' => [
            'Waldhacker\\Oauth2Client\\' => 'Classes',
        ],
    ]
];
