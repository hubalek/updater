<?php

class Updater
{
    private bool $debug = false;

    private DirectoryScanner $directoryScanner;
    private ConfigLoader $configLoader;
    private GitHubParser $githubParser;
    private FileManager $fileManager;
    private JunctionManager $junctionManager;
    private HttpClient $httpClient;
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

        // Setup debug callbacks
        $debugCallback = fn(string $msg) => $this->dbg($msg);
        $this->githubParser->setDebugCallback($debugCallback);
        $this->fileManager->setDebugCallback($debugCallback);
        $this->junctionManager->setDebugCallback($debugCallback);
        $this->httpClient->setDebugCallback($debugCallback);
        $this->junctionManager->setFileManager($this->fileManager);

        // Initialize update processor
        $this->updateProcessor = new UpdateProcessor(
            $this->configLoader,
            $this->githubParser,
            $this->fileManager,
            $this->junctionManager,
            $this->httpClient
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
