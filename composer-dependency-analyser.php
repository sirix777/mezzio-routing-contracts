<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$configuration = new Configuration();

// The contract references MiddlewareInterface in PHPDoc to keep the runtime API
// minimal while still exposing precise static-analysis types to consumers.
$configuration->ignoreErrorsOnPackage('psr/http-server-middleware', [ErrorType::UNUSED_DEPENDENCY]);

return $configuration;
