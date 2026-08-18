<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

interface MiddlewareFactoryInterface
{
    public function create(ContainerInterface $container, MiddlewareSpecification $specification): MiddlewareInterface;
}
