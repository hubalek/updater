<?php

class StepExecutor
{
    private DownloadStepHandler $downloadHandler;
    private ExtractStepHandler $extractHandler;
    private MoveStepHandler $moveHandler;
    private $debugCallback = null;

    public function __construct(
        FileManager $fileManager,
        HttpClient $httpClient,
        GitHubParser $githubParser,
        ConfigLoader $configLoader
    ) {
        $this->downloadHandler = new DownloadStepHandler($httpClient, $githubParser, $configLoader);
        $this->extractHandler = new ExtractStepHandler($fileManager);
        $this->moveHandler = new MoveStepHandler($fileManager);
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
        $this->downloadHandler->setDebugCallback($callback);
        $this->extractHandler->setDebugCallback($callback);
        $this->moveHandler->setDebugCallback($callback);
    }

    private function dbg(string $msg): void
    {
        if ($this->debugCallback !== null) {
            ($this->debugCallback)($msg);
        }
    }

    /**
     * Execute a single step
     * @param array $step Step configuration
     * @param array $variables Variables that can be modified by the step (passed by reference)
     * @param string $basePath Base path for resolving relative paths
     * @return bool Success status
     */
    public function executeStep(array $step, array &$variables, string $basePath): bool
    {
        if (isset($step['download'])) {
            return $this->downloadHandler->execute($step['download'], $variables, $basePath);
        }

        if (isset($step['extract7z'])) {
            return $this->extractHandler->executeExtract7z($step['extract7z'], $variables, $basePath);
        }

        if (isset($step['extractZip'])) {
            return $this->extractHandler->executeExtractZip($step['extractZip'], $variables, $basePath);
        }

        if (isset($step['move'])) {
            return $this->moveHandler->execute($step['move'], $variables, $basePath);
        }

        $this->dbg("Unknown step type: " . json_encode($step));
        return false;
    }
}

