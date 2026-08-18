<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\Test\Stub\MiddlewareFactoryStub;
use stdClass;

use function fopen;
use function var_export;

#[CoversClass(MiddlewareSpecification::class)]
final class MiddlewareSpecificationTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithServiceOnly(): void
    {
        $spec = new MiddlewareSpecification(service: 'middleware');

        self::assertSame('middleware', $spec->service);
        self::assertNull($spec->factory);
        self::assertSame([], $spec->arguments);
    }

    #[Test]
    public function canBeConstructedWithFactoryAndScalarArguments(): void
    {
        $spec = new MiddlewareSpecification(
            service: 'auth.middleware',
            factory: MiddlewareFactoryStub::class,
            arguments: ['profile' => 'api', 'enabled' => true, 'attempts' => 3],
        );

        self::assertSame('auth.middleware', $spec->service);
        self::assertSame(MiddlewareFactoryStub::class, $spec->factory);
        self::assertSame(
            ['profile' => 'api', 'enabled' => true, 'attempts' => 3],
            $spec->arguments,
        );
    }

    #[Test]
    public function canBeConstructedWithNestedScalarArrays(): void
    {
        $spec = new MiddlewareSpecification(
            service: 'nested',
            arguments: ['list' => ['a', 'b', 'c'], 'flags' => ['x' => true]],
        );

        self::assertSame(['list' => ['a', 'b', 'c'], 'flags' => ['x' => true]], $spec->arguments);
    }

    #[Test]
    public function throwsWhenServiceIsEmpty(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('service must be a non-empty string');

        new MiddlewareSpecification(service: '');
    }

    #[Test]
    public function throwsWhenArgumentIsObject(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('argument at "arguments[object]" must be a scalar');

        new MiddlewareSpecification(
            service: 'middleware',
            arguments: ['object' => new stdClass()],
        );
    }

    #[Test]
    public function throwsWhenArgumentIsResource(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('argument at "arguments[resource]" must be a scalar');

        new MiddlewareSpecification(
            service: 'middleware',
            arguments: ['resource' => fopen('php://memory', 'rb')],
        );
    }

    #[Test]
    public function throwsWhenNestedArgumentIsNonScalar(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('argument at "arguments[nested][1]" must be a scalar');

        new MiddlewareSpecification(
            service: 'middleware',
            arguments: ['nested' => ['ok', new stdClass()]],
        );
    }

    #[Test]
    public function signatureIsDeterministicForEqualSpecs(): void
    {
        $first = new MiddlewareSpecification('middleware', MiddlewareFactoryStub::class, ['x' => 1]);
        $second = new MiddlewareSpecification('middleware', MiddlewareFactoryStub::class, ['x' => 1]);

        self::assertSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDiffersWhenServiceDiffers(): void
    {
        $first = new MiddlewareSpecification('middleware.a');
        $second = new MiddlewareSpecification('middleware.b');

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDiffersWhenFactoryDiffers(): void
    {
        $first = new MiddlewareSpecification('middleware', MiddlewareFactoryStub::class);
        $second = new MiddlewareSpecification('middleware', MiddlewareFactoryInterface::class);

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDiffersWhenArgumentsDiffer(): void
    {
        $first = new MiddlewareSpecification('middleware', null, ['x' => 1]);
        $second = new MiddlewareSpecification('middleware', null, ['x' => 2]);

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function setStateRoundTripReproducesOriginal(): void
    {
        $original = new MiddlewareSpecification(
            service: 'middleware',
            factory: MiddlewareFactoryStub::class,
            arguments: ['a' => 1, 'b' => ['c' => true]],
        );

        $exported = var_export($original, true);
        $rehydrated = eval('return ' . $exported . ';');

        self::assertInstanceOf(MiddlewareSpecification::class, $rehydrated);
        self::assertSame($original->service, $rehydrated->service);
        self::assertSame($original->factory, $rehydrated->factory);
        self::assertSame($original->arguments, $rehydrated->arguments);
        self::assertSame($original->signature(), $rehydrated->signature());
    }

    #[Test]
    public function setStateRejectsCachedNonScalarArguments(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('argument at "arguments[bad]" must be a scalar');

        MiddlewareSpecification::__set_state([
            'service' => 'middleware',
            'factory' => null,
            'arguments' => ['bad' => new stdClass()],
        ]);
    }
}
