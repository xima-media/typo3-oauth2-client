<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Events\Listener;

use TYPO3\CMS\FrontendLogin\Event\ModifyLoginFormViewEvent;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;

class ModifyFrontendLoginFormViewListener
{
    public function __construct(private readonly Oauth2ProviderManager $oauth2ProviderManager) {}

    public function __invoke(ModifyLoginFormViewEvent $event): void
    {
        $event->getView()->assign('oauth2Providers', $this->oauth2ProviderManager->getEnabledFrontendProviders());
    }
}
