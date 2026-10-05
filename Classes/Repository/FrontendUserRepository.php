<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Repository;

use DateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Waldhacker\Oauth2Client\Backend\DataHandling\DataHandlerHook;
use Waldhacker\Oauth2Client\Database\Query\Restriction\Oauth2FeUserProviderConfigurationRestriction;

class FrontendUserRepository
{
    private const OAUTH2_FE_CONFIG_TABLE = 'tx_oauth2_feuser_provider_configuration';

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {
    }

    /**
     * @throws Exception
     */
    public function getUserByIdentity(string $provider, string $identifier, int $storagePid): ?array
    {
        if ($provider === DataHandlerHook::INVALID_TOKEN || $identifier === DataHandlerHook::INVALID_TOKEN) {
            return null;
        }
        $userWithEditRightsColumn = $GLOBALS['TCA'][
            self::OAUTH2_FE_CONFIG_TABLE
        ]['ctrl']['enablecolumns']['fe_user'] ?? 'parentid';

        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->getRestrictions()->removeByType(Oauth2FeUserProviderConfigurationRestriction::class);
        $result = $qb->select('fe_users.*')
            ->from(self::OAUTH2_FE_CONFIG_TABLE, 'config')
            ->join('config', 'fe_users', 'fe_users', 'config.' . $userWithEditRightsColumn . '=fe_users.uid')
            ->where(
                $qb->expr()->and(
                    $qb->expr()->eq('identifier', $qb->createNamedParameter($identifier)),
                    $qb->expr()->eq('provider', $qb->createNamedParameter($provider)),
                    $qb->expr()->neq('identifier', $qb->createNamedParameter(DataHandlerHook::INVALID_TOKEN)),
                    $qb->expr()->neq('provider', $qb->createNamedParameter(DataHandlerHook::INVALID_TOKEN)),
                    $qb->expr()->eq('fe_users.pid', $qb->createNamedParameter($storagePid, ParameterType::INTEGER))
                )
            )
            ->executeQuery();

        $result = $result->fetchAllAssociative();

        // @todo: log warning if more than one user matches
        // Do not login if more than one user matches!
        return empty($result) || empty($result[0]) || count($result) > 1 ? null : $result[0];
    }

    /**
     * @throws Exception
     */
    public function persistIdentityForUser(string $provider, string $identifier, int $userid): void
    {
        if (empty($provider)) {
            throw new InvalidArgumentException('"provider" must not be empty', 1642867960);
        }
        if (empty($identifier)) {
            throw new InvalidArgumentException('"identifier" must not be empty', 1642867961);
        }

        $now = new DateTime();
        $userWithEditRightsColumn = $GLOBALS['TCA'][
            self::OAUTH2_FE_CONFIG_TABLE
        ]['ctrl']['enablecolumns']['fe_user'] ?? 'parentid';

        $activeConfigurationUids = array_map(
            'intval',
            array_column($this->getConfigurationsByIdentity($provider, $identifier, $userid), 'uid')
        );
        if (!empty($activeConfigurationUids)) {
            $qb = $this->connectionPool->getQueryBuilderForTable(self::OAUTH2_FE_CONFIG_TABLE);
            $qb->delete(self::OAUTH2_FE_CONFIG_TABLE)
                ->where(
                    $qb->expr()->and(
                        $qb->expr()->eq(
                            $userWithEditRightsColumn,
                            $qb->createNamedParameter($userid, ParameterType::INTEGER)
                        ),
                        $qb->expr()->in(
                            'uid',
                            $qb->createNamedParameter($activeConfigurationUids, ArrayParameterType::INTEGER)
                        )
                    )
                )
                ->executeStatement();
        }

        $qb = $this->connectionPool->getQueryBuilderForTable(self::OAUTH2_FE_CONFIG_TABLE);
        $qb->insert(self::OAUTH2_FE_CONFIG_TABLE)
            ->setValue('pid', 0)
            ->setValue('crdate', $now->format('U'))
            ->setValue('tstamp', $now->format('U'))
            ->setValue('parentid', $userid)
            ->setValue('provider', $provider)
            ->setValue('identifier', $identifier)
            ->executeStatement();

        $activeProviders = $this->getActiveProviders($userid);
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->update('fe_users')
            ->set('tx_oauth2_client_configs', count($activeProviders))
            ->where(
                $qb->expr()->and(
                    $qb->expr()->eq('uid', $qb->createNamedParameter($userid, ParameterType::INTEGER))
                )
            )
            ->executeStatement();
    }

