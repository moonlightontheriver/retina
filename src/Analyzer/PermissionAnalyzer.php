<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class PermissionAnalyzer extends AbstractAnalyzer
{
    public function analyze(): array
    {
        $this->issues = [];

        $pluginYml = $this->context->getPluginYml();
        $declaredPermissions = $pluginYml['permissions'] ?? [];

        if (!is_array($declaredPermissions)) {
            $declaredPermissions = [];
        }

        $this->validateDeclaredPermissions($declaredPermissions);
        $this->checkPermissionUsage($declaredPermissions);

        return $this->issues;
    }

    private function validateDeclaredPermissions(array $permissions): void
    {
        $pluginYmlPath = $this->pluginPath . '/plugin.yml';

        foreach ($permissions as $name => $config) {
            if (!is_string($name) || empty($name)) {
                $this->addIssue(
                    'Permission name must be a non-empty string',
                    $pluginYmlPath,
                    1,
                    IssueCategory::PERMISSION_MISMATCH,
                    IssueSeverity::ERROR
                );
                continue;
            }

            if (!preg_match('/^[a-z0-9._-]+$/', $name)) {
                $this->addIssue(
                    "Permission name '$name' should only contain lowercase letters, numbers, dots, underscores, and hyphens",
                    $pluginYmlPath,
                    1,
                    IssueCategory::PERMISSION_MISMATCH,
                    IssueSeverity::WARNING,
                    'permission_naming_convention'
                );
            }

            if (!is_array($config)) {
                continue;
            }

            if (isset($config['default'])) {
                $validDefaults = ['op', 'notop', 'true', 'false', true, false];
                if (!in_array($config['default'], $validDefaults, true)) {
                    $this->addIssue(
                        "Invalid default value for permission '$name'. Valid: op, notop, true, false",
                        $pluginYmlPath,
                        1,
                        IssueCategory::PERMISSION_MISMATCH,
                        IssueSeverity::ERROR,
                        'invalid_permission_default'
                    );
                }
            }

            if (isset($config['children']) && is_array($config['children'])) {
                foreach ($config['children'] as $child => $value) {
                    if (!isset($permissions[$child])) {
                        $this->addIssue(
                            "Permission '$name' references undefined child permission: $child",
                            $pluginYmlPath,
                            1,
                            IssueCategory::PERMISSION_MISMATCH,
                            IssueSeverity::WARNING,
                            'undefined_child_permission'
                        );
                    }
                }
            }
        }
    }

    private function checkPermissionUsage(array $declaredPermissions): void
    {
        $usedPermissions = $this->findUsedPermissions();

        foreach ($usedPermissions as $permission => $locations) {
            if (!isset($declaredPermissions[$permission])) {
                foreach ($locations as $location) {
                    $this->addIssue(
                        "Permission '$permission' is used but not declared in plugin.yml",
                        $location['file'],
                        $location['line'],
                        IssueCategory::PERMISSION_MISMATCH,
                        IssueSeverity::WARNING,
                        'undeclared_permission_usage',
                        "Add permission '$permission' to the permissions section in plugin.yml"
                    );
                }
            }
        }

        foreach ($declaredPermissions as $name => $config) {
            if (!isset($usedPermissions[$name])) {
                $this->addIssue(
                    "Permission '$name' is declared but never used in code",
                    $this->pluginPath . '/plugin.yml',
                    1,
                    IssueCategory::PERMISSION_MISMATCH,
                    IssueSeverity::INFO,
                    'unused_permission',
                    'Consider removing unused permissions or verify they are used correctly'
                );
            }
        }
    }

    private function findUsedPermissions(): array
    {
        $used = [];

        foreach ($this->context->getParsedFiles() as $file => $ast) {
            if ($ast === null) {
                continue;
            }

            $traverser = new NodeTraverser();
            $currentFile = $file;
            $visitor = new class($currentFile) extends NodeVisitorAbstract {
                private string $file;
                public array $found = [];

                public function __construct(string $file)
                {
                    $this->file = $file;
                }

                public function enterNode(Node $node)
                {
                    if ($node instanceof Node\Expr\MethodCall) {
                        $methodName = $node->name instanceof Node\Identifier 
                            ? $node->name->toString() 
                            : null;

                        if ($methodName === 'hasPermission' || $methodName === 'addAttachment') {
                            $args = $node->args;
                            if (!empty($args) && $args[0]->value instanceof Node\Scalar\String_) {
                                $this->found[] = [
                                    'permission' => $args[0]->value->value,
                                    'file' => $this->file,
                                    'line' => $node->getLine(),
                                ];
                            }
                        }
                    }
                    return null;
                }
            };

            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            foreach ($visitor->found as $item) {
                $perm = $item['permission'];
                if (!isset($used[$perm])) {
                    $used[$perm] = [];
                }
                $used[$perm][] = ['file' => $item['file'], 'line' => $item['line']];
            }
        }

        return $used;
    }
}
