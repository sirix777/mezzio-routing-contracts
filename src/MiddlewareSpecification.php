<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts;

use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;

use function get_debug_type;
use function is_array;
use function is_scalar;
use function is_string;
use function serialize;

/**
 * Serializable value object describing how to build a middleware instance via a factory.
 *
 * Arguments are restricted to scalar values and nested scalar arrays so that the object can be
 * safely rehydrated from route-cache files generated with var_export()/__set_state().
 */
final readonly class MiddlewareSpecification
{
    /** @var non-empty-string */
    public string $service;

    /** @var array<mixed> */
    public array $arguments;

    /**
     * @param array<mixed> $arguments
     */
    public function __construct(string $service, public ?string $factory = null, array $arguments = [])
    {
        if ('' === $service) {
            throw InvalidMiddlewareSpecificationException::emptyService();
        }

        self::validateArguments($arguments, 'arguments');

        $this->service   = $service;
        $this->arguments = $arguments;
    }

    /**
     * @param array<string, mixed> $props
     */
    public static function __set_state(array $props): self
    {
        $service   = isset($props['service']) && is_string($props['service']) ? $props['service'] : '';
        $factory   = isset($props['factory']) && is_string($props['factory']) ? $props['factory'] : null;
        $arguments = isset($props['arguments']) && is_array($props['arguments']) ? $props['arguments'] : [];

        return new self($service, $factory, $arguments);
    }

    /** @return non-empty-string */
    public function signature(): string
    {
        return $this->service . "\0" . ($this->factory ?? '') . "\0" . serialize($this->arguments);
    }

    /**
     * @param array<mixed> $arguments
     */
    private static function validateArguments(array $arguments, string $path): void
    {
        foreach ($arguments as $key => $value) {
            $currentPath = $path . '[' . $key . ']';

            if (is_array($value)) {
                self::validateArguments($value, $currentPath);

                continue;
            }

            if (is_scalar($value)) {
                continue;
            }

            if (null === $value) {
                continue;
            }

            throw InvalidMiddlewareSpecificationException::nonScalarArgument(
                $currentPath,
                get_debug_type($value),
            );
        }
    }
}
