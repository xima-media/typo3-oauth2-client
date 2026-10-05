<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use Waldhacker\Oauth2Client\Backend\UserSettingsModule\ManageProvidersButtonRenderer;

$version = VersionNumberUtility::convertVersionStringToArray(VersionNumberUtility::getNumericTypo3Version());
if ($version['version_main'] < 14) {
    // TYPO3 v13 renders user settings of type "user" through a userFunc.
    $GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_oauth2_client_configs'] = [
        'label' => 'LLL:EXT:oauth2_client/Resources/Private/Language/locallang_be.xlf:userSettings.label',
        'type' => 'user',
        'userFunc' => ManageProvidersButtonRenderer::class . '->render',
    ];
    ExtensionManagementUtility::addFieldsToUserSettings('tx_oauth2_client_configs', 'after:mfaProviders');
}
