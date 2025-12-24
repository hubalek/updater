<?php

class UpdateProcessor
{
    private ConfigLoader $configLoader;
    private GitHubParser $githubParser;
    private FileManager $fileManager;
    private JunctionManager $junctionManager;
    private HttpClient $httpClient;
    private StepExecutor $stepExecutor;
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
        $this->stepExecutor = new StepExecutor($fileManager);
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
        $this->stepExecutor->setDebugCallback($callback);
    }

    private function dbg(string $msg): void
    {
        if ($this->debugCallback !== null) {
            ($this->debugCallback)($msg);
        }
    }

    public function processJson(string $appFolder, string $appPath, string $jsonFile): void
    {
        $this->dbg("=== Processing $jsonFile in $appFolder ===");

        $baseName   = pathinfo($jsonFile, PATHINFO_FILENAME);
        $configPath = $appPath . DIRECTORY_SEPARATOR . $jsonFile;

        // restore junction if missing
        $this->junctionManager->restoreMissingJunction($appPath, $baseName);

        $run = true;

        $cfg = $this->configLoader->loadConfig($configPath);
        if ($cfg === null) {
            $this->dbg("JSON invalid");
            $run = false;
        }

        $apiUrl = $cfg["url"] ?? "";
        $json   = null;
        $data   = null;

        if ($run) {
            $json = $this->httpClient->httpGet($apiUrl);
            if ($json === false) {
                $this->dbg("HTTP failed");
                $run = false;
            }
        }

        if ($run) {
            $data = json_decode($json, true);
            if (!is_array($data) || empty($data["assets"])) {
                $this->dbg("API does not contain assets");
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

        // Check if config uses new steps-based approach
        $hasSteps = isset($cfg["steps"]) && is_array($cfg["steps"]) && !empty($cfg["steps"]);

        if ($run) {
            if ($hasSteps) {
                // New steps-based processing
                $run = $this->processWithSteps($cfg, $assetUrl, $version, $appPath, $appFolder, $baseName);
            } else {
                // Legacy processing (backward compatibility)
                $run = $this->processLegacy($assetUrl, $version, $appPath, $baseName);
            }
        }

        if ($run) {
            echo "UPDATED - {$appFolder}/{$baseName} - {$version}\n";
        }
    }

    /**
     * Process using new steps-based configuration
     */
    private function processWithSteps(array $cfg, string $assetUrl, string $version, string $appPath, string $appFolder, string $baseName): bool
    {
        // Download file
        $downloadedFile = $appPath . DIRECTORY_SEPARATOR . basename($assetUrl);
        if (!$this->httpClient->downloadFile($assetUrl, $downloadedFile)) {
            return false;
        }

        // Determine final directory name
        $finalDirName = $version;
        if (isset($cfg["finalDirPattern"])) {
            $finalDirName = str_replace('{version}', $version, $cfg["finalDirPattern"]);
        }
        $finalDir = $appPath . DIRECTORY_SEPARATOR . $finalDirName;

        // Prepare variables for step execution
        $variables = [
            'downloadedFile' => $downloadedFile,
            'finalDir' => $finalDir,
            'version' => $version,
            'appPath' => $appPath,
            'appFolder' => $appFolder,
            'baseName' => $baseName
        ];

        // Execute steps in order
        $steps = $cfg["steps"];
        foreach ($steps as $step) {
            if (!$this->stepExecutor->executeStep($step, $variables, $appPath)) {
                $this->dbg("Step execution failed: " . json_encode($step));
                return false;
            }
        }

        // Create junction to final directory
        $lnkPath = $appPath . DIRECTORY_SEPARATOR . $baseName;
        $this->junctionManager->createJunction($finalDir, $lnkPath, $baseName);

        return true;
    }

    /**
     * Legacy processing (backward compatibility)
     */
    private function processLegacy(string $assetUrl, string $version, string $appPath, string $baseName): bool
    {
        $zipPath = $appPath . DIRECTORY_SEPARATOR . $version . ".zip";
        $extPath = $appPath . DIRECTORY_SEPARATOR . $version;
        $lnkPath = $appPath . DIRECTORY_SEPARATOR . $baseName;

        if (!$this->httpClient->downloadFile($assetUrl, $zipPath)) {
            return false;
        }

        if (!$this->fileManager->extractZip($zipPath, $extPath)) {
            return false;
        }

        $this->junctionManager->createJunction($extPath, $lnkPath, $baseName);
        return true;
    }
    }
}

