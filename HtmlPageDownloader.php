<?php

class HtmlPageDownloader
{
    use DebugTrait;

    private HttpClient $httpClient;
    private ConfigLoader $configLoader;
    private UrlFilter $urlFilter;

    public function __construct(HttpClient $httpClient, ConfigLoader $configLoader)
    {
        $this->httpClient = $httpClient;
        $this->configLoader = $configLoader;
        $this->urlFilter = new UrlFilter();
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
        $this->urlFilter->setDebugCallback($callback);
    }

    /**
     * Get download URL and version from HTML page
     * @param string $pageUrl URL of the HTML page
     * @param array|null $findLink Filter configuration for finding links
     * @param string|null $versionFrom How to extract version (e.g., "exe")
     * @return array|null Returns ['url' => downloadUrl, 'version' => version] on success, null on failure
     */
    public function getDownloadInfo(string $pageUrl, ?array $findLink, ?string $versionFrom): ?array
    {
        $this->dbg("Fetching HTML page: $pageUrl");
        $html = $this->httpClient->httpGet($pageUrl);
        if ($html === false) {
            $this->dbg("HTTP request failed");
            return null;
        }

        // Find all links in HTML
        $links = $this->extractLinks($html, $pageUrl);
        $this->dbg("Found " . count($links) . " links on page");

        // Merge filter with defaults
        $this->dbg("Original findLink config: " . json_encode($findLink));
        $filter = $this->configLoader->mergeFilters($findLink);
        $this->dbg("Merged filter (with defaults): " . json_encode($filter));
        
        // Find matching link using UrlFilter
        $downloadUrl = $this->urlFilter->findMatch($links, $filter);
        if ($downloadUrl === null) {
            $this->dbg("No matching link found");
            return null;
        }

        // Extract version from URL or filename
        $version = $this->extractVersion($downloadUrl, $versionFrom);
        $this->dbg("Extracted version: $version");

        return [
            'url' => $downloadUrl,
            'version' => $version
        ];
    }

    /**
     * Extract all links from HTML
     */
    private function extractLinks(string $html, string $baseUrl): array
    {
        $links = [];
        
        // Match <a href="..."> tags
        if (preg_match_all('/<a\s+[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                // Resolve relative URLs
                $absoluteUrl = $this->resolveUrl($url, $baseUrl);
                if ($absoluteUrl !== null) {
                    $links[] = $absoluteUrl;
                }
            }
        }

        return $links;
    }

    /**
     * Resolve relative URL to absolute
     */
    private function resolveUrl(string $url, string $baseUrl): ?string
    {
        // If already absolute, return as-is
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $parsed = parse_url($baseUrl);
        if ($parsed === false) {
            return null;
        }
        
        $base = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }
        
        if (strpos($url, '/') === 0) {
            // Absolute path
            return $base . $url;
        } else {
            // Relative path
            $path = isset($parsed['path']) ? dirname($parsed['path']) : '/';
            return $base . rtrim($path, '/') . '/' . ltrim($url, '/');
        }
    }


    /**
     * Extract version from URL or filename
     * @param string $url Download URL
     * @param string|null $versionFrom How to extract version (e.g., "exe" means extract from EXE filename)
     */
    private function extractVersion(string $url, ?string $versionFrom): string
    {
        $filename = basename(parse_url($url, PHP_URL_PATH) ?: $url);
        
        if ($versionFrom === "exe") {
            // Extract version from EXE filename like tcmd1156x64.exe → 11.56
            // Pattern: tcmd followed by 2 digits (major) and 2 digits (minor) before x64
            // Example: tcmd1156x64 → major=11, minor=56
            if (preg_match('/tcmd(\d{2})(\d{2})x64/i', $filename, $matches)) {
                $major = $matches[1];
                $minor = $matches[2];
                return $major . '.' . $minor;
            }
        }

        // Default: remove extension
        return preg_replace('/\.(exe|zip|7z)$/i', '', $filename);
    }
}

