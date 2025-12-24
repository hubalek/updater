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

        // Inicializace komponent
        $this->directoryScanner = new DirectoryScanner($root);
        $this->configLoader = new ConfigLoader();
        $this->githubParser = new GitHubParser();
        $this->fileManager = new FileManager();
        $this->junctionManager = new JunctionManager();
        $this->httpClient = new HttpClient();

        // Nastavení debug callbacků
        $this->githubParser->setDebugCallback([$this, 'dbg']);
        $this->fileManager->setDebugCallback([$this, 'dbg']);
        $this->junctionManager->setDebugCallback([$this, 'dbg']);
        $this->httpClient->setDebugCallback([$this, 'dbg']);
        $this->junctionManager->setFileManager($this->fileManager);

        // Inicializace procesoru aktualizací
        $this->updateProcessor = new UpdateProcessor(
            $this->configLoader,
            $this->githubParser,
            $this->fileManager,
            $this->junctionManager,
            $this->httpClient
        );
        $this->updateProcessor->setDebugCallback([$this, 'dbg']);
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
