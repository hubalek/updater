<?php

class DownloadStepHandler
{
    private GitHubDownloader $githubDownloader;
    private HttpClient $httpClient;
    private $debugCallback = null;

    public function __construct(GitHubDownloader $githubDownloader, HttpClient $httpClient)
    {
        $this->githubDownloader = $githubDownloader;
        $this->httpClient = $httpClient;
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
        $this->githubDownloader->setDebugCallback($callback);
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

        // Get asset info (URL and version)
        $filter = $downloadConfig['filter'] ?? null;
        $result = $this->githubDownloader->getAssetInfo($apiUrl, $filter);
        
        if ($result === null) {
            return false;
        }

        // Download file to specific path
        $downloadedFile = $basePath . DIRECTORY_SEPARATOR . basename($result['url']);
        $this->dbg("Downloading to: $downloadedFile");
        if (!$this->httpClient->downloadFile($result['url'], $downloadedFile)) {
            $this->dbg("Download failed");
            return false;
        }
        $this->dbg("Download completed");

        // Set variables for subsequent steps
        $variables['downloadedFile'] = $downloadedFile;
        $variables['version'] = $result['version'];

        return true;
    }
}

