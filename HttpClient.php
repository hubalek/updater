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
}

