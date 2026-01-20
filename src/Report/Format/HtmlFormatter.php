<?php

declare(strict_types=1);

namespace Retina\Report\Format;

use Retina\Issue\Issue;
use Retina\Issue\IssueSeverity;
use Retina\Scanner\ScanResult;

class HtmlFormatter implements FormatterInterface
{
    public function format(ScanResult $result): string
    {
        $html = $this->getHeader($result);
        $html .= $this->getSummarySection($result);
        $html .= $this->getCategoryChart($result);
        $html .= $this->getIssuesSection($result);
        $html .= $this->getFooter();

        return $html;
    }

    private function getHeader(ScanResult $result): string
    {
        $pluginName = htmlspecialchars($result->getPluginName());

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retina Report - {$pluginName}</title>
    <style>
        :root {
            --bg-primary: #0d1117;
            --bg-secondary: #161b22;
            --bg-tertiary: #21262d;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --border-color: #30363d;
            --accent-blue: #58a6ff;
            --accent-green: #3fb950;
            --accent-red: #f85149;
            --accent-yellow: #d29922;
            --accent-purple: #a371f7;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        h1 {
            font-size: 2.5rem;
            color: var(--accent-blue);
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent-blue);
        }
        
        .stat-value.error {
            color: var(--accent-red);
        }
        
        .stat-value.success {
            color: var(--accent-green);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .section-header {
            background: var(--bg-tertiary);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-content {
            padding: 1.5rem;
        }
        
        .file-group {
            margin-bottom: 1.5rem;
        }
        
        .file-path {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            background: var(--bg-tertiary);
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            color: var(--accent-blue);
            font-size: 0.9rem;
        }
        
        .issue {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }
        
        .issue-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: var(--bg-tertiary);
        }
        
        .severity-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .severity-error {
            background: rgba(248, 81, 73, 0.2);
            color: var(--accent-red);
        }
        
        .severity-warning {
            background: rgba(210, 153, 34, 0.2);
            color: var(--accent-yellow);
        }
        
        .severity-info {
            background: rgba(88, 166, 255, 0.2);
            color: var(--accent-blue);
        }
        
        .severity-hint {
            background: rgba(163, 113, 247, 0.2);
            color: var(--accent-purple);
        }
        
        .issue-category {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .issue-line {
            margin-left: auto;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .issue-body {
            padding: 1rem;
        }
        
        .issue-message {
            margin-bottom: 0.75rem;
        }
        
        .issue-suggestion {
            color: var(--accent-green);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        
        .issue-suggestion::before {
            content: '💡 ';
        }
        
        .code-snippet {
            background: var(--bg-tertiary);
            border-radius: 4px;
            padding: 0.75rem;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            white-space: pre;
        }
        
        .category-chart {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-tertiary);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .category-count {
            background: var(--accent-blue);
            color: var(--bg-primary);
            padding: 0.125rem 0.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .success-banner {
            background: rgba(63, 185, 80, 0.1);
            border: 1px solid var(--accent-green);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            color: var(--accent-green);
        }
        
        .success-banner h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        footer a {
            color: var(--accent-blue);
            text-decoration: none;
        }
        
        footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Retina Report</h1>
            <p class="subtitle">{$pluginName} v{$result->getPluginVersion()}</p>
        </header>
HTML;
    }

    private function getSummarySection(ScanResult $result): string
    {
        $issueCount = $result->getTotalIssueCount();
        $issueClass = $issueCount > 0 ? 'error' : 'success';

        return <<<HTML
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">{$result->getScannedFileCount()}</div>
                <div class="stat-label">Files Scanned</div>
            </div>
            <div class="stat-card">
                <div class="stat-value {$issueClass}">{$issueCount}</div>
                <div class="stat-label">Issues Found</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{$result->getAffectedFileCount()}</div>
                <div class="stat-label">Files Affected</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{$this->formatDuration($result->getScanDuration())}</div>
                <div class="stat-label">Scan Duration</div>
            </div>
        </div>
HTML;
    }

    private function getCategoryChart(ScanResult $result): string
    {
        $summary = $result->getSummary();
        $nonZeroSummary = array_filter($summary, fn($count) => $count > 0);

        if (empty($nonZeroSummary)) {
            return '';
        }

        $badges = '';
        foreach ($nonZeroSummary as $category => $count) {
            $categoryHtml = htmlspecialchars($category);
            $badges .= <<<HTML
                <span class="category-badge">
                    {$categoryHtml}
                    <span class="category-count">{$count}</span>
                </span>
HTML;
        }

        return <<<HTML
        <div class="section">
            <div class="section-header">Issues by Category</div>
            <div class="section-content">
                <div class="category-chart">{$badges}</div>
            </div>
        </div>
HTML;
    }

    private function getIssuesSection(ScanResult $result): string
    {
        $issuesByFile = $result->getIssuesByFile();

        if (empty($issuesByFile)) {
            return <<<HTML
        <div class="success-banner">
            <h2>✅ No Issues Found</h2>
            <p>Congratulations! Your plugin passed all checks.</p>
        </div>
HTML;
        }

        $html = '<div class="section"><div class="section-header">Detailed Issues</div><div class="section-content">';

        foreach ($issuesByFile as $file => $issues) {
            $relativeFile = htmlspecialchars($this->getRelativePath($file, $result->getPluginPath()));
            $html .= '<div class="file-group">';
            $html .= "<div class=\"file-path\">{$relativeFile}</div>";

            foreach ($issues as $issue) {
                $html .= $this->formatIssueHtml($issue);
            }

            $html .= '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    private function formatIssueHtml(Issue $issue): string
    {
        $severity = $issue->getSeverity();
        $severityClass = 'severity-' . $severity->value;
        $severityLabel = htmlspecialchars($severity->getLabel());
        $category = htmlspecialchars($issue->getCategory()->getLabel());
        $message = htmlspecialchars($issue->getMessage());
        $line = $issue->getLine();

        $html = <<<HTML
        <div class="issue">
            <div class="issue-header">
                <span class="severity-badge {$severityClass}">{$severityLabel}</span>
                <span class="issue-category">{$category}</span>
                <span class="issue-line">Line {$line}</span>
            </div>
            <div class="issue-body">
                <div class="issue-message">{$message}</div>
HTML;

        if ($issue->getSuggestion() !== null) {
            $suggestion = htmlspecialchars($issue->getSuggestion());
            $html .= "<div class=\"issue-suggestion\">{$suggestion}</div>";
        }

        if ($issue->getSnippet() !== null) {
            $snippet = htmlspecialchars($issue->getSnippet());
            $html .= "<div class=\"code-snippet\">{$snippet}</div>";
        }

        $html .= '</div></div>';

        return $html;
    }

    private function getFooter(): string
    {
        $date = date('Y-m-d H:i:s');
        return <<<HTML
        <footer>
            <p>Generated on {$date} by <a href="https://github.com/moonlightontheriver/retina">Retina</a> - PocketMine-MP Plugin Static Analyzer</p>
        </footer>
    </div>
</body>
</html>
HTML;
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return round($seconds * 1000) . 'ms';
        }
        return round($seconds, 2) . 's';
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
        return 'html';
    }

    public function getMimeType(): string
    {
        return 'text/html';
    }
}
