<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Tests\Functional\DataHandling;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Waldhacker\Oauth2Client\Database\Query\Restriction\Oauth2BeUserProviderConfigurationRestriction;
use Waldhacker\Oauth2Client\Database\Query\Restriction\Oauth2FeUserProviderConfigurationRestriction;
use Waldhacker\Oauth2Client\Repository\BackendUserRepository;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;
use Waldhacker\Oauth2Client\Tests\Functional\AbstractFunctionalTestCase;

/**
 * Identity rows must only be written through the OAuth2 flows, never through the record commit endpoint.
 */
final class DataHandlerHookTest extends AbstractFunctionalTestCase
{
    private const BACKEND_TABLE = 'tx_oauth2_beuser_provider_configuration';
    private const FRONTEND_TABLE = 'tx_oauth2_feuser_provider_configuration';
    private const ADMIN = 1;
    private const EDITOR = 2;
    private const COLLEAGUE = 3;
    private const FRONTEND_IDENTITY_EDITOR = 4;

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'oauth2_client' => [
                'providers' => [
                    'mock' => ['label' => 'Mock', 'scopes' => [Oauth2ProviderManager::SCOPE_BACKEND, Oauth2ProviderManager::SCOPE_FRONTEND]],
                    'mock-be' => ['label' => 'Mock BE', 'scopes' => [Oauth2ProviderManager::SCOPE_BACKEND]],
                    'mock-fe' => ['label' => 'Mock FE', 'scopes' => [Oauth2ProviderManager::SCOPE_FRONTEND]],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandler.csv');
    }

    public static function newBackendIdentityDataProvider(): \Generator
    {
        $valid = ['provider' => 'mock-be', 'identifier' => 'new-identity'];
        $invalid = [0, '_invalid_', '_invalid_'];

        foreach (['admin' => self::ADMIN, 'editor' => self::EDITOR] as $actor => $userId) {
            yield $actor . ': parentid only' => [$userId, ['parentid' => self::COLLEAGUE], null, $invalid];
            yield $actor . ': backend provider' => [$userId, $valid, null, [0, 'mock-be', 'new-identity']];
            yield $actor . ': backend provider with parentid' => [$userId, $valid + ['parentid' => self::COLLEAGUE], null, [0, 'mock-be', 'new-identity']];
            yield $actor . ': provider without backend scope' => [$userId, ['provider' => 'mock-fe', 'identifier' => 'new-identity'], null, $invalid];
            yield $actor . ': unknown provider' => [$userId, ['provider' => 'unknown', 'identifier' => 'new-identity'], null, $invalid];
            yield $actor . ': attached to the own user record' => [$userId, $valid, $userId, [$userId === self::ADMIN ? self::ADMIN : 0, 'mock-be', 'new-identity']];
        }
        // Admins may edit be_users, so the inline field assigns the identity to any user
        yield 'admin: attached to another user record' => [self::ADMIN, $valid, self::COLLEAGUE, [self::COLLEAGUE, 'mock-be', 'new-identity']];
        yield 'editor: attached to another user record' => [self::EDITOR, $valid, self::COLLEAGUE, [0, 'mock-be', 'new-identity']];
    }

    /**
     * @param array<string, int|string> $fields
     * @param array{0: int, 1: string, 2: string} $expected parentid, provider, identifier
     */
    #[Test]
    #[DataProvider('newBackendIdentityDataProvider')]
    public function newBackendIdentityKeepsOnlyProviderAndIdentifier(int $actor, array $fields, ?int $attachToUser, array $expected): void
    {
        $data = [self::BACKEND_TABLE => ['NEW1' => $fields]];
        if ($attachToUser !== null) {
            $data['be_users'][$attachToUser]['tx_oauth2_client_configs'] = 'NEW1';
        }

        $this->process($actor, $data);

        $rows = $this->rows(self::BACKEND_TABLE, 'uid > 2');
        self::assertCount(1, $rows);
        self::assertSame($expected, [(int)$rows[0]['parentid'], $rows[0]['provider'], $rows[0]['identifier']]);
    }

    #[Test]
    public function editorCannotTakeOverTheIdentityOfAnotherUser(): void
    {
        $this->process(self::EDITOR, [
            self::BACKEND_TABLE => ['NEW1' => ['provider' => 'mock-be', 'identifier' => 'colleague-identity']],
            'be_users' => [self::EDITOR => ['tx_oauth2_client_configs' => 'NEW1,2']],
        ]);

        $planted = $this->rows(self::BACKEND_TABLE, 'uid > 2');
        self::assertCount(1, $planted);
        self::assertSame(0, (int)$planted[0]['parentid']);

        // The orphaned copy neither logs the editor in nor blocks the colleague's login
        $repository = new BackendUserRepository(
            GeneralUtility::makeInstance(DataHandler::class),
            GeneralUtility::makeInstance(ConnectionPool::class)
        );
        self::assertSame(self::COLLEAGUE, (int)($repository->getUserByIdentity('mock-be', 'colleague-identity')['uid'] ?? 0));
    }

    public static function existingBackendIdentityDataProvider(): \Generator
    {
        foreach (['admin' => self::ADMIN, 'editor' => self::EDITOR] as $actor => $userId) {
            yield $actor . ': parentid' => [$userId, [self::BACKEND_TABLE => [1 => ['parentid' => self::EDITOR]]], self::COLLEAGUE];
            yield $actor . ': provider and identifier' => [$userId, [self::BACKEND_TABLE => [1 => ['provider' => 'mock', 'identifier' => 'changed']]], self::COLLEAGUE];
        }
        yield 'editor: via the own be_users inline field' => [self::EDITOR, ['be_users' => [self::EDITOR => ['tx_oauth2_client_configs' => '1,2']]], self::COLLEAGUE];
        // Admins may edit be_users, so the inline field moves the identity to another user
        yield 'admin: via the be_users inline field' => [self::ADMIN, ['be_users' => [self::EDITOR => ['tx_oauth2_client_configs' => '1,2']]], self::EDITOR];
    }

    /**
     * @param array<string, array<int, array<string, int|string>>> $data
     */
    #[Test]
    #[DataProvider('existingBackendIdentityDataProvider')]
    public function existingBackendIdentityCannotBeChangedThroughItsOwnFields(int $actor, array $data, int $expectedParent): void
    {
        $this->process($actor, $data);

        $row = $this->rows(self::BACKEND_TABLE, 'uid = 1')[0];
        self::assertSame([$expectedParent, 'mock-be', 'colleague-identity'], [(int)$row['parentid'], $row['provider'], $row['identifier']]);
    }

    public static function deleteBackendIdentityDataProvider(): \Generator
    {
        yield 'admin deletes an identity of another user' => [self::ADMIN, 1, true];
        yield 'editor deletes an own identity' => [self::EDITOR, 2, true];
        yield 'editor deletes an identity of another user' => [self::EDITOR, 1, false];
    }

    #[Test]
    #[DataProvider('deleteBackendIdentityDataProvider')]
    public function backendIdentityCanOnlyBeDeletedByItsOwnerOrAnAdmin(int $actor, int $identityUid, bool $deleted): void
    {
        $this->process($actor, [], [self::BACKEND_TABLE => [$identityUid => ['delete' => 1]]]);

        self::assertCount($deleted ? 0 : 1, $this->rows(self::BACKEND_TABLE, 'uid = ' . $identityUid));
    }

    public static function newFrontendIdentityDataProvider(): \Generator
    {
        yield 'admin' => [self::ADMIN, 1];
        yield 'editor with access to the identity table' => [self::FRONTEND_IDENTITY_EDITOR, 1];
        yield 'editor without access to the identity table' => [self::EDITOR, 0];
    }

    #[Test]
    #[DataProvider('newFrontendIdentityDataProvider')]
    public function newFrontendIdentityIsAlwaysInvalidated(int $actor, int $expectedRows): void
    {
        $this->process($actor, [
            self::FRONTEND_TABLE => ['NEW1' => ['parentid' => 1, 'provider' => 'mock-fe', 'identifier' => 'new-identity']],
            'fe_users' => [1 => ['tx_oauth2_client_configs' => 'NEW1,1']],
        ]);

        $rows = $this->rows(self::FRONTEND_TABLE, 'uid > 1');
        self::assertCount($expectedRows, $rows);
        foreach ($rows as $row) {
            self::assertSame([0, '_invalid_', '_invalid_'], [(int)$row['parentid'], $row['provider'], $row['identifier']]);
        }
        self::assertSame(1, (int)$this->rows('fe_users', 'uid = 1')[0]['tx_oauth2_client_configs']);
    }

    #[Test]
    public function existingFrontendIdentityCannotBeChanged(): void
    {
        $this->process(self::ADMIN, [
            self::FRONTEND_TABLE => [1 => ['parentid' => 2, 'provider' => 'mock', 'identifier' => 'changed']],
        ]);

        $row = $this->rows(self::FRONTEND_TABLE, 'uid = 1')[0];
        self::assertSame([1, 'mock-fe', 'frontend-identity'], [(int)$row['parentid'], $row['provider'], $row['identifier']]);
    }

    public static function deleteFrontendIdentityDataProvider(): \Generator
    {
        yield 'admin' => [self::ADMIN, true];
        yield 'editor with access to the identity table' => [self::FRONTEND_IDENTITY_EDITOR, true];
        yield 'editor without access to the identity table' => [self::EDITOR, false];
    }

    #[Test]
    #[DataProvider('deleteFrontendIdentityDataProvider')]
    public function frontendIdentityCanBeDeletedWithAccessToTheIdentityTable(int $actor, bool $deleted): void
    {
        $this->process($actor, [], [self::FRONTEND_TABLE => [1 => ['delete' => 1]]]);

        self::assertCount($deleted ? 0 : 1, $this->rows(self::FRONTEND_TABLE, 'uid = 1'));
    }

    /**
     * @param array<string, array<int|string, array<string, int|string>>> $data
     * @param array<string, array<int, array<string, int>>> $cmd
     */
    private function process(int $actor, array $data, array $cmd = []): void
    {
        $backendUser = $this->setUpBackendUser($actor);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        // The frontend identity restriction only lets backend requests see all rows
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/typo3/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, $cmd, $backendUser);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $table, string $where): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->removeByType(Oauth2BeUserProviderConfigurationRestriction::class)
            ->removeByType(Oauth2FeUserProviderConfigurationRestriction::class);

        return $queryBuilder->select('*')->from($table)->where($where)->orderBy('uid')->executeQuery()->fetchAllAssociative();
    }
}
