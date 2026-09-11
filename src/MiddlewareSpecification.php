<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts;

use LogicException;
use ReflectionReference;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;

use function array_is_list;
use function array_key_exists;
use function bin2hex;
use function count;
use function get_debug_type;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function pack;
use function preg_match;
use function strlen;
use function unpack;

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

    /** @var array<string, mixed> */
    private array $canonicalArguments;

    /**
     * @param array<mixed> $arguments
     */
    public function __construct(string $service, public ?string $factory = null, array $arguments = [])
    {
        if ('' === $service) {
            throw InvalidMiddlewareSpecificationException::emptyService();
        }

        self::validateArguments($arguments, 'arguments');

        $this->service            = $service;
        $this->arguments          = $arguments;
        $this->canonicalArguments = self::encodeCanonicalValue($arguments);
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

            return self::fromCanonicalArguments($service, $factory, $props['canonicalArguments']);
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
            'version'            => 1,
            'service'            => $this->service,
            'factory'            => $this->factory,
            'canonicalArguments' => $this->canonicalArguments,
        ];
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        if (array_key_exists('version', $data)) {
            if (! self::hasExactKeys($data, ['version', 'service', 'factory', 'canonicalArguments']) || 1 !== $data['version']) {
                throw InvalidMiddlewareSpecificationException::invalidSerializedState();
            }

            $rehydrated = self::fromCanonicalArguments(
                self::stateService($data['service']),
                self::stateFactory($data['factory']),
                $data['canonicalArguments'],
            );
        } else {
            $rehydrated = self::__set_state($data);
        }

        $this->service            = $rehydrated->service;
        $this->factory            = $rehydrated->factory;
        $this->arguments          = $rehydrated->arguments;
        $this->canonicalArguments = $rehydrated->canonicalArguments;
    }

    /** @return non-empty-string */
    public function signature(): string
    {
        return 'middleware-specification:v1:' . self::encodeArray([
            'service'   => $this->service,
            'factory'   => $this->factory,
            'arguments' => $this->canonicalArguments,
        ]);
    }

    /** @param array<mixed> $value */
    private static function encodeArray(array $value): string
    {
        $encoded = 'a:' . count($value) . ':';

        foreach ($value as $key => $item) {
            $encoded .= self::encodeValue($key) . self::encodeValue($item);
        }

        return $encoded;
    }

    private static function encodeValue(mixed $value): string
    {
        if (is_array($value)) {
            return self::encodeArray($value);
        }

        if (is_string($value)) {
            return 's:' . strlen($value) . ':' . $value;
        }

        if (is_int($value)) {
            return 'i:' . $value . ';';
        }

        if (is_float($value)) {
            return 'f:' . bin2hex(pack('E', $value)) . ';';
        }

        if (is_bool($value)) {
            return $value ? 'b:1;' : 'b:0;';
        }

        if (null === $value) {
            return 'n;';
        }

        throw new LogicException('Middleware specification contains an unsupported argument type.');
    }

    /** @return array<string, mixed> */
    private static function encodeCanonicalValue(mixed $value): array
    {
        if (is_array($value)) {
            $entries = [];

            foreach ($value as $key => $item) {
                $entries[] = [
                    'key'   => self::encodeCanonicalValue($key),
                    'value' => self::encodeCanonicalValue($item),
                ];
            }

            return [
                'type'    => 'array',
                'entries' => $entries,
            ];
        }

        if (is_string($value)) {
            return [
                'type'  => 'string',
                'value' => $value,
            ];
        }

        if (is_int($value)) {
            return [
                'type'  => 'int',
                'value' => $value,
            ];
        }

        if (is_float($value)) {
            return [
                'type'  => 'float',
                'value' => bin2hex(pack('E', $value)),
            ];
        }

        if (is_bool($value)) {
            return [
                'type'  => 'bool',
                'value' => $value,
            ];
        }

        if (null === $value) {
            return [
                'type' => 'null',
            ];
        }

        throw new LogicException('Middleware specification contains an unsupported argument type.');
    }

    /** @return array<mixed> */
    private static function decodeCanonicalArguments(mixed $value): array
    {
        $arguments = self::decodeCanonicalValue($value);

        if (! is_array($arguments)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $arguments;
    }

    private static function fromCanonicalArguments(string $service, ?string $factory, mixed $canonicalArguments): self
    {
        $arguments     = self::decodeCanonicalArguments($canonicalArguments);
        $specification = new self($service, $factory, $arguments);

        if ($specification->canonicalArguments !== $canonicalArguments) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $specification;
    }

    private static function decodeCanonicalValue(mixed $value): mixed
    {
        if (! is_array($value) || ! array_key_exists('type', $value) || ! is_string($value['type'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        self::validateCanonicalReferences($value);

        return match ($value['type']) {
            'null'   => self::decodeCanonicalNull($value),
            'bool'   => self::decodeCanonicalBool($value),
            'int'    => self::decodeCanonicalInt($value),
            'float'  => self::decodeCanonicalFloat($value),
            'string' => self::decodeCanonicalString($value),
            'array'  => self::decodeCanonicalArray($value),
            default  => throw InvalidMiddlewareSpecificationException::invalidCanonicalState(),
        };
    }

    /** @param array<mixed> $value */
    private static function decodeCanonicalNull(array $value): null
    {
        self::validateCanonicalKeys($value, ['type']);

        return null;
    }

    /** @param array<mixed> $value */
    private static function decodeCanonicalBool(array $value): bool
    {
        self::validateCanonicalKeys($value, ['type', 'value']);

        if (! is_bool($value['value'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $value['value'];
    }

    /** @param array<mixed> $value */
    private static function decodeCanonicalInt(array $value): int
    {
        self::validateCanonicalKeys($value, ['type', 'value']);

        if (! is_int($value['value'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $value['value'];
    }

    /** @param array<mixed> $value */
    private static function decodeCanonicalFloat(array $value): float
    {
        self::validateCanonicalKeys($value, ['type', 'value']);

        if (! is_string($value['value']) || 16 !== strlen($value['value']) || 1 !== preg_match('/\A[0-9a-f]{16}\z/D', $value['value'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $float = unpack('Evalue', pack('H*', $value['value']))['value'] ?? null;

        if (! is_float($float)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $float;
    }

    /** @param array<mixed> $value */
    private static function decodeCanonicalString(array $value): string
    {
        self::validateCanonicalKeys($value, ['type', 'value']);

        if (! is_string($value['value'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $value['value'];
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private static function decodeCanonicalArray(array $value): array
    {
        self::validateCanonicalKeys($value, ['type', 'entries']);

        if (! is_array($value['entries']) || ! array_is_list($value['entries'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $result = [];

        foreach ($value['entries'] as $entry) {
            if (! is_array($entry)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            self::validateCanonicalReferences($entry);
            self::validateCanonicalKeys($entry, ['key', 'value']);

            if (
                ! is_array($entry['key'])
                || ! array_key_exists('type', $entry['key'])
                || ! is_string($entry['key']['type'])
                || ! in_array($entry['key']['type'], ['int', 'string'], true)
            ) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            self::validateCanonicalKeys($entry['key'], ['type', 'value']);
            $key = self::decodeCanonicalValue($entry['key']);

            if (! is_int($key) && ! is_string($key)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            if (is_string($key)) {
                if (self::isCoercedArrayKey($key)) {
                    throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
                }
            }

            if (array_key_exists($key, $result)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            $result[$key] = self::decodeCanonicalValue($entry['value']);
        }

        return $result;
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
    private static function validateCanonicalKeys(array $value, array $expectedKeys): void
    {
        if (! self::hasExactKeys($value, $expectedKeys)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }
    }

    /** @param array<mixed> $value */
    private static function validateCanonicalReferences(array $value): void
    {
        foreach ($value as $key => $_) {
            if (ReflectionReference::fromArrayElement($value, $key) instanceof ReflectionReference) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }
        }
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

    private static function isCoercedArrayKey(string $key): bool
    {
        return 1 === preg_match('/\A(?:0|-?[1-9][0-9]*)\z/D', $key) && (string) (int) $key === $key;
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
