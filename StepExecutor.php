<?php

class StepExecutor
{
    private FileManager $fileManager;
    private HttpClient $httpClient;
    private GitHubParser $githubParser;
    private ConfigLoader $configLoader;
    private $debugCallback = null;

    public function __construct(FileManager $fileManager, HttpClient $httpClient, GitHubParser $githubParser, ConfigLoader $configLoader)
    {
        $this->fileManager = $fileManager;
        $this->httpClient = $httpClient;
        $this->githubParser = $githubParser;
        $this->configLoader = $configLoader;
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
    }

    private function dbg(string $msg): void
    {
        if ($this->debugCallback !== null) {
            ($this->debugCallback)($msg);
        }
    }

    /**
     * Replace variables in string with actual values
     */
    private function replaceVariables(string $str, array $vars): string
    {
        $result = $str;
        foreach ($vars as $key => $value) {
            $result = str_replace('$' . $key, $value, $result);
            $result = str_replace('{' . $key . '}', $value, $result);
        }
        return $result;
    }

    /**
     * Execute a single step
     * @param array $step Step configuration
     * @param array $variables Variables that can be modified by the step (passed by reference)
     * @param string $basePath Base path for resolving relative paths
     * @return bool Success status
     */
    public function executeStep(array $step, array &$variables, string $basePath): bool
    {
        if (isset($step['download'])) {
            return $this->executeDownload($step['download'], $variables, $basePath);
        }

        if (isset($step['extract7z'])) {
            return $this->executeExtract7z($step['extract7z'], $variables, $basePath);
        }

        if (isset($step['extractZip'])) {
            return $this->executeExtractZip($step['extractZip'], $variables, $basePath);
        }

        if (isset($step['move'])) {
            return $this->executeMove($step['move'], $variables, $basePath);
        }

        $this->dbg("Unknown step type: " . json_encode($step));
        return false;
    }

    /**
     * Execute extract7z step
     */
    private function executeExtract7z(string $source, array $variables, string $basePath): bool
    {
        $sourcePath = $this->replaceVariables($source, $variables);
        
        // Normalize path separators first
        $sourcePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourcePath);
        
        // If path is not absolute (doesn't start with drive letter or UNC), make it relative to basePath
        if (!preg_match('/^[A-Za-z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . '|^' . preg_quote(DIRECTORY_SEPARATOR, '/') . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $sourcePath)) {
            $sourcePath = $basePath . DIRECTORY_SEPARATOR . $sourcePath;
        }

        $sourcePath = realpath($sourcePath) ?: $sourcePath;

        $this->dbg("Extracting 7z: $sourcePath");

        if (!file_exists($sourcePath)) {
            $this->dbg("Source file does not exist: $sourcePath");
            return false;
        }

        // Extract to finalDir if available, otherwise to same directory as source file
        $extractTo = $variables['finalDir'] ?? dirname($sourcePath);
        
        return $this->fileManager->extract7z($sourcePath, $extractTo);
    }

    /**
     * Execute extractZip step
     */
    private function executeExtractZip(string $source, array $variables, string $basePath): bool
    {
        $sourcePath = $this->replaceVariables($source, $variables);
        
        // Normalize path separators first
        $sourcePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourcePath);
        
        // If path is not absolute (doesn't start with drive letter or UNC), make it relative to basePath
        if (!preg_match('/^[A-Za-z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . '|^' . preg_quote(DIRECTORY_SEPARATOR, '/') . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $sourcePath)) {
            $sourcePath = $basePath . DIRECTORY_SEPARATOR . $sourcePath;
        }

        $sourcePath = realpath($sourcePath) ?: $sourcePath;

        $this->dbg("Extracting ZIP: $sourcePath");

        if (!file_exists($sourcePath)) {
            $this->dbg("Source file does not exist: $sourcePath");
            return false;
        }

        // Extract to finalDir if available, otherwise to same directory as source file
        $extractTo = $variables['finalDir'] ?? dirname($sourcePath);
        
        return $this->fileManager->extractZip($sourcePath, $extractTo);
    }

