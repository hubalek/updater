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
     * Supports GitHub API (url + filter), HTML pages (pageUrl + findLink), or redirect URLs (redirectUrl)
     * Sets $downloadedFile and $version in variables
     */
    public function execute(array $downloadConfig, array &$variables, string $basePath): bool
    {
        // Redirect URL download (e.g., VS Code)
        if (isset($downloadConfig['redirectUrl'])) {
            return $this->executeRedirect($downloadConfig, $variables, $basePath);
        }

        // GitHub API download
        if (isset($downloadConfig['url'])) {
            return $this->executeGitHub($downloadConfig, $variables, $basePath);
        }

        // HTML page download
        if (isset($downloadConfig['pageUrl'])) {
            return $this->executeHtmlPage($downloadConfig, $variables, $basePath);
        }

        $this->dbg("Download step missing 'url', 'pageUrl', or 'redirectUrl'");
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

    /**
     * Download from redirect URL (e.g., VS Code)
     */
    private function executeRedirect(array $downloadConfig, array &$variables, string $basePath): bool
    {
        $redirectUrl = $downloadConfig['redirectUrl'] ?? '';
        if (empty($redirectUrl)) {
            $this->dbg("Download step missing 'redirectUrl'");
            return false;
        }

        // Get final URL from redirect
        $finalUrl = $this->httpClient->getRedirectUrl($redirectUrl);
        if ($finalUrl === false) {
            $this->dbg("Failed to get redirect URL");
            return false;
        }

        // Extract version from filename in final URL
        $filename = basename(parse_url($finalUrl, PHP_URL_PATH) ?: $finalUrl);
        $version = $this->extractVersionFromFilename($filename, $downloadConfig['versionPattern'] ?? null);
        
        if (empty($version)) {
            $this->dbg("Failed to extract version from filename: $filename");
            // Fallback: use filename without extension as version
            $version = preg_replace('/\.(zip|7z|exe)$/i', '', $filename);
        }
        
        $this->dbg("Extracted version: $version");

        // Download file (keep original filename)
        $downloadedFile = $basePath . DIRECTORY_SEPARATOR . $filename;
        $this->dbg("Downloading to: $downloadedFile");
        if (!$this->httpClient->downloadFile($finalUrl, $downloadedFile)) {
            $this->dbg("Download failed");
            return false;
        }
        $this->dbg("Download completed");

        // Set variables for subsequent steps
        $variables['downloadedFile'] = $downloadedFile;
        $variables['version'] = $version;

        return true;
    }

    /**
     * Extract version from filename
     * @param string $filename Filename (e.g., "VSCode-win32-x64-1.107.1.zip")
     * @param string|null $versionPattern Optional custom regex pattern (must contain capture group)
     * @return string Extracted version or empty string
     */
    private function extractVersionFromFilename(string $filename, ?string $versionPattern): string
    {
        if ($versionPattern !== null) {
            // Add delimiter if pattern doesn't already have one
            $pattern = $versionPattern;
            $firstChar = substr($pattern, 0, 1);
            $commonDelimiters = ['/', '#', '~', '`', '!', '@', '%', '&', '*', '+', '=', '|', ':', ';', ',', '-', '[', ']', '{', '}', '(', ')', '?'];
            if (!in_array($firstChar, $commonDelimiters, true)) {
                $pattern = '/' . $pattern . '/i';
            }
            
            if (preg_match($pattern, $filename, $matches)) {
                return isset($matches[1]) ? trim($matches[1]) : trim($matches[0]);
            }
        }

        // Default: try to extract version pattern like "1.107.1" or "1.85.0"
        // Pattern: digits.digits.digits before file extension
        if (preg_match('/(\d+\.\d+\.\d+)(?:\.[^.]+)?\.(zip|7z|exe)$/i', $filename, $matches)) {
            return $matches[1];
        }

        // Fallback: try simpler pattern (digits.digits)
        if (preg_match('/(\d+\.\d+)(?:\.[^.]+)?\.(zip|7z|exe)$/i', $filename, $matches)) {
            return $matches[1];
        }

        return '';
    }
}

