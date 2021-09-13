<?php


namespace Zedstar16\KitPvPEvent;


use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\entity\EntityArmorChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\item\Item;
use pocketmine\level\particle\HappyVillagerParticle;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;

class InfectionEventHandler implements Listener
{
    /** @var Main */
    public $pl;
    /** @var Server */
    public $server;

    /** @var array */
    public $cooldowns = [];
    /** @var array */
    public $hits = [];

    public $infected = [];

    public $infections = [];

    public $surviving = [];

    public function __construct(Main $plugin)
    {
        $this->pl = $plugin;
        $this->server = $this->pl->getServer();
    }

    public function isInfector(Player $p)
    {
        return isset($this->infected[$p->getName()]);
    }

    public function setInfected(Player $p)
    {
        $p->getInventory()->clearAll(true);
        $p->getArmorInventory()->clearAll(true);
        $p->getCursorInventory()->clearAll(true);
        $p->getCraftingGrid()->clearAll(true);
        $p->removeAllEffects();
        if (isset($this->surviving[$p->getName()])) {
            unset($this->surviving[$p->getName()]);
        }
        $this->infected[$p->getName()] = $p->getName();
        Main::$instance->giveInfectorKit($p);
    }

    public function onDamage(EntityDamageEvent $event)
    {
        $cause = $event->getCause();
        $p = $event->getEntity();
        if ($p instanceof Player) {
            if ($this->isInfector($p) && $cause === EntityDamageEvent::CAUSE_MAGIC) {
                echo "cancelld";
                $event->setBaseDamage(0);
                $event->setCancelled();
                return;
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event)
    {
        $p = $event->getPlayer();
        $name = $p->getName();
        if (!$this->isInfector($p) && $event->getItem()->getId() === Item::NETHER_STAR) {
            if (!isset($this->cooldowns[$name]) || (time() - $this->cooldowns[$name]) >= 20) {
                $this->cooldowns[$name] = time();
                $p->addEffect(new EffectInstance(Effect::getEffect(Effect::SPEED), 20 * 12, 1));
            } else $p->sendTip("§bYour Speed Boost is on cooldown for: §f" . (20 - (time() - $this->cooldowns[$name])));
        }
    }

    public function itemDrop(PlayerDropItemEvent $event)
    {
        $event->setCancelled(true);
    }

    public function armorChange(EntityArmorChangeEvent $event)
    {
        if($event->getNewItem() === Item::AIR) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @ignoreCancelled true
     */
    public function onEntityDamage(EntityDamageByEntityEvent $event)
    {
        echo 1;
        $damager = $event->getDamager();
        $target = $event->getEntity();
        if ($damager instanceof Player && $target instanceof Player) {
            $damager_name = $damager->getName();
            $target_name = $target->getName();
            if ($damager->getLevel()->getName() === "WaitingZone") {
                $event->setCancelled(true);
                return;
            }
            if ($this->isInfector($damager) && $this->isInfector($target)) {
                $damager->sendTip("§cYou cannot hit other Infectors");
                $event->setCancelled();
                return;
            }
            if (!$this->isInfector($damager) && !$this->isInfector($target)) {
                $damager->sendTip("§cYou cannot hit other Survivors");
                $event->setCancelled();
                return;
            }
            if ($this->isInfector($damager)) {
                $this->hits[$damager_name][$target_name] = ($this->hits[$damager_name][$target_name] ?? 0) + 1;
                if ($this->hits[$damager_name][$target_name] >= 8) {
                    $this->setInfected($target);
                    $this->infections[$damager_name] = ($this->infections[$damager_name] ?? 0) + 1;
                    $target->sendTitle("§a§lYou are Infected");
                    for ($i = 0; $i < 15; $i++) {
                        $target->getLevel()->addParticle(new HappyVillagerParticle($target->asVector3()->add((mt_rand(-10, 10) / 10), (mt_rand(0, 10) / 10), (mt_rand(-10, 10) / 10))));
                      //  Main::$instance->sendFireworks($damager);
                    }
                    $damager->sendTip("§aYou just Infected §f{$target_name}!");
                    if (count($this->surviving) === 0) {
                        foreach ($this->server->getOnlinePlayers() as $player) {
                            $player->sendTitle("§a{$target_name}", "was the last survivor!", 5, 35, 15);
                        }
                        Main::$instance->endInfectionEvent();
                    }
                    $this->server->broadcastMessage(Main::PREFIX . "§f{$damager_name}§7 §ainfected §f{$target_name}");
                } else {
                    $hits = $this->hits[$damager_name][$target_name];
                    $str = str_repeat("§a█", $hits) . str_repeat("§7█", 8 - $hits);
                    $damager->sendTip("§2Infecting §f{$target_name}\n$str");
                }
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
        $p->removeAllEffects();
        $p->getInventory()->clearAll();
        $p->getArmorInventory()->clearAll();
        if (Main::$event_stage === Main::STAGE_INIT) {
            $p->teleport($this->server->getLevelByName("WaitingZone")->getSpawnLocation());
            Main::$instance->giveInfoBook($p);
        } else {
            $pos = new Position(32.5, 87, -26.5, $this->server->getLevelByName("CaveMap"));
            $event->getPlayer()->setSpawn($pos);
            $p->teleport($pos);
            $this->setInfected($p);
        }
    }

    public function onRespawn(PlayerRespawnEvent $event){
        $p = $event->getPlayer();
        $name = $p->getName();
        $p->removeAllEffects();
        $p->getInventory()->clearAll();
        $p->getArmorInventory()->clearAll();
        if (Main::$event_stage === Main::STAGE_INIT) {
            $p->teleport($this->server->getLevelByName("WaitingZone")->getSpawnLocation());
            Main::$instance->giveInfoBook($p);
        } else {
            $pos = new Position(32.5, 87, -26.5, $this->server->getLevelByName("CaveMap"));
            $event->getPlayer()->setSpawn($pos);
            $p->teleport($pos);
            $this->setInfected($p);
        }
    }

    public function onQuit(PlayerQuitEvent $event){
        $p = $event->getPlayer();
        if (isset($this->surviving[$p->getName()])) {
            unset($this->surviving[$p->getName()]);
        }
    }


    public function onDeath(PlayerDeathEvent $event)
    {
        $event->getPlayer()->setSpawn(new Position(32.5, 87, -26.5, $this->server->getLevelByName("CaveMap")));
    }

    public function commandEvent(CommandEvent $event)
    {
        $p = $event->getSender();
        $cmd = explode(" ", $event->getCommand())[0];
        if (!in_array($cmd, ["msg", "tell", "w"]) && !$p->isOp()) {
            $p->sendMessage("§cYou cannot use this command during the event");
            $event->setCancelled();
        }
    }
}