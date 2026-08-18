<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Test\Stub;

use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

final readonly class RouteAttributeModifierStub implements RouteAttributeModifierInterface
{
    public function __construct(
        /** @var list<class-string<MiddlewareInterface>|MiddlewareSpecification|non-empty-string> */
        private array $middleware = [],
        /** @var array<string, mixed> */
        private array $defaults = []) {}

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }
}
