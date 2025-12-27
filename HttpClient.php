<?php

class HttpClient
{
    use DebugTrait;

    public function httpGet(string $url): string|false
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $error = curl_error($ch);
        curl_close($ch);

        // Log redirect information
        if ($redirectCount > 0) {
            $this->dbg("HTTP GET redirected $redirectCount time(s), final URL: $finalUrl");
        }
        if ($finalUrl !== $url) {
            $this->dbg("HTTP GET original URL: $url");
            $this->dbg("HTTP GET final URL: $finalUrl");
        }

        if ($res === false) {
            $this->dbg("HTTP GET failed: HTTP $httpCode - $error");
            return false;
        }

        if ($httpCode >= 400) {
            $this->dbg("HTTP GET failed: HTTP $httpCode");
            return false;
        }

        return $res;
    }

    public function downloadFile(string $url, string $save): bool
    {
        $this->dbg("Downloading: $url");

        $fp = fopen($save, "w+b");
        if (!$fp) {
            $this->dbg("Failed to open file for writing: $save");
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        // Log redirect information
        if ($redirectCount > 0) {
            $this->dbg("Redirected $redirectCount time(s), final URL: $finalUrl");
        }
        if ($finalUrl !== $url) {
            $this->dbg("Original URL: $url");
            $this->dbg("Final URL: $finalUrl");
        }

        if ($res === false) {
            $this->dbg("Download failed: HTTP $httpCode - $error");
            @unlink($save);
            return false;
        }

        if ($httpCode >= 400) {
            $this->dbg("Download failed: HTTP $httpCode");
            @unlink($save);
            return false;
        }

        $this->dbg("Download successful: HTTP $httpCode");
        return true;
    }

    /**
     * Get redirect URL from Location header
     * @param string $url URL that returns a redirect
     * @return string|false Final URL from Location header, or false on failure
     */
    public function getRedirectUrl(string $url): string|false
    {
        $this->dbg("Getting redirect URL from: $url");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,   // Stop at first redirect
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,             // No body, headers only
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'curl',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->dbg("Failed to get redirect URL: HTTP $httpCode - $error");
            return false;
        }

        if ($httpCode >= 400) {
            $this->dbg("Failed to get redirect URL: HTTP $httpCode");
            return false;
        }

        // Extract Location header
        if (preg_match('/^Location:\s*(.+)$/im', $response, $matches)) {
            $location = trim($matches[1]);
            $this->dbg("Redirect URL found: $location");
            return $location;
        }

        $this->dbg("No Location header found in response");
        return false;
    }
}

