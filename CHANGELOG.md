# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-09-10

### Added
- `AggregatingRouteAttributeModifierInterface`, an opt-in contract for merging accumulated
  route defaults and declaring middleware with a stable, per-route identity key.

## [1.1.0] - 2026-08-18

### Added
- `MiddlewareSpecification` value object for recipe-based middleware resolution.
- `MiddlewareFactoryInterface` for container-aware middleware construction from specifications.
- `InvalidMiddlewareSpecificationException` for scalar-only argument enforcement.

### Changed
- `RouteAttributeModifierInterface::getMiddleware()` return type widened to include `MiddlewareSpecification` (BC-additive).

## [1.0.0] - 2026-05-25

### Added
- Stable `RouteAttributeModifierInterface` contract for route attribute modifiers.
- Documentation for middleware identifiers, route defaults, and SemVer expectations.

## [0.1.0] - 2026-05-09

### Added
- `RouteAttributeModifierInterface` - contract for route attribute modifiers
  - `getMiddleware(): array` - returns middleware FQCNs to append to the route pipeline
  - `getDefaults(): array` - returns default route options (e.g. optional placeholder values)
