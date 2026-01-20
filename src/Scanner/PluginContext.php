<?php

declare(strict_types=1);

namespace Retina\Scanner;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

class PluginContext
{
    private string $pluginPath;
    private array $pluginYml;
    private array $classes = [];
    private array $listeners = [];
    private array $commands = [];
    private array $tasks = [];
    private array $phpFiles = [];
    private array $parsedFiles = [];
    private bool $initialized = false;

    public function __construct(string $pluginPath, array $pluginYml)
    {
        $this->pluginPath = $pluginPath;
        $this->pluginYml = $pluginYml;
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->scanPhpFiles();
        $this->parseAllFiles();
        $this->extractClassInfo();
        $this->initialized = true;
    }

    private function scanPhpFiles(): void
    {
        $srcPath = $this->pluginPath . '/src';
        if (!is_dir($srcPath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()
            ->in($srcPath)
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true);

        foreach ($finder as $file) {
            $this->phpFiles[] = $file->getRealPath();
        }
    }

    private function parseAllFiles(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        foreach ($this->phpFiles as $file) {
            try {
                $code = file_get_contents($file);
                $ast = $parser->parse($code);

                if ($ast !== null) {
                    $traverser = new NodeTraverser();
                    $traverser->addVisitor(new NameResolver());
                    $ast = $traverser->traverse($ast);

                    $this->parsedFiles[$file] = $ast;
                }
            } catch (\Throwable $e) {
                $this->parsedFiles[$file] = null;
            }
        }
    }

    private function extractClassInfo(): void
    {
        foreach ($this->parsedFiles as $file => $ast) {
            if ($ast === null) {
                continue;
            }

            foreach ($ast as $node) {
                $this->extractFromNode($node, $file);
            }
        }
    }

    private function extractFromNode(Node $node, string $file): void
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            foreach ($node->stmts as $stmt) {
                $this->extractFromNode($stmt, $file);
            }
        } elseif ($node instanceof Node\Stmt\Class_) {
            $this->extractClassData($node, $file);
        }
    }

    private function extractClassData(Node\Stmt\Class_ $class, string $file): void
    {
        try {
            $fqcn = $class->namespacedName?->toString();
            if ($fqcn === null) {
                $fqcn = $class->name?->toString() ?? 'Anonymous';
            }
        } catch (\Error $e) {
            $fqcn = $class->name?->toString() ?? 'Anonymous';
        }
        $extends = $class->extends ? $class->extends->toString() : null;
        $implements = array_map(fn($i) => $i->toString(), $class->implements);

        $classInfo = [
            'fqcn' => $fqcn,
            'file' => $file,
            'line' => $class->getLine(),
            'extends' => $extends,
            'implements' => $implements,
            'methods' => [],
            'properties' => [],
            'isAbstract' => $class->isAbstract(),
            'isFinal' => $class->isFinal(),
        ];

        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod) {
                $classInfo['methods'][$stmt->name->toString()] = [
                    'name' => $stmt->name->toString(),
                    'line' => $stmt->getLine(),
                    'params' => $this->extractParams($stmt),
                    'returnType' => $this->extractReturnType($stmt),
                    'isPublic' => $stmt->isPublic(),
                    'isProtected' => $stmt->isProtected(),
                    'isPrivate' => $stmt->isPrivate(),
                    'isStatic' => $stmt->isStatic(),
                    'docComment' => $stmt->getDocComment()?->getText(),
                ];
            } elseif ($stmt instanceof Node\Stmt\Property) {
                foreach ($stmt->props as $prop) {
                    $classInfo['properties'][$prop->name->toString()] = [
                        'name' => $prop->name->toString(),
                        'line' => $stmt->getLine(),
                        'type' => $this->extractPropertyType($stmt),
                        'isPublic' => $stmt->isPublic(),
                        'isProtected' => $stmt->isProtected(),
                        'isPrivate' => $stmt->isPrivate(),
                        'isStatic' => $stmt->isStatic(),
                    ];
                }
            }
        }

        $this->classes[$fqcn] = $classInfo;

        if ($this->isListener($classInfo)) {
            $this->listeners[$fqcn] = $classInfo;
        }

        if ($this->isCommand($classInfo)) {
            $this->commands[$fqcn] = $classInfo;
        }

        if ($this->isAsyncTask($classInfo)) {
            $this->tasks[$fqcn] = $classInfo;
        }
    }

    private function extractParams(Node\Stmt\ClassMethod $method): array
    {
        $params = [];
        foreach ($method->params as $param) {
            $params[] = [
                'name' => '$' . $param->var->name,
                'type' => $param->type ? $this->nodeToString($param->type) : null,
                'default' => $param->default !== null,
            ];
        }
        return $params;
    }

    private function extractReturnType(Node\Stmt\ClassMethod $method): ?string
    {
        if ($method->returnType === null) {
            return null;
        }
        return $this->nodeToString($method->returnType);
    }

    private function extractPropertyType(Node\Stmt\Property $prop): ?string
    {
        if ($prop->type === null) {
            return null;
        }
        return $this->nodeToString($prop->type);
    }

    private function nodeToString($node): string
    {
        if ($node instanceof Node\Identifier) {
            return $node->toString();
        }
        if ($node instanceof Node\Name) {
            return $node->toString();
        }
        if ($node instanceof Node\NullableType) {
            return '?' . $this->nodeToString($node->type);
        }
        if ($node instanceof Node\UnionType) {
            return implode('|', array_map([$this, 'nodeToString'], $node->types));
        }
        if ($node instanceof Node\IntersectionType) {
            return implode('&', array_map([$this, 'nodeToString'], $node->types));
        }
        return '';
    }

    private function isListener(array $classInfo): bool
    {
        if (in_array('pocketmine\\event\\Listener', $classInfo['implements'])) {
            return true;
        }
        return false;
    }

    private function isCommand(array $classInfo): bool
    {
        $extends = $classInfo['extends'];
        return $extends === 'pocketmine\\command\\Command'
            || $extends === 'pocketmine\\command\\PluginCommand'
            || str_ends_with($extends ?? '', '\\Command');
    }

    private function isAsyncTask(array $classInfo): bool
    {
        $extends = $classInfo['extends'];
        return $extends === 'pocketmine\\scheduler\\AsyncTask'
            || str_ends_with($extends ?? '', '\\AsyncTask');
    }

    public function getPluginPath(): string
    {
        return $this->pluginPath;
    }

    public function getPluginYml(): array
    {
        return $this->pluginYml;
    }

    public function getMainClass(): ?string
    {
        return $this->pluginYml['main'] ?? null;
    }

    public function getPhpFiles(): array
    {
        $this->initialize();
        return $this->phpFiles;
    }

    public function getParsedFiles(): array
    {
        $this->initialize();
        return $this->parsedFiles;
    }

    public function getClasses(): array
    {
        $this->initialize();
        return $this->classes;
    }

    public function getListeners(): array
    {
        $this->initialize();
        return $this->listeners;
    }

    public function getCommands(): array
    {
        $this->initialize();
        return $this->commands;
    }

    public function getTasks(): array
    {
        $this->initialize();
        return $this->tasks;
    }

    public function getClass(string $fqcn): ?array
    {
        $this->initialize();
        return $this->classes[$fqcn] ?? null;
    }

    public function classExists(string $fqcn): bool
    {
        $this->initialize();
        return isset($this->classes[$fqcn]);
    }

    public function getParsedFile(string $file): ?array
    {
        $this->initialize();
        return $this->parsedFiles[$file] ?? null;
    }
}
