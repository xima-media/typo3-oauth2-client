<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Backend\SiteConfig;

use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;

class ConfiguredFrontendProvidersItemsProcFunc
{
    public function __construct(private readonly Oauth2ProviderManager $oauth2ProviderManager) {}

    public function getItems(array &$params): void
    {
        foreach ($this->oauth2ProviderManager->getConfiguredFrontendProviders() ?? [] as $provider) {
            $params['items'][] = [$provider->getLabel(), $provider->getIdentifier()];
        }
    }
}
