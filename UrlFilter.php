<?php

class UrlFilter
{
    use DebugTrait;

    /**
     * Check if URL matches filter criteria
     * @param string $url URL to check
     * @param array $filter Filter configuration with mustContain, mustNotContain, allowedExt
     * @return bool True if URL matches filter
     */
    public function matches(string $url, array $filter): bool
    {
        // mustContain: if empty array, no restrictions (all URLs pass)
        foreach ($filter["mustContain"] as $word) {
            if (stripos($url, $word) === false) {
                $this->dbg("   mustContain failed: $word");
                return false;
            }
        }

        // mustNotContain: if empty array, no restrictions (all URLs pass)
        foreach ($filter["mustNotContain"] as $word) {
            if (stripos($url, $word) !== false) {
                $this->dbg("   mustNotContain failed: $word");
                return false;
            }
        }

        // allowedExt: if empty array, no extensions allowed (all URLs fail)
        if (!empty($filter["allowedExt"])) {
            $match = false;
            foreach ($filter["allowedExt"] as $ext) {
                if (preg_match('/\.' . preg_quote($ext) . '$/i', $url)) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                $this->dbg("   allowedExt failed");
                return false;
            }
        }

        return true;
    }

    /**
     * Find first URL in array that matches filter
     * @param array $urls Array of URLs to check
     * @param array $filter Filter configuration
     * @return string|null Matching URL or null if none found
     */
    public function findMatch(array $urls, array $filter): ?string
    {
        $this->dbg("Checking " . count($urls) . " URLs…");

        foreach ($urls as $url) {
            $this->dbg("  URL: $url");
            
            if ($this->matches($url, $filter)) {
                $this->dbg("  ✔ URL matches");
                return $url;
            }

            $this->dbg("  ✘ URL does not match");
        }

        $this->dbg("No suitable URL found");
        return null;
    }
}

