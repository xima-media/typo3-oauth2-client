<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Controller\Backend;

use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use Waldhacker\Oauth2Client\Repository\BackendUserRepository;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;

readonly class ManageProvidersController
{
    public function __construct(
        private Oauth2ProviderManager $oauth2ProviderManager,
        private BackendUserRepository $backendUserRepository,
        private UriBuilder $uriBuilder,
        private IconFactory $iconFactory,
        private ModuleTemplateFactory $moduleTemplateFactory,
        private Context $context,
    ) {
    }

    /**
     * @throws AspectNotFoundException
     * @throws RouteNotFoundException
     * @throws Exception
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $this->addButtons($request, $moduleTemplate);
        $userid = (int)$this->context->getPropertyFromAspect('backend.user', 'id');
        $moduleTemplate->assignMultiple([
            'providers' => $this->oauth2ProviderManager->getConfiguredBackendProviders(),
            'activeProviders' => $this->backendUserRepository->getActiveProviders($userid)
        ]);
        return $moduleTemplate->renderResponse('Backend/ManageProviders');
    }

    /**
     * @throws RouteNotFoundException
     */
    private function addButtons(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): void
    {
        $languageFile = 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:';
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $button = $buttonBar
            ->makeLinkButton()
            ->setHref((string)$this->uriBuilder->buildUriFromRoute('user_setup'))
            ->setIcon($this->iconFactory->getIcon('actions-view-go-back', IconSize::SMALL))
            ->setTitle($this->getLanguageService()->sL($languageFile . 'labels.goBack'))
            ->setShowLabelText(true);
        $buttonBar->addButton($button);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
