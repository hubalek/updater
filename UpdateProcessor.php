<?php

class UpdateProcessor
{
    private ConfigLoader $configLoader;
    private GitHubParser $githubParser;
    private FileManager $fileManager;
    private JunctionManager $junctionManager;
    private HttpClient $httpClient;
    private $debugCallback = null;

    public function __construct(
        ConfigLoader $configLoader,
        GitHubParser $githubParser,
        FileManager $fileManager,
        JunctionManager $junctionManager,
        HttpClient $httpClient
    ) {
        $this->configLoader = $configLoader;
        $this->githubParser = $githubParser;
        $this->fileManager = $fileManager;
        $this->junctionManager = $junctionManager;
        $this->httpClient = $httpClient;
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

    public function processJson(string $appFolder, string $appPath, string $jsonFile): void
    {
        $this->dbg("=== Zpracovávám $jsonFile v $appFolder ===");

        $baseName   = pathinfo($jsonFile, PATHINFO_FILENAME);
        $configPath = $appPath . DIRECTORY_SEPARATOR . $jsonFile;

        // obnov junction pokud chybí
        $this->junctionManager->restoreMissingJunction($appPath, $baseName);

        $run = true;

        $cfg = $this->configLoader->loadConfig($configPath);
        if ($cfg === null) {
            $this->dbg("JSON nevalidní");
            $run = false;
        }

        $apiUrl = $cfg["url"] ?? "";
        $json   = null;
        $data   = null;

        if ($run) {
            $json = $this->httpClient->httpGet($apiUrl);
            if ($json === false) {
                $this->dbg("HTTP selhalo");
                $run = false;
            }
        }

        if ($run) {
            $data = json_decode($json, true);
            if (!is_array($data) || empty($data["assets"])) {
                $this->dbg("API neobsahuje assets");
                $run = false;
            }
        }

        $assetUrl = null;
        $version  = null;
        $zipPath  = null;
        $extPath  = null;
        $lnkPath  = null;

        if ($run) {
            $filter   = $this->configLoader->mergeFilters($cfg["filter"] ?? null);
            $assetUrl = $this->githubParser->findAsset($data["assets"], $filter);

            if ($assetUrl === null) {
                $run = false;
            }
        }

        if ($run) {
            $version = $this->githubParser->extractVersionName($assetUrl);
            if ($this->fileManager->isVersionDownloaded($appPath, $version)) {
                $run = false;
            }
        }

        if ($run) {
            $zipPath = $appPath . DIRECTORY_SEPARATOR . $version . ".zip";
            $extPath = $appPath . DIRECTORY_SEPARATOR . $version;
            $lnkPath = $appPath . DIRECTORY_SEPARATOR . $baseName;

            if (!$this->httpClient->downloadFile($assetUrl, $zipPath)) {
                $run = false;
            }
        }

        if ($run) {
            if (!$this->fileManager->extractZip($zipPath, $extPath)) {
                $run = false;
            }
        }

        if ($run) {
            $this->junctionManager->createJunction($extPath, $lnkPath, $baseName);
            echo "UPDATED - {$appFolder}/{$baseName} - {$version}\n";
        }
    }
}

