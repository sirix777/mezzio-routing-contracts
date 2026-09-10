<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Test\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Contracts\AggregatingRouteAttributeModifierInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;
use Sirix\Mezzio\Routing\Contracts\Test\Stub\AggregatingRouteAttributeModifierStub;

use function interface_exists;

#[CoversNothing]
final class AggregatingRouteAttributeModifierInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(interface_exists(AggregatingRouteAttributeModifierInterface::class));
    }

    #[Test]
    public function isAlsoARouteAttributeModifier(): void
    {
        self::assertInstanceOf(
            RouteAttributeModifierInterface::class,
            new AggregatingRouteAttributeModifierStub(),
        );
    }

    #[Test]
    public function canMergeDefaults(): void
    {
        $stub = new AggregatingRouteAttributeModifierStub(defaultsToMerge: [
            'mappings' => ['body'],
        ]);

        self::assertSame([
            'existing' => 'value',
            'mappings' => ['body'],
        ], $stub->mergeDefaults([
            'existing' => 'value',
        ]));
    }

    #[Test]
    public function returnsUniqueMiddlewareKeyedByStableIdentity(): void
    {
        $specification = new MiddlewareSpecification(service: 'mapper.middleware');
        $stub          = new AggregatingRouteAttributeModifierStub(uniqueMiddleware: [
            'request-mapper' => $specification,
            'authorization'  => 'authorization.middleware',
        ]);

        self::assertSame([
            'request-mapper' => $specification,
            'authorization'  => 'authorization.middleware',
        ], $stub->getUniqueMiddleware());
    }
}
