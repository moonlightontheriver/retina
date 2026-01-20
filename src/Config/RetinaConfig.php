<?php

declare(strict_types=1);

namespace Retina\Config;

use Symfony\Component\Yaml\Yaml;

class RetinaConfig
{
    private int $level = 6;
    private array $paths = ['src'];
    private array $excludePaths = ['vendor', 'tests'];
    private string $reportFormat = 'md';
    private array $rules = [];
    private array $ignoreErrors = [];
    private ?string $baseline = null;
    private bool $simpleReport = false;
    private array $excludeCategories = [];
    private array $excludeAnalyzers = [];
    private array $excludeSeverities = [];

    public function __construct(array $config = [])
    {
        $this->level = $config['level'] ?? $this->level;
        $this->paths = $config['paths'] ?? $this->paths;
        $this->excludePaths = $config['excludePaths'] ?? $this->excludePaths;
        $this->reportFormat = $config['reportFormat'] ?? $this->reportFormat;
        $this->rules = $config['rules'] ?? $this->getDefaultRules();
        $this->ignoreErrors = $config['ignoreErrors'] ?? $this->ignoreErrors;
        $this->baseline = $config['baseline'] ?? $this->baseline;
        $this->simpleReport = $config['simpleReport'] ?? $this->simpleReport;
        $this->excludeCategories = $config['excludeCategories'] ?? $this->excludeCategories;
        $this->excludeAnalyzers = $config['excludeAnalyzers'] ?? $this->excludeAnalyzers;
        $this->excludeSeverities = $config['excludeSeverities'] ?? $this->excludeSeverities;
    }

    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            return new self();
        }

        $config = Yaml::parseFile($path) ?? [];
        return new self($config);
    }

    private function getDefaultRules(): array
    {
        return [
            'undefinedVariable' => true,
            'undefinedMethod' => true,
            'undefinedClass' => true,
            'undefinedConstant' => true,
            'undefinedFunction' => true,
            'typeMismatch' => true,
            'unusedVariable' => true,
            'unusedParameter' => true,
            'unusedImport' => true,
            'deadCode' => true,
            'invalidEventHandler' => true,
            'unregisteredListener' => true,
            'invalidPluginYml' => true,
            'mainClassMismatch' => true,
            'invalidApiVersion' => true,
            'deprecatedApiUsage' => true,
            'asyncTaskMisuse' => true,
            'schedulerMisuse' => true,
            'configMisuse' => true,
            'permissionMismatch' => true,
            'commandMismatch' => true,
            'resourceMissing' => true,
            'invalidEventPriority' => true,
            'cancelledEventAccess' => true,
            'threadSafetyViolation' => true,
        ];
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getPaths(): array
    {
        return $this->paths;
    }

    public function getExcludePaths(): array
    {
        return $this->excludePaths;
    }

    public function getReportFormat(): string
    {
        return $this->reportFormat;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function isRuleEnabled(string $rule): bool
    {
        return $this->rules[$rule] ?? true;
    }

    public function getIgnoreErrors(): array
    {
        return $this->ignoreErrors;
    }

    public function getBaseline(): ?string
    {
        return $this->baseline;
    }

    public function isSimpleReport(): bool
    {
        return $this->simpleReport;
    }

    public function getExcludeCategories(): array
    {
        return $this->excludeCategories;
    }

    public function getExcludeAnalyzers(): array
    {
        return $this->excludeAnalyzers;
    }

    public function getExcludeSeverities(): array
    {
        return $this->excludeSeverities;
    }

    public function toFilterConfig(): \Retina\Filter\FilterConfig
    {
        return \Retina\Filter\FilterConfig::fromRetinaConfig($this);
    }
}
