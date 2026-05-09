<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts;

use Psr\Http\Server\MiddlewareInterface;

interface RouteAttributeModifierInterface
{
    /**
     * @return list<class-string<MiddlewareInterface>|non-empty-string>
     */
    public function getMiddleware(): array;

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array;
}
