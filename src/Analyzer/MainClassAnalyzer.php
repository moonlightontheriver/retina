<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

class MainClassAnalyzer extends AbstractAnalyzer
{
    public function analyze(): array
    {
        $this->issues = [];
        $pluginYml = $this->context->getPluginYml();

        if (!isset($pluginYml['main'])) {
            return $this->issues;
        }

        $mainClass = $pluginYml['main'];
        $expectedPath = $this->getExpectedMainClassPath($mainClass);

        if (!file_exists($expectedPath)) {
            $this->addIssue(
                "Main class file not found at expected path: " . $this->getRelativePath($expectedPath),
                $this->pluginPath . '/plugin.yml',
                1,
                IssueCategory::MAIN_CLASS_MISMATCH,
                IssueSeverity::ERROR,
                'main_class_not_found',
                "Create the main class file at: " . $this->getRelativePath($expectedPath)
            );
            return $this->issues;
        }

        $this->validateMainClass($mainClass, $expectedPath);

        return $this->issues;
    }

    private function getExpectedMainClassPath(string $mainClass): string
    {
        $relativePath = str_replace('\\', '/', $mainClass) . '.php';
        return $this->pluginPath . '/src/' . $relativePath;
    }

    private function validateMainClass(string $expectedFqcn, string $file): void
    {
        $code = file_get_contents($file);
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Throwable $e) {
            $this->addIssue(
                'Failed to parse main class file: ' . $e->getMessage(),
                $file,
                1,
                IssueCategory::SYNTAX_ERROR,
                IssueSeverity::ERROR
            );
            return;
        }

        if ($ast === null) {
            return;
        }

        $classInfo = $this->extractClassInfo($ast);

        if ($classInfo === null) {
            $this->addIssue(
                'No class definition found in main class file',
                $file,
                1,
                IssueCategory::MAIN_CLASS_MISMATCH,
                IssueSeverity::ERROR
            );
            return;
        }

        if ($classInfo['fqcn'] !== $expectedFqcn) {
            $this->addIssue(
                "Main class FQCN mismatch. Expected: $expectedFqcn, Found: {$classInfo['fqcn']}",
                $file,
                $classInfo['line'],
                IssueCategory::MAIN_CLASS_MISMATCH,
                IssueSeverity::ERROR,
                'main_class_fqcn_mismatch',
                "Change the class name/namespace to match: $expectedFqcn"
            );
        }

        if ($classInfo['isAbstract']) {
            $this->addIssue(
                'Main class must not be abstract',
                $file,
                $classInfo['line'],
                IssueCategory::MAIN_CLASS_MISMATCH,
                IssueSeverity::ERROR,
                'main_class_abstract',
                'Remove the abstract modifier from the main class'
            );
        }

        $implementsPlugin = false;
        $extendsPluginBase = false;

        foreach ($classInfo['implements'] as $interface) {
            if ($interface === 'pocketmine\\plugin\\Plugin' || str_ends_with($interface, '\\Plugin')) {
                $implementsPlugin = true;
                break;
            }
        }

        if ($classInfo['extends'] !== null) {
            $extends = $classInfo['extends'];
            if ($extends === 'pocketmine\\plugin\\PluginBase' || str_ends_with($extends, '\\PluginBase')) {
                $extendsPluginBase = true;
            }
        }

        if (!$implementsPlugin && !$extendsPluginBase) {
            $this->addIssue(
                'Main class must implement pocketmine\\plugin\\Plugin interface or extend PluginBase',
                $file,
                $classInfo['line'],
                IssueCategory::MAIN_CLASS_MISMATCH,
                IssueSeverity::ERROR,
                'main_class_not_plugin',
                'Extend pocketmine\\plugin\\PluginBase or implement pocketmine\\plugin\\Plugin'
            );
        }

        $this->validatePluginMethods($classInfo, $file);
    }

    private function extractClassInfo(array $ast): ?array
    {
        $classInfo = null;

        $traverser = new NodeTraverser();
        $visitor = new class($classInfo) extends NodeVisitorAbstract {
            public ?array $classInfo = null;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_) {
                    try {
                        $fqcn = $node->namespacedName?->toString();
                        if ($fqcn === null) {
                            $fqcn = $node->name?->toString() ?? 'Anonymous';
                        }
                    } catch (\Error $e) {
                        $fqcn = $node->name?->toString() ?? 'Anonymous';
                    }
                    
                    $this->classInfo = [
                        'fqcn' => $fqcn,
                        'line' => $node->getLine(),
                        'isAbstract' => $node->isAbstract(),
                        'extends' => $node->extends ? $node->extends->toString() : null,
                        'implements' => array_map(fn($i) => $i->toString(), $node->implements),
                        'methods' => [],
                    ];

                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof Node\Stmt\ClassMethod) {
                            $this->classInfo['methods'][$stmt->name->toString()] = [
                                'name' => $stmt->name->toString(),
                                'line' => $stmt->getLine(),
                                'isPublic' => $stmt->isPublic(),
                                'isProtected' => $stmt->isProtected(),
                                'isPrivate' => $stmt->isPrivate(),
                                'isStatic' => $stmt->isStatic(),
                            ];
                        }
                    }

                    return NodeTraverser::STOP_TRAVERSAL;
                }
                return null;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->classInfo;
    }

    private function validatePluginMethods(array $classInfo, string $file): void
    {
        $methods = $classInfo['methods'];

        $lifeCycleMethods = ['onLoad', 'onEnable', 'onDisable'];

        foreach ($lifeCycleMethods as $method) {
            if (isset($methods[$method])) {
                $methodInfo = $methods[$method];
                if (!$methodInfo['isPublic'] && !$methodInfo['isProtected']) {
                    $this->addIssue(
                        "Lifecycle method $method() should be public or protected",
                        $file,
                        $methodInfo['line'],
                        IssueCategory::VISIBILITY_VIOLATION,
                        IssueSeverity::WARNING,
                        'lifecycle_method_visibility'
                    );
                }
            }
        }
    }
}
