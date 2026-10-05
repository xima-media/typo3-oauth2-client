<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Service;

use Exception;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use Waldhacker\Oauth2Client\Session\SessionManager;

class Oauth2Service
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly Oauth2ProviderManager $oauth2ProviderManager,
        private readonly SessionManager $sessionManager,
        private readonly LogManager $logManager,
    ) {
        $this->logger = $this->logManager->getLogger(__CLASS__);
    }

    /**
     * @throws SessionNotCreatedException
     */
    public function buildGetResourceOwnerAuthorizationUrl(
        string $providerId,
        ?string $callbackUrl = null,
        ?ServerRequestInterface $request = null
    ): string {
        $provider = $this->oauth2ProviderManager->createProvider($providerId, $callbackUrl);
        $authorizationUrl = $provider->getAuthorizationUrl();
        $this->sessionManager->setAndSaveSessionData(
            SessionManager::SESSION_NAME_STATE,
            $provider->getState(),
            $request
        );

        return $authorizationUrl;
    }

    /**
     * @throws SessionNotCreatedException
     */
    public function buildGetResourceOwnerProvider(
        string $state,
        string $providerId,
        ?string $callbackUrl = null,
        ?ServerRequestInterface $request = null
    ): ?AbstractProvider {
        $oauth2StateFromSession = $this->sessionManager->getSessionData(SessionManager::SESSION_NAME_STATE, $request);

        $this->sessionManager->setAndSaveSessionData(SessionManager::SESSION_NAME_STATE, null, $request);
        if (empty($oauth2StateFromSession) || $oauth2StateFromSession !== $state) {
            return null;
        }

        return $this->oauth2ProviderManager->createProvider($providerId, $callbackUrl);
    }

    public function buildGetResourceOwnerAccessToken(AbstractProvider $provider, string $code): ?AccessToken
    {
        try {
            $accessToken = $provider->getAccessToken(
                'authorization_code',
                [
                    'code' => $code,
                ]
            );
            if ($accessToken instanceof AccessToken) {
                return $accessToken;
            }
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());
        }
        return null;
    }

    public function getResourceOwner(AbstractProvider $provider, AccessToken $accessToken): ?ResourceOwnerInterface
    {
        $user = null;
        try {
            $user = $provider->getResourceOwner($accessToken);
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());
        }
        return $user;
    }
}
