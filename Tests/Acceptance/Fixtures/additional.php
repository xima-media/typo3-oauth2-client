<?php

declare(strict_types=1);

use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';
$GLOBALS['TYPO3_CONF_VARS']['BE']['loginRateLimit'] = 0;
$GLOBALS['TYPO3_CONF_VARS']['FE']['loginRateLimit'] = 0;

// Plain HTTP through the DDEV router: the mock derives `iss` from the request, so browser and web container see the same issuer
$mockIssuer = sprintf('http://oidc.%s.%s/default', getenv('DDEV_SITENAME'), getenv('DDEV_TLD') ?: 'ddev.site');
$mockProvider = static fn(string $label, array $scopes, string $clientId): array => [
    'label' => $label,
    'iconIdentifier' => 'actions-key',
    'description' => 'Login with ' . $label,
    'scopes' => $scopes,
    'options' => [
        'clientId' => $clientId,
        'clientSecret' => 'secret',
        'urlAuthorize' => $mockIssuer . '/authorize',
        'urlAccessToken' => $mockIssuer . '/token',
        'urlResourceOwnerDetails' => $mockIssuer . '/userinfo',
        'responseResourceOwnerId' => 'sub',
        'scopes' => ['openid', 'email', 'profile'],
        'scopeSeparator' => ' ',
    ],
];

$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']['providers'] = [
    'mock' => $mockProvider('Mock', [Oauth2ProviderManager::SCOPE_BACKEND, Oauth2ProviderManager::SCOPE_FRONTEND], 'typo3-both'),
    'mock-be' => $mockProvider('Mock BE', [Oauth2ProviderManager::SCOPE_BACKEND], 'typo3-be'),
    'mock-fe' => $mockProvider('Mock FE', [Oauth2ProviderManager::SCOPE_FRONTEND], 'typo3-fe'),
];
