<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$testsDir = getenv('WP_TESTS_DIR');

if (!is_string($testsDir) || $testsDir === '') {
    throw new RuntimeException(
        'WP_TESTS_DIR is required. Execute integration tests inside the dedicated wp-env test configuration.'
    );
}

$wordpressBootstrap = rtrim($testsDir, '/') . '/includes/bootstrap.php';

if (!is_file($wordpressBootstrap)) {
    throw new RuntimeException('WordPress PHPUnit bootstrap not found in WP_TESTS_DIR.');
}

// Important: do not load the historical plugin automatically here.
// WEM-3 records its known fatal bootstrap error. Plugin activation
// receives its own bounded regression coverage during remediation.
require_once $wordpressBootstrap;
