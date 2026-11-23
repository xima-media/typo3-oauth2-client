<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Middleware\Frontend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use Waldhacker\Oauth2Client\Frontend\RedirectRequestService;
use Waldhacker\Oauth2Client\Frontend\RequestStates;
use Waldhacker\Oauth2Client\Service\SiteService;
use Waldhacker\Oauth2Client\Session\SessionManager;

class BeforeAuthenticationHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly SiteService $siteService,
        private readonly RequestStates $requestStates,
        private readonly RedirectRequestService $redirectRequestService
    ) {}

    /**
     * @throws SessionNotCreatedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $mergedRequestedParameters = array_replace_recursive(
            $request->getQueryParams(),
            is_array($request->getParsedBody()) ? $request->getParsedBody() : []
        );
        $getParameters = $request->getQueryParams();

        // TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication->formfield_status
        $loginControllerIsRequested = ($mergedRequestedParameters['logintype'] ?? null) === 'login'
            || $this->requestStates->isCurrentController(RequestStates::CONTROLLER_LOGIN, $request);
        $registrationControllerIsRequested = ($getParameters['tx_oauth2client']['action'] ?? null) === 'authorize'
            || ($getParameters['tx_oauth2client']['action'] ?? null) === 'verify';
        $theRemoteInstanceCallsUsBack = $this->siteService->doesTheRemoteInstanceCallUsBack($request);
        $oauth2FlowIsDone = $this->requestStates->isCurrentAction(RequestStates::ACTION_LOGIN_DONE, $request);

        if ($loginControllerIsRequested && $oauth2FlowIsDone && !$registrationControllerIsRequested) {
            // we are here because of a sub-request from AfterAuthenticationHandler
            $request = $this->requestStates->removeCurrentController($request);
            $request = $this->requestStates->removeCurrentAction($request)
                ->withoutAttribute('oauth2.requestedProvider')
                ->withoutAttribute('oauth2.code')
                ->withoutAttribute('oauth2.state');
        } elseif ($loginControllerIsRequested && $theRemoteInstanceCallsUsBack && !$registrationControllerIsRequested) {
            // we are here because of a redirect from FrontendAuthenticationService::getUser()
            $request = $this->requestStates->setCurrentController(RequestStates::CONTROLLER_LOGIN, $request);
            $request = $this->requestStates->setCurrentAction(RequestStates::ACTION_LOGIN_VERIFY, $request)
                ->withAttribute('oauth2.requestedProvider', $getParameters['oauth2-provider'] ?? null)
                ->withAttribute('oauth2.code', $getParameters['code'] ?? null)
                ->withAttribute('oauth2.state', $getParameters['state'] ?? null);
            $GLOBALS['TYPO3_REQUEST'] = $request;
        } elseif (
            $loginControllerIsRequested
            && !empty($mergedRequestedParameters['oauth2-provider'])
            && !$registrationControllerIsRequested
        ) {
            // we are here because of a login request which should be performed by an oauth2 provider
            $request = $this->requestStates->setCurrentController(RequestStates::CONTROLLER_LOGIN, $request);
            $request = $this->requestStates->setCurrentAction(RequestStates::ACTION_LOGIN_AUTHORIZE, $request)
                ->withAttribute('oauth2.requestedProvider', $mergedRequestedParameters['oauth2-provider']);
            $GLOBALS['TYPO3_REQUEST'] = $request;

            $originalRequestData = $this->redirectRequestService->buildOriginalRequestData($request, true);
            $this->sessionManager->setAndSaveSessionData(
                SessionManager::SESSION_NAME_ORIGINAL_REQUEST,
                $originalRequestData,
                $request
            );
        } elseif ($registrationControllerIsRequested && !$loginControllerIsRequested) {
            $request = $this->requestStates->setCurrentController(RequestStates::CONTROLLER_REGISTRATION, $request);
            $action = $getParameters['tx_oauth2client']['action'] === 'authorize'
                ? RequestStates::ACTION_REGISTRATION_AUTHORIZE
                : RequestStates::ACTION_REGISTRATION_VERIFY;
            $request = $this->requestStates->setCurrentAction($action, $request);
        }

        return $handler->handle($request);
    }
}
