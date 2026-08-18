<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Test\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;
use Sirix\Mezzio\Routing\Contracts\Test\Stub\MiddlewareFactoryStub;
use Sirix\Mezzio\Routing\Contracts\Test\Stub\RouteAttributeModifierStub;

use function interface_exists;

#[CoversNothing]
final class RouteAttributeModifierInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(interface_exists(RouteAttributeModifierInterface::class));
    }

    #[Test]
    public function canBeImplemented(): void
    {
        $stub = new RouteAttributeModifierStub();

        self::assertInstanceOf(RouteAttributeModifierInterface::class, $stub);
    }

    #[Test]
    public function getMiddlewareReturnsEmptyArrayByDefault(): void
    {
        $stub = new RouteAttributeModifierStub();

        self::assertSame([], $stub->getMiddleware());
    }

    #[Test]
    public function getMiddlewareReturnsPassedClasses(): void
    {
        $middleware = ['Some\Middleware'];
        $stub = new RouteAttributeModifierStub(middleware: $middleware);

        self::assertSame($middleware, $stub->getMiddleware());
    }

    #[Test]
    public function getDefaultsReturnsEmptyArrayByDefault(): void
    {
        $stub = new RouteAttributeModifierStub();

        self::assertSame([], $stub->getDefaults());
    }

    #[Test]
    public function getDefaultsReturnsPassedValues(): void
    {
        $defaults = ['key' => 'value', 'number' => 42];
        $stub = new RouteAttributeModifierStub(defaults: $defaults);

        self::assertSame($defaults, $stub->getDefaults());
    }

    #[Test]
    public function multipleImplementationsCanCoexist(): void
    {
        $first = new RouteAttributeModifierStub(middleware: ['A\Middleware'], defaults: ['a' => 1]);
        $second = new RouteAttributeModifierStub(middleware: ['B\Middleware'], defaults: ['b' => 2]);

        self::assertCount(1, $first->getMiddleware());
        self::assertCount(1, $second->getMiddleware());
        self::assertSame('A\Middleware', $first->getMiddleware()[0]);
        self::assertSame('B\Middleware', $second->getMiddleware()[0]);
        self::assertSame(['a' => 1], $first->getDefaults());
        self::assertSame(['b' => 2], $second->getDefaults());
    }

    #[Test]
    public function getMiddlewareAcceptsMiddlewareSpecifications(): void
    {
        $spec = new MiddlewareSpecification(
            service: 'auth.middleware',
            factory: MiddlewareFactoryStub::class,
            arguments: ['profile' => 'api'],
        );
        $stub = new RouteAttributeModifierStub(middleware: ['A\Middleware', $spec]);

        $middleware = $stub->getMiddleware();

        self::assertCount(2, $middleware);
        self::assertSame('A\Middleware', $middleware[0]);
        self::assertSame($spec, $middleware[1]);
    }
}
