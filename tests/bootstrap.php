<?php

declare(strict_types=1);

// PHPUnit must never inherit a locally cached application configuration.
// The cached config can contain the non-testing database and bypass phpunit.xml.
$configCache = dirname(__DIR__).'/bootstrap/cache/config.php';

if (is_file($configCache)) {
    unlink($configCache);
}

require dirname(__DIR__).'/vendor/autoload.php';
