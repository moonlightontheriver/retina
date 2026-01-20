<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Filter\FilterConfig;
use Retina\Scanner\PluginContext;

class AnalyzerRegistry
{
    private array $analyzers = [];

    public function __construct()
    {
        $this->registerDefaultAnalyzers();
    }

    public function registerAnalyzer(string $name, string $className): void
    {
        $this->analyzers[$name] = $className;
    }

    public function getAvailableAnalyzers(): array
    {
        return $this->analyzers;
    }

    public function getEnabledAnalyzers(FilterConfig $config): array
    {
        $enabled = [];
        
        foreach ($this->analyzers as $name => $className) {
            if (!$config->shouldExcludeAnalyzer($name)) {
                $enabled[$name] = $className;
            }
        }
        
        return $enabled;
    }

    public function createAnalyzers(
        string $pluginPath,
        PluginContext $context,
        FilterConfig $config
    ): array {
        $instances = [];
        $enabled = $this->getEnabledAnalyzers($config);
        
        foreach ($enabled as $name => $className) {
            if ($name === 'PHPStan') {
                continue;
            }
            $instances[] = new $className($pluginPath, $context);
        }
        
        return $instances;
    }

    public function normalizeAnalyzerName(string $name): string
    {
        $name = str_replace('Analyzer', '', $name);
        $name = str_replace('analyzer', '', $name);
        $name = ucfirst($name);
        
        if (strtolower($name) === 'phpstan') {
            return 'PHPStan';
        }
        
        return $name;
    }

    public function isValidAnalyzer(string $name): bool
    {
        $normalized = $this->normalizeAnalyzerName($name);
        return isset($this->analyzers[$normalized]);
    }

    private function registerDefaultAnalyzers(): void
    {
        $this->registerAnalyzer('PluginYml', PluginYmlAnalyzer::class);
        $this->registerAnalyzer('MainClass', MainClassAnalyzer::class);
        $this->registerAnalyzer('PhpFile', PhpFileAnalyzer::class);
        $this->registerAnalyzer('EventHandler', EventHandlerAnalyzer::class);
        $this->registerAnalyzer('Listener', ListenerAnalyzer::class);
        $this->registerAnalyzer('Command', CommandAnalyzer::class);
        $this->registerAnalyzer('Permission', PermissionAnalyzer::class);
        $this->registerAnalyzer('AsyncTask', AsyncTaskAnalyzer::class);
        $this->registerAnalyzer('Scheduler', SchedulerAnalyzer::class);
        $this->registerAnalyzer('Config', ConfigAnalyzer::class);
        $this->registerAnalyzer('Resource', ResourceAnalyzer::class);
        $this->registerAnalyzer('DeprecatedApi', DeprecatedApiAnalyzer::class);
        $this->registerAnalyzer('ThreadSafety', ThreadSafetyAnalyzer::class);
        $this->registerAnalyzer('PHPStan', PHPStanAnalyzer::class);
    }
}
