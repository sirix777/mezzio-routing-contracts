# Mezzio Routing Contracts
[![Latest Stable Version](http://poser.pugx.org/sirix/mezzio-routing-contracts/v)](https://packagist.org/packages/sirix/mezzio-routing-contracts) [![Total Downloads](http://poser.pugx.org/sirix/mezzio-routing-contracts/downloads)](https://packagist.org/packages/sirix/mezzio-routing-contracts) [![Latest Unstable Version](http://poser.pugx.org/sirix/mezzio-routing-contracts/v/unstable)](https://packagist.org/packages/sirix/mezzio-routing-contracts) [![License](http://poser.pugx.org/sirix/mezzio-routing-contracts/license)](https://packagist.org/packages/sirix/mezzio-routing-contracts) [![PHP Version Require](http://poser.pugx.org/sirix/mezzio-routing-contracts/require/php)](https://packagist.org/packages/sirix/mezzio-routing-contracts)

Contracts for [sirix/mezzio-routing-attributes](https://github.com/sirix777/mezzio-routing-attributes) route attribute modifiers.

This package contains the stable public contract used by routing attributes that need to contribute middleware or default route options to a route definition.

## Installation

```bash
composer require sirix/mezzio-routing-contracts
```

## Usage

Implement `RouteAttributeModifierInterface` on any route-related attribute to inject middleware and/or provide default route options.

```php
use Attribute;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RequireTenant implements RouteAttributeModifierInterface
{
    public function __construct(private string $tenantHeader = 'x-tenant-id') {}

    public function getMiddleware(): array
    {
        return [RequireTenantMiddleware::class];
    }

    public function getDefaults(): array
    {
        return ['tenant_header' => $this->tenantHeader];
    }
}
```

Usage with routing attributes:

```php
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

final class OrdersHandler
{
    #[Get('/orders', name: 'orders.list')]
    #[RequireTenant('x-org-id')]
    public function list(): ResponseInterface
    {
        // ...
    }
}
```

The routing-attributes package discovers all implementations of `RouteAttributeModifierInterface` at boot time and merges their middleware and defaults into the route definition.

## MiddlewareSpecification

`MiddlewareSpecification` is a serializable value object that describes how to build a middleware instance via a factory. Use it when an attribute needs to pass recipe-style arguments to a middleware factory instead of returning only a plain service id.

```php
use Attribute;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Authenticated implements RouteAttributeModifierInterface
{
    public function __construct(private string $profile = 'default') {}

    public function getMiddleware(): array
    {
        return [
            new MiddlewareSpecification(
                service: AuthenticatedMiddleware::class,
                factory: AuthenticatedMiddlewareFactory::class,
                arguments: ['profile' => $this->profile],
            ),
        ];
    }

    public function getDefaults(): array
    {
        return [];
    }
}
```

## MiddlewareFactoryInterface

Implement this contract to turn a `MiddlewareSpecification` into a `MiddlewareInterface` instance. The factory receives the PSR-11 container and the specification, so it can resolve dependencies and interpret the recipe at pipeline-build time.

```php
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareFactoryInterface;
use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

final readonly class AuthenticatedMiddlewareFactory implements MiddlewareFactoryInterface
{
    public function create(
        ContainerInterface $container,
        MiddlewareSpecification $specification,
    ): MiddlewareInterface {
        return new AuthenticatedMiddleware(profile: $specification->arguments['profile']);
    }
}
```

## Contract

```php
interface RouteAttributeModifierInterface
{
    /**
     * @return list<class-string<MiddlewareInterface>|non-empty-string|MiddlewareSpecification>
     */
    public function getMiddleware(): array;

    /** @return array<string, mixed> */
    public function getDefaults(): array;
}
```

### `AggregatingRouteAttributeModifierInterface`

Implement this opt-in extension when repeatable modifiers must accumulate values under
the same defaults key, or when one of their middleware entries must be included only once
per route definition. Existing `RouteAttributeModifierInterface` implementations keep their
shallow defaults merge and repeatable middleware behavior.

```php
interface AggregatingRouteAttributeModifierInterface extends RouteAttributeModifierInterface
{
    /**
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public function mergeDefaults(array $defaults): array;

    /**
     * @return array<non-empty-string, MiddlewareSpecification|non-empty-string>
     */
    public function getUniqueMiddleware(): array;
}
```

### `mergeDefaults()`

Receives the defaults accumulated for the route before the modifier is applied and must return
the complete defaults array to use afterwards. This lets a modifier append to an existing list
instead of replacing it through the ordinary shallow merge.

### `getUniqueMiddleware()`

Returns an associative array whose non-empty string keys are stable middleware identity keys. Prefer
a namespaced key such as `vendor.package.middleware`. A key identifies exactly one middleware identity
for an entire route definition. Each value is a non-empty middleware service identifier or a
`MiddlewareSpecification`.

A consumer adds the first value for a key and deduplicates only later values with the same identity.
It must reject a later value for the same key when its identity differs: string identifiers are equal
only when identical; `MiddlewareSpecification` values are equal only when their `signature()` values
are identical; and a string is never equal to a `MiddlewareSpecification`. Unrelated middleware and
middleware returned by `getMiddleware()` remain repeatable.

### `getMiddleware()`

Returns middleware identifiers that should be appended to the route pipeline.
Each item must be one of:

- a middleware class name implementing `Psr\Http\Server\MiddlewareInterface`;
- another non-empty middleware identifier supported by the consuming router integration;
- a `MiddlewareSpecification` describing how to build a middleware instance via a factory.

Existing implementations that return only strings remain valid; the `MiddlewareSpecification` type is additive.

### `getDefaults()`

Returns default route options keyed by option name.
Consumers merge these values into the route defaults/options for the route that carries the attribute.

## Caching

Route definitions (including middleware specifications) are exported into PHP route-cache files. Because `MiddlewareSpecification` is rehydrated through `__set_state`, its `$arguments` are restricted to serializable scalars and nested scalar arrays. The constructor enforces this invariant at creation time and again during rehydration, so cached route definitions cannot accidentally contain non-exportable values.

## Versioning

The `1.x` series follows [Semantic Versioning](https://semver.org/).
Breaking changes to public contracts are reserved for the next major version.
