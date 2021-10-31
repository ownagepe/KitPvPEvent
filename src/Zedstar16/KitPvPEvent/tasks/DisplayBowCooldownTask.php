<?php


namespace Zedstar16\KitPvPEvent\tasks;


use pocketmine\Player;
use pocketmine\scheduler\Task;
use Zedstar16\KitPvPEvent\Main;

class DisplayBowCooldownTask extends Task
{
    /** @var Player */
    public $player;

    public $cooldown = 10*5;

    public $current_tick = 0;

    public function __construct(Player $player){
        $this->player = $player;
    }

    public function onRun(int $currentTick)
    {
        $p = $this->player;
        if($this->current_tick === $this->cooldown){
            $p->sendActionBarMessage("§bCooldown Ended");
            unset(Main::$bow_cooldown[$p->getName()]);
            $this->getHandler()->cancel();
            return;
        }
        $str = "§7Cooldown: ".str_repeat("§a|", intval($this->current_tick)/2).str_repeat("§c|", intval(($this->cooldown-$this->current_tick)/2))." §f".(($this->cooldown-$this->current_tick)/10)."s";
        $p->sendActionBarMessage($str);
        $this->current_tick++;

    }

}