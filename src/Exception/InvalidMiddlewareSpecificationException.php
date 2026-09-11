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

    public static function invalidStateProperties(): self
    {
        return new self(
            'Cached middleware specification properties do not match a supported state schema.',
        );
    }

    public static function invalidStateService(): self
    {
        return new self('Cached middleware specification service must be a string.');
    }

    public static function invalidStateFactory(): self
    {
        return new self('Cached middleware specification factory must be a string or null.');
    }

    public static function invalidStateArguments(): self
    {
        return new self('Cached middleware specification arguments must be an array.');
    }

    public static function invalidCanonicalState(): self
    {
        return new self('Cached middleware specification canonical arguments are invalid.');
    }

    public static function referencedArgument(string $path): self
    {
        return new self(
            sprintf('Middleware specification argument at "%s" must not contain references.', $path),
        );
    }

    public static function invalidSerializedState(): self
    {
        return new self('Serialized middleware specification state is invalid.');
    }
}
