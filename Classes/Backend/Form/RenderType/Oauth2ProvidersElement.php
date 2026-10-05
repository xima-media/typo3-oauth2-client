<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Backend\Form\RenderType;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Utility\StringUtility;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;

class Oauth2ProvidersElement extends AbstractFormElement
{
    private const BE_USERS_TABLE = 'be_users';
    private const FE_USERS_TABLE = 'fe_users';

    public function __construct(
        protected IconFactory $iconFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly Oauth2ProviderManager $oauth2ProviderManager,
    ) {
    }

    /**
     * @throws RouteNotFoundException
     */
    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $tableName = $this->data['tableName'];

        if (!($tableName === self::BE_USERS_TABLE || $tableName === self::FE_USERS_TABLE)) {
            return $resultArray;
        }

        $languageFile = 'LLL:EXT:oauth2_client/Resources/Private/Language/locallang_be.xlf:';
        $coreLanguageFile = 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:';

        $html = $childHtml = [];
        $lang = $this->getLanguageService();
        $enabledLabel = htmlspecialchars(
            $lang->sL($languageFile . 'labels.oauth2.enabled'),
            ENT_QUOTES | ENT_HTML5
        );
        $disabledLabel = htmlspecialchars(
            $lang->sL($languageFile . 'labels.oauth2.disabled'),
            ENT_QUOTES | ENT_HTML5
        );
        $status = '<span class="badge badge-danger me-2 t3js-oauth2-status-label" data-alternative-label="'
            . $enabledLabel . '">' . $disabledLabel . '</span>';

        $configuredProviders = $tableName === self::BE_USERS_TABLE
                               ? $this->oauth2ProviderManager->getConfiguredBackendProviders()
                               : $this->oauth2ProviderManager->getConfiguredFrontendProviders();
        $oauth2ProviderConfigurationTable = $tableName === self::BE_USERS_TABLE
                               ? 'tx_oauth2_beuser_provider_configuration'
                               : 'tx_oauth2_feuser_provider_configuration';

        if ($configuredProviders !== null) {
            $activeProvidersDb = array_column($this->data['parameterArray']['fieldConf']['children'], 'databaseRow');
            $activeProviders = array_combine(array_column($activeProvidersDb, 'provider'), $activeProvidersDb);

            // Check if remaining providers are active and/or locked for the user
            foreach ($configuredProviders as $provider) {
                if ($activeProviders[$provider->getIdentifier()] ?? false) {
                    $activeProviders[$provider->getIdentifier()]['providerConfiguration'] = $provider;
                }
            }

            if ($activeProviders !== []) {
                $status = '<span class="badge badge-success me-2 t3js-oauth2-status-label"'
                    . ' data-alternative-label="' . $disabledLabel . '">' . $enabledLabel . '</span>';

                // Add providers list
                $childHtml[] = '<ul class="list-group t3js-oauth2-active-providers-list">';
                foreach ($activeProviders as $identifier => $activeProvider) {
                    $childHtml[] = '<li class="list-group-item" id="provider-'
                        . htmlspecialchars((string)$identifier, ENT_QUOTES | ENT_HTML5)
                        . '" style="line-height: 2.1em;">';
                    $childHtml[] = $this->iconFactory->getIcon(
                        $activeProvider['providerConfiguration']->getIconIdentifier(),
                        IconSize::SMALL
                    );
                    $childHtml[] = htmlspecialchars(
                        $lang->sL($activeProvider['providerConfiguration']->getLabel()),
                        ENT_QUOTES | ENT_HTML5
                    );

                    $deleteThis = $this->uriBuilder->buildUriFromRoute(
                        'tce_db',
                        [
                            'cmd' => [
                                $oauth2ProviderConfigurationTable => [
                                    $activeProvider['uid'] => [
                                        'delete' => 1,
                                    ],
                                ],
                            ],
                            'redirect' => $GLOBALS['TYPO3_REQUEST']->getAttribute('normalizedParams')->getRequestUri(),
                        ]
                    );
                    $childHtml[] = '<a href="' . $deleteThis . '" ';
                    $childHtml[] = ' class="btn btn-default btn-sm pull-right t3js-modal-trigger"';
                    $childHtml[] = ' data-title="' .
                                   htmlspecialchars(
                                       sprintf(
                                           $lang->sL($languageFile . 'buttons.deactivateProvider'),
                                           $lang->sL($activeProvider['providerConfiguration']->getLabel())
                                       ),
                                       ENT_QUOTES | ENT_HTML5
                                   ) .
                                   '"';
                    $childHtml[] = ' data-bs-content="' .
                                   htmlspecialchars(
                                       sprintf(
                                           $lang->sL($languageFile . 'buttons.deactivateProvider.confirmation.text'),
                                           $lang->sL($activeProvider['providerConfiguration']->getLabel())
                                       ),
                                       ENT_QUOTES | ENT_HTML5
                                   ) .
                                   '"';
                    $childHtml[] = ' data-button-close-text="'
                        . htmlspecialchars($lang->sL($coreLanguageFile . 'labels.cancel'), ENT_QUOTES | ENT_HTML5)
                        . '"';
                    $childHtml[] = ' data-button-ok-text="'
                        . htmlspecialchars($lang->sL($coreLanguageFile . 'labels.deactivate'), ENT_QUOTES | ENT_HTML5)
                        . '"';
                    $childHtml[] = ' data-severity="warning"';
                    $childHtml[] = ' title="' .
                                   htmlspecialchars(
                                       sprintf(
                                           $lang->sL($languageFile . 'buttons.deactivateProvider'),
                                           $lang->sL($activeProvider['providerConfiguration']->getLabel())
                                       ),
                                       ENT_QUOTES | ENT_HTML5
                                   ) .
                                   '"';
                    $childHtml[] = '>';
                    $childHtml[] = $this->iconFactory->getIcon('actions-delete', IconSize::SMALL)->render('inline');
                    $childHtml[] = '</a>';
                    $childHtml[] = '</li>';
                }
                $childHtml[] = '</ul>';
            }
        }
        $fieldId = 't3js-form-field-oauth2-id' . StringUtility::getUniqueId('-');

        $html[] = $this->renderLabel($fieldId);
        $html[] = $status;
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item" id="'
            . htmlspecialchars($fieldId) . '">';
        $html[] = '<div class="form-control-wrap" style="max-width: '
            . $this->formMaxWidth($this->defaultInputWidth) . 'px">';
        $html[] = '<div class="form-wizards-wrap">';
        $html[] = '<div class="form-wizards-element">';
        $html[] = implode(PHP_EOL, $childHtml);
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';

        $resultArray['html'] = implode(PHP_EOL, $html);
        return $resultArray;
    }
}
