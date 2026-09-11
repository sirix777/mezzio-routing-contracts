<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts\Test;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Contracts\Exception\InvalidMiddlewareSpecificationException;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\Test\Stub\MiddlewareFactoryStub;
use stdClass;

use function array_unique;
use function bin2hex;
use function fopen;
use function ini_get;
use function ini_set;
use function is_float;
use function pack;
use function serialize;
use function strlen;
use function unpack;
use function unserialize;
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
            arguments: [
                'profile'  => 'api',
                'enabled'  => true,
                'attempts' => 3,
            ],
        );

        self::assertSame('auth.middleware', $spec->service);
        self::assertSame(MiddlewareFactoryStub::class, $spec->factory);
        self::assertSame(
            [
                'profile'  => 'api',
                'enabled'  => true,
                'attempts' => 3,
            ],
            $spec->arguments,
        );
    }

    #[Test]
    public function canBeConstructedWithNestedScalarArrays(): void
    {
        $spec = new MiddlewareSpecification(
            service: 'nested',
            arguments: [
                'list'  => ['a', 'b', 'c'],
                'flags' => [
                    'x' => true,
                ],
            ],
        );

        self::assertSame([
            'list'  => ['a', 'b', 'c'],
            'flags' => [
                'x' => true,
            ],
        ], $spec->arguments);
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
            arguments: [
                'object' => new stdClass(),
            ],
        );
    }

    #[Test]
    public function throwsWhenArgumentIsResource(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('argument at "arguments[resource]" must be a scalar');

        new MiddlewareSpecification(
            service: 'middleware',
            arguments: [
                'resource' => fopen('php://memory', 'rb'),
            ],
        );
    }

    #[Test]
    public function throwsWhenNestedArgumentIsNonScalar(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('argument at "arguments[nested][1]" must be a scalar');

        new MiddlewareSpecification(
            service: 'middleware',
            arguments: [
                'nested' => ['ok', new stdClass()],
            ],
        );
    }

    #[Test]
    public function signatureIsDeterministicForEqualSpecs(): void
    {
        $first = new MiddlewareSpecification('middleware', MiddlewareFactoryStub::class, [
            'x' => 1,
        ]);
        $second = new MiddlewareSpecification('middleware', MiddlewareFactoryStub::class, [
            'x' => 1,
        ]);

        self::assertSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDiffersWhenServiceDiffers(): void
    {
        $first  = new MiddlewareSpecification('middleware.a');
        $second = new MiddlewareSpecification('middleware.b');

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDiffersWhenFactoryDiffers(): void
    {
        $first  = new MiddlewareSpecification('middleware', MiddlewareFactoryStub::class);
        $second = new MiddlewareSpecification('middleware', MiddlewareFactoryInterface::class);

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDiffersWhenArgumentsDiffer(): void
    {
        $first = new MiddlewareSpecification('middleware', null, [
            'x' => 1,
        ]);
        $second = new MiddlewareSpecification('middleware', null, [
            'x' => 2,
        ]);

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDoesNotCollideAcrossServiceAndFactoryBoundaries(): void
    {
        $first  = new MiddlewareSpecification("middleware\0factory", 'builder');
        $second = new MiddlewareSpecification('middleware', "factory\0builder");

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDistinguishesNullAndEmptyStringFactories(): void
    {
        $withoutFactory = new MiddlewareSpecification('middleware');
        $emptyFactory   = new MiddlewareSpecification('middleware', '');

        self::assertNotSame($withoutFactory->signature(), $emptyFactory->signature());
    }

    #[Test]
    public function signatureDistinguishesScalarArgumentTypes(): void
    {
        $signatures = [];

        foreach ([null, false, true, 0, 0.0, '0'] as $value) {
            $signatures[] = (new MiddlewareSpecification('middleware', arguments: [
                'value' => $value,
            ]))->signature();
        }

        self::assertCount(6, array_unique($signatures));
    }

    #[Test]
    public function signatureDistinguishesIntegerAndStringArgumentKeys(): void
    {
        $integerKey = new MiddlewareSpecification('middleware', arguments: [
            1 => 'value',
        ]);
        $stringKey  = new MiddlewareSpecification('middleware', arguments: [
            '01' => 'value',
        ]);

        self::assertNotSame($integerKey->signature(), $stringKey->signature());
    }

    #[Test]
    public function signaturePreservesArgumentKeyOrder(): void
    {
        $first = new MiddlewareSpecification('middleware', arguments: [
            'first'  => 'a',
            'second' => 'b',
        ]);
        $second = new MiddlewareSpecification('middleware', arguments: [
            'second' => 'b',
            'first'  => 'a',
        ]);

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDistinguishesNestedArguments(): void
    {
        $first = new MiddlewareSpecification('middleware', arguments: [
            'nested' => [
                'enabled' => true,
            ],
        ]);
        $second = new MiddlewareSpecification('middleware', arguments: [
            'nested' => [
                'enabled' => false,
            ],
        ]);

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function signatureDoesNotDependOnSerializePrecision(): void
    {
        $previousSerializePrecision = ini_get('serialize_precision');

        try {
            ini_set('serialize_precision', '1');

            self::assertSame(serialize(1.01), serialize(1.09));

            $first = new MiddlewareSpecification('middleware', arguments: [
                'value' => 1.01,
            ]);
            $second = new MiddlewareSpecification('middleware', arguments: [
                'value' => 1.09,
            ]);

            self::assertNotSame($first->signature(), $second->signature());
        } finally {
            ini_set('serialize_precision', (string) $previousSerializePrecision);
        }
    }

    #[Test]
    public function signatureDistinguishesNanPayloads(): void
    {
        $first = new MiddlewareSpecification('middleware', arguments: [
            'value' => $this->ieee754Float('7ff8000000000001'),
        ]);
        $second = new MiddlewareSpecification('middleware', arguments: [
            'value' => $this->ieee754Float('7ff8000000000002'),
        ]);

        self::assertNotSame($first->signature(), $second->signature());
    }

    #[Test]
    public function cacheRoundTripPreservesFloatBitsWithCorrectExportPrecision(): void
    {
        $previousSerializePrecision = ini_get('serialize_precision');

        try {
            ini_set('serialize_precision', '-1');

            $first = new MiddlewareSpecification('middleware', arguments: [
                'value' => 1.01,
            ]);
            $second = new MiddlewareSpecification('middleware', arguments: [
                'value' => 1.09,
            ]);
            $rehydratedFirst            = eval('return ' . var_export($first, true) . ';');
            $rehydratedSecond           = eval('return ' . var_export($second, true) . ';');

            self::assertInstanceOf(MiddlewareSpecification::class, $rehydratedFirst);
            self::assertInstanceOf(MiddlewareSpecification::class, $rehydratedSecond);
            self::assertSame(
                bin2hex(pack('E', $first->arguments['value'])),
                bin2hex(pack('E', $rehydratedFirst->arguments['value'])),
            );
            self::assertSame(
                bin2hex(pack('E', $second->arguments['value'])),
                bin2hex(pack('E', $rehydratedSecond->arguments['value'])),
            );
            self::assertNotSame($rehydratedFirst->signature(), $rehydratedSecond->signature());
        } finally {
            ini_set('serialize_precision', (string) $previousSerializePrecision);
        }
    }

    #[Test]
    public function cacheRoundTripPreservesNanPayloadsWithCorrectExportPrecision(): void
    {
        $previousSerializePrecision = ini_get('serialize_precision');

        try {
            ini_set('serialize_precision', '-1');

            $first = new MiddlewareSpecification('middleware', arguments: [
                'value' => $this->ieee754Float('7ff8000000000001'),
            ]);
            $second = new MiddlewareSpecification('middleware', arguments: [
                'value' => $this->ieee754Float('7ff8000000000002'),
            ]);
            $positiveInfinity = new MiddlewareSpecification('middleware', arguments: [
                'value' => INF,
            ]);
            $negativeInfinity = new MiddlewareSpecification('middleware', arguments: [
                'value' => -INF,
            ]);
            $rehydratedFirst            = eval('return ' . var_export($first, true) . ';');
            $rehydratedSecond           = eval('return ' . var_export($second, true) . ';');
            $rehydratedPositiveInfinity = eval('return ' . var_export($positiveInfinity, true) . ';');
            $rehydratedNegativeInfinity = eval('return ' . var_export($negativeInfinity, true) . ';');

            self::assertInstanceOf(MiddlewareSpecification::class, $rehydratedFirst);
            self::assertInstanceOf(MiddlewareSpecification::class, $rehydratedSecond);
            self::assertSame($first->signature(), $rehydratedFirst->signature());
            self::assertSame($second->signature(), $rehydratedSecond->signature());
            self::assertSame($positiveInfinity->signature(), $rehydratedPositiveInfinity->signature());
            self::assertSame($negativeInfinity->signature(), $rehydratedNegativeInfinity->signature());
            self::assertNotSame($rehydratedFirst->signature(), $rehydratedSecond->signature());
        } finally {
            ini_set('serialize_precision', (string) $previousSerializePrecision);
        }
    }

    #[Test]
    public function nativeSerializationRoundTripPreservesExactFloatAndNanSignatures(): void
    {
        $closeFloat = new MiddlewareSpecification('middleware', arguments: [
            'value' => 1.01,
        ]);
        $nan = new MiddlewareSpecification('middleware', arguments: [
            'value' => $this->ieee754Float('7ff8000000000001'),
        ]);
        $otherNan = new MiddlewareSpecification('middleware', arguments: [
            'value' => $this->ieee754Float('7ff8000000000002'),
        ]);
        $rehydratedCloseFloat = unserialize(serialize($closeFloat), [
            'allowed_classes' => true,
        ]);
        $rehydratedNan        = unserialize(serialize($nan), [
            'allowed_classes' => true,
        ]);
        $rehydratedOtherNan   = unserialize(serialize($otherNan), [
            'allowed_classes' => true,
        ]);

        self::assertInstanceOf(MiddlewareSpecification::class, $rehydratedCloseFloat);
        self::assertInstanceOf(MiddlewareSpecification::class, $rehydratedNan);
        self::assertInstanceOf(MiddlewareSpecification::class, $rehydratedOtherNan);
        self::assertSame($closeFloat->signature(), $rehydratedCloseFloat->signature());
        self::assertSame($nan->signature(), $rehydratedNan->signature());
        self::assertNotSame($rehydratedNan->signature(), $rehydratedOtherNan->signature());
        self::assertArrayNotHasKey('arguments', $closeFloat->__serialize());
    }

    #[Test]
    public function nativeUnserializationSupportsLegacyThreePropertyPayloads(): void
    {
        $class   = MiddlewareSpecification::class;
        $payload = 'O:' . strlen($class) . ':"' . $class
            . '":3:{s:7:"service";s:10:"middleware";s:7:"factory";N;s:9:"arguments";a:1:{s:6:"nested";a:1:{s:5:"value";d:1.01;}}}';
        $specification = unserialize($payload, [
            'allowed_classes' => [$class],
        ]);

        self::assertInstanceOf(MiddlewareSpecification::class, $specification);
        self::assertSame([
            'nested' => [
                'value' => 1.01,
            ],
        ], $specification->arguments);
        self::assertSame(
            (new MiddlewareSpecification('middleware', arguments: $specification->arguments))->signature(),
            $specification->signature(),
        );
    }

    #[Test]
    public function setStateRoundTripReproducesOriginal(): void
    {
        $original = new MiddlewareSpecification(
            service: 'middleware',
            factory: MiddlewareFactoryStub::class,
            arguments: [
                'a' => 1,
                'b' => [
                    'c' => true,
                ],
            ],
        );

        $exported   = var_export($original, true);
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
            'service'   => 'middleware',
            'factory'   => null,
            'arguments' => [
                'bad' => new stdClass(),
            ],
        ]);
    }

    #[Test]
    public function setStateRejectsMalformedCanonicalArguments(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('canonical arguments are invalid');

        MiddlewareSpecification::__set_state([
            'service'            => 'middleware',
            'factory'            => null,
            'arguments'          => [],
            'canonicalArguments' => [
                'type'  => 'float',
                'value' => 'not-a-float',
            ],
        ]);
    }

    #[Test]
    public function setStateRejectsCanonicalNumericStringKeys(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);

        MiddlewareSpecification::__set_state($this->canonicalState([
            $this->canonicalEntry(
                [
                    'type'  => 'string',
                    'value' => '1',
                ],
                [
                    'type'  => 'string',
                    'value' => 'value',
                ],
            ),
        ]));
    }

    #[Test]
    public function setStateRejectsDeepCanonicalArrayKeysBeforeRecursiveDecoding(): void
    {
        $key = [
            'type'    => 'array',
            'entries' => [],
        ];

        for ($depth = 0; $depth < 128; ++$depth) {
            $key = [
                'type'    => 'array',
                'entries' => [[
                    'key'   => [
                        'type'  => 'int',
                        'value' => 0,
                    ],
                    'value' => $key,
                ]],
            ];
        }

        $this->expectException(InvalidMiddlewareSpecificationException::class);

        MiddlewareSpecification::__set_state($this->canonicalState([
            $this->canonicalEntry($key, [
                'type'  => 'string',
                'value' => 'value',
            ]),
        ]));
    }

    #[Test]
    public function setStateRejectsDuplicateCanonicalKeys(): void
    {
        $entry = $this->canonicalEntry(
            [
                'type'  => 'int',
                'value' => 1,
            ],
            [
                'type'  => 'string',
                'value' => 'value',
            ],
        );

        $this->expectException(InvalidMiddlewareSpecificationException::class);

        MiddlewareSpecification::__set_state($this->canonicalState([$entry, $entry]));
    }

    #[Test]
    public function setStateRejectsCollidingIntegerAndNumericStringCanonicalKeys(): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);

        MiddlewareSpecification::__set_state($this->canonicalState([
            $this->canonicalEntry(
                [
                    'type'  => 'int',
                    'value' => 1,
                ],
                [
                    'type'  => 'string',
                    'value' => 'integer',
                ],
            ),
            $this->canonicalEntry(
                [
                    'type'  => 'string',
                    'value' => '1',
                ],
                [
                    'type'  => 'string',
                    'value' => 'string',
                ],
            ),
        ]));
    }

    #[Test]
    public function setStateAcceptsNonCoercingCanonicalStringKeys(): void
    {
        $specification = MiddlewareSpecification::__set_state($this->canonicalState([
            $this->canonicalEntry(
                [
                    'type'  => 'string',
                    'value' => '01',
                ],
                [
                    'type'  => 'string',
                    'value' => 'value',
                ],
            ),
        ]));

        self::assertSame([
            '01' => 'value',
        ], $specification->arguments);
    }

    #[Test]
    public function nativeUnserializationRejectsMalformedCanonicalState(): void
    {
        $specification               = new MiddlewareSpecification('middleware');
        $state                       = $specification->__serialize();
        $state['canonicalArguments'] = [
            'type'    => 'array',
            'entries' => 'invalid',
        ];

        $this->expectException(InvalidMiddlewareSpecificationException::class);

        $specification->__unserialize($state);
    }

    #[Test]
    public function rejectsSelfReferentialArguments(): void
    {
        $arguments         = [];
        $arguments['self'] = &$arguments;

        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('must not contain references');

        new MiddlewareSpecification('middleware', arguments: $arguments);
    }

    #[Test]
    public function rejectsExternalScalarReferencesBeforeTheyCanMutateASpecification(): void
    {
        $argument  = 'before';
        $arguments = [
            'value' => &$argument,
        ];

        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('must not contain references');

        new MiddlewareSpecification('middleware', arguments: $arguments);
    }

    #[Test]
    public function rejectsExternalArrayReferencesBeforeTheyCanMutateASpecification(): void
    {
        $nested    = [
            'value' => 'before',
        ];
        $arguments = [
            'nested' => &$nested,
        ];

        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage('must not contain references');

        new MiddlewareSpecification('middleware', arguments: $arguments);
    }

    #[Test]
    public function supportsStrictLegacySetStateWithNestedFloatArguments(): void
    {
        $arguments = [
            'nested' => [
                'value' => 1.01,
            ],
        ];
        $specification = MiddlewareSpecification::__set_state([
            'service'   => 'middleware',
            'factory'   => null,
            'arguments' => $arguments,
        ]);

        self::assertSame($arguments, $specification->arguments);
        self::assertSame(
            (new MiddlewareSpecification('middleware', arguments: $arguments))->signature(),
            $specification->signature(),
        );
    }

    /**
     * @param array<string, mixed> $props
     */
    #[Test]
    #[DataProvider('invalidStateProperties')]
    public function setStateRejectsInvalidProperties(array $props, string $message): void
    {
        $this->expectException(InvalidMiddlewareSpecificationException::class);
        $this->expectExceptionMessage($message);

        MiddlewareSpecification::__set_state($props);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidStateProperties(): iterable
    {
        yield 'missing service' => [
            [
                'factory'   => null,
                'arguments' => [],
            ],
            'supported state schema',
        ];

        yield 'missing factory' => [
            [
                'service'   => 'middleware',
                'arguments' => [],
            ],
            'supported state schema',
        ];

        yield 'missing arguments' => [
            [
                'service' => 'middleware',
                'factory' => null,
            ],
            'supported state schema',
        ];

        yield 'unknown property' => [
            [
                'service'   => 'middleware',
                'factory'   => null,
                'arguments' => [],
                'unknown'   => true,
            ],
            'supported state schema',
        ];

        yield 'wrong service type' => [
            [
                'service'   => 1,
                'factory'   => null,
                'arguments' => [],
            ],
            'service must be a string',
        ];

        yield 'empty service' => [
            [
                'service'   => '',
                'factory'   => null,
                'arguments' => [],
            ],
            'service must be a non-empty string',
        ];

        yield 'wrong factory type' => [
            [
                'service'   => 'middleware',
                'factory'   => 1,
                'arguments' => [],
            ],
            'factory must be a string or null',
        ];

        yield 'wrong arguments type' => [
            [
                'service'   => 'middleware',
                'factory'   => null,
                'arguments' => 'arguments',
            ],
            'arguments must be an array',
        ];
    }

    private function ieee754Float(string $hex): float
    {
        $value = unpack('Evalue', pack('H*', $hex))['value'] ?? null;

        if (! is_float($value)) {
            throw new LogicException('Could not create an IEEE-754 float.');
        }

        return $value;
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return array<string, mixed>
     */
    private function canonicalState(array $entries): array
    {
        return [
            'service'            => 'middleware',
            'factory'            => null,
            'arguments'          => [],
            'canonicalArguments' => [
                'type'    => 'array',
                'entries' => $entries,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $key
     * @param array<string, mixed> $value
     *
     * @return array<string, array<string, mixed>>
     */
    private function canonicalEntry(array $key, array $value): array
    {
        return [
            'key'   => $key,
            'value' => $value,
        ];
    }
}
