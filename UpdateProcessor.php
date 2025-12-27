<?php

class UpdateProcessor
{
    use DebugTrait;

    private ConfigLoader $configLoader;
    private GitHubParser $githubParser;
    private FileManager $fileManager;
    private JunctionManager $junctionManager;
    private HttpClient $httpClient;
    private InstallationScanner $installationScanner;
    private StepExecutor $stepExecutor;

    public function __construct(
        ConfigLoader $configLoader,
        GitHubParser $githubParser,
        FileManager $fileManager,
        JunctionManager $junctionManager,
        HttpClient $httpClient,
        InstallationScanner $installationScanner
    ) {
        $this->configLoader = $configLoader;
        $this->githubParser = $githubParser;
        $this->fileManager = $fileManager;
        $this->junctionManager = $junctionManager;
        $this->httpClient = $httpClient;
        $this->installationScanner = $installationScanner;
        $this->stepExecutor = new StepExecutor($fileManager, $httpClient, $githubParser, $configLoader, $installationScanner);
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
        $this->stepExecutor->setDebugCallback($callback);
        $this->httpClient->setDebugCallback($callback);
        $this->githubParser->setDebugCallback($callback);
    }

    public function processJson(string $appFolder, string $appPath, string $jsonFile): void
    {
        $this->dbg("=== Processing $jsonFile in $appFolder ===");

        $baseName   = pathinfo($jsonFile, PATHINFO_FILENAME);
        $configPath = $appPath . DIRECTORY_SEPARATOR . $jsonFile;

        // restore junction if missing
        $this->junctionManager->restoreMissingJunction($appPath, $baseName);

        $cfg = $this->configLoader->loadConfig($configPath);
        if ($cfg === null) {
            $this->dbg("JSON invalid");
            return;
        }

        $this->dbg("Loaded config from: $configPath");
        $this->dbg("Config keys: " . implode(', ', array_keys($cfg)));
        if (isset($cfg["finalDirPattern"])) {
            $this->dbg("Config finalDirPattern: " . $cfg["finalDirPattern"]);
        } else {
            $this->dbg("Config does NOT contain finalDirPattern");
        }

        // Steps are now required
        if (!isset($cfg["steps"]) || !is_array($cfg["steps"]) || empty($cfg["steps"])) {
            $this->dbg("Config missing 'steps' array");
            return;
        }

        // First step must be download
        $firstStep = $cfg["steps"][0];
        if (!isset($firstStep["download"])) {
            $this->dbg("First step must be 'download'");
            return;
        }

        // Prepare initial variables
        $variables = [
            'appPath' => $appPath,
            'appFolder' => $appFolder,
            'baseName' => $baseName
        ];

        // Execute download step first to get version and downloadedFile
        $this->dbg("Executing download step");
        if (!$this->stepExecutor->executeStep($firstStep, $variables, $appPath)) {
            $this->dbg("Download step failed");
            return;
        }

        // Check if version and downloadedFile were set
        if (!isset($variables['version']) || !isset($variables['downloadedFile'])) {
            $this->dbg("Download step did not set 'version' and 'downloadedFile'");
            return;
        }

        $version = $variables['version'];
        $this->dbg("Version: $version");

        // Check if version directory already exists
        $finalDirName = $version;
        if (isset($cfg["finalDirPattern"])) {
            $this->dbg("Using finalDirPattern: " . $cfg["finalDirPattern"]);
            $finalDirName = str_replace('{version}', $version, $cfg["finalDirPattern"]);
            $this->dbg("Final directory name after replacement: $finalDirName");
        } else {
            $this->dbg("No finalDirPattern found in config, using version as directory name");
        }
        $finalDir = $appPath . DIRECTORY_SEPARATOR . $finalDirName;
        
        if (is_dir($finalDir)) {
            $this->dbg("Version directory already exists, skipping: $finalDir");
            return;
        }

        // Set finalDir in variables for subsequent steps
        $variables['finalDir'] = $finalDir;
        $this->dbg("Final directory: $finalDir");
        
        // Create finalDir if it doesn't exist
        if (!is_dir($finalDir)) {
            mkdir($finalDir, 0777, true);
            $this->dbg("Created final directory: $finalDir");
        }

        // Create temp directory for temporary extraction
        $tempDir = $appPath . DIRECTORY_SEPARATOR . '.temp_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        $variables['tempDir'] = $tempDir;
        $this->dbg("Temp directory: $tempDir");

        // Execute remaining steps (skip first download step)
        $steps = $cfg["steps"];
        $this->dbg("Executing " . (count($steps) - 1) . " additional step(s)");
        for ($i = 1; $i < count($steps); $i++) {
            $step = $steps[$i];
            $this->dbg("Executing step " . ($i + 1) . ": " . json_encode($step));
            if (!$this->stepExecutor->executeStep($step, $variables, $appPath)) {
                $this->dbg("Step execution failed: " . json_encode($step));
                return;
            }
            $this->dbg("Step " . ($i + 1) . " completed");
        }

        // Create junction to final directory
        $lnkPath = $appPath . DIRECTORY_SEPARATOR . $baseName;
        $this->dbg("Creating junction: $lnkPath -> $finalDir");
        $this->junctionManager->createJunction($finalDir, $lnkPath, $baseName);
        $this->dbg("Junction created");

        // Cleanup temp directory if it exists
        if (isset($variables['tempDir']) && is_dir($variables['tempDir'])) {
            $this->dbg("Cleaning up temp directory: " . $variables['tempDir']);
            $this->removeDirectory($variables['tempDir']);
        }

        echo "UPDATED - {$appFolder}/{$baseName} - {$version}\n";
    }

    /**
     * Recursively remove directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

}

