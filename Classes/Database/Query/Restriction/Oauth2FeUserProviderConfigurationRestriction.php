<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Database\Query\Restriction;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EnforceableQueryRestrictionInterface as EnforceableQueryRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface as QueryRestriction;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/*
 * Allow access only to tx_oauth2_feuser_provider_configuration records that were created
 * for the current logged in frontend user.
 */
class Oauth2FeUserProviderConfigurationRestriction implements QueryRestriction, EnforceableQueryRestriction
{
    private const OAUTH2_FE_TABLE = 'tx_oauth2_feuser_provider_configuration';
    private int $frontendUserId;
    private bool $isBackendUser;

    /**
     * @throws AspectNotFoundException
     */
    public function __construct(?Context $context = null)
    {
        /** @var Context $context */
        $context = $context ?? GeneralUtility::makeInstance(Context::class);

        $this->frontendUserId = 0;
        $this->isBackendUser = false;
        if ($context->hasAspect('frontend.user')) {
            $this->frontendUserId = (int)$context->getPropertyFromAspect('frontend.user', 'id');
        }

        if (
            ($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface
            && is_int($GLOBALS['TYPO3_REQUEST']->getAttribute('applicationType'))
            && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()
        ) {
            $this->isBackendUser = true;
        }
    }

    public function buildExpression(array $queriedTables, ExpressionBuilder $expressionBuilder): CompositeExpression
    {
        $constraints = [];
        $userWithEditRightsColumn = $GLOBALS['TCA'][
            self::OAUTH2_FE_TABLE
        ]['ctrl']['enablecolumns']['fe_user'] ?? 'parentid';

        foreach ($queriedTables as $tableAlias => $tableName) {
            if ($tableName !== 'tx_oauth2_feuser_provider_configuration' || $this->isBackendUser) {
                continue;
            }

            $constraints[] = $expressionBuilder->eq(
                $tableAlias . '.' . $userWithEditRightsColumn,
                $this->frontendUserId
            );
        }

        return $expressionBuilder->and(...$constraints);
    }

    public function isEnforced(): bool
    {
        return true;
    }
}
