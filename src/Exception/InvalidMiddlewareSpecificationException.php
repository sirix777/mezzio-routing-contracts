<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Exception;

use InvalidArgumentException;

use function sprintf;

final class InvalidMiddlewareSpecificationException extends InvalidArgumentException
{
    public static function nonScalarArgument(string $path, string $type): self
    {
        return new self(
            sprintf(
                'Middleware specification argument at "%s" must be a scalar or nested scalar array, %s given.',
                $path,
                $type,
            ),
        );
    }

    public static function emptyService(): self
    {
        return new self('Middleware specification service must be a non-empty string.');
    }
}
