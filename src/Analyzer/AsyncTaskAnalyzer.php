<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class AsyncTaskAnalyzer extends AbstractAnalyzer
{
    private const THREAD_UNSAFE_METHODS = [
        'getServer',
        'getPlugin',
        'getOwningPlugin',
        'getPlayer',
        'getWorld',
        'getLevel',
    ];

    public function analyze(): array
    {
        $this->issues = [];

        foreach ($this->context->getTasks() as $fqcn => $task) {
            $this->analyzeAsyncTask($task);
        }

        return $this->issues;
    }

    private function analyzeAsyncTask(array $task): void
    {
        $file = $task['file'];
        $methods = $task['methods'];

        if (!isset($methods['onRun'])) {
            $this->addIssue(
                "AsyncTask class {$task['fqcn']} is missing the onRun() method",
                $file,
                $task['line'],
                IssueCategory::ASYNC_TASK_MISUSE,
                IssueSeverity::ERROR,
                'async_task_missing_onrun',
                'Implement the onRun(): void method'
            );
        } else {
            $onRun = $methods['onRun'];
            if (!$onRun['isPublic']) {
                $this->addIssue(
                    "AsyncTask onRun() method must be public",
                    $file,
                    $onRun['line'],
                    IssueCategory::VISIBILITY_VIOLATION,
                    IssueSeverity::ERROR
                );
            }
        }

        $this->checkThreadSafetyInOnRun($task);
        $this->checkOnCompletionUsage($task);
        $this->checkStoredData($task);
    }

    private function checkThreadSafetyInOnRun(array $task): void
    {
        $ast = $this->context->getParsedFile($task['file']);
        if ($ast === null) {
            return;
        }

        $file = $task['file'];
        $className = $this->getShortClassName($task['fqcn']);

        $traverser = new NodeTraverser();
        $issues = [];

        $visitor = new class($className, $file, $issues) extends NodeVisitorAbstract {
            private string $className;
            private string $file;
            public array $issues = [];
            private bool $inOnRun = false;
            private array $unsafeMethods;

            public function __construct(string $className, string $file, array &$issues)
            {
                $this->className = $className;
                $this->file = $file;
                $this->issues = &$issues;
                $this->unsafeMethods = [
                    'getServer',
                    'getPlugin', 
                    'getOwningPlugin',
                    'getPlayer',
                    'getWorld',
                    'getLevel',
                ];
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod && 
                    $node->name->toString() === 'onRun') {
                    $this->inOnRun = true;
                }

                if ($this->inOnRun && $node instanceof Node\Expr\MethodCall) {
                    $methodName = $node->name instanceof Node\Identifier 
                        ? $node->name->toString() 
                        : null;

                    if ($methodName !== null && in_array($methodName, $this->unsafeMethods)) {
                        $this->issues[] = [
                            'message' => "Thread-unsafe call to $methodName() in AsyncTask::onRun()",
                            'file' => $this->file,
                            'line' => $node->getLine(),
                        ];
                    }
                }

                return null;
            }

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod && 
                    $node->name->toString() === 'onRun') {
                    $this->inOnRun = false;
                }
                return null;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        foreach ($visitor->issues as $issue) {
            $this->addIssue(
                $issue['message'],
                $issue['file'],
                $issue['line'],
                IssueCategory::THREAD_SAFETY,
                IssueSeverity::ERROR,
                'async_task_thread_unsafe',
                'Do not access server/plugin/player/world objects in onRun(). Pass data through storeLocal() instead.'
            );
        }
    }

    private function checkOnCompletionUsage(array $task): void
    {
        $methods = $task['methods'];

        if (isset($methods['onCompletion'])) {
            $onCompletion = $methods['onCompletion'];
            if (!$onCompletion['isPublic']) {
                $this->addIssue(
                    "AsyncTask onCompletion() method must be public",
                    $task['file'],
                    $onCompletion['line'],
                    IssueCategory::VISIBILITY_VIOLATION,
                    IssueSeverity::ERROR
                );
            }
        }
    }

    private function checkStoredData(array $task): void
    {
        $ast = $this->context->getParsedFile($task['file']);
        if ($ast === null) {
            return;
        }

        $hasStoreLocal = false;
        $hasFetchLocal = false;

        $traverser = new NodeTraverser();
        $visitor = new class($hasStoreLocal, $hasFetchLocal) extends NodeVisitorAbstract {
            public bool $hasStoreLocal = false;
            public bool $hasFetchLocal = false;

            public function __construct(bool &$hasStoreLocal, bool &$hasFetchLocal)
            {
                $this->hasStoreLocal = &$hasStoreLocal;
                $this->hasFetchLocal = &$hasFetchLocal;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Expr\MethodCall) {
                    $methodName = $node->name instanceof Node\Identifier 
                        ? $node->name->toString() 
                        : null;

                    if ($methodName === 'storeLocal') {
                        $this->hasStoreLocal = true;
                    }
                    if ($methodName === 'fetchLocal') {
                        $this->hasFetchLocal = true;
                    }
                }
                return null;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        if ($visitor->hasStoreLocal && !$visitor->hasFetchLocal) {
            $this->addIssue(
                "AsyncTask uses storeLocal() but never calls fetchLocal()",
                $task['file'],
                $task['line'],
                IssueCategory::ASYNC_TASK_MISUSE,
                IssueSeverity::WARNING,
                'async_task_unfetched_data',
                'Call fetchLocal() in onCompletion() to retrieve stored data'
            );
        }
    }

    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
