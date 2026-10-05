<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Service;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class SiteService
{
    public const CALLBACK_SLUG = '_oauth2';

    public function getSite(?ServerRequestInterface $request = null): ?SiteInterface
    {
        $request = $this->getRequest($request);
        return $request->getAttribute('site');
    }

    public function getLanguage(?ServerRequestInterface $request = null): ?SiteLanguage
    {
        $request = $this->getRequest($request);
        return $request->getAttribute('language');
    }

    public function buildCallbackUri(array $queryParameters, ?ServerRequestInterface $request = null): string
    {
        return sprintf(
            '%s?%s',
            $this->buildCallbackBaseUri($request),
            http_build_query($queryParameters)
        );
    }

    public function doesTheRemoteInstanceCallUsBack(?ServerRequestInterface $request = null): bool
    {
        $request = $this->getRequest($request);
        $callbackUri = new Uri($this->buildCallbackBaseUri($request));
        return trim($request->getUri()->getPath(), '/') === trim($callbackUri->getPath(), '/');
    }

    public function getBaseUri(?ServerRequestInterface $request = null): string
    {
        $request = $this->getRequest($request);

        $language = $this->getLanguage($request);
        if ($language) {
            $base = (string)$language->getBase();
        } else {
            $base = sprintf('%s://%s', $request->getUri()->getScheme(), $request->getUri()->getAuthority());
        }
        return rtrim($base, '/');
    }

    private function buildCallbackBaseUri(?ServerRequestInterface $request = null): string
    {
        return sprintf(
            '%s/%s',
            $this->getBaseUri($request),
            $this->buildCallbackSlug($request)
        );
    }

    private function buildCallbackSlug(?ServerRequestInterface $request = null): string
    {
        /** @var Site|null $site */
        $site = $this->getSite($request);
        $language = $this->getLanguage($request);
        if (!$site instanceof Site || $language === null) {
            return self::CALLBACK_SLUG;
        }

        $siteConfiguration = $site->getConfiguration();
        $languageConfiguration = $language->toArray();
        $callbackSlug = empty($languageConfiguration['oauth2_callback_slug'])
                              ? ($siteConfiguration['oauth2_callback_slug'] ?? '')
                              : ($languageConfiguration['oauth2_callback_slug']);
        $callbackSlug = trim($callbackSlug, '/');

        return empty($callbackSlug) ? self::CALLBACK_SLUG : $callbackSlug;
    }

    private function getRequest(?ServerRequestInterface $request = null): ServerRequestInterface
    {
        $request = $request ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
        if (!($request instanceof ServerRequestInterface)) {
            throw new InvalidArgumentException(
                sprintf('Request must implement "%s"', ServerRequestInterface::class),
                1643446000
            );
        }
        return $request;
    }
}
