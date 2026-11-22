<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Tests\Unit\Service;

use League\OAuth2\Client\Provider\GenericProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;
use Waldhacker\Oauth2Client\Service\SiteService;

final class Oauth2ProviderManagerTest extends UnitTestCase
{
    private const PROVIDERS = [
        'both' => ['label' => 'Both'],
        'backend' => ['label' => 'Backend', 'scopes' => ['backend']],
        'frontend' => ['label' => 'Frontend', 'scopes' => ['frontend', 'unknown']],
        'none' => ['label' => 'None', 'scopes' => ['unknown']],
    ];

    #[Test]
    public function providersDefaultToTheGenericProviderAndBothScopes(): void
    {
        $provider = $this->createSubject(['providers' => ['minimal' => ['label' => 'Minimal']]])->getConfiguredProviders()['minimal'];

        self::assertSame('minimal', $provider->getIdentifier());
        self::assertSame('Minimal', $provider->getLabel());
        self::assertSame('', $provider->getDescription());
        self::assertSame('actions-key', $provider->getIconIdentifier());
        self::assertSame(GenericProvider::class, $provider->getImplementationClassName());
        self::assertSame(['backend', 'frontend'], array_values($provider->getScopes()));
        self::assertSame([], $provider->getOptions());
        self::assertSame([], $provider->getCollaborators());
    }

    #[Test]
    public function unknownScopesAreDropped(): void
    {
        $providers = $this->createSubject(['providers' => self::PROVIDERS])->getConfiguredProviders();

        self::assertSame(['frontend'], array_values($providers['frontend']->getScopes()));
        self::assertSame([], array_values($providers['none']->getScopes()));
    }

    public static function missingProvidersDataProvider(): \Generator
    {
        yield 'no configuration' => [[]];
        yield 'providers is not an array' => [['providers' => '']];
        yield 'empty providers' => [['providers' => []]];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[Test]
    #[DataProvider('missingProvidersDataProvider')]
    public function withoutProvidersEveryListIsNull(array $configuration): void
    {
        $subject = $this->createSubject($configuration);

        self::assertNull($subject->getConfiguredProviders());
        self::assertNull($subject->getConfiguredBackendProviders());
        self::assertNull($subject->getConfiguredFrontendProviders());
        self::assertFalse($subject->hasBackendProvider('both'));
    }

    #[Test]
    public function providersAreListedByScope(): void
    {
        $subject = $this->createSubject(['providers' => self::PROVIDERS]);

        self::assertSame(['both', 'backend', 'frontend', 'none'], array_keys($subject->getConfiguredProviders() ?? []));
        self::assertSame(['both', 'backend'], array_keys($subject->getConfiguredBackendProviders() ?? []));
        self::assertSame(['both', 'frontend'], array_keys($subject->getConfiguredFrontendProviders() ?? []));
        self::assertTrue($subject->hasBackendProvider('backend'));
        self::assertFalse($subject->hasBackendProvider('frontend'));
        self::assertFalse($subject->hasBackendProvider('unknown'));
    }

    #[Test]
    public function createProviderPassesOptionsCollaboratorsAndTheRedirectUri(): void
    {
        $subject = $this->createSubject(['providers' => ['mock' => [
            'label' => 'Mock',
            'implementationClassName' => GenericProvider::class,
            'options' => [
                'clientId' => 'client',
                'urlAuthorize' => 'https://idp.example.org/authorize',
                'urlAccessToken' => 'https://idp.example.org/token',
                'urlResourceOwnerDetails' => 'https://idp.example.org/userinfo',
                'redirectUri' => 'https://configured.example.org/',
            ],
        ]]]);

        $configured = $subject->createProvider('mock');
        $overridden = $subject->createProvider('mock', 'https://www.example.org/_oauth2');

        self::assertInstanceOf(GenericProvider::class, $configured);
        self::assertStringContainsString('redirect_uri=' . urlencode('https://configured.example.org/'), $configured->getAuthorizationUrl());
        self::assertStringContainsString('redirect_uri=' . urlencode('https://www.example.org/_oauth2'), $overridden->getAuthorizationUrl());
        self::assertStringStartsWith('https://idp.example.org/authorize?', $overridden->getAuthorizationUrl());
        self::assertStringContainsString('client_id=client', $overridden->getAuthorizationUrl());
    }

    #[Test]
    public function createProviderRejectsAnUnknownProvider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1642867944);

        $this->createSubject(['providers' => self::PROVIDERS])->createProvider('unknown');
    }

    public static function enabledFrontendProvidersDataProvider(): \Generator
    {
        yield 'site setting as list, in site order' => [['enabled_oauth2_providers' => 'frontend, both'], [], ['frontend', 'both']];
        yield 'site setting as array' => [['enabled_oauth2_providers' => ['both']], [], ['both']];
        yield 'language setting wins' => [['enabled_oauth2_providers' => 'both,frontend'], ['enabled_oauth2_providers' => 'frontend'], ['frontend']];
        yield 'backend-only and unknown providers are ignored' => [['enabled_oauth2_providers' => 'backend,unknown,both'], [], ['both']];
        yield 'nothing enabled' => [[], [], null];
        yield 'only backend providers enabled' => [['enabled_oauth2_providers' => 'backend'], [], null];
    }

    /**
     * @param array<string, mixed> $siteConfiguration
     * @param array<string, mixed> $languageConfiguration
     * @param list<string>|null $expected
     */
    #[Test]
    #[DataProvider('enabledFrontendProvidersDataProvider')]
    public function enabledFrontendProvidersFollowTheSiteAndLanguageConfiguration(array $siteConfiguration, array $languageConfiguration, ?array $expected): void
    {
        $request = $this->createFrontendRequest($siteConfiguration, $languageConfiguration);
        $subject = $this->createSubject(['providers' => self::PROVIDERS]);

        $providers = $subject->getEnabledFrontendProviders($request);

        self::assertSame($expected, $providers === null ? null : array_keys($providers));
        self::assertSame($expected !== null && in_array('frontend', $expected, true), $subject->hasFrontendProvider('frontend', $request));
    }

    #[Test]
    public function withoutSiteNoFrontendProviderIsEnabled(): void
    {
        $subject = $this->createSubject(['providers' => self::PROVIDERS]);

        self::assertNull($subject->getEnabledFrontendProviders(new ServerRequest('https://www.example.org/')));
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createSubject(array $configuration): Oauth2ProviderManager
    {
        $extensionConfiguration = self::createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($configuration);

        return new Oauth2ProviderManager($extensionConfiguration, new SiteService());
    }

    /**
     * @param array<string, mixed> $siteConfiguration
     * @param array<string, mixed> $languageConfiguration
     */
    private function createFrontendRequest(array $siteConfiguration, array $languageConfiguration): ServerRequest
    {
        $site = new Site('main', 1, $siteConfiguration + [
            'base' => 'https://www.example.org/',
            'languages' => [
                ['languageId' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8', 'base' => '/'] + $languageConfiguration,
            ],
        ]);

        return (new ServerRequest('https://www.example.org/'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getLanguageById(0));
    }
}
