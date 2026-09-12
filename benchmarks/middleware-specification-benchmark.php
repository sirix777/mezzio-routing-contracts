<?php

declare(strict_types=1);

/**
 * Isolated MiddlewareSpecification resource benchmark.
 *
 * Measures construction, memory, signature() and var_export() size for the
 * payloads named in docs/plans/01-middleware-specification-resource-hardening.md:
 *
 *  - 100 000 specifications without arguments (memory + construction time);
 *  - 10 000 specifications with 20 nested entries (memory + construction + signature time);
 *  - 100 000 repeated signature() calls of one typical object;
 *  - var_export() and serialize() size for a typical 3-scalar specification and a
 *    20-nested-entry specification;
 *  - retained memory after releasing route definitions;
 *  - signed memory: 100 000 objects, one signature() call each, before and after gc.
 *
 * Usage: php benchmarks/middleware-specification-benchmark.php [label] [--samples=N]
 * Output: machine-readable JSON on stdout. The orchestrator spawns one fresh PHP worker
 * process per sample, so allocator and autoload warmup never leak between samples.
 */

use Sirix\Mezzio\Routing\Contracts\MiddlewareSpecification;

require \dirname(__DIR__) . '/vendor/autoload.php';

/** @return list<array{label: string, unit: string, value: float}> */
function opcacheEnabled(): bool
{
    return \extension_loaded('Zend OPcache')
        && (filter_var(\ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOLEAN) || \ini_get('opcache.enable') === '1');
}

function runBenchmarks(): array
{
    $results = [];

    // -- 100 000 specifications without arguments --------------------------------------------
    gc_collect_cycles();
    $memoryBefore = memory_get_usage();
    $start        = hrtime(true);
    $plainSpecs   = [];
    for ($i = 0; $i < 100_000; ++$i) {
        $plainSpecs[] = new MiddlewareSpecification('service.middleware');
    }
    $elapsed = (hrtime(true) - $start) / 1e6;
    $plainMemory = memory_get_usage() - $memoryBefore;
    $results[] = ['label' => 'plain_100k_memory_bytes', 'unit' => 'bytes', 'value' => (float) $plainMemory];
    $results[] = ['label' => 'plain_100k_construction_ms', 'unit' => 'ms', 'value' => $elapsed];
    unset($plainSpecs);

    // -- 10 000 specifications with 20 nested entries ----------------------------------------
    gc_collect_cycles();
    $memoryBefore = memory_get_usage();
    $start        = hrtime(true);
    $nestedSpecs  = [];
    for ($i = 0; $i < 10_000; ++$i) {
        $arguments = [];
        for ($j = 0; $j < 20; ++$j) {
            $arguments['entry_' . $j] = [
                'enabled' => true,
                'rate'    => 1.5 + $j,
                'name'    => 'value-' . $j,
                'retries' => $j,
            ];
        }
        $nestedSpecs[] = new MiddlewareSpecification('service.middleware', null, $arguments);
    }
    $elapsed = (hrtime(true) - $start) / 1e6;
    $nestedMemory = memory_get_usage() - $memoryBefore;
    $results[] = ['label' => 'nested_10k_memory_bytes', 'unit' => 'bytes', 'value' => (float) $nestedMemory];
    $results[] = ['label' => 'nested_10k_construction_ms', 'unit' => 'ms', 'value' => $elapsed];

    // -- construction + signature() for the same nested payload -------------------------------
    gc_collect_cycles();
    $start = hrtime(true);
    for ($i = 0; $i < 10_000; ++$i) {
        $arguments = [];
        for ($j = 0; $j < 20; ++$j) {
            $arguments['entry_' . $j] = [
                'enabled' => true,
                'rate'    => 1.5 + $j,
                'name'    => 'value-' . $j,
                'retries' => $j,
            ];
        }
        $specification = new MiddlewareSpecification('service.middleware', null, $arguments);
        $specification->signature();
    }
    unset($specification, $arguments);
    $elapsed = (hrtime(true) - $start) / 1e6;
    $results[] = ['label' => 'nested_10k_construction_plus_signature_ms', 'unit' => 'ms', 'value' => $elapsed];
    unset($nestedSpecs);

    // -- 100 000 repeated signature() of one typical object -----------------------------------
    $specification = new MiddlewareSpecification('auth.middleware', 'AuthFactory', [
        'profile'  => 'api',
        'enabled'  => true,
        'attempts' => 3,
    ]);
    $signature = $specification->signature();
    $start     = hrtime(true);
    for ($i = 0; $i < 100_000; ++$i) {
        $signature = $specification->signature();
    }
    $elapsed = (hrtime(true) - $start) / 1e6;
    $results[] = ['label' => 'repeated_signature_100k_ms', 'unit' => 'ms', 'value' => $elapsed];

    // -- var_export() size --------------------------------------------------------------------
    $typical = new MiddlewareSpecification('auth.middleware', 'AuthFactory', [
        'profile'  => 'api',
        'enabled'  => true,
        'attempts' => 3,
    ]);
    $typicalExport = var_export($typical, true);
    $results[] = ['label' => 'var_export_typical_bytes', 'unit' => 'bytes', 'value' => (float) strlen($typicalExport)];

    $nestedArguments = [];
    for ($j = 0; $j < 20; ++$j) {
        $nestedArguments['entry_' . $j] = [
            'enabled' => true,
            'rate'    => 1.5 + $j,
            'name'    => 'value-' . $j,
            'retries' => $j,
        ];
    }
    $nested = new MiddlewareSpecification('service.middleware', null, $nestedArguments);
    $nestedExport = var_export($nested, true);
    $results[] = ['label' => 'var_export_nested_bytes', 'unit' => 'bytes', 'value' => (float) strlen($nestedExport)];

    // -- native serialized payload size --------------------------------------------------------
    $results[] = ['label' => 'serialize_typical_bytes', 'unit' => 'bytes', 'value' => (float) strlen(serialize($typical))];
    $results[] = ['label' => 'serialize_nested_bytes', 'unit' => 'bytes', 'value' => (float) strlen(serialize($nested))];

    // -- retained memory after releasing route definitions ------------------------------------
    gc_collect_cycles();
    $memoryBefore = memory_get_usage();
    $routeTable   = [];
    for ($i = 0; $i < 10_000; ++$i) {
        $routeTable[] = new MiddlewareSpecification('route.middleware', null, [
            'profile' => 'api',
            'flags'   => ['a' => true, 'b' => false],
        ]);
    }
    unset($routeTable);
    gc_collect_cycles();
    $retained = memory_get_usage() - $memoryBefore;
    $results[] = ['label' => 'retained_after_release_bytes', 'unit' => 'bytes', 'value' => (float) $retained];

    // -- signed memory: 100 000 objects, one signature() call each -----------------------------
    gc_collect_cycles();
    $memoryBefore = memory_get_usage();
    $signedWave   = [];
    for ($i = 0; $i < 100_000; ++$i) {
        $specification = new MiddlewareSpecification('service.middleware');
        $signedWave[]  = $specification;
        $specification->signature();
    }
    $signedLive = memory_get_usage() - $memoryBefore;
    unset($signedWave, $specification);
    gc_collect_cycles();
    $signedRetained = memory_get_usage() - $memoryBefore;
    $results[] = ['label' => 'signed_100k_live_memory_bytes', 'unit' => 'bytes', 'value' => (float) $signedLive];
    $results[] = ['label' => 'signed_100k_retained_after_gc_bytes', 'unit' => 'bytes', 'value' => (float) $signedRetained];

    return $results;
}

