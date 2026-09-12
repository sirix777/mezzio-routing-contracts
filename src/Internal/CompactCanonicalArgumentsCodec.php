<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Internal;

use LogicException;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;

use function array_key_exists;
use function bin2hex;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function pack;
use function preg_match;
use function strlen;
use function strpos;
use function substr;
use function unpack;

/**
 * Encodes and decodes the compact canonical string format used by version 2 native payloads.
 *
 * Grammar (validated scalar trees only):
 *
 *   value := array | string | int | float | bool | null
 *   array := 'a:' digits ':' (key value)*          // exactly count entries
 *   string := 's:' digits ':' bytes ';'            // exactly length bytes
 *   int := 'i:' '-'? ('0' | nonzero-digit digits*) ';'
 *   float := 'f:' hex{16} ';'
 *   bool := 'b:' ('1' | '0') ';'
 *   null := 'n;'
 *
 * Counts and lengths use a canonical digit grammar without leading zeros. The decoder is strict:
 * it fails closed on truncated input, unknown tags, non-canonical numbers, int overflow,
 * non-canonical numeric string keys, duplicate keys, and any trailing bytes.
 *
 * @internal
 */
final readonly class CompactCanonicalArgumentsCodec
{
    private function __construct() {}

    /**
     * Encodes a validated scalar tree to the compact canonical string. Floats are stored as
     * exact big-endian IEEE-754 bit strings; the encoder uses pack() and strlen() only and is
     * independent of serialize_precision.
     *
     * @param array<mixed> $arguments
     */
    public static function encode(array $arguments): string
    {
        return self::encodeValue($arguments);
    }

    /**
     * Decodes a compact canonical string back to the arguments array.
     *
     * @return array<mixed>
     */
    public static function decode(mixed $value): array
    {
        if (! is_string($value)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $offset    = 0;
        $arguments = self::decodeValue($value, $offset);

        if (! is_array($arguments) || $offset !== strlen($value)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $arguments;
    }

    /**
     * Returns the exact big-endian IEEE-754 bit representation of a float.
     */
    private static function encodeFloat(float $value): string
    {
        return bin2hex(pack('E', $value));
    }

    private static function encodeValue(mixed $value): string
    {
        if (is_array($value)) {
            $encoded = 'a:' . count($value) . ':';

            foreach ($value as $key => $item) {
                $encoded .= self::encodeValue($key) . self::encodeValue($item);
            }

            return $encoded;
        }

        if (is_string($value)) {
            return 's:' . strlen($value) . ':' . $value . ';';
        }

        if (is_int($value)) {
            return 'i:' . $value . ';';
        }

        if (is_float($value)) {
            return 'f:' . self::encodeFloat($value) . ';';
        }

        if (is_bool($value)) {
            return $value ? 'b:1;' : 'b:0;';
        }

        if (null === $value) {
            return 'n;';
        }

        throw new LogicException('Middleware specification contains an unsupported argument type.');
    }

    private static function decodeValue(string $value, int &$offset): mixed
    {
        $tag = $value[$offset] ?? null;

        if (null === $tag) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        ++$offset;

        return match ($tag) {
            'a'     => self::decodeArray($value, $offset),
            's'     => self::decodeString($value, $offset),
            'i'     => self::decodeInt($value, $offset),
            'f'     => self::decodeFloat($value, $offset),
            'b'     => self::decodeBool($value, $offset),
            'n'     => self::decodeNull($value, $offset),
            default => throw InvalidMiddlewareSpecificationException::invalidCanonicalState(),
        };
    }

    private static function requireColon(string $value, int &$offset): void
    {
        if (':' !== ($value[$offset] ?? null)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        ++$offset;
    }

    /**
     * @return array<mixed>
     */
    private static function decodeArray(string $value, int &$offset): array
    {
        self::requireColon($value, $offset);

        $count  = self::readCanonicalDigits($value, $offset, [':']);
        $result = [];

        for ($index = 0; $index < $count; ++$index) {
            $key = self::decodeValue($value, $offset);

            if (! is_int($key) && ! is_string($key)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            if (is_string($key) && self::isCoercedArrayKey($key)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            if (array_key_exists($key, $result)) {
                throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
            }

            $result[$key] = self::decodeValue($value, $offset);
        }

        return $result;
    }

    /**
     * Reads digits terminated by one of the expected terminator characters and validates the
     * canonical digit grammar: no leading zeros. The integer cast is verified to round trip
     * exactly, which also rejects values beyond the int range, where the cast saturates.
     *
     * @param list<string> $terminators
     */
    private static function readCanonicalDigits(string $value, int &$offset, array $terminators): int
    {
        $start  = $offset;
        $end    = $offset;
        $maxEnd = strlen($value);

        while ($end < $maxEnd && in_array($value[$end], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], true)) {
            ++$end;
        }

        $digits = substr($value, $start, $end - $start);

        if (
            '' === $digits
            || ! isset($value[$end])
            || ! in_array($value[$end], $terminators, true)
            || ('0' === $digits[0] && '0' !== $digits)
        ) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $offset = $end + 1;

        $intValue = (int) $digits;

        if ((string) $intValue !== $digits) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $intValue;
    }

    private static function decodeString(string $value, int &$offset): string
    {
        self::requireColon($value, $offset);
        $length = self::readCanonicalDigits($value, $offset, [':']);

        if ($offset + $length + 1 > strlen($value) || ';' !== ($value[$offset + $length] ?? null)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $string = substr($value, $offset, $length);
        $offset += $length + 1;

        return $string;
    }

    private static function decodeInt(string $value, int &$offset): int
    {
        self::requireColon($value, $offset);

        $semicolon = strpos($value, ';', $offset);

        if (false === $semicolon) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $digits = substr($value, $offset, $semicolon - $offset);

        if (1 !== preg_match('/\A-?(?:0|[1-9][0-9]*)\z/D', $digits)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $intValue = (int) $digits;

        if ((string) $intValue !== $digits) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $offset = $semicolon + 1;

        return $intValue;
    }

    private static function decodeFloat(string $value, int &$offset): float
    {
        self::requireColon($value, $offset);

        if ($offset + 17 > strlen($value) || ';' !== ($value[$offset + 16] ?? null)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $hex = substr($value, $offset, 16);

        if (1 !== preg_match('/\A[0-9a-f]{16}\z/D', $hex)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $offset += 17;

        $float = unpack('Evalue', pack('H*', $hex))['value'] ?? null;

        if (! is_float($float)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        return $float;
    }

    private static function decodeBool(string $value, int &$offset): bool
    {
        self::requireColon($value, $offset);

        $flag = $value[$offset] ?? null;

        if ('1' !== $flag && '0' !== $flag || ';' !== ($value[$offset + 1] ?? null)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        $offset += 2;

        return '1' === $flag;
    }

    private static function decodeNull(string $value, int &$offset): null
    {
        if (';' !== ($value[$offset] ?? null)) {
            throw InvalidMiddlewareSpecificationException::invalidCanonicalState();
        }

        ++$offset;

        return null;
    }

    private static function isCoercedArrayKey(string $key): bool
    {
        return 1 === preg_match('/\A(?:0|-?[1-9][0-9]*)\z/D', $key) && (string) (int) $key === $key;
    }
}
