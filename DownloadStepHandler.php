<?php

class DownloadStepHandler
{
    private HttpClient $httpClient;
    private GitHubParser $githubParser;
    private ConfigLoader $configLoader;
    private $debugCallback = null;

    public function __construct(HttpClient $httpClient, GitHubParser $githubParser, ConfigLoader $configLoader)
    {
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
     * Execute download step
     * Supports GitHub API (url + filter) or HTML pages (pageUrl + findLink + versionFrom)
     * Sets $downloadedFile and $version in variables
     */
    public function execute(array $downloadConfig, array &$variables, string $basePath): bool
    {
        // GitHub API download
        if (isset($downloadConfig['url'])) {
            return $this->executeGitHub($downloadConfig, $variables, $basePath);
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
    private function executeGitHub(array $downloadConfig, array &$variables, string $basePath): bool
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