$label         = $argv[1] ?? 'unlabeled';
$revision      = trim((string) shell_exec(sprintf(
    'git -C %s rev-parse HEAD 2>/dev/null',
    escapeshellarg(\dirname(__DIR__)),
)));
$dirtyRaw = (string) shell_exec(sprintf(
    'git -C %s status --porcelain -- src test benchmarks/middleware-specification-benchmark.php 2>/dev/null',
    escapeshellarg(\dirname(__DIR__)),
));
/** @var list<string> $dirty */
$dirty = preg_split('/\R/', rtrim($dirtyRaw, "\r\n"), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$harnessSha256 = hash_file('sha256', __FILE__) ?: null;

// -- worker mode: run one sample in this (fresh) process and print its results as JSON ------
if (in_array('--worker', $argv, true)) {
    echo json_encode(
        [
            'php'          => PHP_VERSION,
            'os'           => PHP_OS,
            'opcache'      => opcacheEnabled(),
            'memory_limit' => (string) ini_get('memory_limit'),
            'results'      => runBenchmarks(),
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ), PHP_EOL;

    exit(0);
}

$samples = 3;

foreach ($argv as $argument) {
    if (preg_match('/^--samples=([1-9][0-9]*)\z/D', (string) $argument, $matches) === 1) {
        $digits = $matches[1];

        // Round-trip check rejects values beyond the int range, where the cast saturates.
        if (strlen($digits) > strlen((string) PHP_INT_MAX) || (int) $digits . '' !== $digits) {
            fwrite(STDERR, sprintf('--samples=%s is out of range, using the default of %d.', $digits, $samples) . PHP_EOL);

            break;
        }

        $maxSamples = 16;

        if ((int) $digits > $maxSamples) {
            fwrite(STDERR, sprintf('--samples=%d exceeds the upper bound of %d, using %d.', (int) $digits, $maxSamples, $maxSamples) . PHP_EOL);
            $samples = $maxSamples;

            break;
        }

        $samples = (int) $digits;

        break;
    }
}

$runs     = [];
$phps     = [];
$oses     = [];
$opcaches = [];
$limits   = [];

for ($sample = 1; $sample <= $samples; ++$sample) {
    $output = shell_exec(sprintf(
        '%s -d memory_limit=3G %s --worker 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__FILE__),
    ));

    if (! is_string($output)) {
        fwrite(STDERR, 'Worker process failed to start.' . PHP_EOL);
        exit(1);
    }

    $decoded = json_decode($output, true);

    if (! is_array($decoded)) {
        fwrite(STDERR, 'Worker output was not valid JSON.' . PHP_EOL);
        exit(1);
    }

    $runs[]     = $decoded['results'];
    $phps[]     = $decoded['php'];
    $oses[]     = $decoded['os'];
    $opcaches[] = $decoded['opcache'];
    $limits[]   = $decoded['memory_limit'];
}

echo json_encode(
    [
        'label'          => $label,
        'revision'       => '' !== $revision ? $revision : null,
        'dirty_files'    => $dirty,
        'harness_sha256' => $harnessSha256,
        'php'            => implode(', ', array_unique($phps)),
        'os'             => implode(', ', array_unique($oses)),
        'opcache'        => implode(', ', array_map(static fn ($value): string => $value ? 'on' : 'off', $opcaches)),
        'memory_limit'   => implode(', ', array_unique($limits)),
        'samples'        => count($runs),
        'results'        => $runs,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
), PHP_EOL;