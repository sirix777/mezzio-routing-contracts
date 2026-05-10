# Mezzio Routing Contracts
[![Latest Stable Version](http://poser.pugx.org/sirix/mezzio-routing-contracts/v)](https://packagist.org/packages/sirix/mezzio-routing-contracts) [![Total Downloads](http://poser.pugx.org/sirix/mezzio-routing-contracts/downloads)](https://packagist.org/packages/sirix/mezzio-routing-contracts) [![Latest Unstable Version](http://poser.pugx.org/sirix/mezzio-routing-contracts/v/unstable)](https://packagist.org/packages/sirix/mezzio-routing-contracts) [![License](http://poser.pugx.org/sirix/mezzio-routing-contracts/license)](https://packagist.org/packages/sirix/mezzio-routing-contracts) [![PHP Version Require](http://poser.pugx.org/sirix/mezzio-routing-contracts/require/php)](https://packagist.org/packages/sirix/mezzio-routing-contracts)

Contracts for [sirix/mezzio-routing-attributes](https://github.com/sirix777/mezzio-routing-attributes) route attribute modifiers.

> **Pre-1.0 package:** Not yet production-ready. Public contracts may change with breaking changes before `1.0.0`.

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

## Interface

```php
interface RouteAttributeModifierInterface
{
    /** @return list<class-string<MiddlewareInterface>|non-empty-string> */
    public function getMiddleware(): array;

    /** @return array<string, mixed> */
    public function getDefaults(): array;
}
```
