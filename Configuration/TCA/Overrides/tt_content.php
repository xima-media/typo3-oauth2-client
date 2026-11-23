<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function () {
    $languageFile = 'LLL:EXT:oauth2_client/Resources/Private/Language/locallang_be.xlf:';

    ExtensionUtility::registerPlugin(
        'oauth2Client',
        'ManageProviders',
        $languageFile . 'plugin.manage_providers',
        'oauth2_client_plugin_manage_providers',
        'plugins',
        $languageFile . 'plugin.manage_providers.description',
    );
})();
