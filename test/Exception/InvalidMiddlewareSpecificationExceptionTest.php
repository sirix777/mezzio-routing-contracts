<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Test\Exception;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;

#[CoversClass(InvalidMiddlewareSpecificationException::class)]
final class InvalidMiddlewareSpecificationExceptionTest extends TestCase
{
    #[Test]
    public function nonScalarArgumentProducesExpectedMessageAndType(): void
    {
        $exception = InvalidMiddlewareSpecificationException::nonScalarArgument(
            'arguments[foo][bar]',
            'stdClass',
        );

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame(
            'Middleware specification argument at "arguments[foo][bar]" '
            . 'must be a scalar or nested scalar array, stdClass given.',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function emptyServiceProducesExpectedMessageAndType(): void
    {
        $exception = InvalidMiddlewareSpecificationException::emptyService();

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame(
            'Middleware specification service must be a non-empty string.',
            $exception->getMessage(),
        );
    }
}
