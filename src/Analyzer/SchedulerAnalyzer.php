<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class SchedulerAnalyzer extends AbstractAnalyzer
{
    public function analyze(): array
    {
        $this->issues = [];

        foreach ($this->context->getParsedFiles() as $file => $ast) {
            if ($ast === null) {
                continue;
            }

            $this->analyzeSchedulerUsage($file, $ast);
        }

        $this->analyzeTaskClasses();

        return $this->issues;
    }

    private function analyzeSchedulerUsage(string $file, array $ast): void
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

                    $schedulerMethods = [
                        'scheduleTask',
                        'scheduleDelayedTask', 
                        'scheduleRepeatingTask',
                        'scheduleDelayedRepeatingTask',
                    ];

                    if (in_array($methodName, $schedulerMethods)) {
                        $this->checkSchedulerCall($node, $methodName);
                    }

                    $deprecatedMethods = [
                        'addTask' => 'scheduleTask',
                        'scheduleAsyncTask' => 'AsyncPool::submitTask',
                    ];

                    if (isset($deprecatedMethods[$methodName])) {
                        $this->issues[] = [
                            'message' => "Deprecated scheduler method '$methodName' used. Use {$deprecatedMethods[$methodName]} instead",
                            'file' => $this->file,
                            'line' => $node->getLine(),
                            'severity' => 'warning',
                            'code' => 'deprecated_scheduler_method',
                        ];
                    }
                }
                return null;
            }

            private function checkSchedulerCall(Node\Expr\MethodCall $node, string $methodName): void
            {
                $args = $node->args;

                if ($methodName === 'scheduleDelayedTask' && count($args) >= 2) {
                    $delayArg = $args[1]->value;
                    if ($delayArg instanceof Node\Scalar\LNumber && $delayArg->value < 0) {
                        $this->issues[] = [
                            'message' => 'Negative delay value in scheduleDelayedTask()',
                            'file' => $this->file,
                            'line' => $node->getLine(),
                            'severity' => 'error',
                            'code' => 'negative_scheduler_delay',
                        ];
                    }
                }

                if ($methodName === 'scheduleRepeatingTask' && count($args) >= 2) {
                    $periodArg = $args[1]->value;
                    if ($periodArg instanceof Node\Scalar\LNumber) {
                        if ($periodArg->value <= 0) {
                            $this->issues[] = [
                                'message' => 'Period must be positive in scheduleRepeatingTask()',
                                'file' => $this->file,
                                'line' => $node->getLine(),
                                'severity' => 'error',
                                'code' => 'invalid_scheduler_period',
                            ];
                        } elseif ($periodArg->value < 20) {
                            $this->issues[] = [
                                'message' => 'Very short repeat period (' . $periodArg->value . ' ticks). Consider if this frequency is necessary.',
                                'file' => $this->file,
                                'line' => $node->getLine(),
                                'severity' => 'info',
                                'code' => 'short_scheduler_period',
                            ];
                        }
                    }
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
                IssueCategory::SCHEDULER_MISUSE,
                $severity,
                $issue['code'] ?? null
            );
        }
    }

    private function analyzeTaskClasses(): void
    {
        foreach ($this->context->getClasses() as $fqcn => $classInfo) {
            $extends = $classInfo['extends'] ?? '';
            
            if ($extends === 'pocketmine\\scheduler\\Task' || 
                str_ends_with($extends, '\\Task')) {
                
                $this->validateTaskClass($classInfo);
            }
        }
    }

    private function validateTaskClass(array $classInfo): void
    {
        $file = $classInfo['file'];
        $methods = $classInfo['methods'];

        if (!isset($methods['onRun'])) {
            $this->addIssue(
                "Task class {$classInfo['fqcn']} is missing the onRun() method",
                $file,
                $classInfo['line'],
                IssueCategory::SCHEDULER_MISUSE,
                IssueSeverity::ERROR,
                'task_missing_onrun',
                'Implement the onRun(): void method'
            );
        } else {
            $onRun = $methods['onRun'];
            if (!$onRun['isPublic']) {
                $this->addIssue(
                    "Task onRun() method must be public",
                    $file,
                    $onRun['line'],
                    IssueCategory::VISIBILITY_VIOLATION,
                    IssueSeverity::ERROR
                );
            }
        }

        if (isset($methods['getHandler'])) {
            $this->addIssue(
                "Task class overrides getHandler() which is deprecated",
                $file,
                $methods['getHandler']['line'],
                IssueCategory::DEPRECATED_API,
                IssueSeverity::WARNING,
                'deprecated_task_gethandler'
            );
        }
    }
}
