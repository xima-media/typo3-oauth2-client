<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Middleware\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BeforeAuthenticationHandler implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $GLOBALS['TYPO3_REQUEST'] = $request;
        return $handler->handle($request);
    }
}
