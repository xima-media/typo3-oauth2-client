<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Backend\Form\RenderType;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Waldhacker\Oauth2Client\Repository\BackendUserRepository;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;

/**
 * Custom FormEngine element for the backend user settings module. Renders the status and the
 * "manage / setup providers" button (formerly handled by the ManageProvidersButtonRenderer userFunc).
 */
class ManageProvidersButtonElement extends AbstractFormElement
{

    public function __construct(
        protected IconFactory $iconFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly Oauth2ProviderManager $oauth2ProviderManager,
        private readonly Context $context
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws AspectNotFoundException
     * @throws RouteNotFoundException
     */
    public function render(): array
    {
        $resultArray = $this->initializeResultArray();

        $languageFile = 'LLL:EXT:oauth2_client/Resources/Private/Language/locallang_be.xlf:';
        $lang = $this->getLanguageService();
        $userId = (int)$this->context->getPropertyFromAspect('backend.user', 'id');
        $activeProviders = $this->backendUserRepository->getActiveProviders($userId);
        $hasActiveProviders = $activeProviders !== [];

        $html = '';
        $html .= $this->renderLabel('oauth2Providers');
        if ($hasActiveProviders) {
            $html .= ' <span class="badge badge-success">'
                . htmlspecialchars($lang->sL($languageFile . 'oauth2Providers.enabled'), ENT_QUOTES | ENT_HTML5)
                . '</span>';
        }
        $html .= '<p class="text-muted">'
            . nl2br(htmlspecialchars($lang->sL($languageFile . 'oauth2Providers.description'), ENT_QUOTES | ENT_HTML5))
            . '</p>';
        if ($this->oauth2ProviderManager->getConfiguredBackendProviders() === null) {
            $html .= '<span class="badge badge-danger">'
                . htmlspecialchars($lang->sL($languageFile . 'oauth2Providers.notAvailable'), ENT_QUOTES | ENT_HTML5)
                . '</span><br />';
        } else {
            $html .= '<a href="'
                . htmlspecialchars(
                    (string)$this->uriBuilder->buildUriFromRoute('oauth2_manage_providers'),
                    ENT_QUOTES | ENT_HTML5
                )
                . '" class="btn btn-' . ($hasActiveProviders ? 'default' : 'success') . '">';
            $html .= $this->iconFactory->getIcon(
                $hasActiveProviders ? 'actions-cog' : 'actions-add',
                IconSize::SMALL
            )->render();
            $html .= ' <span>'
                . htmlspecialchars(
                    $lang->sL(
                        $languageFile . 'oauth2Providers.' . ($hasActiveProviders ? 'manageLinkTitle' : 'setupLinkTitle')
                    ),
                    ENT_QUOTES | ENT_HTML5
                )
                . '</span>';
            $html .= '</a>';
        }

        $resultArray['html'] = $html;
        return $resultArray;
    }
}
