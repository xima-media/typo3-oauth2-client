<?php

declare(strict_types=1);

namespace Waldhacker\Oauth2Client\Domain\Model;

use League\OAuth2\Client\Provider\AbstractProvider;

class ProviderConfiguration
{
    public function __construct(
        private readonly string $identifier,
        private readonly string $label,
        private readonly string $description,
        private readonly string $iconIdentifier,
        private readonly string $implementationClassName,
        private readonly array $scopes,
        private readonly array $options,
        private readonly array $collaborators
    ) {
        if (
            !class_exists($this->implementationClassName)
            || !is_a($this->implementationClassName, AbstractProvider::class, true)
        ) {
            throw new \InvalidArgumentException(
                'Registered class ' . $this->implementationClassName
                . ' does not exist or is not an implementation of ' . AbstractProvider::class,
                1642867945
            );
        }
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIconIdentifier(): string
    {
        return $this->iconIdentifier;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getImplementationClassName(): string
    {
        return $this->implementationClassName;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getCollaborators(): array
    {
        return $this->collaborators;
    }
}
