<?php

declare(strict_types=1);

namespace Retina\Scanner;

use Retina\Analyzer\AnalyzerInterface;
use Retina\Analyzer\PluginYmlAnalyzer;
use Retina\Analyzer\PhpFileAnalyzer;
use Retina\Analyzer\EventHandlerAnalyzer;
use Retina\Analyzer\ListenerAnalyzer;
use Retina\Analyzer\MainClassAnalyzer;
use Retina\Analyzer\CommandAnalyzer;
use Retina\Analyzer\PermissionAnalyzer;
use Retina\Analyzer\AsyncTaskAnalyzer;
use Retina\Analyzer\SchedulerAnalyzer;
use Retina\Analyzer\ConfigAnalyzer;
use Retina\Analyzer\ResourceAnalyzer;
use Retina\Analyzer\DeprecatedApiAnalyzer;
use Retina\Analyzer\ThreadSafetyAnalyzer;
use Retina\Analyzer\PHPStanAnalyzer;
use Retina\Config\RetinaConfig;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Retina\Issue\Issue;
use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;

class PluginScanner
{
    private string $pluginPath;
    private int $level;
    private ?SymfonyStyle $io;
    private bool $showProgress;
    private array $pluginYml = [];
    private array $yamlParseErrors = [];
    private RetinaConfig $config;
    private PluginContext $context;
    private \Retina\Filter\FilterConfig $filterConfig;

    public function __construct(
        string $pluginPath,
        int $level = 6,
        ?SymfonyStyle $io = null,
        bool $showProgress = true,
        ?\Retina\Filter\FilterConfig $filterConfig = null
    ) {
        $this->pluginPath = rtrim($pluginPath, '/\\');
        $this->level = max(1, min(9, $level));
        $this->io = $io;
        $this->showProgress = $showProgress;
        $this->config = $this->loadConfig();
        $this->filterConfig = $filterConfig ?? new \Retina\Filter\FilterConfig();
    }

    public function scan(): ScanResult
    {
        $startTime = microtime(true);

        $this->loadPluginYml();
        $this->context = new PluginContext($this->pluginPath, $this->pluginYml);

        $result = new ScanResult(
            $this->pluginPath,
            $this->pluginYml['name'] ?? 'Unknown',
            $this->pluginYml['version'] ?? '0.0.0'
        );

        $phpFiles = $this->findPhpFiles();
        $result->setScannedFileCount(count($phpFiles));

        if (!empty($this->yamlParseErrors)) {
            $result->addIssues($this->yamlParseErrors);
        }

        if (!$this->filterConfig->shouldExcludeAnalyzer('PHPStan')) {
            $this->progress('Running PHPStan analysis...');
            $phpstanAnalyzer = new PHPStanAnalyzer($this->pluginPath, $this->level, $this->context);
            $result->addIssues($phpstanAnalyzer->analyze());
        }

        $analyzers = $this->getAnalyzers();

        foreach ($analyzers as $analyzer) {
            $analyzerName = (new \ReflectionClass($analyzer))->getShortName();
            $this->progress("Running $analyzerName...");
            $result->addIssues($analyzer->analyze());
        }

        $resultFilter = new \Retina\Filter\ResultFilter($this->filterConfig);
        $result = $resultFilter->filter($result);

        $result->setScanDuration(microtime(true) - $startTime);

        return $result;
    }

    private function loadPluginYml(): void
    {
        $path = $this->pluginPath . '/plugin.yml';
        if (!file_exists($path)) {
            $this->yamlParseErrors[] = new Issue(
                'plugin.yml file not found',
                $path,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::ERROR,
                'plugin_yml_missing',
                null,
                'Create a plugin.yml file in the plugin root directory'
            );
            return;
        }

        try {
            $this->pluginYml = Yaml::parseFile($path) ?? [];
        } catch (ParseException $e) {
            $message = $e->getMessage();
            $line = $e->getParsedLine() ?? 1;
            
            $this->yamlParseErrors[] = new Issue(
                'plugin.yml parse error: ' . $message,
                $path,
                $line,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::ERROR,
                'plugin_yml_parse_error',
                null,
                'Fix the YAML syntax error in plugin.yml'
            );
            
            $this->pluginYml = $this->tryParseYamlWithFallback($path);
        }
    }

    private function tryParseYamlWithFallback(string $path): array
    {
        $content = file_get_contents($path);
        $result = [];
        $currentKey = null;
        $currentArray = [];
        
        foreach (explode("\n", $content) as $lineNum => $line) {
            $trimmed = trim($line);
            
            if (empty($trimmed) || str_starts_with($trimmed, '#')) {
                continue;
            }
            
            if (preg_match('/^(\w+):\s*(.*)$/', $line, $matches)) {
                if ($currentKey !== null && !empty($currentArray)) {
                    $result[$currentKey] = $currentArray;
                    $currentArray = [];
                }
                
                $key = $matches[1];
                $value = trim($matches[2]);
                
                if (empty($value)) {
                    $currentKey = $key;
                } else {
                    $result[$key] = $value;
                    $currentKey = null;
                }
            } elseif (preg_match('/^\s*-\s*(.+)$/', $line, $matches)) {
                if ($currentKey !== null) {
                    $currentArray[] = trim($matches[1]);
                }
            }
        }
        
        if ($currentKey !== null && !empty($currentArray)) {
            $result[$currentKey] = $currentArray;
        }
        
        return $result;
    }

    private function loadConfig(): RetinaConfig
    {
        $configPath = $this->pluginPath . '/retina.yml';
        if (file_exists($configPath)) {
            return RetinaConfig::fromFile($configPath);
        }
        return new RetinaConfig();
    }

    private function findPhpFiles(): array
    {
        $srcPath = $this->pluginPath . '/src';
        if (!is_dir($srcPath)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($srcPath)
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true);

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    private function getAnalyzers(): array
    {
        $registry = new \Retina\Analyzer\AnalyzerRegistry();
        return $registry->createAnalyzers($this->pluginPath, $this->context, $this->filterConfig);
    }

    private function progress(string $message): void
    {
        if ($this->io !== null && $this->showProgress) {
            $this->io->text("  <fg=cyan>→</> $message");
        }
    }
}
