<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class PluginYmlAnalyzer extends AbstractAnalyzer
{
    private const REQUIRED_FIELDS = ['name', 'version', 'main', 'api'];
    private const VALID_API_VERSIONS = ['5.0.0', '4.0.0', '3.0.0'];
    private const NAME_PATTERN = '/^[A-Za-z0-9_\-. ]+$/';

    public function analyze(): array
    {
        $this->issues = [];
        $pluginYmlPath = $this->pluginPath . '/plugin.yml';

        if (!file_exists($pluginYmlPath)) {
            $this->addIssue(
                'plugin.yml not found in plugin root directory',
                $pluginYmlPath,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::ERROR,
                'missing_plugin_yml',
                'Create a plugin.yml file with required fields: name, version, main, api'
            );
            return $this->issues;
        }

        try {
            $content = file_get_contents($pluginYmlPath);
            $yaml = Yaml::parse($content);
        } catch (ParseException $e) {
            $this->addIssue(
                'Invalid YAML syntax in plugin.yml: ' . $e->getMessage(),
                $pluginYmlPath,
                $e->getParsedLine(),
                IssueCategory::SYNTAX_ERROR,
                IssueSeverity::ERROR,
                'yaml_syntax_error'
            );
            return $this->issues;
        }

        if (!is_array($yaml)) {
            $this->addIssue(
                'plugin.yml must contain a YAML mapping',
                $pluginYmlPath,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::ERROR
            );
            return $this->issues;
        }

        $this->validateRequiredFields($yaml, $pluginYmlPath);
        $this->validateName($yaml, $pluginYmlPath);
        $this->validateVersion($yaml, $pluginYmlPath);
        $this->validateMain($yaml, $pluginYmlPath);
        $this->validateApi($yaml, $pluginYmlPath);
        $this->validateOptionalFields($yaml, $pluginYmlPath);

        return $this->issues;
    }

    private function validateRequiredFields(array $yaml, string $file): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($yaml[$field]) || $yaml[$field] === null || $yaml[$field] === '') {
                $this->addIssue(
                    "Required field '$field' is missing in plugin.yml",
                    $file,
                    1,
                    IssueCategory::INVALID_PLUGIN_YML,
                    IssueSeverity::ERROR,
                    'missing_required_field',
                    "Add the '$field' field to plugin.yml"
                );
            }
        }
    }

    private function validateName(array $yaml, string $file): void
    {
        if (!isset($yaml['name'])) {
            return;
        }

        $name = $yaml['name'];

        if (!is_string($name)) {
            $this->addIssue(
                'Plugin name must be a string',
                $file,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::ERROR
            );
            return;
        }

        if (!preg_match(self::NAME_PATTERN, $name)) {
            $this->addIssue(
                'Plugin name contains invalid characters. Only letters, numbers, hyphens, periods, underscores, and spaces are allowed',
                $file,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::WARNING
            );
        }

        if (str_contains($name, ' ')) {
            $this->addIssue(
                'Plugin name contains spaces, which is discouraged',
                $file,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::INFO,
                'name_with_spaces',
                'Consider using hyphens or underscores instead of spaces'
            );
        }
    }

    private function validateVersion(array $yaml, string $file): void
    {
        if (!isset($yaml['version'])) {
            return;
        }

        $version = $yaml['version'];

        if (!is_string($version) && !is_numeric($version)) {
            $this->addIssue(
                'Plugin version must be a string or number',
                $file,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::ERROR
            );
            return;
        }

        $version = (string) $version;

        if (!preg_match('/^\d+(\.\d+)*(-[\w.]+)?(\+[\w.]+)?$/', $version)) {
            $this->addIssue(
                'Plugin version does not follow semantic versioning (e.g., 1.0.0)',
                $file,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::INFO,
                'non_semver_version',
                'Consider using semantic versioning (MAJOR.MINOR.PATCH)'
            );
        }
    }

    private function validateMain(array $yaml, string $file): void
    {
        if (!isset($yaml['main'])) {
            return;
        }

        $main = $yaml['main'];

        if (!is_string($main)) {
            $this->addIssue(
                'Main class must be a fully-qualified class name string',
                $file,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::ERROR
            );
            return;
        }

        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*$/', $main)) {
            $this->addIssue(
                "Invalid main class name format: '$main'",
                $file,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::ERROR,
                'invalid_main_class_format',
                'Main class must be a valid fully-qualified PHP class name'
            );
        }
    }

    private function validateApi(array $yaml, string $file): void
    {
        if (!isset($yaml['api'])) {
            return;
        }

        $api = $yaml['api'];
        $versions = is_array($api) ? $api : [$api];

        foreach ($versions as $version) {
            if (!is_string($version)) {
                $this->addIssue(
                    'API version must be a string',
                    $file,
                    1,
                    IssueCategory::INVALID_API_VERSION,
                    IssueSeverity::ERROR
                );
                continue;
            }

            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                $this->addIssue(
                    "Invalid API version format: '$version'. Expected format: X.Y.Z",
                    $file,
                    1,
                    IssueCategory::INVALID_API_VERSION,
                    IssueSeverity::ERROR
                );
                continue;
            }

            $majorVersion = explode('.', $version)[0];
            if ((int) $majorVersion < 4) {
                $this->addIssue(
                    "API version $version is outdated. Consider upgrading to API 5.x",
                    $file,
                    1,
                    IssueCategory::DEPRECATED_API,
                    IssueSeverity::WARNING,
                    'outdated_api_version',
                    'Update your plugin to use the latest PocketMine-MP API'
                );
            }
        }
    }

    private function validateOptionalFields(array $yaml, string $file): void
    {
        if (isset($yaml['commands']) && is_array($yaml['commands'])) {
            foreach ($yaml['commands'] as $name => $command) {
                if (!is_array($command)) {
                    $this->addIssue(
                        "Command '$name' configuration must be a mapping",
                        $file,
                        1,
                        IssueCategory::COMMAND_MISMATCH,
                        IssueSeverity::ERROR
                    );
                }
            }
        }

        if (isset($yaml['permissions']) && is_array($yaml['permissions'])) {
            foreach ($yaml['permissions'] as $name => $permission) {
                if (!is_array($permission)) {
                    continue;
                }
                if (isset($permission['default'])) {
                    $validDefaults = ['op', 'notop', 'true', 'false', true, false];
                    if (!in_array($permission['default'], $validDefaults, true)) {
                        $this->addIssue(
                            "Invalid permission default value for '$name'. Valid values: op, notop, true, false",
                            $file,
                            1,
                            IssueCategory::PERMISSION_MISMATCH,
                            IssueSeverity::ERROR
                        );
                    }
                }
            }
        }

        if (isset($yaml['depend'])) {
            $depend = is_array($yaml['depend']) ? $yaml['depend'] : [$yaml['depend']];
            foreach ($depend as $dep) {
                if (!is_string($dep)) {
                    $this->addIssue(
                        'Plugin dependencies must be strings',
                        $file,
                        1,
                        IssueCategory::INVALID_PLUGIN_YML,
                        IssueSeverity::ERROR
                    );
                }
            }
        }

        if (isset($yaml['softdepend'])) {
            $softdepend = is_array($yaml['softdepend']) ? $yaml['softdepend'] : [$yaml['softdepend']];
            foreach ($softdepend as $dep) {
                if (!is_string($dep)) {
                    $this->addIssue(
                        'Plugin soft dependencies must be strings',
                        $file,
                        1,
                        IssueCategory::INVALID_PLUGIN_YML,
                        IssueSeverity::ERROR
                    );
                }
            }
        }

        if (isset($yaml['loadbefore'])) {
            $loadbefore = is_array($yaml['loadbefore']) ? $yaml['loadbefore'] : [$yaml['loadbefore']];
            foreach ($loadbefore as $dep) {
                if (!is_string($dep)) {
                    $this->addIssue(
                        'Plugin loadbefore entries must be strings',
                        $file,
                        1,
                        IssueCategory::INVALID_PLUGIN_YML,
                        IssueSeverity::ERROR
                    );
                }
            }
        }

        if (isset($yaml['load']) && !in_array($yaml['load'], ['STARTUP', 'POSTWORLD'], true)) {
            $this->addIssue(
                "Invalid load value: '{$yaml['load']}'. Valid values: STARTUP, POSTWORLD",
                $file,
                1,
                IssueCategory::INVALID_PLUGIN_YML,
                IssueSeverity::ERROR
            );
        }
    }
}
