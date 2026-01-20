<?php

declare(strict_types=1);

namespace Retina\Report\Format\Simple;

use Retina\Report\Format\FormatterInterface;
use Retina\Issue\Issue;
use Retina\Scanner\ScanResult;

class SimpleHtmlFormatter implements FormatterInterface
{
    public function format(ScanResult $result): string
    {
        $html = [];

        $html[] = '<!DOCTYPE html>';
        $html[] = '<html lang="en">';
        $html[] = '<head>';
        $html[] = '    <meta charset="UTF-8">';
        $html[] = '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html[] = '    <title>Retina Scan Report - ' . htmlspecialchars($result->getPluginName()) . '</title>';
        $html[] = $this->getStyles();
        $html[] = '</head>';
        $html[] = '<body>';
        $html[] = '    <div class="container">';
        $html[] = '        <h1>Retina Scan Report (Simple)</h1>';
        
        $html[] = '        <div class="info-box">';
        $html[] = '            <h2>Plugin Information</h2>';
        $html[] = '            <p><strong>Name:</strong> ' . htmlspecialchars($result->getPluginName()) . '</p>';
        $html[] = '            <p><strong>Version:</strong> ' . htmlspecialchars($result->getPluginVersion()) . '</p>';
        $html[] = '            <p><strong>Scan Date:</strong> ' . $result->getScanDate()->format('Y-m-d H:i:s') . '</p>';
        $html[] = '        </div>';

        $html[] = '        <div class="summary-box">';
        $html[] = '            <h2>Summary</h2>';
        $html[] = '            <p><strong>Files Scanned:</strong> ' . $result->getScannedFileCount() . '</p>';
        $html[] = '            <p><strong>Total Issues:</strong> ' . $result->getTotalIssueCount() . '</p>';
        $html[] = '        </div>';

        $summary = $result->getSummary();
        $nonZeroSummary = array_filter($summary, fn($count) => $count > 0);

        if (!empty($nonZeroSummary)) {
            $html[] = '        <div class="category-box">';
            $html[] = '            <h2>Issues by Category</h2>';
            $html[] = '            <ul>';
            foreach ($nonZeroSummary as $category => $count) {
                $html[] = sprintf('                <li><strong>%s:</strong> %d</li>', htmlspecialchars($category), $count);
            }
            $html[] = '            </ul>';
            $html[] = '        </div>';
        }

        $issuesByFile = $result->getIssuesByFile();

        if (!empty($issuesByFile)) {
            $html[] = '        <div class="issues-box">';
            $html[] = '            <h2>Issues</h2>';
            $html[] = '            <table>';
            $html[] = '                <thead>';
            $html[] = '                    <tr>';
            $html[] = '                        <th>File</th>';
            $html[] = '                        <th>Line</th>';
            $html[] = '                        <th>Severity</th>';
            $html[] = '                        <th>Category</th>';
            $html[] = '                        <th>Message</th>';
            $html[] = '                    </tr>';
            $html[] = '                </thead>';
            $html[] = '                <tbody>';

            foreach ($issuesByFile as $file => $issues) {
                $relativeFile = $this->getRelativePath($file, $result->getPluginPath());
                foreach ($issues as $issue) {
                    $html[] = $this->formatIssueRow($issue, $relativeFile);
                }
            }

            $html[] = '                </tbody>';
            $html[] = '            </table>';
            $html[] = '        </div>';
        }

        if ($result->getTotalIssueCount() === 0) {
            $html[] = '        <div class="success-box">';
            $html[] = '            <h2>✅ No Issues Found</h2>';
            $html[] = '        </div>';
        }

        $html[] = '    </div>';
        $html[] = '</body>';
        $html[] = '</html>';

        return implode("\n", $html);
    }

    private function formatIssueRow(Issue $issue, string $relativeFile): string
    {
        $severityClass = 'severity-' . $issue->getSeverity()->value;
        
        return sprintf(
            '                    <tr class="%s"><td>%s</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            $severityClass,
            htmlspecialchars($relativeFile),
            $issue->getLine(),
            htmlspecialchars(strtoupper($issue->getSeverity()->value)),
            htmlspecialchars($issue->getCategory()->getLabel()),
            htmlspecialchars($issue->getMessage())
        );
    }

    private function getRelativePath(string $path, string $basePath): string
    {
        if (str_starts_with($path, $basePath)) {
            return ltrim(substr($path, strlen($basePath)), '/\\');
        }
        return $path;
    }

    private function getStyles(): string
    {
        return <<<'CSS'
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 20px; }
        h2 { color: #555; margin: 20px 0 10px; font-size: 1.3em; }
        .info-box, .summary-box, .category-box, .issues-box, .success-box { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px; }
        .success-box { background: #d4edda; color: #155724; }
        p { margin: 5px 0; }
        ul { list-style: none; padding-left: 0; }
        ul li { padding: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: 600; }
        .severity-error { background: #fee; }
        .severity-warning { background: #ffc; }
        .severity-info { background: #eff; }
        .severity-hint { background: #f9f9f9; }
    </style>
CSS;
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
