<?php

class DownloadStepHandler
{
    use DebugTrait;

    private GitHubDownloader $githubDownloader;
    private HtmlPageDownloader $htmlPageDownloader;
    private HttpClient $httpClient;

    public function __construct(GitHubDownloader $githubDownloader, HtmlPageDownloader $htmlPageDownloader, HttpClient $httpClient)
    {
        $this->githubDownloader = $githubDownloader;
        $this->htmlPageDownloader = $htmlPageDownloader;
        $this->httpClient = $httpClient;
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
        $this->githubDownloader->setDebugCallback($callback);
        $this->htmlPageDownloader->setDebugCallback($callback);
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

        // HTML page download
        if (isset($downloadConfig['pageUrl'])) {
            return $this->executeHtmlPage($downloadConfig, $variables, $basePath);
        }

        $this->dbg("Download step missing 'url' or 'pageUrl'");
        return false;
    }

    /**
     * Download from HTML page
     */
    private function executeHtmlPage(array $downloadConfig, array &$variables, string $basePath): bool
    {
        $pageUrl = $downloadConfig['pageUrl'] ?? '';
        if (empty($pageUrl)) {
            $this->dbg("Download step missing 'pageUrl'");
            return false;
        }

        // Get download info (URL and version)
        $findLink = $downloadConfig['findLink'] ?? null;
        $versionFrom = $downloadConfig['versionFrom'] ?? null;
        $result = $this->htmlPageDownloader->getDownloadInfo($pageUrl, $findLink, $versionFrom);
        
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

