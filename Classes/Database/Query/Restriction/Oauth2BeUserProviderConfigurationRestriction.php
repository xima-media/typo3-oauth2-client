<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Database\Query\Restriction;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EnforceableQueryRestrictionInterface as EnforceableQueryRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface as QueryRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/*
 * Allow access only to tx_oauth2_beuser_provider_configuration records that were created
 * for the current logged in backend user.
 */
class Oauth2BeUserProviderConfigurationRestriction implements QueryRestriction, EnforceableQueryRestriction
{
    private const OAUTH2_BE_TABLE = 'tx_oauth2_beuser_provider_configuration';
    private int $backendUserId;
    private bool $isAdmin;

    /**
     * @throws AspectNotFoundException
     */
    public function __construct(?Context $context = null)
    {
        /** @var Context $context */
        $context = $context ?? GeneralUtility::makeInstance(Context::class);

        $this->backendUserId = 0;
        $this->isAdmin = false;
        if ($context->hasAspect('backend.user')) {
            $this->backendUserId = (int)$context->getPropertyFromAspect('backend.user', 'id');
            $this->isAdmin = $context->getPropertyFromAspect('backend.user', 'isAdmin');
        }
    }

    public function buildExpression(array $queriedTables, ExpressionBuilder $expressionBuilder): CompositeExpression
    {
        $constraints = [];
        $userWithEditRightsColumn = $GLOBALS['TCA'][
            self::OAUTH2_BE_TABLE
        ]['ctrl']['enablecolumns']['be_user'] ?? 'parentid';

        foreach ($queriedTables as $tableAlias => $tableName) {
            if ($tableName !== self::OAUTH2_BE_TABLE || $this->isAdmin) {
                continue;
            }

            $constraints[] = $expressionBuilder->eq(
                $tableAlias . '.' . $userWithEditRightsColumn,
                $this->backendUserId
            );
        }

        return $expressionBuilder->and(...$constraints);
    }

    public function isEnforced(): bool
    {
        return true;
    }
}
