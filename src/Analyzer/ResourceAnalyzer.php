<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Symfony\Component\Finder\Finder;

class ResourceAnalyzer extends AbstractAnalyzer
{
    public function analyze(): array
    {
        $this->issues = [];

        $resourcesPath = $this->pluginPath . '/resources';
        $availableResources = $this->scanResources($resourcesPath);

        $this->checkResourcesDirectory($resourcesPath);
        $this->analyzeResourceUsage($availableResources);

        return $this->issues;
    }

    private function scanResources(string $resourcesPath): array
    {
        $resources = [];

        if (!is_dir($resourcesPath)) {
            return $resources;
        }

        $finder = new Finder();
        $finder->files()->in($resourcesPath)->ignoreDotFiles(true);

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $resources[$relativePath] = $file->getRealPath();
        }

        return $resources;
    }

    private function checkResourcesDirectory(string $resourcesPath): void
    {
        if (!is_dir($resourcesPath)) {
            $pluginYml = $this->context->getPluginYml();
            
            if (!empty($pluginYml)) {
                $this->addIssue(
                    'No resources directory found. Consider creating one for config files and other assets.',
                    $this->pluginPath,
                    1,
                    IssueCategory::RESOURCE_MISSING,
                    IssueSeverity::INFO,
                    'no_resources_directory',
                    'Create a resources/ directory for plugin assets like config.yml'
                );
            }
            return;
        }

        $configPath = $resourcesPath . '/config.yml';
        $pluginYml = $this->context->getPluginYml();

        if (!file_exists($configPath) && !empty($pluginYml)) {
            $hasConfigUsage = $this->checkForConfigUsage();
            if ($hasConfigUsage) {
                $this->addIssue(
                    'Plugin uses getConfig() but no config.yml found in resources/',
                    $this->pluginPath . '/resources',
                    1,
                    IssueCategory::RESOURCE_MISSING,
                    IssueSeverity::WARNING,
                    'missing_config_yml',
                    'Create resources/config.yml with default configuration values'
                );
            }
        }
    }

    private function checkForConfigUsage(): bool
    {
        foreach ($this->context->getParsedFiles() as $file => $ast) {
            if ($ast === null) {
                continue;
            }

            $hasConfig = false;
            $traverser = new NodeTraverser();
            $visitor = new class($hasConfig) extends NodeVisitorAbstract {
                public bool $hasConfig = false;

                public function __construct(bool &$hasConfig)
                {
                    $this->hasConfig = &$hasConfig;
                }

                public function enterNode(Node $node)
                {
                    if ($node instanceof Node\Expr\MethodCall) {
                        $methodName = $node->name instanceof Node\Identifier 
                            ? $node->name->toString() 
                            : null;

                        if ($methodName === 'getConfig' || 
                            $methodName === 'saveDefaultConfig' ||
                            $methodName === 'reloadConfig') {
                            $this->hasConfig = true;
                            return NodeTraverser::STOP_TRAVERSAL;
                        }
                    }
                    return null;
                }
            };

            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            if ($visitor->hasConfig) {
                return true;
            }
        }

        return false;
    }

    private function analyzeResourceUsage(array $availableResources): void
    {
        $usedResources = [];

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

                        $resourceMethods = [
                            'getResource',
                            'saveResource', 
                            'getResourcePath',
                            'getResourceFolder',
                        ];

                        if ($methodName !== null && in_array($methodName, $resourceMethods)) {
                            $args = $node->args;
                            if (!empty($args) && $args[0]->value instanceof Node\Scalar\String_) {
                                $this->found[] = [
                                    'resource' => $args[0]->value->value,
                                    'file' => $this->file,
                                    'line' => $node->getLine(),
                                    'method' => $methodName,
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
                $res = $item['resource'];
                if (!isset($usedResources[$res])) {
                    $usedResources[$res] = [];
                }
                $usedResources[$res][] = ['file' => $item['file'], 'line' => $item['line'], 'method' => $item['method']];
            }
        }

        foreach ($usedResources as $resourceName => $locations) {
            if (!isset($availableResources[$resourceName])) {
                foreach ($locations as $location) {
                    $this->addIssue(
                        "Resource file '$resourceName' is referenced but not found in resources/",
                        $location['file'],
                        $location['line'],
                        IssueCategory::RESOURCE_MISSING,
                        IssueSeverity::ERROR,
                        'missing_resource_file',
                        "Create the file resources/$resourceName"
                    );
                }
            }
        }
    }
}
