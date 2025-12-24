<?php

class GitHubDownloader
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
     * Get asset URL and version from GitHub release
     * @param string $apiUrl GitHub API URL
     * @param array|null $filter Filter configuration
     * @return array|null Returns ['url' => assetUrl, 'version' => version] on success, null on failure
     */
    public function getAssetInfo(string $apiUrl, ?array $filter): ?array
    {
        $this->dbg("Fetching GitHub API: $apiUrl");
        $json = $this->httpClient->httpGet($apiUrl);
        if ($json === false) {
            $this->dbg("HTTP request failed");
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data["assets"])) {
            $this->dbg("API response does not contain assets");
            return null;
        }

        // Merge filter with defaults
        $mergedFilter = $this->configLoader->mergeFilters($filter);
        
        // Find matching asset
        $assetUrl = $this->githubParser->findAsset($data["assets"], $mergedFilter);
        if ($assetUrl === null) {
            $this->dbg("No matching asset found");
            return null;
        }

        // Extract version from asset URL
        $version = $this->githubParser->extractVersionName($assetUrl);
        $this->dbg("Extracted version: $version");

        return [
            'url' => $assetUrl,
            'version' => $version
        ];
    }
}

