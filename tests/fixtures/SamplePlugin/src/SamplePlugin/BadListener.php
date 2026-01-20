<?php

declare(strict_types=1);

namespace SamplePlugin;

use pocketmine\event\player\PlayerChatEvent;

class BadListener
{
    private function onChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $message = $event->getMessage();
        
        $player->sendMessage("You said: " . $message);
    }

    public static function staticHandler(PlayerChatEvent $event): void
    {
        $event->cancel();
    }
}
