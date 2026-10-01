<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__);
require_once $pluginRoot . '/vendor/autoload.php';

$polyfillsPath = $pluginRoot . '/vendor/yoast/phpunit-polyfills';
if (!is_file($polyfillsPath . '/phpunitpolyfills-autoload.php')) {
    throw new RuntimeException('WordPress PHPUnit polyfills are missing. Run Composer update.');
}

// WordPress Core's test bootstrap requires these polyfills to be discoverable.
require_once $polyfillsPath . '/phpunitpolyfills-autoload.php';
if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfillsPath);
}

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