    /**
     * Execute move step
     */
    private function executeMove(array $moveConfig, array $variables, string $basePath): bool
    {
        $from = $moveConfig['from'] ?? '';
        $to = $moveConfig['to'] ?? '';

        if (empty($from) || empty($to)) {
            $this->dbg("Move step missing 'from' or 'to'");
            return false;
        }

        $fromPath = $this->replaceVariables($from, $variables);
        $toPath = $this->replaceVariables($to, $variables);

        // Normalize path separators first
        $fromPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fromPath);
        $toPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $toPath);

        // If paths are not absolute (don't start with drive letter or UNC), make them relative to basePath
        if (!preg_match('/^[A-Za-z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . '|^' . preg_quote(DIRECTORY_SEPARATOR, '/') . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $fromPath)) {
            $fromPath = $basePath . DIRECTORY_SEPARATOR . $fromPath;
        }
        if (!preg_match('/^[A-Za-z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . '|^' . preg_quote(DIRECTORY_SEPARATOR, '/') . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $toPath)) {
            $toPath = $basePath . DIRECTORY_SEPARATOR . $toPath;
        }

        // Handle wildcards in from path
        if (strpos($fromPath, '*') !== false) {
            return $this->moveWithWildcard($fromPath, $toPath);
        }

        $this->dbg("Moving: $fromPath -> $toPath");

        if (!file_exists($fromPath)) {
            $this->dbg("Source does not exist: $fromPath");
            return false;
        }

        // Create destination directory if it doesn't exist
        $toDir = is_dir($toPath) ? $toPath : dirname($toPath);
        if (!is_dir($toDir)) {
            mkdir($toDir, 0777, true);
        }

        // If destination is a directory, move into it
        if (is_dir($toPath)) {
            $toPath = $toPath . DIRECTORY_SEPARATOR . basename($fromPath);
        }

        return $this->fileManager->moveFileOrDir($fromPath, $toPath);
    }

    /**
     * Move files matching wildcard pattern
     */
    private function moveWithWildcard(string $fromPattern, string $toDir): bool
    {
        $baseDir = dirname($fromPattern);
        $pattern = basename($fromPattern);

        if (!is_dir($baseDir)) {
            $this->dbg("Base directory does not exist: $baseDir");
            return false;
        }

        if (!is_dir($toDir)) {
            mkdir($toDir, 0777, true);
        }

        $files = glob($baseDir . DIRECTORY_SEPARATOR . $pattern);
        if (empty($files)) {
            $this->dbg("No files match pattern: $fromPattern");
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            $dest = $toDir . DIRECTORY_SEPARATOR . basename($file);
            $this->dbg("Moving: $file -> $dest");
            if (!$this->fileManager->moveFileOrDir($file, $dest)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Execute download step
     * Supports GitHub API (url + filter) or HTML pages (pageUrl + findLink + versionFrom)
     * Sets $downloadedFile and $version in variables
     */
    private function executeDownload(array $downloadConfig, array &$variables, string $basePath): bool
    {
        // GitHub API download
        if (isset($downloadConfig['url'])) {
            return $this->executeDownloadGitHub($downloadConfig, $variables, $basePath);
        }

        // HTML page download (not implemented yet)
        if (isset($downloadConfig['pageUrl'])) {
            $this->dbg("HTML page download not yet implemented");
            return false;
        }

        $this->dbg("Download step missing 'url' or 'pageUrl'");
        return false;
    }

    /**
     * Download from GitHub API
     */
    private function executeDownloadGitHub(array $downloadConfig, array &$variables, string $basePath): bool
    {
        $apiUrl = $downloadConfig['url'] ?? '';
        if (empty($apiUrl)) {
            $this->dbg("Download step missing 'url'");
            return false;
        }

        $this->dbg("Fetching GitHub API: $apiUrl");
        $json = $this->httpClient->httpGet($apiUrl);
        if ($json === false) {
            $this->dbg("HTTP request failed");
            return false;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data["assets"])) {
            $this->dbg("API response does not contain assets");
            return false;
        }

        // Merge filter with defaults
        $filter = $this->configLoader->mergeFilters($downloadConfig['filter'] ?? null);
        
        // Find matching asset
        $assetUrl = $this->githubParser->findAsset($data["assets"], $filter);
        if ($assetUrl === null) {
            $this->dbg("No matching asset found");
            return false;
        }

        // Extract version from asset URL
        $version = $this->githubParser->extractVersionName($assetUrl);
        $this->dbg("Extracted version: $version");

        // Download file
        $downloadedFile = $basePath . DIRECTORY_SEPARATOR . basename($assetUrl);
        $this->dbg("Downloading to: $downloadedFile");
        if (!$this->httpClient->downloadFile($assetUrl, $downloadedFile)) {
            $this->dbg("Download failed");
            return false;
        }
        $this->dbg("Download completed");

        // Set variables for subsequent steps
        $variables['downloadedFile'] = $downloadedFile;
        $variables['version'] = $version;

        return true;
    }
}

