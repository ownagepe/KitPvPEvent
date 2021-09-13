<?php


namespace Zedstar16\KitPvPEvent;


use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\Server;

class LMSEventHandler implements Listener
{
    /** @var Main */
    public $pl;
    /** @var array */
    public $cooldowns = [];
    /** @var Level */
    public $level;
    /** @var Server */
    public $server;

    public $alive = [];

    public $kills = [];

    public $cooldown = [];

    public function __construct(Main $plugin)
    {
        $this->pl = $plugin;
        $this->server = $this->pl->getServer();
        if($this->server->getLevelByName("FPS") === null){
            $this->server->loadLevel("FPS");
        }
        $this->level = $this->pl->getServer()->getLevelByName("FPS");
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @ignoreCancelled true
     */
    public function onDamage(EntityDamageByEntityEvent $event)
    {
        $damager = $event->getDamager();
        $target = $event->getEntity();
        if ($damager instanceof Player && $target instanceof Player) {
            if (Main::$event_stage === Main::STAGE_INIT || $damager->getY() >= 89) {
                $target->extinguish();
                $event->setCancelled(true);
            }
        }
    }

    /**
     * @param PlayerJoinEvent $event
     * @priority HIGHEST
     */
    public function onJoin(PlayerJoinEvent $event)
    {
        $p = $event->getPlayer();
        $name = $p->getName();
        $p->teleport($this->level->getSpawnLocation());
    }

    public function onQuit(PlayerQuitEvent $event){
        $p = $event->getPlayer();
        $name = $p->getName();
        if(isset($this->alive[$name])){
            unset($this->alive[$name]);
        }
    }

    public function onConsume(PlayerItemConsumeEvent $event)
    {
        $p = $event->getPlayer();
        $name = $p->getName();
        $item = $event->getItem();
        if ($item->getId() === Item::ENCHANTED_GOLDEN_APPLE) {
            $p->sendMessage(Main::PREFIX . "You cannot use this item during the event");
            $event->setCancelled();
        }
    }

    public function onDeath(PlayerDeathEvent $event)
    {
        $p = $event->getPlayer();
        $name = $p->getName();
        $cause = $p->getLastDamageCause();
        $killed = false;
        $damager = null;
        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();
            if ($damager instanceof Player) {

                $this->addKill($damager->getName());
                $killed = true;
            }
        } elseif ($cause instanceof EntityDamageByChildEntityEvent) {
            $damager = $cause->getDamager();
            if ($damager !== null) {
                $owner = $damager->getOwningEntity();
                if ($owner instanceof Player) {
                    $this->addKill($owner->getName());
                    $killed = true;
                }
            }
        }
        if($killed){
            if(isset($this->alive[$name])){
                unset($this->alive[$name]);
            }
            var_dump($this->alive);
            if(count($this->alive) === 1){
                foreach ($this->server->getOnlinePlayers() as $player){
                    $player->sendTitle("§6§lEvent Over", "§b{$damager->getName()}§f was the §6LMS!", 5, 35, 15);
                    Main::$instance->sendFireworks($damager);
                    Main::$event_stage = Main::STAGE_OVER;
                }
            }
        }
    }

    public function onSpawn(PlayerRespawnEvent $event)
    {
        $p = $event->getPlayer();
        $name = $p->getName();
        $p->teleport($this->level->getSpawnLocation());
    }

    public function commandEvent(CommandEvent $event)
    {
        $p = $event->getSender();
        $cmd = explode(" ", $event->getCommand())[0];
        if (!in_array($cmd, ["msg", "tell", "w", "pv", "enchantmenu", "echest", "kit", "shop"]) && !$p->isOp()) {
            $p->sendMessage("§cYou cannot use this command during the event");
            $event->setCancelled();
        }
    }

    public function addKill($name)
    {
        if (!isset($this->kills[$name])) {
            $this->kills[$name] = 1;
        } else  $this->kills[$name]++;
    }

    public function onLaunch(ProjectileLaunchEvent $event)
    {
        $entity = $event->getEntity();
        $owner = $entity->getOwningEntity();
        if ($owner instanceof Player) {
            if($owner->getY() >= 89) {
                $event->setCancelled(true);
                $owner->sendMessage("§cYou cannot shoot this up here");
            }
        }
    }

    public function onShoot(EntityShootBowEvent $e)
    {
        $p = $e->getEntity();
        try {
            if ($p instanceof Player) {
                $item = $e->getBow();
                $ench = array_filter($item->getEnchantments(), function (EnchantmentInstance $enchantment) {
                    return $enchantment->getId() === Enchantment::POWER;
                });
                if (!empty($ench)) {
                    $power = $ench[0];
                    $level = $power->getLevel();
                    if ($level > 0) {
                        $cooldown = ($level <= 2) ? 1.2 : $level / 2;
                        $name = $p->getName();
                        if (isset($this->cooldown[$name]) && (microtime(true) - $this->cooldown[$name]) < $cooldown) {
                            $e->setCancelled(true);
                            $p->sendPopup("§7• Bow on cooldown for §f" . ($cooldown - round((microtime(true) - $this->cooldown[$name]), 1)) . "s§7 •");
                        } else {
                            $this->cooldown[$name] = microtime(true);
                        }
                    }
                }
            }
        }catch (\Throwable $error){

        }
    }



}
