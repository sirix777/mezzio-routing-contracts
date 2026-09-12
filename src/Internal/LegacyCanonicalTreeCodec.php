<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Internal;

use ReflectionReference;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function bin2hex;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_nan;
use function is_string;
use function pack;
use function preg_match;
use function strlen;
use function unpack;

/**
 * Decodes the canonical arguments tree emitted by version 1.2.1 cache payloads.
 *
 * Transitional support for route caches generated with 1.2.1; will be removed.
 *
 * @internal
 */
final readonly class LegacyCanonicalTreeCodec
{
    private function __construct() {}

    /** @return array<mixed> */
    public static function decode(mixed $value): array
    {
        $arguments = self::decodeValue($value);

        if (! is_array($arguments)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $arguments;
    }

    /**
     * Compares two validated scalar trees for exact equality.
     *
     * Finite floats are compared by their IEEE-754 bit representation, so +0.0 and -0.0 are
     * rejected as different. Any NaN value equals any other NaN value, matching the signature
     * contract that normalizes all NaN payloads to one identity.
     *
     * @param array<mixed> $decoded
     * @param array<mixed> $exported
     */
    public static function treesEqual(array $decoded, array $exported): bool
    {
        if (array_keys($decoded) !== array_keys($exported)) {
            return false;
        }

        foreach ($decoded as $key => $value) {
            $other = $exported[$key];

            if (is_array($value)) {
                if (! is_array($other) || ! self::treesEqual($value, $other)) {
                    return false;
                }

                continue;
            }

            if (is_float($value) && is_float($other)) {
                if (self::floatEqual($value, $other)) {
                    continue;
                }

                return false;
            }

            if ($value !== $other) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the exact big-endian IEEE-754 bit representation of a float.
     */
    private static function encodeFloat(float $value): string
    {
        return bin2hex(pack('E', $value));
    }

    private static function floatEqual(float $value, float $other): bool
    {
        if (is_nan($value) && is_nan($other)) {
            return true;
        }

        return self::encodeFloat($value) === self::encodeFloat($other);
    }

    private static function decodeValue(mixed $value): mixed
    {
        if (! is_array($value) || ! array_key_exists('type', $value) || ! is_string($value['type'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        self::validateReferences($value);

        return match ($value['type']) {
            'null'   => self::decodeNull($value),
            'bool'   => self::decodeBool($value),
            'int'    => self::decodeInt($value),
            'float'  => self::decodeFloat($value),
            'string' => self::decodeString($value),
            'array'  => self::decodeArray($value),
            default  => throw InvalidMiddlewareSpecificationException::invalidCanonicalState(),
        };
    }

    /** @param array<mixed> $value */
    private static function decodeNull(array $value): null
    {
        self::validateKeys($value, ['type']);

        return null;
    }

    /** @param array<mixed> $value */
    private static function decodeBool(array $value): bool
    {
        self::validateKeys($value, ['type', 'value']);

        if (! is_bool($value['value'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $value['value'];
    }

    /** @param array<mixed> $value */
    private static function decodeInt(array $value): int
    {
        self::validateKeys($value, ['type', 'value']);

        if (! is_int($value['value'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $value['value'];
    }

    /** @param array<mixed> $value */
    private static function decodeFloat(array $value): float
    {
        self::validateKeys($value, ['type', 'value']);

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
    private static function decodeString(array $value): string
    {
        self::validateKeys($value, ['type', 'value']);

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
    private static function decodeArray(array $value): array
    {
        self::validateKeys($value, ['type', 'entries']);

        if (! is_array($value['entries']) || ! array_is_list($value['entries'])) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $result = [];

        foreach ($value['entries'] as $entry) {
            if (! is_array($entry)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            self::validateReferences($entry);
            self::validateKeys($entry, ['key', 'value']);

            if (
                ! is_array($entry['key'])
                || ! array_key_exists('type', $entry['key'])
                || ! is_string($entry['key']['type'])
                || ! in_array($entry['key']['type'], ['int', 'string'], true)
            ) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            self::validateKeys($entry['key'], ['type', 'value']);
            $key = self::decodeValue($entry['key']);

            if (! is_int($key) && ! is_string($key)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            if (is_string($key) && self::isCoercedArrayKey($key)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            if (array_key_exists($key, $result)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            $result[$key] = self::decodeValue($entry['value']);
        }

        return $result;
    }

    /**
     * @param array<mixed> $value
     * @param list<string> $expectedKeys
     */
    private static function validateKeys(array $value, array $expectedKeys): void
    {
        if (count($value) !== count($expectedKeys)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        foreach ($expectedKeys as $expectedKey) {
            if (! array_key_exists($expectedKey, $value)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }
        }
    }

    /** @param array<mixed> $value */
    private static function validateReferences(array $value): void
    {
        foreach ($value as $key => $_) {
            if (ReflectionReference::fromArrayElement($value, $key) instanceof ReflectionReference) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }
        }
    }

    private static function isCoercedArrayKey(string $key): bool
    {
        return 1 === preg_match('/\A(?:0|-?[1-9][0-9]*)\z/D', $key) && (string) (int) $key === $key;
    }
}
