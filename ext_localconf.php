<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Waldhacker\Oauth2Client\Authentication\BackendAuthenticationService;
use Waldhacker\Oauth2Client\Authentication\FrontendAuthenticationService;
use Waldhacker\Oauth2Client\Backend\DataHandling\DataHandlerHook;
use Waldhacker\Oauth2Client\Backend\Form\RenderType\ManageProvidersButtonElement;
use Waldhacker\Oauth2Client\Backend\Form\RenderType\Oauth2ProvidersElement;
use Waldhacker\Oauth2Client\Backend\LoginProvider\Oauth2LoginProvider;
use Waldhacker\Oauth2Client\Controller\Frontend\ManageProvidersController;
use Waldhacker\Oauth2Client\Database\Query\Restriction\Oauth2BeUserProviderConfigurationRestriction;
use Waldhacker\Oauth2Client\Database\Query\Restriction\Oauth2FeUserProviderConfigurationRestriction;

defined('TYPO3') || die();

(static function () {
    ExtensionManagementUtility::addService(
        'oauth2_client',
        'auth',
        BackendAuthenticationService::class,
        [
            'title' => 'OAuth2 Authentication',
            'description' => 'OAuth2 authentication for backend users',
            'subtype' => 'getUserBE,authUserBE,processLoginDataBE',
            'available' => true,
            'priority' => 75,
            'quality' => 50,
            'os' => '',
            'exec' => '',
            'className' => BackendAuthenticationService::class
        ]
    );

    ExtensionManagementUtility::addService(
        'oauth2_client',
        'auth',
        FrontendAuthenticationService::class,
        [
            'title' => 'OAuth2 Authentication',
            'description' => 'OAuth2 authentication for frontend users',
            'subtype' => 'getUserFE,authUserFE,processLoginDataFE',
            'available' => true,
            'priority' => 75,
            'quality' => 50,
            'os' => '',
            'exec' => '',
            'className' => FrontendAuthenticationService::class
        ]
    );

    ExtensionUtility::configurePlugin(
        'oauth2Client',
        'ManageProviders',
        [ManageProvidersController::class => 'list,deactivate'],
        [ManageProvidersController::class => 'list,deactivate'],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][Oauth2LoginProvider::PROVIDER_ID] = [
        'provider' => Oauth2LoginProvider::class,
        'sorting' => 25,
        'iconIdentifier' => 'actions-key',
        'label' => 'LLL:EXT:oauth2_client/Resources/Private/Language/locallang_be.xlf:login.link',
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1616684029] = [
        'nodeName' => 'oauth2providers',
        'priority' => '70',
        'class' => Oauth2ProvidersElement::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1616684030] = [
        'nodeName' => 'oauth2manageprovidersbutton',
        'priority' => '70',
        'class' => ManageProvidersButtonElement::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'][
        Oauth2BeUserProviderConfigurationRestriction::class
    ] = [];
    $GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'][
        Oauth2FeUserProviderConfigurationRestriction::class
    ] = [];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][1625556930]
        = DataHandlerHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][1625556930]
        = DataHandlerHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['checkModifyAccessList'][1625556930]
        = DataHandlerHook::class;

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']
        = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client'] ?? [];
})();
