<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Test\Stub;

use Sirix\Mezzio\Routing\Contracts\AggregatingRouteAttributeModifierInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

final readonly class AggregatingRouteAttributeModifierStub implements AggregatingRouteAttributeModifierInterface
{
    /**
     * @param array<string, mixed>                                              $defaultsToMerge
     * @param array<non-empty-string, MiddlewareSpecification|non-empty-string> $uniqueMiddleware
     */
    public function __construct(private array $defaultsToMerge = [], private array $uniqueMiddleware = []) {}

    public function getMiddleware(): array
    {
        return [];
    }

    public function getDefaults(): array
    {
        return [];
    }

    public function mergeDefaults(array $defaults): array
    {
        return [...$defaults, ...$this->defaultsToMerge];
    }

    public function getUniqueMiddleware(): array
    {
        return $this->uniqueMiddleware;
    }
}
