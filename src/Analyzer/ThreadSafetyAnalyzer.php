<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class ThreadSafetyAnalyzer extends AbstractAnalyzer
{
    private const THREAD_UNSAFE_GLOBALS = [
        '$_SERVER',
        '$_GET',
        '$_POST',
        '$_FILES',
        '$_COOKIE',
        '$_SESSION',
        '$_REQUEST',
        '$_ENV',
        '$GLOBALS',
    ];

    private const THREAD_UNSAFE_STATIC = [
        'static variables in async context',
    ];

    public function analyze(): array
    {
        $this->issues = [];

        foreach ($this->context->getTasks() as $fqcn => $task) {
            $this->analyzeAsyncTaskThreadSafety($task);
        }

        foreach ($this->context->getParsedFiles() as $file => $ast) {
            if ($ast === null) {
                continue;
            }

            $this->analyzeGlobalUsage($file, $ast);
        }

        return $this->issues;
    }

    private function analyzeAsyncTaskThreadSafety(array $task): void
    {
        $ast = $this->context->getParsedFile($task['file']);
        if ($ast === null) {
            return;
        }

        $file = $task['file'];
        $issues = [];

        $traverser = new NodeTraverser();
        $visitor = new class($file, $issues) extends NodeVisitorAbstract {
            private string $file;
            public array $issues = [];
            private bool $inAsyncMethod = false;
            private array $unsafeGlobals;

            public function __construct(string $file, array &$issues)
            {
                $this->file = $file;
                $this->issues = &$issues;
                $this->unsafeGlobals = [
                    '_SERVER', '_GET', '_POST', '_FILES',
                    '_COOKIE', '_SESSION', '_REQUEST', '_ENV', 'GLOBALS',
                ];
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod) {
                    $name = $node->name->toString();
                    if ($name === 'onRun' || $name === '__construct') {
                        $this->inAsyncMethod = true;
                    }
                }

                if ($this->inAsyncMethod) {
                    if ($node instanceof Node\Expr\Variable) {
                        $varName = is_string($node->name) ? $node->name : null;
                        if ($varName !== null && in_array($varName, $this->unsafeGlobals)) {
                            $this->issues[] = [
                                'message' => "Access to superglobal \$$varName in async context is thread-unsafe",
                                'file' => $this->file,
                                'line' => $node->getLine(),
                                'code' => 'superglobal_in_async',
                            ];
                        }
                    }

                    if ($node instanceof Node\Stmt\Static_) {
                        $this->issues[] = [
                            'message' => 'Static variable declaration in async context is thread-unsafe',
                            'file' => $this->file,
                            'line' => $node->getLine(),
                            'code' => 'static_in_async',
                        ];
                    }

                    if ($node instanceof Node\Expr\StaticPropertyFetch) {
                        $this->issues[] = [
                            'message' => 'Static property access in async context may be thread-unsafe',
                            'file' => $this->file,
                            'line' => $node->getLine(),
                            'code' => 'static_property_in_async',
                        ];
                    }

                    if ($node instanceof Node\Expr\FuncCall) {
                        $funcName = $node->name instanceof Node\Name 
                            ? $node->name->toString() 
                            : null;

                        $unsafeFunctions = [
                            'file_get_contents', 'file_put_contents', 'fopen', 'fclose',
                            'fread', 'fwrite', 'file', 'readfile', 'curl_init', 'curl_exec',
                            'fsockopen', 'stream_socket_client', 'mysqli_connect', 'pg_connect',
                        ];

                        if ($funcName !== null && !in_array($funcName, $unsafeFunctions)) {
                        }
                    }
                }

                return null;
            }

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod) {
                    $name = $node->name->toString();
                    if ($name === 'onRun' || $name === '__construct') {
                        $this->inAsyncMethod = false;
                    }
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
                $issue['code'],
                'Avoid sharing mutable state between threads. Use storeLocal()/fetchLocal() for data passing.'
            );
        }
    }

    private function analyzeGlobalUsage(string $file, array $ast): void
    {
        $traverser = new NodeTraverser();
        $issues = [];

        $visitor = new class($file, $issues) extends NodeVisitorAbstract {
            private string $file;
            public array $issues = [];
            private bool $inClass = false;
            private ?string $currentClass = null;

            public function __construct(string $file, array &$issues)
            {
                $this->file = $file;
                $this->issues = &$issues;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->inClass = true;
                    $this->currentClass = $node->namespacedName?->toString() ?? $node->name?->toString();
                }

                if ($node instanceof Node\Stmt\Global_) {
                    $this->issues[] = [
                        'message' => 'Use of global keyword detected. This can cause issues with thread safety and testability.',
                        'file' => $this->file,
                        'line' => $node->getLine(),
                        'code' => 'global_keyword',
                        'severity' => 'warning',
                    ];
                }

                return null;
            }

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->inClass = false;
                    $this->currentClass = null;
                }
                return null;
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
                IssueCategory::THREAD_SAFETY,
                $severity,
                $issue['code']
            );
        }
    }
}
