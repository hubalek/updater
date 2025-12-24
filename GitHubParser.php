<?php

class GitHubParser
{
    use DebugTrait;

    private UrlFilter $urlFilter;

    public function __construct()
    {
        $this->urlFilter = new UrlFilter();
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
        $this->urlFilter->setDebugCallback($callback);
    }

    public function findAsset(array $assets, array $filter): string|null
    {
        // Extract URLs from assets
        $urls = [];
        foreach ($assets as $asset) {
            $url = $asset["browser_download_url"] ?? "";
            if (!empty($url)) {
                $urls[] = $url;
            }
        }

        // Use UrlFilter to find matching URL
        return $this->urlFilter->findMatch($urls, $filter);
    }

    public function extractVersionName(string $url): string
    {
        $name = basename($url);
        return preg_replace('/\.(zip|7z)$/i', '', $name);
    }
}

