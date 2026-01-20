<?php

declare(strict_types=1);

namespace Retina\Report\Format\Simple;

use Retina\Report\Format\FormatterInterface;
use Retina\Issue\Issue;
use Retina\Scanner\ScanResult;

class SimpleJsonFormatter implements FormatterInterface
{
    public function format(ScanResult $result): string
    {
        $data = [
            'plugin' => [
                'name' => $result->getPluginName(),
                'version' => $result->getPluginVersion(),
            ],
            'scan' => [
                'date' => $result->getScanDate()->format('c'),
                'duration' => $result->getScanDuration(),
                'filesScanned' => $result->getScannedFileCount(),
                'issuesFound' => $result->getTotalIssueCount(),
            ],
            'summary' => array_filter($result->getSummary(), fn($count) => $count > 0),
            'issues' => array_map(fn(Issue $i) => $this->formatSimpleIssue($i, $result->getPluginPath()), $result->getIssues()),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function formatSimpleIssue(Issue $issue, string $basePath): array
    {
        return [
            'file' => $this->getRelativePath($issue->getFile(), $basePath),
            'line' => $issue->getLine(),
            'category' => $issue->getCategory()->value,
            'severity' => $issue->getSeverity()->value,
            'message' => $issue->getMessage(),
        ];
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
        return 'json';
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }
}
