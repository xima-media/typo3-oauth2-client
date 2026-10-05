<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Session;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\CookieHeaderTrait;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Security\JwtTrait;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class SessionManager
{
    use CookieHeaderTrait;
    use JwtTrait;

    public const SESSION_NAME_STATE = 'oauth2-state';
    public const SESSION_NAME_ORIGINAL_REQUEST = 'oauth2-original-registration-request-data';
    private const REQUEST_TYPE_FE = 'FE';
    private const REQUEST_TYPE_BE = 'BE';

    private array $userSessionManagers = [
        self::REQUEST_TYPE_FE => null,
        self::REQUEST_TYPE_BE => null
    ];
    private array $userSessions = [
        self::REQUEST_TYPE_FE => null,
        self::REQUEST_TYPE_BE => null
    ];

    public function getSessionData(string $key, ?ServerRequestInterface $request = null): mixed
    {
        $request = $this->getRequest($request);
        $requestType = $this->determineRequestType($request);
        return $this->getUserSession($requestType, $request)->get($key);
    }

    /**
     * @throws SessionNotCreatedException
     */
    public function setAndSaveSessionData(string $key, mixed $data, ?ServerRequestInterface $request = null): void
    {
        $request = $this->getRequest($request);
        $requestType = $this->determineRequestType($request);
        $userSessionManager = $this->getUserSessionManager($requestType);
        $userSession = $this->getUserSession($requestType, $request);
        $userSession->set($key, $data);
        if ($userSessionManager->isSessionPersisted($userSession)) {
            $this->userSessions[$requestType] = $userSessionManager->updateSession($userSession);
        } else {
            $this->userSessions[$requestType] = $userSessionManager->fixateAnonymousSession($userSession);
        }
    }

    public function removeSessionData(?ServerRequestInterface $request = null): void
    {
        $request = $this->getRequest($request);
        $requestType = $this->determineRequestType($request);
        $userSessionManager = $this->getUserSessionManager($requestType);
        $userSession = $this->getUserSession($requestType, $request);
        if ($userSessionManager->isSessionPersisted($userSession)) {
            $userSessionManager->removeSession($userSession);
        }
    }

    public function appendOAuth2CookieToResponse(
        ResponseInterface $response,
        ?ServerRequestInterface $request = null
    ): ResponseInterface {
        return $response->withAddedHeader('Set-Cookie', (string)$this->buildOAuth2Cookie($request));
    }

    public function appendRemoveOAuth2CookieToResponse(
        ResponseInterface $response,
        ?ServerRequestInterface $request = null
    ): ResponseInterface {
        $request = $this->getRequest($request);
        $requestType = $this->determineRequestType($request);

        $cookieName = $this->getOAuth2CookieName($request);
        $sessionId = '';
        $cookieExpire = -1;
        $cookieDomain = $this->getCookieDomain($requestType);
        $sitePath = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
        $cookiePath = $cookieDomain ? '/' : (is_string($sitePath) ? $sitePath : '/');

        $cookie = new Cookie(
            $cookieName,
            $sessionId,
            $cookieExpire,
            $cookiePath,
            $cookieDomain
        );

        return $response->withAddedHeader('Set-Cookie', (string)$cookie);
    }

    public function getOAuth2CookieName(?ServerRequestInterface $request = null): string
    {
        $request = $this->getRequest($request);
        $requestType = $this->determineRequestType($request);

        $cookieName = $requestType === self::REQUEST_TYPE_FE
            ? FrontendUserAuthentication::getCookieName()
            : BackendUserAuthentication::getCookieName();
        return $cookieName . '_oauth2';
    }

    private function getUserSession(string $requestType, ServerRequestInterface $request): ?UserSession
    {
        if ($this->userSessions[$requestType] === null) {
            $this->userSessions[$requestType] = $this->createUserSession($requestType, $request);
        }
        return $this->userSessions[$requestType];
    }

    private function createUserSession(string $requestType, ServerRequestInterface $request): UserSession
    {
        return $this->getUserSessionManager($requestType)
            ->createFromRequestOrAnonymous($request, $this->getOAuth2CookieName($request));
    }

    private function getUserSessionManager(string $requestType): ?UserSessionManager
    {
        if ($this->userSessionManagers[$requestType] === null) {
            $this->userSessionManagers[$requestType] = $this->createUserSessionManager($requestType);
        }
        return $this->userSessionManagers[$requestType];
    }

    private function createUserSessionManager(string $requestType): UserSessionManager
    {
        return UserSessionManager::create($requestType);
    }

    private function buildOAuth2Cookie(?ServerRequestInterface $request = null): Cookie
    {
        $request = $this->getRequest($request);
        $requestType = $this->determineRequestType($request);

        $cookieName = $this->getOAuth2CookieName($request);
        $sessionId = $this->getUserSession($requestType, $request)->getIdentifier();
        $cookieExpire = 0;
        $cookieDomain = $this->getCookieDomain($requestType);
        $sitePath = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
        $cookiePath = $cookieDomain ? '/' : (is_string($sitePath) ? $sitePath : '/');
        $cookieSameSite = $this->sanitizeSameSiteCookieValue(
            strtolower($GLOBALS['TYPO3_CONF_VARS'][$requestType]['cookieSameSite'] ?? Cookie::SAMESITE_STRICT)
        );
        $isSecure = $cookieSameSite === Cookie::SAMESITE_NONE || GeneralUtility::getIndpEnv('TYPO3_SSL');

        $sessionId = self::encodeHashSignedJwt(
            [
                'identifier' => $sessionId,
                'time' => (new DateTimeImmutable())->format(DateTimeInterface::RFC3339),
            ],
            self::createSigningKeyFromEncryptionKey(UserSession::class)
        );

        return new Cookie(
            $cookieName,
            $sessionId,
            $cookieExpire,
            $cookiePath,
            $cookieDomain,
            $isSecure,
            true,
            false,
            $cookieSameSite
        );
    }

    private function getCookieDomain(string $requestType): string
    {
        $cookieDomain = empty($GLOBALS['TYPO3_CONF_VARS'][$requestType]['cookieDomain'])
            ? (string)$GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain']
            : (string)$GLOBALS['TYPO3_CONF_VARS'][$requestType]['cookieDomain'];

        if (empty($cookieDomain) || $cookieDomain[0] !== '/') {
            return $cookieDomain;
        }

        $match = [];
        $host = GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');
        $found = @preg_match($cookieDomain, (is_string($host) ? $host : ''), $match);
        return $found ? $match[0] : '';
    }

    private function determineRequestType(ServerRequestInterface $request): string
    {
        $frontendRequestType = $this->isFrontendRequest($request) ? self::REQUEST_TYPE_FE : null;
        $requestType = $this->isBackendRequest($request) ? self::REQUEST_TYPE_BE : $frontendRequestType;
        if (!in_array($requestType, [self::REQUEST_TYPE_FE, self::REQUEST_TYPE_BE], true)) {
            throw new InvalidArgumentException('Invalid request type', 1642868012);
        }
        return $requestType;
    }

    private function isBackendRequest(ServerRequestInterface $request): bool
    {
        return ApplicationType::fromRequest($request)->isBackend();
    }

    private function isFrontendRequest(ServerRequestInterface $request): bool
    {
        return ApplicationType::fromRequest($request)->isFrontend();
    }

    private function getRequest(?ServerRequestInterface $request = null): ServerRequestInterface
    {
        $request = $request ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
        if (!($request instanceof ServerRequestInterface)) {
            throw new InvalidArgumentException(
                sprintf('Request must implement "%s"', ServerRequestInterface::class),
                1643445716
            );
        }
        return $request;
    }
}
