<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Events;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BackendUserLookupEvent
{
    public function __construct(
        private readonly string $providerId,
        private readonly AbstractProvider $provider,
        private readonly AccessTokenInterface $accessToken,
        private readonly ResourceOwnerInterface $remoteUser,
        private ?array $typo3User,
        private ServerRequestInterface $request
    ) {
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function getProvider(): AbstractProvider
    {
        return $this->provider;
    }

    public function getAccessToken(): AccessTokenInterface
    {
        return $this->accessToken;
    }

    public function getRemoteUser(): ResourceOwnerInterface
    {
        return $this->remoteUser;
    }

    public function getTypo3User(): ?array
    {
        return $this->typo3User;
    }

    public function setTypo3User(array $typo3User): void
    {
        $this->typo3User = $typo3User;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
