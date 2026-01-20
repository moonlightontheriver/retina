<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class ConfigAnalyzer extends AbstractAnalyzer
{
    public function analyze(): array
    {
        $this->issues = [];

        $this->checkConfigYml();
        $this->analyzeConfigUsage();

        return $this->issues;
    }

    private function checkConfigYml(): void
    {
        $configPath = $this->pluginPath . '/resources/config.yml';

        if (file_exists($configPath)) {
            try {
                $content = file_get_contents($configPath);
                \Symfony\Component\Yaml\Yaml::parse($content);
            } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
                $this->addIssue(
                    'Invalid YAML syntax in config.yml: ' . $e->getMessage(),
                    $configPath,
                    $e->getParsedLine(),
                    IssueCategory::SYNTAX_ERROR,
                    IssueSeverity::ERROR,
                    'config_yaml_syntax'
                );
            }
        }
    }

    private function analyzeConfigUsage(): void
    {
        foreach ($this->context->getParsedFiles() as $file => $ast) {
            if ($ast === null) {
                continue;
            }

            $this->checkConfigMethods($file, $ast);
        }
    }

    private function checkConfigMethods(string $file, array $ast): void
    {
        $traverser = new NodeTraverser();
        $issues = [];

        $visitor = new class($file, $issues) extends NodeVisitorAbstract {
            private string $file;
            public array $issues = [];

            public function __construct(string $file, array &$issues)
            {
                $this->file = $file;
                $this->issues = &$issues;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Expr\MethodCall) {
                    $methodName = $node->name instanceof Node\Identifier 
                        ? $node->name->toString() 
                        : null;

                    if ($methodName === null) {
                        return null;
                    }

                    if ($methodName === 'get' || $methodName === 'getNested') {
                        $this->checkGetCall($node, $methodName);
                    }

                    if ($methodName === 'set' || $methodName === 'setNested') {
                        $this->checkSetCall($node, $methodName);
                    }

                    $deprecatedMethods = [
                        'getAll' => 'Use getConfig()->getAll() instead',
                        'setAll' => 'Use getConfig()->setAll() instead',
                    ];

                    if (isset($deprecatedMethods[$methodName])) {
                        $var = $node->var;
                        if ($var instanceof Node\Expr\MethodCall &&
                            $var->name instanceof Node\Identifier &&
                            $var->name->toString() === 'getConfig') {
                        }
                    }
                }

                if ($node instanceof Node\Expr\MethodCall) {
                    $this->checkSaveConfigUsage($node);
                }

                return null;
            }

            private function checkGetCall(Node\Expr\MethodCall $node, string $methodName): void
            {
                $args = $node->args;

                if (empty($args)) {
                    $this->issues[] = [
                        'message' => "Config $methodName() called without key argument",
                        'file' => $this->file,
                        'line' => $node->getLine(),
                        'severity' => 'error',
                        'code' => 'config_missing_key',
                    ];
                    return;
                }

                $keyArg = $args[0]->value;
                if (!($keyArg instanceof Node\Scalar\String_) && 
                    !($keyArg instanceof Node\Expr\Variable) &&
                    !($keyArg instanceof Node\Expr\BinaryOp\Concat)) {
                    $this->issues[] = [
                        'message' => "Config key should be a string literal for better static analysis",
                        'file' => $this->file,
                        'line' => $node->getLine(),
                        'severity' => 'info',
                        'code' => 'config_dynamic_key',
                    ];
                }

                if (count($args) < 2) {
                    $this->issues[] = [
                        'message' => "Config $methodName() called without default value. Consider providing a default.",
                        'file' => $this->file,
                        'line' => $node->getLine(),
                        'severity' => 'info',
                        'code' => 'config_no_default',
                    ];
                }
            }

            private function checkSetCall(Node\Expr\MethodCall $node, string $methodName): void
            {
                $args = $node->args;

                if (count($args) < 2) {
                    $this->issues[] = [
                        'message' => "Config $methodName() requires both key and value arguments",
                        'file' => $this->file,
                        'line' => $node->getLine(),
                        'severity' => 'error',
                        'code' => 'config_set_missing_args',
                    ];
                }
            }

            private function checkSaveConfigUsage(Node\Expr\MethodCall $node): void
            {
                $methodName = $node->name instanceof Node\Identifier 
                    ? $node->name->toString() 
                    : null;

                if ($methodName === 'saveConfig') {
                    $this->issues[] = [
                        'message' => 'saveConfig() is called. Ensure config changes are intentional.',
                        'file' => $this->file,
                        'line' => $node->getLine(),
                        'severity' => 'info',
                        'code' => 'config_save_notice',
                    ];
                }
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        foreach ($visitor->issues as $issue) {
            $severity = match ($issue['severity'] ?? 'error') {
                'warning' => IssueSeverity::WARNING,
                'info' => IssueSeverity::INFO,
                default => IssueSeverity::ERROR,
            };

            $this->addIssue(
                $issue['message'],
                $issue['file'],
                $issue['line'],
                IssueCategory::CONFIG_MISUSE,
                $severity,
                $issue['code'] ?? null
            );
        }
    }
}
