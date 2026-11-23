<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Frontend;

use Psr\Http\Message\ServerRequestInterface;

class RequestStates
{
    public const CONTROLLER_ATTRIBUTE = 'oauth2.controller';
    public const ACTION_ATTRIBUTE = 'oauth2.action';
    public const CONTROLLER_LOGIN = 'login';
    public const CONTROLLER_REGISTRATION = 'registration';
    public const ACTION_LOGIN_AUTHORIZE = 'login.authorize';
    public const ACTION_LOGIN_VERIFY = 'login.verify';
    public const ACTION_LOGIN_DONE = 'login.done';
    public const ACTION_REGISTRATION_AUTHORIZE = 'registration.authorize';
    public const ACTION_REGISTRATION_VERIFY = 'registration.verify';

    public function isCurrentController(string $controllerName, ServerRequestInterface $request): bool
    {
        return $request->getAttribute(self::CONTROLLER_ATTRIBUTE) === $controllerName;
    }

    public function isCurrentAction(string $actionName, ServerRequestInterface $request): bool
    {
        return $request->getAttribute(self::ACTION_ATTRIBUTE) === $actionName;
    }

    public function setCurrentController(
        string $controllerName,
        ServerRequestInterface $request
    ): ServerRequestInterface {
        return $request->withAttribute(self::CONTROLLER_ATTRIBUTE, $controllerName);
    }

    public function setCurrentAction(string $actionName, ServerRequestInterface $request): ServerRequestInterface
    {
        return $request->withAttribute(self::ACTION_ATTRIBUTE, $actionName);
    }

    public function removeCurrentController(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request->withoutAttribute(self::CONTROLLER_ATTRIBUTE);
    }

    public function removeCurrentAction(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request->withoutAttribute(self::ACTION_ATTRIBUTE);
    }
}
