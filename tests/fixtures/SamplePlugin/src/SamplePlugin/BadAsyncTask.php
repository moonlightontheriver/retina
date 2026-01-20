<?php

declare(strict_types=1);

namespace SamplePlugin;

use pocketmine\scheduler\AsyncTask;

class BadAsyncTask extends AsyncTask
{
    public function onRun(): void
    {
        $server = $this->getServer();
        
        $player = $server->getPlayer("test");
        
        $globalVar = $_SERVER['HTTP_HOST'];
        
        static $counter = 0;
        $counter++;
    }

    public function onCompletion(): void
    {
        $result = $this->getResult();
    }
}
