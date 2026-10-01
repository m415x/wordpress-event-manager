<?php
declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    throw new RuntimeException(
        'Composer dependencies are missing. Install them before running PHPUnit.'
    );
}

require_once $autoload;
