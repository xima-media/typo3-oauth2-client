<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Middleware\Frontend;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Http\Application;
use Waldhacker\Oauth2Client\Controller\Frontend\RegistrationController;
use Waldhacker\Oauth2Client\Frontend\RedirectRequestService;
use Waldhacker\Oauth2Client\Frontend\RequestStates;
use Waldhacker\Oauth2Client\Service\SiteService;
use Waldhacker\Oauth2Client\Session\SessionManager;

class AfterAuthenticationHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly RegistrationController $registrationController,
        private readonly SessionManager $sessionManager,
        private readonly SiteService $siteService,
        private readonly RequestStates $requestStates,
        private readonly RedirectRequestService $redirectRequestService,
        private readonly Context $context,
        private readonly ResponseFactoryInterface $responseFactory
    ) {}

    /**
     * @throws ContainerExceptionInterface
     * @throws AspectNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws SessionNotCreatedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $mergedRequestedParameters = array_replace_recursive(
            $request->getQueryParams(),
            is_array($request->getParsedBody()) ? $request->getParsedBody() : []
        );

        // TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication->formfield_status
        $loginControllerIsRequested = ($mergedRequestedParameters['logintype'] ?? null) === 'login'
            || $this->requestStates->isCurrentController(RequestStates::CONTROLLER_LOGIN, $request);
        $registrationControllerIsRequested = $this->requestStates->isCurrentController(
            RequestStates::CONTROLLER_REGISTRATION,
            $request
        );
        $theRemoteInstanceCallsUsBack = $this->siteService->doesTheRemoteInstanceCallUsBack($request);

        if ($loginControllerIsRequested && $theRemoteInstanceCallsUsBack && !$registrationControllerIsRequested) {
            $originalRequestData = $this->sessionManager->getSessionData(
                SessionManager::SESSION_NAME_ORIGINAL_REQUEST,
                $request
            );
            $this->sessionManager->removeSessionData($request);
            if (empty($originalRequestData)) {
                $response = $this->responseFactory
                    ->createResponse(302, 'OAuth2: Done, but unable to find the original requested location')
                    ->withHeader('location', $this->siteService->getBaseUri());

                return $this->sessionManager->appendRemoveOAuth2CookieToResponse($response, $request);
            }

            if ($originalRequestData['method'] === 'POST') {
                $subRequest = new ServerRequest(
                    $originalRequestData['uri'],
                    $originalRequestData['method'],
                    'php://input',
                    $originalRequestData['headers'],
                    $_SERVER
                );

                $subRequest = $subRequest->withProtocolVersion($originalRequestData['protocolVersion']);
                $subRequest = $subRequest->withParsedBody($originalRequestData['parsedBody']);
                $subRequest = $this->requestStates->setCurrentAction(RequestStates::ACTION_LOGIN_DONE, $subRequest);

                unset($_COOKIE[$this->sessionManager->getOAuth2CookieName($request)]);
                /** @var FrontendUserAuthentication $frontendUser */
                $frontendUser = $request->getAttribute('frontend.user');
                /** @var UserAspect $frontendUserAspect */
                $frontendUserAspect = $this->context->getAspect('frontend.user');
                $userIsLoggedIn = $frontendUser instanceof FrontendUserAuthentication
                    && $frontendUserAspect->isLoggedIn();
                if ($userIsLoggedIn) {
                    $sessionId = $request->getAttribute('frontend.user')->getSession()->getIdentifier();

                    $_COOKIE = array_replace_recursive(
                        $_COOKIE,
                        [FrontendUserAuthentication::getCookieName() => $sessionId]
                    );
                }

                $subRequest = $subRequest->withCookieParams($_COOKIE ?? []);
                $GLOBALS['TSFE'] = null;

                $response = $this->performSubRequest($subRequest);

                $this->sessionManager->removeSessionData($request);
                // response code was not changed by legacy code (eg. extbase redirect)
                // make a (GET) redirect to the post url after the post subrequest.
                if (
                    http_response_code() === 200
                    && $response->getStatusCode() >= 200
                    && $response->getStatusCode() <= 299
                ) {
                    $redirectUri = $this->redirectRequestService->removeOauth2ParametersFromUri(
                        $originalRequestData['uri']
                    );
                    $response = $this->responseFactory
                        ->createResponse(302, 'OAuth2: Done. Redirection via GET to original requested POST location')
                        ->withHeader('location', $redirectUri);
                }

                return $this->sessionManager->appendRemoveOAuth2CookieToResponse($response, $request);
            }

            $redirectUri = $this->redirectRequestService->removeOauth2ParametersFromUri($originalRequestData['uri']);
            $response = $this->responseFactory
                ->createResponse(302, 'OAuth2: Done. Redirection to original requested location')
                ->withHeader('location', $redirectUri);

            /** @var FrontendUserAuthentication $frontendUser */
            $frontendUser = $request->getAttribute('frontend.user');
            /** @var UserAspect $frontendUserAspect */
            $frontendUserAspect = $this->context->getAspect('frontend.user');
            $userIsLoggedIn = $frontendUser instanceof FrontendUserAuthentication
                && $frontendUserAspect->isLoggedIn();
            if ($userIsLoggedIn) {
                $response = $request->getAttribute('frontend.user')->appendCookieToResponse($response);
            }

            return $this->sessionManager->appendRemoveOAuth2CookieToResponse($response, $request);
        }
        if ($registrationControllerIsRequested && !$loginControllerIsRequested) {
            if (
                $this->requestStates->isCurrentAction(RequestStates::ACTION_REGISTRATION_AUTHORIZE, $request)
                || (
                    $this->requestStates->isCurrentAction(RequestStates::ACTION_REGISTRATION_VERIFY, $request)
                    && $theRemoteInstanceCallsUsBack
                )
            ) {
                $GLOBALS['TYPO3_REQUEST'] = $request;
                return $this->registrationController->handleRequest($request);
            }
        }

        return $handler->handle($request);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function performSubRequest(ServerRequestInterface $request): ResponseInterface
    {
        return GeneralUtility::getContainer()->get(Application::class)->handle($request);
    }
}
