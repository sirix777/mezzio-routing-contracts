<?php

declare(strict_types=1);

namespace Sirix\Mezzio\Routing\Contracts;

/**
 * Opt-in contract for route modifiers that aggregate defaults or deduplicate middleware.
 */
interface AggregatingRouteAttributeModifierInterface extends RouteAttributeModifierInterface
{
    /**
     * Merges this modifier's values into the defaults accumulated for the route.
     *
     * @param array<string, mixed> $defaults defaults accumulated before this modifier
     *
     * @return array<string, mixed> defaults to use after this modifier has been applied
     */
    public function mergeDefaults(array $defaults): array;

    /**
     * Returns middleware that must occur at most once for each stable identity key in a route.
     *
     * Array keys are non-empty, stable middleware identity keys. Use a namespaced key where
     * appropriate, for example 'vendor.package.middleware'. A key identifies exactly one
     * middleware identity for the entire route definition. Consumers must add the first value for
     * a key, deduplicate a later equivalent value, and reject a later value with a different
     * identity. Two string identifiers are equivalent only when they are identical. Two
     * MiddlewareSpecification instances are equivalent only when their signature() values are
     * identical; a string and a MiddlewareSpecification are never equivalent.
     *
     * @return array<non-empty-string, MiddlewareSpecification|non-empty-string>
     */
    public function getUniqueMiddleware(): array;
}
