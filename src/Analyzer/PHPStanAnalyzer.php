<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\Issue;
use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use Retina\Scanner\PluginContext;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PHPStanAnalyzer
{
    private string $pluginPath;
    private int $level;
    private PluginContext $context;
    private string $stubsPath;
    private string $configPath;

    public function __construct(string $pluginPath, int $level, PluginContext $context)
    {
        $this->pluginPath = $pluginPath;
        $this->level = $level;
        $this->context = $context;
        $this->stubsPath = dirname(__DIR__, 2) . '/stubs';
        $this->configPath = $this->createTempConfig();
    }

    public function analyze(): array
    {
        $issues = [];

        $srcPath = $this->pluginPath . '/src';
        if (!is_dir($srcPath)) {
            return $issues;
        }

        $phpstanBin = $this->findPhpstanBinary();
        if ($phpstanBin === null) {
            $issues[] = new Issue(
                'PHPStan binary not found. Install PHPStan for enhanced static analysis: composer require --dev phpstan/phpstan',
                $this->pluginPath . '/composer.json',
                1,
                IssueCategory::OTHER,
                IssueSeverity::INFO,
                'phpstan_not_found',
                null,
                'Run: composer require --dev phpstan/phpstan'
            );
            return $issues;
        }

        $command = [
            $phpstanBin,
            'analyse',
            '--error-format=json',
            '--no-progress',
            '-c', $this->configPath,
            '-l', (string) $this->level,
            $srcPath,
        ];

        $process = new Process($command, $this->pluginPath);
        $process->setTimeout(300);

        try {
            $process->run();
            $output = $process->getOutput();

            if (!empty($output)) {
                $result = json_decode($output, true);
                if ($result !== null && isset($result['files'])) {
                    $issues = $this->parseResults($result);
                }
            }

            $errorOutput = $process->getErrorOutput();
            if (!empty($errorOutput) && empty($output)) {
                if (str_contains($errorOutput, 'No files found to analyse')) {
                    return $issues;
                }
            }
        } catch (\Throwable $e) {
            $issues[] = new Issue(
                'PHPStan analysis failed: ' . $e->getMessage(),
                $this->pluginPath . '/src',
                1,
                IssueCategory::OTHER,
                IssueSeverity::WARNING,
                'phpstan_analysis_failed',
                null,
                'Check PHPStan configuration and ensure it is properly installed'
            );
        } finally {
            $this->cleanupSafely();
        }

        return $issues;
    }

    private function findPhpstanBinary(): ?string
    {
        $possiblePaths = [
            $this->pluginPath . '/vendor/bin/phpstan',
            dirname(__DIR__, 2) . '/vendor/bin/phpstan',
            '/usr/local/bin/phpstan',
            '/usr/bin/phpstan',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        $process = new Process(['which', 'phpstan']);
        $process->run();
        $output = trim($process->getOutput());
        if (!empty($output) && file_exists($output)) {
            return $output;
        }

        return null;
    }

    private function createTempConfig(): string
    {
        $config = [
            'parameters' => [
                'level' => $this->level,
                'paths' => [$this->pluginPath . '/src'],
                'scanDirectories' => [$this->stubsPath],
                'stubFiles' => $this->getStubFiles(),
                'reportUnmatchedIgnoredErrors' => false,
                'treatPhpDocTypesAsCertain' => false,
            ],
        ];

        $configContent = "parameters:\n";
        $configContent .= "    level: {$this->level}\n";
        $configContent .= "    paths:\n";
        $configContent .= "        - {$this->pluginPath}/src\n";
        
        if (is_dir($this->stubsPath)) {
            $configContent .= "    scanDirectories:\n";
            $configContent .= "        - {$this->stubsPath}\n";
            
            $stubFiles = $this->getStubFiles();
            if (!empty($stubFiles)) {
                $configContent .= "    stubFiles:\n";
                foreach ($stubFiles as $stubFile) {
                    $configContent .= "        - {$stubFile}\n";
                }
            }
        }
        
        $configContent .= "    reportUnmatchedIgnoredErrors: false\n";
        $configContent .= "    treatPhpDocTypesAsCertain: false\n";

        $tempPath = sys_get_temp_dir() . '/retina-phpstan-' . uniqid() . '.neon';
        file_put_contents($tempPath, $configContent);

        return $tempPath;
    }

    private function getStubFiles(): array
    {
        $stubFiles = [];

        if (!is_dir($this->stubsPath)) {
            return $stubFiles;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->stubsPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $stubFiles[] = $file->getRealPath();
            }
        }

        return $stubFiles;
    }

    private function parseResults(array $result): array
    {
        $issues = [];

        foreach ($result['files'] as $file => $fileData) {
            foreach ($fileData['messages'] as $message) {
                $category = $this->categorizeError($message['message']);
                $severity = $this->determineSeverity($message);

                $issue = new Issue(
                    $message['message'],
                    $file,
                    $message['line'] ?? 1,
                    $category,
                    $severity,
                    $message['identifier'] ?? $category->value,
                    null,
                    $message['tip'] ?? null
                );

                $issues[] = $issue;
            }
        }

        return $issues;
    }

    private function categorizeError(string $message): IssueCategory
    {
        $patterns = [
            '/Undefined variable:?\s*\$/' => IssueCategory::UNDEFINED_VARIABLE,
            '/Call to undefined method/' => IssueCategory::UNDEFINED_METHOD,
            '/Call to an undefined method/' => IssueCategory::UNDEFINED_METHOD,
            '/Access to undefined constant/' => IssueCategory::UNDEFINED_CONSTANT,
            '/Class .+ not found/' => IssueCategory::UNDEFINED_CLASS,
            '/Interface .+ not found/' => IssueCategory::UNDEFINED_CLASS,
            '/Trait .+ not found/' => IssueCategory::UNDEFINED_CLASS,
            '/Function .+ not found/' => IssueCategory::UNDEFINED_FUNCTION,
            '/Call to undefined function/' => IssueCategory::UNDEFINED_FUNCTION,
            '/Access to an undefined property/' => IssueCategory::UNDEFINED_PROPERTY,
            '/Undefined property/' => IssueCategory::UNDEFINED_PROPERTY,
            '/expects .+, .+ given/' => IssueCategory::TYPE_MISMATCH,
            '/Parameter .+ expects .+, .+ given/' => IssueCategory::PARAMETER_TYPE,
            '/should return .+ but returns/' => IssueCategory::RETURN_TYPE,
            '/Method .+ should return .+ but return statement is missing/' => IssueCategory::MISSING_RETURN,
            '/Unused variable/' => IssueCategory::UNUSED_VARIABLE,
            '/is never used/' => IssueCategory::UNUSED_VARIABLE,
            '/Dead code/' => IssueCategory::DEAD_CODE,
            '/Unreachable statement/' => IssueCategory::DEAD_CODE,
            '/implements interface .+ but does not implement/' => IssueCategory::INTERFACE_VIOLATION,
            '/must implement interface/' => IssueCategory::INTERFACE_VIOLATION,
            '/cannot extend .+ class/' => IssueCategory::INVALID_INHERITANCE,
            '/extends final class/' => IssueCategory::INVALID_INHERITANCE,
            '/abstract class .+ contains abstract method/' => IssueCategory::ABSTRACT_VIOLATION,
            '/Cannot instantiate (abstract class|interface)/' => IssueCategory::INSTANTIATION_ERROR,
            '/Cannot call abstract method/' => IssueCategory::ABSTRACT_VIOLATION,
            '/Visibility .+ must be/' => IssueCategory::VISIBILITY_VIOLATION,
            '/Access to .+ (private|protected)/' => IssueCategory::VISIBILITY_VIOLATION,
            '/Cannot access (private|protected)/' => IssueCategory::VISIBILITY_VIOLATION,
            '/Static call to instance method/' => IssueCategory::STATIC_CALL_ERROR,
            '/Non-static method .+ cannot be called statically/' => IssueCategory::STATIC_CALL_ERROR,
            '/Cannot access offset .+ on/' => IssueCategory::ARRAY_ACCESS_ERROR,
            '/Offset .+ does not exist/' => IssueCategory::ARRAY_ACCESS_ERROR,
            '/on null/' => IssueCategory::NULL_SAFETY,
            '/might be null/' => IssueCategory::NULL_SAFETY,
            '/possibly null/' => IssueCategory::NULL_SAFETY,
        ];

        foreach ($patterns as $pattern => $category) {
            if (preg_match($pattern, $message)) {
                return $category;
            }
        }

        return IssueCategory::OTHER;
    }

    private function determineSeverity(array $message): IssueSeverity
    {
        $ignorable = $message['ignorable'] ?? true;
        $tip = $message['tip'] ?? null;

        if (!$ignorable) {
            return IssueSeverity::ERROR;
        }

        $msgText = strtolower($message['message']);

        if (str_contains($msgText, 'deprecated')) {
            return IssueSeverity::WARNING;
        }

        if (str_contains($msgText, 'unused') || str_contains($msgText, 'never used')) {
            return IssueSeverity::WARNING;
        }

        if (str_contains($msgText, 'might') || str_contains($msgText, 'possibly')) {
            return IssueSeverity::WARNING;
        }

        return IssueSeverity::ERROR;
    }

    private function cleanupSafely(): void
    {
        if (file_exists($this->configPath)) {
            try {
                unlink($this->configPath);
            } catch (\Throwable $e) {
            }
        }
    }
}
