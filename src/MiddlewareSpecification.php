<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts;

use ReflectionReference;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;
use Sirix\Mezzio\Routing\Contracts\Internal\CompactCanonicalArgumentsCodec;
use Sirix\Mezzio\Routing\Contracts\Internal\LegacyCanonicalTreeCodec;

use function array_key_exists;
use function count;
use function get_debug_type;
use function ini_get;
use function ini_set;
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
        if (array_key_exists('canonicalArguments', $props)) {
            self::validateStateProperties($props, ['service', 'factory', 'arguments', 'canonicalArguments']);

            $service   = self::stateService($props['service']);
            $factory   = self::stateFactory($props['factory']);
            $arguments = self::stateArguments($props['arguments']);

            self::validateArguments($arguments, 'arguments');

            $decoded = LegacyCanonicalTreeCodec::decode($props['canonicalArguments']);

            if (! LegacyCanonicalTreeCodec::treesEqual($decoded, $arguments)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            return new self($service, $factory, $arguments);
        }

        self::validateStateProperties($props, ['service', 'factory', 'arguments']);

        return new self(
            self::stateService($props['service']),
            self::stateFactory($props['factory']),
            self::stateArguments($props['arguments']),
        );
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        return [
            'version'   => 2,
            'service'   => $this->service,
            'factory'   => $this->factory,
            'arguments' => CompactCanonicalArgumentsCodec::encode($this->arguments),
        ];
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        $rehydrated = array_key_exists('version', $data)
            ? $this->fromVersionedState($data)
            : self::__set_state($data);

        $this->service   = $rehydrated->service;
        $this->factory   = $rehydrated->factory;
        $this->arguments = $rehydrated->arguments;
    }

    /** @return non-empty-string */
    public function signature(): string
    {
        $previousSerializePrecision = ini_get('serialize_precision');

        ini_set('serialize_precision', '-1');

        try {
            return 'middleware-specification:v1:' . serialize([
                'service'   => $this->service,
                'factory'   => $this->factory,
                'arguments' => $this->arguments,
            ]);
        } finally {
            ini_set('serialize_precision', $previousSerializePrecision);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function fromVersionedState(array $data): self
    {
        if (
            2 === $data['version']
            && self::hasExactKeys($data, ['version', 'service', 'factory', 'arguments'])
        ) {
            return new self(
                self::stateService($data['service']),
                self::stateFactory($data['factory']),
                CompactCanonicalArgumentsCodec::decode($data['arguments']),
            );
        }

        if (
            1 === $data['version']
            && self::hasExactKeys($data, ['version', 'service', 'factory', 'canonicalArguments'])
        ) {
            return new self(
                self::stateService($data['service']),
                self::stateFactory($data['factory']),
                LegacyCanonicalTreeCodec::decode($data['canonicalArguments']),
            );
        }

        throw InvalidMiddlewareSpecificationException::invalidSerializedState();
    }

    /**
     * @param array<mixed> $props
     * @param list<string> $expectedKeys
     */
    private static function validateStateProperties(array $props, array $expectedKeys): void
    {
        if (! self::hasExactKeys($props, $expectedKeys)) {
            throw InvalidMiddlewareSpecificationException::invalidStateProperties();
        }
    }

    private static function stateService(mixed $service): string
    {
        if (! is_string($service)) {
            throw InvalidMiddlewareSpecificationException::invalidStateService();
        }

        return $service;
    }

    private static function stateFactory(mixed $factory): ?string
    {
        if (null !== $factory && ! is_string($factory)) {
            throw InvalidMiddlewareSpecificationException::invalidStateFactory();
        }

        return $factory;
    }

    /** @return array<mixed> */
    private static function stateArguments(mixed $arguments): array
    {
        if (! is_array($arguments)) {
            throw InvalidMiddlewareSpecificationException::invalidStateArguments();
        }

        return $arguments;
    }

    /**
     * @param array<mixed> $value
     * @param list<string> $expectedKeys
     */
    private static function hasExactKeys(array $value, array $expectedKeys): bool
    {
        if (count($value) !== count($expectedKeys)) {
            return false;
        }

        foreach ($expectedKeys as $expectedKey) {
            if (! array_key_exists($expectedKey, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $arguments
     */
    private static function validateArguments(array $arguments, string $path): void
    {
        foreach ($arguments as $key => $value) {
            $currentPath = $path . '[' . $key . ']';

            if (ReflectionReference::fromArrayElement($arguments, $key) instanceof ReflectionReference) {
                throw InvalidMiddlewareSpecificationException::referencedArgument($currentPath);
            }

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
