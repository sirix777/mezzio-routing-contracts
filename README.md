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
