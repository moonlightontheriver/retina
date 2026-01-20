<?php

declare(strict_types=1);

namespace Retina\Filter;

use Retina\Config\RetinaConfig;
use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use Retina\Filter\Exception\InvalidCategoryException;
use Retina\Filter\Exception\InvalidAnalyzerException;
use Retina\Filter\Exception\InvalidSeverityException;
use Retina\Filter\Exception\InvalidFilterConfigException;

class FilterConfig
{
    private array $excludedCategories;
    private array $excludedAnalyzers;
    private array $excludedSeverities;
    private bool $simpleReport;

    public function __construct(
        array $excludedCategories = [],
        array $excludedAnalyzers = [],
        array $excludedSeverities = [],
        bool $simpleReport = false
    ) {
        $this->excludedCategories = $excludedCategories;
        $this->excludedAnalyzers = $excludedAnalyzers;
        $this->excludedSeverities = $excludedSeverities;
        $this->simpleReport = $simpleReport;
    }

    public static function fromArray(array $config): self
    {
        return new self(
            $config['excludeCategories'] ?? $config['excludedCategories'] ?? [],
            $config['excludeAnalyzers'] ?? $config['excludedAnalyzers'] ?? [],
            $config['excludeSeverities'] ?? $config['excludedSeverities'] ?? [],
            $config['simpleReport'] ?? false
        );
    }

    public static function fromRetinaConfig(RetinaConfig $config): self
    {
        return new self(
            $config->getExcludeCategories(),
            $config->getExcludeAnalyzers(),
            $config->getExcludeSeverities(),
            $config->isSimpleReport()
        );
    }

    public function shouldExcludeCategory(IssueCategory $category): bool
    {
        return in_array($category->value, $this->excludedCategories, true);
    }

    public function shouldExcludeAnalyzer(string $analyzerName): bool
    {
        $normalized = $this->normalizeAnalyzerName($analyzerName);
        foreach ($this->excludedAnalyzers as $excluded) {
            if ($this->normalizeAnalyzerName($excluded) === $normalized) {
                return true;
            }
        }
        return false;
    }

    public function shouldExcludeSeverity(IssueSeverity $severity): bool
    {
        return in_array(strtolower($severity->value), array_map('strtolower', $this->excludedSeverities), true);
    }

    public function isSimpleReport(): bool
    {
        return $this->simpleReport;
    }

    public function getExcludedCategories(): array
    {
        return $this->excludedCategories;
    }

    public function getExcludedAnalyzers(): array
    {
        return $this->excludedAnalyzers;
    }

    public function getExcludedSeverities(): array
    {
        return $this->excludedSeverities;
    }

    public function validate(): void
    {
        $this->validateCategories();
        $this->validateAnalyzers();
        $this->validateSeverities();
    }

    public function expandPresets(): void
    {
        $expanded = [];
        
        foreach ($this->excludedCategories as $category) {
            if ($category === 'unused') {
                $expanded = array_merge($expanded, $this->getUnusedCategories());
            } elseif ($category === 'undefined') {
                $expanded = array_merge($expanded, $this->getUndefinedCategories());
            } elseif ($category === 'pocketmine') {
                $expanded = array_merge($expanded, $this->getPocketMineCategories());
            } else {
                $expanded[] = $category;
            }
        }
        
        $this->excludedCategories = array_unique($expanded);
    }

    private function validateCategories(): void
    {
        $validCategories = array_map(fn($c) => $c->value, IssueCategory::cases());
        $presets = ['unused', 'undefined', 'pocketmine'];
        
        foreach ($this->excludedCategories as $category) {
            if (in_array($category, $presets, true)) {
                continue;
            }
            
            if (!in_array($category, $validCategories, true)) {
                $suggestions = $this->findSimilarStrings($category, $validCategories);
                throw new InvalidCategoryException(
                    sprintf(
                        'Invalid category: "%s". Did you mean: %s',
                        $category,
                        implode(', ', array_map(fn($s) => '"' . $s . '"', $suggestions))
                    )
                );
            }
        }
    }

    private function validateAnalyzers(): void
    {
        $validAnalyzers = [
            'PluginYml', 'MainClass', 'PhpFile', 'EventHandler', 'Listener',
            'Command', 'Permission', 'AsyncTask', 'Scheduler', 'Config',
            'Resource', 'DeprecatedApi', 'ThreadSafety', 'PHPStan'
        ];
        
        foreach ($this->excludedAnalyzers as $analyzer) {
            $normalized = $this->normalizeAnalyzerName($analyzer);
            
            if (!in_array($normalized, $validAnalyzers, true)) {
                throw new InvalidAnalyzerException(
                    sprintf(
                        'Invalid analyzer: "%s". Available analyzers: %s',
                        $analyzer,
                        implode(', ', $validAnalyzers)
                    )
                );
            }
        }
        
        if (count($this->excludedAnalyzers) >= count($validAnalyzers)) {
            throw new InvalidFilterConfigException(
                'Cannot exclude all analyzers. At least one must remain active.'
            );
        }
    }

    private function validateSeverities(): void
    {
        $validSeverities = array_map(fn($s) => $s->value, IssueSeverity::cases());
        
        foreach ($this->excludedSeverities as $severity) {
            $normalized = strtolower($severity);
            
            if (!in_array($normalized, $validSeverities, true)) {
                throw new InvalidSeverityException(
                    sprintf(
                        'Invalid severity: "%s". Valid severities: %s',
                        $severity,
                        implode(', ', $validSeverities)
                    )
                );
            }
        }
        
        if (count($this->excludedSeverities) >= count($validSeverities)) {
            throw new InvalidFilterConfigException(
                'Cannot exclude all severities. At least one must be shown.'
            );
        }
    }

    private function getUnusedCategories(): array
    {
        return array_map(
            fn($c) => $c->value,
            array_filter(
                IssueCategory::cases(),
                fn($c) => str_starts_with($c->value, 'unused_')
            )
        );
    }

    private function getUndefinedCategories(): array
    {
        return array_map(
            fn($c) => $c->value,
            array_filter(
                IssueCategory::cases(),
                fn($c) => str_starts_with($c->value, 'undefined_')
            )
        );
    }

    private function getPocketMineCategories(): array
    {
        return array_map(
            fn($c) => $c->value,
            array_filter(
                IssueCategory::cases(),
                fn($c) => $c->isPocketMineSpecific()
            )
        );
    }

    private function normalizeAnalyzerName(string $name): string
    {
        $name = str_replace('Analyzer', '', $name);
        $name = str_replace('analyzer', '', $name);
        $name = ucfirst($name);
        
        if (strtolower($name) === 'phpstan') {
            return 'PHPStan';
        }
        
        return $name;
    }

    private function findSimilarStrings(string $needle, array $haystack, int $maxResults = 3): array
    {
        $distances = [];
        foreach ($haystack as $item) {
            $distance = levenshtein(strtolower($needle), strtolower($item));
            if ($distance <= 3) {
                $distances[$item] = $distance;
            }
        }
        
        asort($distances);
        return array_slice(array_keys($distances), 0, $maxResults);
    }
}
