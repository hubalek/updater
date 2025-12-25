<?php
ini_set('memory_limit', '2048M');

require_once __DIR__ . '/DebugTrait.php';
require_once __DIR__ . '/ConfigLoader.php';
require_once __DIR__ . '/UrlFilter.php';
require_once __DIR__ . '/GitHubParser.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/GitHubDownloader.php';
require_once __DIR__ . '/HtmlPageDownloader.php';
require_once __DIR__ . '/ZipExtractor.php';
require_once __DIR__ . '/SevenZipExtractor.php';
require_once __DIR__ . '/FileManager.php';
require_once __DIR__ . '/InstallationScanner.php';
require_once __DIR__ . '/JunctionManager.php';
require_once __DIR__ . '/DirectoryScanner.php';
require_once __DIR__ . '/PathResolver.php';
require_once __DIR__ . '/DownloadStepHandler.php';
require_once __DIR__ . '/ExtractStepHandler.php';
require_once __DIR__ . '/MoveStepHandler.php';
require_once __DIR__ . '/UtilityStepHandler.php';
require_once __DIR__ . '/StepExecutor.php';
require_once __DIR__ . '/UpdateProcessor.php';
require_once __DIR__ . '/UpdateOrchestrator.php';

// Load .env file
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

// Load debug setting from .env, default to false if not set
$debug = isset($_ENV['DEBUG']) && strtolower(trim($_ENV['DEBUG'])) === 'true';

$u = new UpdateOrchestrator($root, debug: $debug);
$u->run();

