<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Tests\Functional\Repository;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Waldhacker\Oauth2Client\Repository\FrontendUserRepository;
use Waldhacker\Oauth2Client\Tests\Functional\AbstractFunctionalTestCase;

final class FrontendUserRepositoryTest extends AbstractFunctionalTestCase
{
    private FrontendUserRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FrontendUsers.csv');
        $this->subject = new FrontendUserRepository(GeneralUtility::makeInstance(ConnectionPool::class));
    }

    #[Test]
    public function identityLinkedToOneUserInTheStorageFolderReturnsThatUser(): void
    {
        $user = $this->subject->getUserByIdentity('mock', 'linked', 2);

        self::assertSame(1, (int)($user['uid'] ?? 0));
    }

    #[Test]
    public function identityOfAUserInAnotherFolderReturnsNoUser(): void
    {
        self::assertNull($this->subject->getUserByIdentity('mock', 'other-folder', 2));
    }

    #[Test]
    public function identityLinkedToTwoUsersInTheStorageFolderReturnsNoUser(): void
    {
        self::assertNull($this->subject->getUserByIdentity('mock', 'twice', 2));
    }

    #[Test]
    public function identityLinkedOncePerStorageFolderReturnsTheUserOfTheRequestedFolder(): void
    {
        self::assertSame(6, (int)($this->subject->getUserByIdentity('mock', 'one-per-folder', 2)['uid'] ?? 0));
        self::assertSame(7, (int)($this->subject->getUserByIdentity('mock', 'one-per-folder', 3)['uid'] ?? 0));
    }

    #[Test]
    public function identityOfADisabledUserReturnsNoUser(): void
    {
        self::assertNull($this->subject->getUserByIdentity('mock', 'disabled', 2));
    }

    #[Test]
    public function invalidatedIdentitiesAreNeverLookedUp(): void
    {
        self::assertNull($this->subject->getUserByIdentity('_invalid_', '_invalid_', 2));
    }
}
