<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Controller\Frontend;

use Doctrine\DBAL\Exception;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Waldhacker\Oauth2Client\Repository\FrontendUserRepository;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;
use Waldhacker\Oauth2Client\Service\Oauth2Service;
use Waldhacker\Oauth2Client\Service\SiteService;
use Waldhacker\Oauth2Client\Session\SessionManager;

class RegistrationController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private static array $allowedActions = [
        'authorize',
        'verify',
    ];

    public function __construct(
        private readonly Oauth2Service $oauth2Service,
        private readonly Oauth2ProviderManager $oauth2ProviderManager,
        private readonly FrontendUserRepository $frontendUserRepository,
        private readonly SessionManager $sessionManager,
        private readonly SiteService $siteService,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Context $context
    ) {
    }

    /**
     * @throws SessionNotCreatedException
     * @throws AspectNotFoundException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $getParameters = $request->getQueryParams();

        $action = $getParameters['tx_oauth2client']['action'] ?? null;
        $providerId = (string)($getParameters['oauth2-provider'] ?? '');
        /** @var UserAspect $frontendUser */
        $frontendUser = $this->context->getAspect('frontend.user');

        if (
            !$frontendUser->isLoggedIn()
            || empty($providerId)
            || !$this->oauth2ProviderManager->hasFrontendProvider($providerId, $request)
            || !in_array($action, self::$allowedActions, true)
        ) {
            $response = $this->responseFactory->createResponse(401, 'OAuth2: Not logged in or invalid data');

            $this->sessionManager->removeSessionData($request);
            return $this->sessionManager->appendRemoveOAuth2CookieToResponse($response, $request);
        }

        if ($action === 'verify') {
            $code = (string)($getParameters['code'] ?? '');
            $state = (string)($getParameters['state'] ?? '');
            return $this->verify($providerId, $code, $state, $request);
        }
        return $this->authorize($providerId, $request);
    }

    /**
     * @throws SessionNotCreatedException
     */
    private function authorize(string $providerId, ServerRequestInterface $request): ResponseInterface
    {
        $authorizationUrl = $this->oauth2Service->buildGetResourceOwnerAuthorizationUrl(
            $providerId,
            $this->buildCallbackUri($providerId, $request),
            $request
        );

        $response = $this->responseFactory->createResponse(302)->withHeader('location', $authorizationUrl);
        return $this->sessionManager->appendOAuth2CookieToResponse($response, $request);
    }

    /**
     * @throws SessionNotCreatedException
     * @throws AspectNotFoundException
     */
    private function verify(
        string $providerId,
        string $code,
        string $state,
        ServerRequestInterface $request
    ): ResponseInterface {
        $originalRequestData = $this->sessionManager->getSessionData(
            SessionManager::SESSION_NAME_ORIGINAL_REQUEST,
            $request
        );
        $warningRedirectUri = empty($originalRequestData)
            ? $this->siteService->getBaseUri()
            : $originalRequestData['uri'];
        if (empty($code) || empty($state)) {
            return $this->redirectWithWarning($warningRedirectUri, $request);
        }

        if (!$this->typo3UserIsWithinConfiguredStorage($request)) {
            return $this->redirectWithWarning($warningRedirectUri, $request);
        }

        $provider = $this->oauth2Service->buildGetResourceOwnerProvider(
            $state,
            $providerId,
            $this->buildCallbackUri($providerId, $request),
            $request
        );
        if ($provider === null) {
            return $this->redirectWithWarning($warningRedirectUri, $request);
        }
        $accessToken = $this->oauth2Service->buildGetResourceOwnerAccessToken(
            $provider,
            $code
        );
        if ($accessToken === null) {
            return $this->redirectWithWarning($warningRedirectUri, $request);
        }
        $remoteUser = $this->oauth2Service->getResourceOwner($provider, $accessToken);
        $userId = (int)$this->context->getPropertyFromAspect('frontend.user', 'id');

        if ($remoteUser instanceof ResourceOwnerInterface) {
            try {
                $this->frontendUserRepository->persistIdentityForUser($providerId, (string)$remoteUser->getId(), $userId);
            } catch (Exception | AspectNotFoundException) {
                return $this->redirectWithWarning($warningRedirectUri, $request);
            }
        } else {
            return $this->redirectWithWarning($warningRedirectUri, $request);
        }

        if (empty($originalRequestData)) {
            $response = $this->responseFactory
                ->createResponse(302, 'OAuth2: Done, but unable to find the original requested location')
                ->withHeader('location', $this->siteService->getBaseUri());
        } else {
            $response = $this->responseFactory
                ->createResponse(302, 'OAuth2: Done. Redirection to original requested location')
                ->withHeader('location', $originalRequestData['uri']);
        }

        $this->sessionManager->removeSessionData($request);
        return $this->sessionManager->appendRemoveOAuth2CookieToResponse($response, $request);
    }

    private function buildCallbackUri(string $providerId, ServerRequestInterface $request): string
    {
        return $this->siteService->buildCallbackUri(
            [
                'oauth2-provider' => $providerId,
                'tx_oauth2client' => [
                    'action' => 'verify'
                ],
            ],
            $request
        );
    }

    private function redirectWithWarning(string $redirectUri, ServerRequestInterface $request): ResponseInterface
    {
        $this->sessionManager->removeSessionData($request);

        $response = $this->responseFactory
            ->createResponse(302, 'OAuth2: Not logged in or invalid data')
            ->withHeader('location', $redirectUri);

        return $this->sessionManager->appendRemoveOAuth2CookieToResponse($response, $request);
    }

    private function typo3UserIsWithinConfiguredStorage(ServerRequestInterface $request): bool
    {
        $typo3User = $request->getAttribute('frontend.user');
        if (!($typo3User instanceof FrontendUserAuthentication)) {
            return false;
        }

        /** @var Site|null $site */
        $site = $this->siteService->getSite();
        $language = $this->siteService->getLanguage();
        if ($site === null || $language === null) {
            return false;
        }

        $siteConfiguration = $site->getConfiguration();
        $languageConfiguration = $language->toArray();
        $configuredStoragePid = empty($languageConfiguration['oauth2_storage_pid'])
            ? ($siteConfiguration['oauth2_storage_pid'] ?? null)
            : $languageConfiguration['oauth2_storage_pid'];

        $typo3UserStoragePid = $typo3User->user['pid'] ?? null;
        if ($typo3UserStoragePid === null) {
            return false;
        }

        return (int)$typo3UserStoragePid === (int)$configuredStoragePid;
    }
}
