<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Controller\Frontend;

use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Waldhacker\Oauth2Client\Frontend\RedirectRequestService;
use Waldhacker\Oauth2Client\Repository\FrontendUserRepository;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;
use Waldhacker\Oauth2Client\Service\SiteService;
use Waldhacker\Oauth2Client\Session\SessionManager;

class ManageProvidersController extends ActionController
{
    public function __construct(
        private readonly Oauth2ProviderManager $oauth2ProviderManager,
        private readonly SiteService $siteService,
        private readonly FrontendUserRepository $frontendUserRepository,
        private readonly SessionManager $sessionManager,
        private readonly RedirectRequestService $redirectRequestService,
        private readonly Context $context
    ) {
    }

    /**
     * @throws AspectNotFoundException
     * @throws AspectPropertyNotFoundException
     * @throws SessionNotCreatedException
     * @throws Exception
     */
    public function listAction(): ?ResponseInterface
    {
        /** @var ServerRequestInterface $serverRequest */
        $serverRequest = $this->request;
        /** @var UserAspect $frontendUser */
        $frontendUser = $this->context->getAspect('frontend.user');

        if (
            $frontendUser->isLoggedIn()
            && $this->typo3UserIsWithinConfiguredStorage($serverRequest)
        ) {
            $userid = (int)$frontendUser->get('id');
            $this->view->assignMultiple([
                'providers' => $this->oauth2ProviderManager->getEnabledFrontendProviders(),
                'activeProviders' => $this->frontendUserRepository->getActiveProviders($userid)
            ]);
        }

        $psrResponse = $this->htmlResponse();

        if (
            $frontendUser->isLoggedIn()
            && $this->typo3UserIsWithinConfiguredStorage($serverRequest)
        ) {
            $originalRequestData = $this->redirectRequestService->buildOriginalRequestData($serverRequest);
            $this->sessionManager->setAndSaveSessionData(
                SessionManager::SESSION_NAME_ORIGINAL_REQUEST,
                $originalRequestData,
                $serverRequest
            );
            $psrResponse = $this->sessionManager->appendOAuth2CookieToResponse($psrResponse, $serverRequest);
        }

        return $psrResponse;
    }

    /**
     * @throws AspectNotFoundException
     * @throws AspectPropertyNotFoundException
     * @throws Exception
     */
    public function deactivateAction(int $providerId): ResponseInterface
    {
        /** @var UserAspect $frontendUser */
        $frontendUser = $this->context->getAspect('frontend.user');

        if ($frontendUser->isLoggedIn()) {
            $userid = (int)$frontendUser->get('id');
            $this->frontendUserRepository->deactivateProviderByUid($providerId, $userid);
        }

        return $this->redirect('list');
    }

    private function typo3UserIsWithinConfiguredStorage(ServerRequestInterface $request): bool
    {
        $frontendUser = $request->getAttribute('frontend.user');
        if (!($frontendUser instanceof FrontendUserAuthentication)) {
            return false;
        }

        $frontuserStoragePid = $frontendUser->user['pid'] ?? null;
        if ($frontuserStoragePid === null) {
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

        return (int)$frontuserStoragePid === (int)$configuredStoragePid;
    }
}
