<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\Issue;
use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use Retina\Scanner\PluginContext;

abstract class AbstractAnalyzer implements AnalyzerInterface
{
    protected string $pluginPath;
    protected PluginContext $context;
    protected array $issues = [];

    public function __construct(string $pluginPath, PluginContext $context)
    {
        $this->pluginPath = $pluginPath;
        $this->context = $context;
    }

    abstract public function analyze(): array;

    public function getName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    protected function addIssue(
        string $message,
        string $file,
        int $line,
        IssueCategory $category,
        IssueSeverity $severity = IssueSeverity::ERROR,
        ?string $code = null,
        ?string $suggestion = null
    ): void {
        $issue = new Issue(
            $message,
            $file,
            $line,
            $category,
            $severity,
            $code ?? $category->value,
            null,
            $suggestion
        );

        $this->addSnippet($issue, $file, $line);
        $this->issues[] = $issue;
    }

    protected function addSnippet(Issue $issue, string $file, int $line): void
    {
        if (!file_exists($file) || is_dir($file)) {
            return;
        }

        $lines = @file($file);
        if ($lines === false) {
            return;
        }

        $start = max(0, $line - 3);
        $end = min(count($lines), $line + 2);

        $snippet = '';
        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $prefix = $lineNum === $line ? '> ' : '  ';
            $snippet .= sprintf("%s%4d | %s", $prefix, $lineNum, $lines[$i]);
        }

        $issue->setSnippet(rtrim($snippet));
    }

    protected function getRelativePath(string $path): string
    {
        if (str_starts_with($path, $this->pluginPath)) {
            return ltrim(substr($path, strlen($this->pluginPath)), '/\\');
        }
        return $path;
    }
}
