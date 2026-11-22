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
    // Composer ignores ext_emconf.php autoload; only classic mode loads the bundled libraries.
    'autoload' => json_decode(file_get_contents(__DIR__ . '/Resources/Private/Libs/autoload.json'), true, 512, JSON_THROW_ON_ERROR),
    'constraints'      => [
        'depends' => [
            'backend' => '13.4.0-14.4.99',
            'fluid' => '13.4.0-14.4.99',
            'typo3' => '13.4.0-14.4.99',
        ],
        // EXT:setup is part of EXT:backend since TYPO3 14.3
        'suggests' => [
            'setup' => '13.4.0-13.4.99',
        ],
    ],
];
