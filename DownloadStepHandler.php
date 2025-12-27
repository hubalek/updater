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
        $versionPattern = $downloadConfig['versionPattern'] ?? null;
        $result = $this->htmlPageDownloader->getDownloadInfo($pageUrl, $findLink, $versionFrom, $versionPattern);
        
        if ($result === null) {
            return false;
        }

        // Download file to temporary path (original filename from URL)
        $originalFile = $basePath . DIRECTORY_SEPARATOR . basename($result['url']);
        $this->dbg("Downloading to: $originalFile");
        if (!$this->httpClient->downloadFile($result['url'], $originalFile)) {
            $this->dbg("Download failed");
            return false;
        }
        $this->dbg("Download completed");

        // Rename file if filenamePattern is specified
        $downloadedFile = $originalFile;
        $filenamePattern = $downloadConfig['filenamePattern'] ?? null;
        if ($filenamePattern !== null && isset($result['version'])) {
            $version = $result['version'];
            $extension = pathinfo($originalFile, PATHINFO_EXTENSION);
            $newFilename = str_replace('{version}', $version, $filenamePattern);
            // If pattern doesn't include extension, add it
            if (!preg_match('/\.' . preg_quote($extension, '/') . '$/i', $newFilename)) {
                $newFilename .= '.' . $extension;
            }
            $downloadedFile = $basePath . DIRECTORY_SEPARATOR . $newFilename;
            $this->dbg("Renaming file from: " . basename($originalFile) . " to: " . basename($downloadedFile));
            if (rename($originalFile, $downloadedFile)) {
                $this->dbg("File renamed successfully");
            } else {
                $this->dbg("Failed to rename file, using original filename");
                $downloadedFile = $originalFile;
            }
        }

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

        // Clean up URL - remove any escape sequences
        $cleanUrl = str_replace('\\/', '/', $result['url']);
        $cleanUrl = str_replace('\\', '', $cleanUrl);
        
        // Download file to temporary path (original filename from URL)
        $originalFile = $basePath . DIRECTORY_SEPARATOR . basename($cleanUrl);
        $this->dbg("Downloading to: $originalFile");
        if (!$this->httpClient->downloadFile($cleanUrl, $originalFile)) {
            $this->dbg("Download failed");
            return false;
        }
        $this->dbg("Download completed");

        // Rename file if filenamePattern is specified
        $downloadedFile = $originalFile;
        $filenamePattern = $downloadConfig['filenamePattern'] ?? null;
        if ($filenamePattern !== null && isset($result['version'])) {
            $version = $result['version'];
            $extension = pathinfo($originalFile, PATHINFO_EXTENSION);
            $newFilename = str_replace('{version}', $version, $filenamePattern);
            // If pattern doesn't include extension, add it
            if (!preg_match('/\.' . preg_quote($extension, '/') . '$/i', $newFilename)) {
                $newFilename .= '.' . $extension;
            }
            $downloadedFile = $basePath . DIRECTORY_SEPARATOR . $newFilename;
            $this->dbg("Renaming file from: " . basename($originalFile) . " to: " . basename($downloadedFile));
            if (rename($originalFile, $downloadedFile)) {
                $this->dbg("File renamed successfully");
            } else {
                $this->dbg("Failed to rename file, using original filename");
                $downloadedFile = $originalFile;
            }
        }

        // Set variables for subsequent steps
        $variables['downloadedFile'] = $downloadedFile;
        $variables['version'] = $result['version'];

        return true;
    }
}

