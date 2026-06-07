<?php
declare(strict_types=1);

$tests = [
    __DIR__ . '/hp-checkout-shipping-filter-contract-test.php',
];

foreach ($tests as $test) {
    passthru(escapeshellarg(PHP_BINARY ?: 'php') . ' ' . escapeshellarg($test), $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}

echo "HP Conditional Shipping PHP contract test suite passed.\n";
