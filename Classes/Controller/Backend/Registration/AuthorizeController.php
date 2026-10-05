<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Controller\Backend\Registration;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;
use Waldhacker\Oauth2Client\Service\Oauth2Service;
use Waldhacker\Oauth2Client\Session\SessionManager;

class AuthorizeController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private static array $allowedActions = [
        'authorize',
        'callback',
    ];

    public function __construct(
        private readonly Oauth2ProviderManager $oauth2ProviderManager,
        private readonly Oauth2Service $oauth2Service,
        private readonly SessionManager $sessionManager,
        private readonly UriBuilder $uriBuilder,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Context $context,
        private readonly ViewFactoryInterface $viewFactory,
    ) {
    }

    /**
     * @throws SessionNotCreatedException
     * @throws AspectNotFoundException
     * @throws RouteNotFoundException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $getParameters = $request->getQueryParams();
        $action = $getParameters['action'] ?? null;
        $providerId = (string)($getParameters['oauth2-provider'] ?? '');
        /** @var UserAspect $backendUser */
        $backendUser = $this->context->getAspect('backend.user');

        if (
            !$backendUser->isLoggedIn()
            || empty($providerId)
            || !$this->oauth2ProviderManager->hasBackendProvider($providerId)
            || !in_array($action, self::$allowedActions, true)
        ) {
            $response = $this->responseFactory->createResponse(401);

            $this->sessionManager->removeSessionData($request);
            return $this->sessionManager->appendRemoveOAuth2CookieToResponse($response, $request);
        }

        if ($action === 'callback') {
            return $this->callback();
        }
        return $this->authorize($providerId, $request);
    }

    /**
     * @throws SessionNotCreatedException
     * @throws RouteNotFoundException
     */
    private function authorize(string $providerId, ServerRequestInterface $request): ResponseInterface
    {
        $callbackUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'oauth2_registration_authorize',
            [
                'oauth2-provider' => $providerId,
                'action' => 'callback',
            ],
            UriBuilder::ABSOLUTE_URL
        );

        $authorizationUrl = $this->oauth2Service->buildGetResourceOwnerAuthorizationUrl(
            $providerId,
            $callbackUrl,
            $request
        );

        $response = $this->responseFactory->createResponse(302)
            ->withHeader('location', $authorizationUrl);

        return $this->sessionManager->appendOAuth2CookieToResponse($response, $request);
    }

    private function callback(): ResponseInterface
    {
        $viewFactoryData = new ViewFactoryData(
            templatePathAndFilename: 'EXT:oauth2_client/Resources/Private/Templates/Backend/Callback.html'
        );
        $view = $this->viewFactory->create($viewFactoryData);
        $view->assign('path', PathUtility::getAbsoluteWebPath(
            GeneralUtility::getFileAbsFileName('EXT:oauth2_client/Resources/Public/JavaScript/callback.js')
        ));
        $response = $this->responseFactory->createResponse()->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write($view->render());
        return $response;
    }
}
