<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;

class CommandAnalyzer extends AbstractAnalyzer
{
    public function analyze(): array
    {
        $this->issues = [];

        $pluginYml = $this->context->getPluginYml();
        $declaredCommands = $pluginYml['commands'] ?? [];
        $commandClasses = $this->context->getCommands();

        $this->validateDeclaredCommands($declaredCommands);
        $this->validateCommandClasses($commandClasses);
        $this->crossValidateCommands($declaredCommands, $commandClasses);

        return $this->issues;
    }

    private function validateDeclaredCommands(array $commands): void
    {
        $pluginYmlPath = $this->pluginPath . '/plugin.yml';

        foreach ($commands as $name => $config) {
            if (!is_array($config)) {
                continue;
            }

            if (isset($config['permission'])) {
                $permission = $config['permission'];
                $pluginYml = $this->context->getPluginYml();
                $declaredPermissions = $pluginYml['permissions'] ?? [];

                if (!isset($declaredPermissions[$permission])) {
                    $this->addIssue(
                        "Command '$name' references undeclared permission: $permission",
                        $pluginYmlPath,
                        1,
                        IssueCategory::PERMISSION_MISMATCH,
                        IssueSeverity::WARNING,
                        'command_undefined_permission',
                        "Add permission '$permission' to the permissions section in plugin.yml"
                    );
                }
            }

            if (isset($config['aliases'])) {
                $aliases = is_array($config['aliases']) ? $config['aliases'] : [$config['aliases']];
                foreach ($aliases as $alias) {
                    if (!is_string($alias)) {
                        $this->addIssue(
                            "Command '$name' has invalid alias type (must be string)",
                            $pluginYmlPath,
                            1,
                            IssueCategory::COMMAND_MISMATCH,
                            IssueSeverity::ERROR
                        );
                    }
                }
            }
        }
    }

    private function validateCommandClasses(array $commandClasses): void
    {
        foreach ($commandClasses as $fqcn => $classInfo) {
            $file = $classInfo['file'];
            $methods = $classInfo['methods'];

            if (!isset($methods['execute'])) {
                $this->addIssue(
                    "Command class $fqcn is missing the execute() method",
                    $file,
                    $classInfo['line'],
                    IssueCategory::COMMAND_MISMATCH,
                    IssueSeverity::ERROR,
                    'command_missing_execute',
                    'Implement the execute(CommandSender $sender, string $commandLabel, array $args): bool method'
                );
            } else {
                $executeMethod = $methods['execute'];
                if (!$executeMethod['isPublic']) {
                    $this->addIssue(
                        "Command execute() method in $fqcn must be public",
                        $file,
                        $executeMethod['line'],
                        IssueCategory::VISIBILITY_VIOLATION,
                        IssueSeverity::ERROR
                    );
                }

                $params = $executeMethod['params'] ?? [];
                if (count($params) < 3) {
                    $this->addIssue(
                        "Command execute() method should have 3 parameters: CommandSender, string, array",
                        $file,
                        $executeMethod['line'],
                        IssueCategory::COMMAND_MISMATCH,
                        IssueSeverity::WARNING,
                        'command_execute_params'
                    );
                }

                $returnType = $executeMethod['returnType'] ?? null;
                if ($returnType !== null && $returnType !== 'bool') {
                    $this->addIssue(
                        "Command execute() method should return bool",
                        $file,
                        $executeMethod['line'],
                        IssueCategory::RETURN_TYPE,
                        IssueSeverity::WARNING,
                        'command_execute_return_type'
                    );
                }
            }

            $this->checkCommandConstructor($classInfo);
        }
    }

    private function checkCommandConstructor(array $classInfo): void
    {
        $methods = $classInfo['methods'];

        if (isset($methods['__construct'])) {
            $constructor = $methods['__construct'];
            $docComment = $constructor['docComment'] ?? '';

            if (str_contains($docComment, 'parent::__construct') === false) {
                $this->addIssue(
                    "Command class constructor may not be calling parent::__construct()",
                    $classInfo['file'],
                    $constructor['line'],
                    IssueCategory::COMMAND_MISMATCH,
                    IssueSeverity::INFO,
                    'command_no_parent_construct',
                    'Ensure parent::__construct() is called with the command name'
                );
            }
        }
    }

    private function crossValidateCommands(array $declaredCommands, array $commandClasses): void
    {
        $pluginYmlPath = $this->pluginPath . '/plugin.yml';

        if (empty($declaredCommands) && !empty($commandClasses)) {
            $this->addIssue(
                'Plugin has command classes but no commands declared in plugin.yml',
                $pluginYmlPath,
                1,
                IssueCategory::COMMAND_MISMATCH,
                IssueSeverity::WARNING,
                'commands_not_declared',
                'Add command definitions to the commands section in plugin.yml'
            );
        }
    }
}
