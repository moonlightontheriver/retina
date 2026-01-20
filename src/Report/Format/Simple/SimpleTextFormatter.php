<?php

declare(strict_types=1);

namespace Retina\Report\Format\Simple;

use Retina\Report\Format\FormatterInterface;
use Retina\Issue\Issue;
use Retina\Scanner\ScanResult;

class SimpleTextFormatter implements FormatterInterface
{
    public function format(ScanResult $result): string
    {
        $output = [];

        $output[] = '=== RETINA SCAN REPORT (SIMPLE) ===';
        $output[] = '';
        $output[] = 'Plugin: ' . $result->getPluginName() . ' v' . $result->getPluginVersion();
        $output[] = 'Scan Date: ' . $result->getScanDate()->format('Y-m-d H:i:s');
        $output[] = '';

        $output[] = '--- SUMMARY ---';
        $output[] = sprintf('Files Scanned: %d', $result->getScannedFileCount());
        $output[] = sprintf('Total Issues: %d', $result->getTotalIssueCount());
        $output[] = '';

        $summary = $result->getSummary();
        $nonZeroSummary = array_filter($summary, fn($count) => $count > 0);

        if (!empty($nonZeroSummary)) {
            $output[] = '--- ISSUES BY CATEGORY ---';
            foreach ($nonZeroSummary as $category => $count) {
                $output[] = sprintf('  %s: %d', $category, $count);
            }
            $output[] = '';
        }

        $issuesByFile = $result->getIssuesByFile();

        if (!empty($issuesByFile)) {
            $output[] = '--- ISSUES ---';
            $output[] = '';

            foreach ($issuesByFile as $file => $issues) {
                $relativeFile = $this->getRelativePath($file, $result->getPluginPath());
                $output[] = sprintf('%s (%d issues):', $relativeFile, count($issues));

                foreach ($issues as $issue) {
                    $output[] = $this->formatSimpleIssue($issue);
                }

                $output[] = '';
            }
        }

        if ($result->getTotalIssueCount() === 0) {
            $output[] = '--- NO ISSUES FOUND ---';
            $output[] = '';
        }

        return implode("\n", $output);
    }

    private function formatSimpleIssue(Issue $issue): string
    {
        return sprintf(
            '  [%s] Line %d - %s: %s',
            strtoupper($issue->getSeverity()->value),
            $issue->getLine(),
            $issue->getCategory()->getLabel(),
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
        return 'txt';
    }

    public function getMimeType(): string
    {
        return 'text/plain';
    }
}
