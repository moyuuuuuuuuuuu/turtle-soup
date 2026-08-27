<?php

declare(strict_types=1);

use App\Common\Support\ProductionConfiguration;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = $argv[1] ?? dirname(__DIR__) . '/.env';
if (!is_readable($path)) {
    fwrite(STDERR, "Production environment file is not readable: {$path}\n");
    exit(2);
}

$values = parse_ini_file($path, false, INI_SCANNER_RAW);
if (!is_array($values)) {
    fwrite(STDERR, "Production environment file cannot be parsed: {$path}\n");
    exit(2);
}

$normalized = [];
foreach ($values as $name => $value) {
    if (is_string($name) && is_scalar($value)) {
        $normalized[$name] = (string) $value;
    }
}

if (($normalized['APP_ENV'] ?? '') !== 'production') {
    fwrite(STDERR, "APP_ENV must be production for a production configuration check.\n");
    exit(1);
}

$violations = ProductionConfiguration::violations($normalized);
if ($violations !== []) {
    fwrite(STDERR, "Production configuration is invalid:\n- " . implode("\n- ", $violations) . "\n");
    exit(1);
}

fwrite(STDOUT, "Production configuration is valid. No secret values were displayed.\n");
