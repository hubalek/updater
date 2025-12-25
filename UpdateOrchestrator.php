<?php

class UpdateOrchestrator
{
    private bool $debug = false;

    private DirectoryScanner $directoryScanner;
    private ConfigLoader $configLoader;
    private GitHubParser $githubParser;
    private FileManager $fileManager;
    private JunctionManager $junctionManager;
    private HttpClient $httpClient;
    private InstallationScanner $installationScanner;
    private UpdateProcessor $updateProcessor;

    public function __construct(string $root, bool $debug = false)
    {
        $this->debug = $debug;

        // Initialize components
        $this->directoryScanner = new DirectoryScanner($root);
        $this->configLoader = new ConfigLoader();
        $this->githubParser = new GitHubParser();
        $this->fileManager = new FileManager();
        $this->junctionManager = new JunctionManager();
        $this->httpClient = new HttpClient();
        $this->installationScanner = new InstallationScanner();

        // Setup debug callbacks
        $debugCallback = fn(string $msg) => $this->dbg($msg);
        $this->githubParser->setDebugCallback($debugCallback);
        $this->fileManager->setDebugCallback($debugCallback);
        $this->junctionManager->setDebugCallback($debugCallback);
        $this->httpClient->setDebugCallback($debugCallback);
        $this->installationScanner->setDebugCallback($debugCallback);
        $this->junctionManager->setInstallationScanner($this->installationScanner);

        // Initialize update processor
        $this->updateProcessor = new UpdateProcessor(
            $this->configLoader,
            $this->githubParser,
            $this->fileManager,
            $this->junctionManager,
            $this->httpClient,
            $this->installationScanner
        );
        $this->updateProcessor->setDebugCallback($debugCallback);
    }

    private function dbg(string $msg): void
    {
        if ($this->debug) {
            echo "[DEBUG] $msg\n";
        }
    }

    public function run(): void
    {
        foreach ($this->directoryScanner->getAppFolders() as $folder) {
            $appPath = $this->directoryScanner->getAppPath($folder);
            foreach ($this->directoryScanner->getJsonConfigs($appPath) as $jsonFile) {
                $this->updateProcessor->processJson($folder, $appPath, $jsonFile);
            }
        }
    }
}
