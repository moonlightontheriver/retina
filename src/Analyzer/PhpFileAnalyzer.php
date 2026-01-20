<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Error;
use PhpParser\ParserFactory;

class PhpFileAnalyzer extends AbstractAnalyzer
{
    public function analyze(): array
    {
        $this->issues = [];
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        foreach ($this->context->getPhpFiles() as $file) {
            $code = file_get_contents($file);

            try {
                $ast = $parser->parse($code);
                if ($ast !== null) {
                    $this->analyzeFile($file, $ast, $code);
                }
            } catch (Error $e) {
                $this->addIssue(
                    'PHP syntax error: ' . $e->getMessage(),
                    $file,
                    $e->getStartLine(),
                    IssueCategory::SYNTAX_ERROR,
                    IssueSeverity::ERROR,
                    'php_syntax_error'
                );
            }
        }

        return $this->issues;
    }

    private function analyzeFile(string $file, array $ast, string $code): void
    {
        $this->checkNamespaceDeclaration($file, $ast);
        $this->checkStrictTypes($file, $ast);
        $this->checkUnusedUseStatements($file, $ast, $code);
    }

    private function checkNamespaceDeclaration(string $file, array $ast): void
    {
        $hasNamespace = false;
        $hasClass = false;

        foreach ($ast as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                $hasNamespace = true;
            }
            if ($node instanceof \PhpParser\Node\Stmt\Class_ ||
                $node instanceof \PhpParser\Node\Stmt\Interface_ ||
                $node instanceof \PhpParser\Node\Stmt\Trait_ ||
                $node instanceof \PhpParser\Node\Stmt\Enum_) {
                $hasClass = true;
            }
        }

        if ($hasClass && !$hasNamespace) {
            $this->addIssue(
                'File contains class/interface/trait without namespace declaration',
                $file,
                1,
                IssueCategory::OTHER,
                IssueSeverity::WARNING,
                'missing_namespace',
                'Add a namespace declaration to follow PSR-4 autoloading'
            );
        }
    }

    private function checkStrictTypes(string $file, array $ast): void
    {
        $hasStrictTypes = false;

        foreach ($ast as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Declare_) {
                foreach ($node->declares as $declare) {
                    if ($declare->key->toString() === 'strict_types') {
                        $hasStrictTypes = true;
                        break 2;
                    }
                }
            }
        }

        if (!$hasStrictTypes) {
            $this->addIssue(
                'Missing declare(strict_types=1) at the top of the file',
                $file,
                1,
                IssueCategory::OTHER,
                IssueSeverity::INFO,
                'missing_strict_types',
                'Add declare(strict_types=1); at the top of the file for better type safety'
            );
        }
    }

    private function checkUnusedUseStatements(string $file, array $ast, string $code): void
    {
        $useStatements = [];

        foreach ($ast as $node) {
            if ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof \PhpParser\Node\Stmt\Use_) {
                        foreach ($stmt->uses as $use) {
                            $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
                            $useStatements[$alias] = [
                                'fqcn' => $use->name->toString(),
                                'line' => $stmt->getLine(),
                            ];
                        }
                    }
                }
            } elseif ($node instanceof \PhpParser\Node\Stmt\Use_) {
                foreach ($node->uses as $use) {
                    $alias = $use->alias ? $use->alias->toString() : $use->name->getLast();
                    $useStatements[$alias] = [
                        'fqcn' => $use->name->toString(),
                        'line' => $node->getLine(),
                    ];
                }
            }
        }

        $codeWithoutUseStatements = preg_replace('/^use\s+[^;]+;/m', '', $code);

        foreach ($useStatements as $alias => $info) {
            $pattern = '/\b' . preg_quote($alias, '/') . '\b/';
            $occurrences = preg_match_all($pattern, $codeWithoutUseStatements);
            
            if ($occurrences === 0) {
                $this->addIssue(
                    "Unused import: {$info['fqcn']}",
                    $file,
                    $info['line'],
                    IssueCategory::UNUSED_IMPORT,
                    IssueSeverity::INFO,
                    'unused_import',
                    'Remove the unused use statement or verify it is not used in PHPDoc comments'
                );
            }
        }
    }
}
