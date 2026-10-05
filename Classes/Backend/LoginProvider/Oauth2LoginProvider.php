<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Backend\LoginProvider;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;

#[Autoconfigure(public: true)]
class Oauth2LoginProvider implements LoginProviderInterface
{
    public const PROVIDER_ID = '1616569531';

    public function __construct(
        private readonly Oauth2ProviderManager $oauth2ProviderManager,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
    }

    /**
     * Backend login rendering is handled by modifyView(). On TYPO3 v13 the
     * LoginController calls modifyView() whenever it exists and only falls back
     * to render(); v14 removed render() from the interface entirely. This stub
     * therefore only satisfies the deprecated v13 LoginProviderInterface and is
     * never actually invoked.
     *
     * @param mixed $view StandaloneView on TYPO3 v13
     */
    public function render($view, PageRenderer $pageRenderer, LoginController $loginController): void
    {
    }

    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        $viewConfiguration = $this->getViewConfiguration();

        if ($view instanceof FluidViewAdapter) {
            $templatePaths = $view->getRenderingContext()->getTemplatePaths();

            $templatePaths->setTemplateRootPaths(array_merge(
                $templatePaths->getTemplateRootPaths(),
                ['EXT:oauth2_client/Resources/Private/Templates/Backend/'],
                $viewConfiguration['templateRootPaths'] ?? []
            ));
            $templatePaths->setLayoutRootPaths(array_merge(
                $templatePaths->getLayoutRootPaths(),
                ['EXT:oauth2_client/Resources/Private/Layouts/Backend/'],
                $viewConfiguration['layoutRootPaths'] ?? []
            ));
            $templatePaths->setPartialRootPaths(array_merge(
                $templatePaths->getPartialRootPaths(),
                ['EXT:oauth2_client/Resources/Private/Partials/Backend/'],
                $viewConfiguration['partialRootPaths'] ?? []
            ));
        }

        $view->assign('providers', $this->oauth2ProviderManager->getConfiguredBackendProviders());

        return $viewConfiguration['template'] ?? 'Oauth2LoginProvider';
    }

    /**
     * @return array<string, mixed>
     */
    private function getViewConfiguration(): array
    {
        try {
            $extensionConfiguration = $this->extensionConfiguration->get('oauth2_client');
        } catch (ExtensionConfigurationExtensionNotConfiguredException | ExtensionConfigurationPathDoesNotExistException) {
            return [];
        }

        return is_array($extensionConfiguration['view'] ?? null) ? $extensionConfiguration['view'] : [];
    }
}
