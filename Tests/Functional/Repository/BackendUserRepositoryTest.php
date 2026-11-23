<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Tests\Functional\Repository;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Waldhacker\Oauth2Client\Repository\BackendUserRepository;
use Waldhacker\Oauth2Client\Tests\Functional\AbstractFunctionalTestCase;

final class BackendUserRepositoryTest extends AbstractFunctionalTestCase
{
    private BackendUserRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BackendUsers.csv');
        $this->subject = new BackendUserRepository(
            GeneralUtility::makeInstance(DataHandler::class),
            GeneralUtility::makeInstance(ConnectionPool::class)
        );
    }

    #[Test]
    public function identityLinkedToOneUserReturnsThatUser(): void
    {
        $user = $this->subject->getUserByIdentity('mock', 'linked');

        self::assertSame(10, (int)($user['uid'] ?? 0));
    }

    #[Test]
    public function identityLinkedToTwoUsersReturnsNoUser(): void
    {
        self::assertNull($this->subject->getUserByIdentity('mock', 'twice'));
    }

    #[Test]
    public function identityLinkedTwiceToTheSameUserReturnsNoUser(): void
    {
        self::assertNull($this->subject->getUserByIdentity('mock', 'same-twice'));
    }

    #[Test]
    public function invalidatedIdentitiesAreNeverLookedUp(): void
    {
        self::assertNull($this->subject->getUserByIdentity('_invalid_', '_invalid_'));
        self::assertNull($this->subject->getUserByIdentity('mock', '_invalid_'));
        self::assertNull($this->subject->getUserByIdentity('_invalid_', 'linked'));
    }

    public static function identityWithoutActiveUserDataProvider(): \Generator
    {
        yield 'disabled user' => ['disabled'];
        yield 'deleted user' => ['deleted'];
        yield 'no user (parentid 0)' => ['orphan'];
    }

    #[Test]
    #[DataProvider('identityWithoutActiveUserDataProvider')]
    public function identityWithoutActiveUserReturnsNoUser(string $identifier): void
    {
        self::assertNull($this->subject->getUserByIdentity('mock', $identifier));
    }

    #[Test]
    public function identityOfAnotherProviderReturnsNoUser(): void
    {
        self::assertNull($this->subject->getUserByIdentity('mock-be', 'linked'));
    }
}
