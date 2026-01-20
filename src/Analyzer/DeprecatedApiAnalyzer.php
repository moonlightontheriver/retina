<?php

declare(strict_types=1);

namespace Retina\Analyzer;

use Retina\Issue\IssueCategory;
use Retina\Issue\IssueSeverity;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class DeprecatedApiAnalyzer extends AbstractAnalyzer
{
    private const DEPRECATED_METHODS = [
        // Level -> World migration (PM4)
        'getLevel' => ['replacement' => 'getWorld', 'since' => '4.0.0'],
        'setLevel' => ['replacement' => 'setWorld', 'since' => '4.0.0'],
        'getLevelNonNull' => ['replacement' => 'getWorld', 'since' => '4.0.0'],
        // Player methods
        'teleportImmediate' => ['replacement' => 'teleport', 'since' => '4.0.0'],
        'addTitle' => ['replacement' => 'sendTitle', 'since' => '4.0.0'],
        'addSubTitle' => ['replacement' => 'sendSubTitle', 'since' => '4.0.0'],
        'addActionBarMessage' => ['replacement' => 'sendActionBarMessage', 'since' => '4.0.0'],
        'sendPopup' => ['replacement' => 'sendToastNotification', 'since' => '5.0.0'],
        'sendTip' => ['replacement' => 'sendToastNotification', 'since' => '5.0.0'],
        'getDrops' => ['replacement' => 'getDrops with cause parameter', 'since' => '4.0.0'],
        'getSpawnLocation' => ['replacement' => 'getSpawnPoint', 'since' => '4.0.0'],
        'setSpawnLocation' => ['replacement' => 'setSpawnPoint', 'since' => '4.0.0'],
        'addParticle' => ['replacement' => 'getWorld()->addParticle()', 'since' => '4.0.0'],
        'broadcastLevelEvent' => ['replacement' => 'broadcastPacketToViewers', 'since' => '4.0.0'],
        'broadcastLevelSoundEvent' => ['replacement' => 'broadcastPacketToViewers', 'since' => '4.0.0'],
        // Metadata (removed in PM4)
        'getMetadata' => ['replacement' => 'Custom data storage', 'since' => '4.0.0'],
        'setMetadata' => ['replacement' => 'Custom data storage', 'since' => '4.0.0'],
        'hasMetadata' => ['replacement' => 'Custom data storage', 'since' => '4.0.0'],
        'removeMetadata' => ['replacement' => 'Custom data storage', 'since' => '4.0.0'],
        // Server methods (PM5)
        'getPlayerExact' => ['replacement' => 'getPlayerByPrefix or custom lookup', 'since' => '5.0.0'],
        // Entity methods (PM5)
        'hidePlayer' => ['replacement' => 'hideEntity with despawnFromAll', 'since' => '5.0.0'],
        'showPlayer' => ['replacement' => 'showEntity with spawnTo', 'since' => '5.0.0'],
        'canSee' => ['replacement' => 'Direct visibility checks', 'since' => '5.0.0'],
        // PluginBase methods (PM5)
        'getResource' => ['replacement' => 'PHP file functions with getResourcePath()', 'since' => '5.0.0'],
        'saveResource' => ['replacement' => 'PHP file functions with getResourcePath()', 'since' => '5.0.0'],
        // AsyncTask methods (PM5)
        'peekLocal' => ['replacement' => 'Static class properties', 'since' => '5.0.0'],
        'fetchLocal' => ['replacement' => 'Static class properties', 'since' => '5.0.0'],
        'storeLocal' => ['replacement' => 'Static class properties', 'since' => '5.0.0'],
        'getFromThreadStore' => ['replacement' => 'Static class properties', 'since' => '5.0.0'],
        'saveToThreadStore' => ['replacement' => 'Static class properties', 'since' => '5.0.0'],
        'removeFromThreadStore' => ['replacement' => 'Static class properties', 'since' => '5.0.0'],
        // Effect methods (PM5)
        'canTick' => ['replacement' => 'getApplyInterval()', 'since' => '5.0.0'],
        // World methods (PM5)
        'getCollisionCubes' => ['replacement' => 'getBlockCollisionBoxes', 'since' => '5.0.0'],
        'getFullBlock' => ['replacement' => 'getBlock()->getStateId()', 'since' => '5.0.0'],
        // Projectile methods (PM5)
        'canSaveToDisk' => ['replacement' => 'Return false from onHitEntity()', 'since' => '5.0.0'],
        // Item methods (PM5)
        'equals' => ['replacement' => 'equalsExact or canStackWith', 'since' => '5.0.0'],
        // Enchantment methods (PM5)
        'getPrimaryItemFlags' => ['replacement' => 'ItemEnchantmentTags', 'since' => '5.0.0'],
        'getSecondaryItemFlags' => ['replacement' => 'ItemEnchantmentTags', 'since' => '5.0.0'],
        'hasPrimaryItemType' => ['replacement' => 'ItemEnchantmentTags', 'since' => '5.0.0'],
        'hasSecondaryItemType' => ['replacement' => 'ItemEnchantmentTags', 'since' => '5.0.0'],
        // TextFormat (PM5)
        'toHTML' => ['replacement' => 'Custom implementation', 'since' => '5.0.0'],
        // Utils (PM5)
        'getMemoryUsage' => ['replacement' => 'Process::getAdvancedMemoryUsage()', 'since' => '5.0.0'],
        // Timings (PM5)
        'getStartTime' => ['replacement' => 'TimingsRecord', 'since' => '5.0.0'],
    ];

    private const DEPRECATED_CLASSES = [
        'pocketmine\\level\\Level' => ['replacement' => 'pocketmine\\world\\World', 'since' => '4.0.0'],
        'pocketmine\\level\\Position' => ['replacement' => 'pocketmine\\world\\Position', 'since' => '4.0.0'],
        'pocketmine\\level\\Location' => ['replacement' => 'pocketmine\\world\\Location', 'since' => '4.0.0'],
        'pocketmine\\level\\ChunkManager' => ['replacement' => 'pocketmine\\world\\ChunkManager', 'since' => '4.0.0'],
        'pocketmine\\level\\format\\Chunk' => ['replacement' => 'pocketmine\\world\\format\\Chunk', 'since' => '4.0.0'],
        'pocketmine\\tile\\Tile' => ['replacement' => 'pocketmine\\block\\tile\\Tile', 'since' => '4.0.0'],
        'pocketmine\\metadata\\Metadatable' => ['replacement' => 'Custom data storage', 'since' => '4.0.0'],
        'pocketmine\\plugin\\PluginLogger' => ['replacement' => 'PSR-3 Logger', 'since' => '4.0.0'],
    ];

    private const DEPRECATED_CONSTANTS = [
        'pocketmine\\RESOURCE_PATH' => ['replacement' => 'Plugin::getResourcePath()', 'since' => '4.0.0'],
    ];

    public function analyze(): array
    {
        $this->issues = [];

        foreach ($this->context->getParsedFiles() as $file => $ast) {
            if ($ast === null) {
                continue;
            }

            $this->analyzeFile($file, $ast);
        }

        return $this->issues;
    }

    private function analyzeFile(string $file, array $ast): void
    {
        $traverser = new NodeTraverser();

        $deprecatedMethods = self::DEPRECATED_METHODS;
        $deprecatedClasses = self::DEPRECATED_CLASSES;
        $deprecatedConstants = self::DEPRECATED_CONSTANTS;
        $issues = [];

        $visitor = new class($file, $deprecatedMethods, $deprecatedClasses, $deprecatedConstants, $issues) extends NodeVisitorAbstract {
            private string $file;
            private array $deprecatedMethods;
            private array $deprecatedClasses;
            private array $deprecatedConstants;
            public array $issues = [];

            public function __construct(
                string $file,
                array $deprecatedMethods,
                array $deprecatedClasses,
                array $deprecatedConstants,
                array &$issues
            ) {
                $this->file = $file;
                $this->deprecatedMethods = $deprecatedMethods;
                $this->deprecatedClasses = $deprecatedClasses;
                $this->deprecatedConstants = $deprecatedConstants;
                $this->issues = &$issues;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\StaticCall) {
                    $methodName = $node->name instanceof Node\Identifier 
                        ? $node->name->toString() 
                        : null;

                    if ($methodName !== null && isset($this->deprecatedMethods[$methodName])) {
                        $info = $this->deprecatedMethods[$methodName];
                        $this->issues[] = [
                            'type' => 'method',
                            'name' => $methodName,
                            'replacement' => $info['replacement'],
                            'since' => $info['since'],
                            'line' => $node->getLine(),
                        ];
                    }
                }

                if ($node instanceof Node\Stmt\Use_) {
                    foreach ($node->uses as $use) {
                        $className = $use->name->toString();
                        if (isset($this->deprecatedClasses[$className])) {
                            $info = $this->deprecatedClasses[$className];
                            $this->issues[] = [
                                'type' => 'class',
                                'name' => $className,
                                'replacement' => $info['replacement'],
                                'since' => $info['since'],
                                'line' => $node->getLine(),
                            ];
                        }
                    }
                }

                if ($node instanceof Node\Name\FullyQualified) {
                    $className = $node->toString();
                    if (isset($this->deprecatedClasses[$className])) {
                        $info = $this->deprecatedClasses[$className];
                        $this->issues[] = [
                            'type' => 'class',
                            'name' => $className,
                            'replacement' => $info['replacement'],
                            'since' => $info['since'],
                            'line' => $node->getLine(),
                        ];
                    }
                }

                if ($node instanceof Node\Expr\ConstFetch || $node instanceof Node\Expr\ClassConstFetch) {
                    $constName = null;
                    if ($node instanceof Node\Expr\ConstFetch) {
                        $constName = $node->name->toString();
                    }

                    if ($constName !== null && isset($this->deprecatedConstants[$constName])) {
                        $info = $this->deprecatedConstants[$constName];
                        $this->issues[] = [
                            'type' => 'constant',
                            'name' => $constName,
                            'replacement' => $info['replacement'],
                            'since' => $info['since'],
                            'line' => $node->getLine(),
                        ];
                    }
                }

                return null;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        foreach ($visitor->issues as $issue) {
            $typeLabel = ucfirst($issue['type']);
            $this->addIssue(
                "$typeLabel '{$issue['name']}' is deprecated since API {$issue['since']}. Use {$issue['replacement']} instead.",
                $file,
                $issue['line'],
                IssueCategory::DEPRECATED_API,
                IssueSeverity::WARNING,
                'deprecated_' . $issue['type'],
                "Replace with {$issue['replacement']}"
            );
        }
    }
}
