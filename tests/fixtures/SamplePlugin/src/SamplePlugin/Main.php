<?php

declare(strict_types=1);

namespace SamplePlugin;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class Main extends PluginBase implements Listener
{
    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("SamplePlugin enabled!");
        
        $undefinedVar = $someUndefinedVariable;
        
        $config = $this->getConfig();
        $value = $config->get("test_key");
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $player->sendMessage("Welcome to the server!");
        
        $this->nonExistentMethod();
        
        $wrongType = $player->getName();
        $wrongType = 123;
    }

    protected function privateHandler(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() === "test") {
            $sender->sendMessage("Test command executed!");
        }
    }
}
