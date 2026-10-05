<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Service;

use InvalidArgumentException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Waldhacker\Oauth2Client\Domain\Model\ProviderConfiguration;

class Oauth2ProviderManager
{
    public const SCOPE_FRONTEND = 'frontend';
    public const SCOPE_BACKEND = 'backend';

    private SiteService $siteService;

    /**
     * @var ProviderConfiguration[]
     */
    private array $providerConfigurations = [];

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct(
        ExtensionConfiguration $extensionConfiguration,
        SiteService $siteService
    ) {
        $this->siteService = $siteService;
        $extensionConfiguration = $extensionConfiguration->get('oauth2_client');
        if (is_array($extensionConfiguration['providers'] ?? false)) {
            foreach ($extensionConfiguration['providers'] as $identifier => $provider) {
                $scopes = array_filter(
                    array_map('strval', $provider['scopes'] ?? [self::SCOPE_BACKEND, self::SCOPE_FRONTEND]),
                    static function (string $value): bool {
                        return in_array($value, [self::SCOPE_BACKEND, self::SCOPE_FRONTEND], true);
                    }
                );
                $this->providerConfigurations[$identifier] = new ProviderConfiguration(
                    $identifier,
                    $provider['label'],
                    $provider['description'] ?? '',
                    $provider['iconIdentifier'] ?? 'actions-key',
                    $provider['implementationClassName'] ?? GenericProvider::class,
                    $scopes,
                    $provider['options'] ?? [],
                    $provider['collaborators'] ?? []
                );
            }
        }
    }

    /**
     * @api
     * @param string $providerId
     * @param string|null $redirectUrl
     * @return AbstractProvider
     */
    public function createProvider(string $providerId, ?string $redirectUrl = null): AbstractProvider
    {
        if (!isset($this->providerConfigurations[$providerId])) {
            throw new InvalidArgumentException('No such provider: ' . $providerId, 1642867944);
        }
        $provider = $this->providerConfigurations[$providerId];
        $options = $provider->getOptions();
        if ($redirectUrl !== null) {
            $options['redirectUri'] = $redirectUrl;
        }
        $collaborators = [];
        foreach ($provider->getCollaborators() as $name => $value) {
            if (is_string($value)) {
                /** @var class-string $value */
                $collaborators[$name] = GeneralUtility::makeInstance($value);
            } else {
                $collaborators[$name] = $value;
            }
        }
        /** @var class-string $implementationClassName */
        $implementationClassName = $provider->getImplementationClassName();
        /** @var AbstractProvider $implementation */
        $implementation = new $implementationClassName($options, $collaborators);
        return $implementation;
    }

    public function getConfiguredProviders(): ?array
    {
        $providers = $this->providerConfigurations;
        if (count($providers) > 0) {
            return $providers;
        }
        return null;
    }

    public function getConfiguredBackendProviders(): ?array
    {
        $providers = array_filter(
            $this->getConfiguredProviders() ?? [],
            static fn (ProviderConfiguration $provider): bool => $provider->hasScope(self::SCOPE_BACKEND)
        );
        if (count($providers) > 0) {
            return $providers;
        }
        return null;
    }

    public function getConfiguredFrontendProviders(): ?array
    {
        $providers = array_filter(
            $this->getConfiguredProviders() ?? [],
            static fn (ProviderConfiguration $provider): bool => $provider->hasScope(self::SCOPE_FRONTEND)
        );
        if (count($providers) > 0) {
            return $providers;
        }
        return null;
    }

    public function getEnabledFrontendProviders(?ServerRequestInterface $request = null): ?array
    {
        /** @var Site|null $site */
        $site = $this->siteService->getSite($request);
        $language = $this->siteService->getLanguage($request);
        if ($site === null || $language === null) {
            return null;
        }

        $siteConfiguration = $site->getConfiguration();
        $languageConfiguration = $language->toArray();
        $enabledProviderIds = $languageConfiguration['enabled_oauth2_providers'] ?? $siteConfiguration['enabled_oauth2_providers'] ?? null;
        $enabledProviderIds = is_string($enabledProviderIds) ? GeneralUtility::trimExplode(',', $enabledProviderIds, true) : $enabledProviderIds;
        if (!is_array($enabledProviderIds)) {
            return null;
        }

        $configuredEnabledProviders = array_filter(
            $this->getConfiguredFrontendProviders() ?? [],
            static fn (ProviderConfiguration $provider): bool
                => in_array($provider->getIdentifier(), $enabledProviderIds)
        );

        if (count($configuredEnabledProviders) > 0) {
            // sort
            $providers = [];
            foreach ($enabledProviderIds as $enabledProviderId) {
                if (!array_key_exists($enabledProviderId, $configuredEnabledProviders)) {
                    continue;
                }
                $providers[$enabledProviderId] = $configuredEnabledProviders[$enabledProviderId];
            }
            return $providers;
        }
        return null;
    }

    public function hasBackendProvider(string $providerId): bool
    {
        $providers = $this->getConfiguredBackendProviders();
        return $providers && isset($providers[$providerId]);
    }

    public function hasFrontendProvider(string $providerId, ?ServerRequestInterface $request = null): bool
    {
        $providers = $this->getEnabledFrontendProviders($request);
        return $providers && isset($providers[$providerId]);
    }
}
