<?php

declare(strict_types=1);

namespace Retina\Scanner;

use Retina\Issue\Issue;
use Retina\Issue\IssueCategory;

class ScanResult
{
    private string $pluginName;
    private string $pluginVersion;
    private string $pluginPath;
    private array $issues = [];
    private int $scannedFileCount = 0;
    private float $scanDuration = 0.0;
    private \DateTimeImmutable $scanDate;

    public function __construct(string $pluginPath, string $pluginName = '', string $pluginVersion = '')
    {
        $this->pluginPath = $pluginPath;
        $this->pluginName = $pluginName;
        $this->pluginVersion = $pluginVersion;
        $this->scanDate = new \DateTimeImmutable();
    }

    public function addIssue(Issue $issue): void
    {
        $this->issues[] = $issue;
    }

    public function addIssues(array $issues): void
    {
        foreach ($issues as $issue) {
            $this->addIssue($issue);
        }
    }

    public function getIssues(): array
    {
        return $this->issues;
    }

    public function getIssuesByFile(): array
    {
        $grouped = [];
        foreach ($this->issues as $issue) {
            $file = $issue->getFile();
            if (!isset($grouped[$file])) {
                $grouped[$file] = [];
            }
            $grouped[$file][] = $issue;
        }
        return $grouped;
    }

    public function getIssuesByCategory(): array
    {
        $grouped = [];
        foreach ($this->issues as $issue) {
            $category = $issue->getCategory()->value;
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $issue;
        }
        return $grouped;
    }

    public function getIssuesBySeverity(): array
    {
        $grouped = [];
        foreach ($this->issues as $issue) {
            $severity = $issue->getSeverity()->value;
            if (!isset($grouped[$severity])) {
                $grouped[$severity] = [];
            }
            $grouped[$severity][] = $issue;
        }
        return $grouped;
    }

    public function hasErrors(): bool
    {
        return count($this->issues) > 0;
    }

    public function getTotalIssueCount(): int
    {
        return count($this->issues);
    }

    public function getAffectedFileCount(): int
    {
        return count(array_unique(array_map(fn(Issue $i) => $i->getFile(), $this->issues)));
    }

    public function getSummary(): array
    {
        $summary = [];
        foreach (IssueCategory::cases() as $category) {
            $summary[$category->getLabel()] = 0;
        }

        foreach ($this->issues as $issue) {
            $label = $issue->getCategory()->getLabel();
            $summary[$label]++;
        }

        return $summary;
    }

    public function setScannedFileCount(int $count): void
    {
        $this->scannedFileCount = $count;
    }

    public function getScannedFileCount(): int
    {
        return $this->scannedFileCount;
    }

    public function setScanDuration(float $duration): void
    {
        $this->scanDuration = $duration;
    }

    public function getScanDuration(): float
    {
        return $this->scanDuration;
    }

    public function getPluginName(): string
    {
        return $this->pluginName;
    }

    public function setPluginName(string $name): void
    {
        $this->pluginName = $name;
    }

    public function getPluginVersion(): string
    {
        return $this->pluginVersion;
    }

    public function setPluginVersion(string $version): void
    {
        $this->pluginVersion = $version;
    }

    public function getPluginPath(): string
    {
        return $this->pluginPath;
    }

    public function getScanDate(): \DateTimeImmutable
    {
        return $this->scanDate;
    }

    public function toArray(): array
    {
        return [
            'plugin' => [
                'name' => $this->pluginName,
                'version' => $this->pluginVersion,
                'path' => $this->pluginPath,
            ],
            'scan' => [
                'date' => $this->scanDate->format('c'),
                'duration' => $this->scanDuration,
                'filesScanned' => $this->scannedFileCount,
                'issuesFound' => $this->getTotalIssueCount(),
                'filesAffected' => $this->getAffectedFileCount(),
            ],
            'summary' => $this->getSummary(),
            'issues' => array_map(fn(Issue $i) => $i->toArray(), $this->issues),
        ];
    }
}
