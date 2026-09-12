## Results summary (PHP 8.2.33, no opcache, Linux)

Revisions: 1.2.0 = `06f255652b6fc7dd635f7bcd5c7e59966a1c09a3`, 1.2.1 = `b131d3dff4a64af32121ab5ce46a69da285b9984`,
fix = `b131d3dff4a64af32121ab5ce46a69da285b9984` + dirty working tree (see `dirty_files` in each
raw JSON). Every raw JSON file records the full commit SHA, the list of dirty files, the harness
SHA-256, the PHP version, OS, opcache status, memory limit, and 3 worker-process samples: the
harness spawns one fresh PHP process per sample, so allocator and autoload warmup never leak
between samples. The figures below are medians of those samples.

| Metric (median of 3 worker samples) | 1.2.0 | 1.2.1 (b131d3d) | 1.2.2 fix | fix/1.2.0 | Gate |
| --- | ---: | ---: | ---: | ---: | --- |
| plain_100k_memory_bytes | 12,749,760 | 51,996,136 | 12,766,008 | 1.00 | ≤2× ✅ |
| plain_100k_construction_ms | 13.4 | 30.6 | 11.1 | 0.83 | — |
| nested_10k_memory_bytes | 104,186,296 | 1,295,866,296 | 104,186,296 | 1.00 | ≤2× ✅ |
| nested_10k_construction_ms | 82.0 | 534.3 | 95.3 | 1.16 | — |
| nested_10k_construction_plus_signature_ms | 86.1 | 1,012.8 | 94.7 | 1.10 | — |
| repeated_signature_100k_ms | 10.7 | 279.9 | 20.2 | 1.90 | ≤3× ✅ |
| var_export_typical_bytes | 246 | 1,081 | 246 | 1.00 | ≤2× ✅ |
| var_export_nested_bytes | 2,853 | 34,456 | 2,853 | 1.00 | ≤2× ✅ |
| serialize_typical_bytes | 219 | 633 | 235 | 1.07 | — |
| serialize_nested_bytes | 2,146 | 14,076 | 2,141 | 1.00 | — |
| retained_after_release_bytes | 0 | 0 | 0 | n/a | 0 ✅ |
| signed_100k_live_memory_bytes | 11,701,232 | 50,897,240 | 11,701,232 | 1.00 | ≤2× ✅ |
| signed_100k_retained_after_gc_bytes | -96 | -4,088 | -96 | n/a | ~0 ✅ |

`signature()` encodes the `(service, factory, arguments)` tuple with `serialize()` under a forced
`serialize_precision=-1` (restored afterwards), so it is ini-independent, collision-free
(length-prefixed strings), and holds no persistent per-instance or global state. The
`signed_100k_*` scenario mirrors the review memory gate: 100 000 objects, one `signature()` call
per object, then `unset()` + `gc_collect_cycles()`; retained memory returns to the 1.2.0 baseline
(negative values are allocator noise below the pre-wave watermark).

Native serialization stores a compact canonical string of the arguments with exact IEEE-754
float bit strings, built on demand inside `__serialize()`; rehydration is independent of
`serialize_precision` and rejects non-canonical numbers (leading zeros, values beyond
`PHP_INT_MAX`/below `PHP_INT_MIN`).

Raw JSON files live next to this README. Regenerate with:

    php -d memory_limit=3G benchmarks/middleware-specification-benchmark.php <label>

For final release evidence, regenerate after committing the fix so that `dirty_files` is empty.