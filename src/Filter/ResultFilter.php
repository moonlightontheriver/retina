<?php

declare(strict_types=1);

namespace Retina\Filter;

use Retina\Scanner\ScanResult;
use Retina\Issue\Issue;

class ResultFilter
{
    private FilterConfig $config;

    public function __construct(FilterConfig $config)
    {
        $this->config = $config;
    }

    public function filter(ScanResult $result): ScanResult
    {
        $issues = $result->getIssues();
        $filteredIssues = $this->filterIssues($issues);
        
        return $this->createFilteredResult($result, $filteredIssues);
    }

    private function filterIssues(array $issues): array
    {
        $filtered = [];
        
        foreach ($issues as $issue) {
            if ($this->config->shouldExcludeCategory($issue->getCategory())) {
                continue;
            }
            
            if ($this->config->shouldExcludeSeverity($issue->getSeverity())) {
                continue;
            }
            
            $filtered[] = $issue;
        }
        
        return $filtered;
    }

    private function createFilteredResult(ScanResult $original, array $filteredIssues): ScanResult
    {
        $filtered = new ScanResult(
            $original->getPluginPath(),
            $original->getPluginName(),
            $original->getPluginVersion()
        );
        
        $filtered->setScannedFileCount($original->getScannedFileCount());
        $filtered->setScanDuration($original->getScanDuration());
        $filtered->addIssues($filteredIssues);
        
        return $filtered;
    }
}
