<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Tests\Functional;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'co-stack/typo3-oauth2-client',
    ];

    protected function setUp(): void
    {
        // TYPO3 v14 merged EXT:setup into EXT:backend
        if ((new Typo3Version())->getMajorVersion() < 14) {
            $this->coreExtensionsToLoad[] = 'setup';
        }
        parent::setUp();
    }
}
