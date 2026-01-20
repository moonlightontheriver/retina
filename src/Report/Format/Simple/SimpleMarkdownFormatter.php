<?php

declare(strict_types=1);

namespace Retina\Report\Format\Simple;

use Retina\Report\Format\FormatterInterface;
use Retina\Issue\Issue;
use Retina\Scanner\ScanResult;

class SimpleMarkdownFormatter implements FormatterInterface
{
    public function format(ScanResult $result): string
    {
        $output = [];

        $output[] = '# Retina Scan Report (Simple)';
        $output[] = '';
        $output[] = '## Plugin Information';
        $output[] = '';
        $output[] = sprintf('- **Name:** %s', $result->getPluginName());
        $output[] = sprintf('- **Version:** %s', $result->getPluginVersion());
        $output[] = sprintf('- **Scan Date:** %s', $result->getScanDate()->format('Y-m-d H:i:s'));
        $output[] = '';

        $output[] = '## Summary';
        $output[] = '';
        $output[] = sprintf('- **Files Scanned:** %d', $result->getScannedFileCount());
        $output[] = sprintf('- **Total Issues:** %d', $result->getTotalIssueCount());
        $output[] = '';

        $summary = $result->getSummary();
        $nonZeroSummary = array_filter($summary, fn($count) => $count > 0);

        if (!empty($nonZeroSummary)) {
            $output[] = '### Issues by Category';
            $output[] = '';
            foreach ($nonZeroSummary as $category => $count) {
                $output[] = sprintf('- **%s:** %d', $category, $count);
            }
            $output[] = '';
        }

        $issuesByFile = $result->getIssuesByFile();

        if (!empty($issuesByFile)) {
            $output[] = '## Issues';
            $output[] = '';

            foreach ($issuesByFile as $file => $issues) {
                $relativeFile = $this->getRelativePath($file, $result->getPluginPath());
                $output[] = sprintf('### %s (%d)', $relativeFile, count($issues));
                $output[] = '';

                foreach ($issues as $issue) {
                    $output[] = $this->formatSimpleIssue($issue);
                }

                $output[] = '';
            }
        }

        if ($result->getTotalIssueCount() === 0) {
            $output[] = '## ✅ No Issues Found';
            $output[] = '';
        }

        return implode("\n", $output);
    }

    private function formatSimpleIssue(Issue $issue): string
    {
        return sprintf(
            '- [%s] %s (Line %d): %s',
            strtoupper($issue->getSeverity()->value),
            $issue->getCategory()->getLabel(),
            $issue->getLine(),
            $issue->getMessage()
        );
    }

    private function getRelativePath(string $path, string $basePath): string
    {
        if (str_starts_with($path, $basePath)) {
            return ltrim(substr($path, strlen($basePath)), '/\\');
        }
        return $path;
    }

    public function getExtension(): string
    {
        return 'md';
    }

    public function getMimeType(): string
    {
        return 'text/markdown';
    }
}