    /**
     * @throws Exception
     */
    public function getActiveProviders($userid): array
    {
        $userWithEditRightsColumn = $GLOBALS['TCA'][
            self::OAUTH2_FE_CONFIG_TABLE
        ]['ctrl']['enablecolumns']['fe_user'] ?? 'parentid';

        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->getRestrictions()->removeByType(Oauth2FeUserProviderConfigurationRestriction::class);
        $result = $qb->select('config.*')
            ->from(self::OAUTH2_FE_CONFIG_TABLE, 'config')
            ->join('config', 'fe_users', 'fe_users', 'config.' . $userWithEditRightsColumn . '=fe_users.uid')
            ->where(
                $qb->expr()->and(
                    $qb->expr()->eq('fe_users.uid', $qb->createNamedParameter($userid, ParameterType::INTEGER)),
                    $qb->expr()->neq('config.identifier', $qb->createNamedParameter(DataHandlerHook::INVALID_TOKEN)),
                    $qb->expr()->neq('config.provider', $qb->createNamedParameter(DataHandlerHook::INVALID_TOKEN))
                )
            )
            ->executeQuery();

        $result = $result->fetchAllAssociative();

        $keys = array_column($result, 'provider');
        return array_combine($keys, $result);
    }

    /**
     * @throws Exception
     */
    public function deactivateProviderByUid(int $providerUid, int $userid): void
    {
        $activeProviders = $this->getActiveProviders($userid);
        if (!in_array($providerUid, array_map('intval', array_column($activeProviders, 'uid')), true)) {
            return;
        }

        $userWithEditRightsColumn = $GLOBALS['TCA'][
            self::OAUTH2_FE_CONFIG_TABLE
        ]['ctrl']['enablecolumns']['fe_user'] ?? 'parentid';

        $qb = $this->connectionPool->getQueryBuilderForTable(self::OAUTH2_FE_CONFIG_TABLE);
        $qb->delete(self::OAUTH2_FE_CONFIG_TABLE)
            ->where(
                $qb->expr()->and(
                    $qb->expr()->eq(
                        $userWithEditRightsColumn,
                        $qb->createNamedParameter($userid, ParameterType::INTEGER)
                    ),
                    $qb->expr()->eq('uid', $qb->createNamedParameter($providerUid, ParameterType::INTEGER))
                )
            )
            ->executeStatement();

        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->update('fe_users')
            ->set('tx_oauth2_client_configs', count($activeProviders) - 1)
            ->where(
                $qb->expr()->and(
                    $qb->expr()->eq('uid', $qb->createNamedParameter($userid, ParameterType::INTEGER))
                )
            )
            ->executeStatement();
    }

    /**
     * @throws Exception
     */
    private function getConfigurationsByIdentity(string $provider, string $identifier, int $userid): array
    {
        $userWithEditRightsColumn = $GLOBALS['TCA'][
            self::OAUTH2_FE_CONFIG_TABLE
        ]['ctrl']['enablecolumns']['fe_user'] ?? 'parentid';

        $qb = $this->connectionPool->getQueryBuilderForTable(self::OAUTH2_FE_CONFIG_TABLE);
        $qb->getRestrictions()->removeByType(Oauth2FeUserProviderConfigurationRestriction::class);
        $result = $qb->select('*')
            ->from(self::OAUTH2_FE_CONFIG_TABLE)
            ->where(
                $qb->expr()->and(
                    $qb->expr()->eq('identifier', $qb->createNamedParameter($identifier)),
                    $qb->expr()->eq('provider', $qb->createNamedParameter($provider)),
                    $qb->expr()->neq('identifier', $qb->createNamedParameter(DataHandlerHook::INVALID_TOKEN)),
                    $qb->expr()->neq('provider', $qb->createNamedParameter(DataHandlerHook::INVALID_TOKEN)),
                    $qb->expr()->eq(
                        $userWithEditRightsColumn,
                        $qb->createNamedParameter($userid, ParameterType::INTEGER)
                    )
                )
            )
            ->executeQuery();

        return $result->fetchAllAssociative();
    }
}
