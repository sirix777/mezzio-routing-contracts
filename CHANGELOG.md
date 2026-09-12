# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.2] - 2026-09-12

### Fixed
- Reverted the resource regression introduced in 1.2.1: `MiddlewareSpecification` no longer stores
  a duplicate canonical arguments tree per object. Memory for route tables dropped from 12.5×
  (nested fixtures) and 4.1× (plain fixtures) back to 1.0× of 1.2.0, `var_export()` cache payload
  size returned to 1.0× of 1.2.0, and construction returned to 1.2.0 cost. Raw measurements:
  `benchmarks/results/`, summary: `benchmarks/README.md`.
- Transitional 1.2.1 `__set_state()` payloads whose public `arguments` and `canonicalArguments`
  disagree are now rejected as malformed state instead of silently rehydrating from
  `canonicalArguments`. Finite floats in that comparison are matched by their IEEE-754 bit
  representation, so `0.0` versus `-0.0` mismatches are also rejected; any NaN payload matches
  any other NaN payload, following the NaN identity contract below.
- Native serialization round trips preserve exact float identity under any `serialize_precision`:
  `__serialize()` builds a compact canonical string of the arguments with IEEE-754 float bit
  strings on demand (nothing extra is stored on the object), and `__unserialize()` decodes it
  through the same validated codec used for legacy payloads.

### Changed
- `signature()` encodes the (`service`, `factory`, `arguments`) tuple with `serialize()` under a
  temporarily forced `serialize_precision=-1`: collision-free, ini-independent, computed on
  demand, with no persistent per-instance or global state (`serialize_precision` is restored in a
  `finally` block). All `NAN` values share one canonical
  identity in signatures, while `INF`, `-INF`, `0.0`, and `-0.0` remain distinct. 1.2.1 briefly
  distinguished NaN payload bits; NaN payloads cannot survive `var_export()` rehydration, so they
  are outside the cache-safe contract. Specifications that differ only in NaN payload are now
  equivalent.
- Native serialization payload moved to version 2 (`version`, `service`, `factory`, `arguments`
  holding a compact canonical string). Version 1 payloads (1.2.1) and legacy version-less
  payloads (1.2.0) remain readable.
- Route-cache exports no longer embed `canonicalArguments`; new exports contain only
  `service`, `factory`, and `arguments`.

## [1.2.1] - 2026-09-11

### Fixed
- `MiddlewareSpecification::signature()` now uses a canonical type-tagged encoding of the full
  (`service`, `factory`, and `arguments`) tuple, preserving service/factory boundaries, null values,
  scalar and key types, exact float representations, array order, and nested arguments.
- Route-cache rehydration now preserves exact float representations through a canonical shadow state
  when cache generators export with `serialize_precision=-1`, retains strict legacy cache compatibility,
  and rejects references that could introduce cycles or mutable middleware arguments.

### Changed
- Clarified that consumers deduplicating a repeated unique middleware key must use the canonical
  signature and fail closed when it differs.
- Cache rehydration now rejects missing, unknown, and incorrectly typed properties.

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
