<?php
ini_set('memory_limit', '2048M');

require_once __DIR__ . '/Updater.php';

/* načtení .env */
$envFile = __DIR__ . '/.env';
if (!is_file($envFile)) {
    throw new RuntimeException('.env file not found');
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#') continue;
    [$key, $value] = array_map('trim', explode('=', $line, 2));
    $_ENV[$key] = trim($value, '"\'');
}

if (empty($_ENV['SOFTWARE_ROOT'])) {
    throw new RuntimeException('SOFTWARE_ROOT not defined in .env');
}

$root = $_ENV['SOFTWARE_ROOT'];

$u = new Updater($root, debug: false);
$u->run();
