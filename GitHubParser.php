<?php

class GitHubParser
{
    private $debugCallback = null;

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

    public function findAsset(array $assets, array $filter): string|null
    {
        $this->dbg("Checking " . count($assets) . " assets…");

        foreach ($assets as $asset) {
            $url = $asset["browser_download_url"] ?? "";
            $this->dbg("  Asset: $url");
            $ok = true;

            foreach ($filter["mustContain"] as $word) {
                if (stripos($url, $word) === false) {
                    $this->dbg("   mustContain failed: $word");
                    $ok = false;
                    break;
                }
            }

            if ($ok) {
                foreach ($filter["mustNotContain"] as $word) {
                    if (stripos($url, $word) !== false) {
                        $this->dbg("   mustNotContain failed: $word");
                        $ok = false;
                        break;
                    }
                }
            }

            if ($ok) {
                $match = false;
                foreach ($filter["allowedExt"] as $ext) {
                    if (preg_match('/\.' . preg_quote($ext) . '$/i', $url)) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    $this->dbg("   allowedExt failed");
                    $ok = false;
                }
            }

            if ($ok) {
                $this->dbg("  ✔ Asset matches");
                return $url;
            }

            $this->dbg("  ✘ Asset does not match");
        }

        $this->dbg("No suitable asset found");
        return null;
    }

    public function extractVersionName(string $url): string
    {
        $name = basename($url);
        return preg_replace('/\.(zip|7z)$/i', '', $name);
    }
}

