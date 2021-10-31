<?php


namespace Zedstar16\KitPvPEvent;


use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\entity\EntityArmorChangeEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
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
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\DiamondBoots;
use pocketmine\item\Item;
use pocketmine\level\particle\HappyVillagerParticle;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use Zedstar16\KitPvPEvent\tasks\DisplayBowCooldownTask;

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
                $event->setBaseDamage(0);
                $event->setCancelled();
                return;
            }
        }
    }

    public function onMove(PlayerMoveEvent $event){
        if(Main::$sex && Main::$event_stage === Main::STAGE_RUNNING){
            $p = $event->getPlayer();
            if(isset($this->infected[$p->getName()])) {
                $ents = $p->getLevel()->getNearbyEntities($p->getBoundingBox(), $p);
                foreach ($ents as $entity) {
                    if($entity instanceof  Player && isset($this->surviving[$entity->getName()])) {
                        if ($entity->getBoundingBox()->expandedCopy(0.5, 0.5, 0.5)->isVectorInside($event->getTo())) {
                            $dmg = new EntityDamageByEntityEvent($entity, $p, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 3, [], 0);
                            $p->attack($dmg);
                            $p->setMotion($p->getDirectionVector()->multiply(-1));
                        }
                    }
                }
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
                $p->addEffect(new EffectInstance(Effect::getEffect(Effect::SPEED), 20 * 12, 2));
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

    public function onInventoryTransaction(InventoryTransactionEvent $event){
        $t = $event->getTransaction();
       foreach ($t->getInventories() as $inventory){
           if($inventory instanceof ArmorInventory){
               $event->setCancelled(true);
           }
       }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @ignoreCancelled true
     */
    public function onEntityDamage(EntityDamageEvent $event)
    {
        $bool = false;
        if($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            $target = $event->getEntity();
            $bool = true;
            if(Main::$event_stage !== Main::STAGE_RUNNING){
                $event->setCancelled(true);
                return;
            }
        }
        if($event instanceof EntityDamageByChildEntityEvent){
            $damager = $event->getChild()->getOwningEntity();
            $target = $event->getEntity();
            $bool = true;
        }
        if(!$bool){
            return;
        }
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
                    }
                    $damager->sendTip("§aYou just Infected §f{$target_name}!");
                    if (count($this->surviving) === 1) {
                        foreach ($this->server->getOnlinePlayers() as $player) {
                            $survivor = array_keys($this->surviving)[0];
                            $player->sendTitle("§a$survivor", "was the last survivor!", 5, 35, 15);
                        }
                        Main::$instance->endInfectionEvent();
                    }
                    $this->server->broadcastMessage(Main::PREFIX . "§f{$damager_name}§7 §ainfected §f{$target_name}");
                } else {
                    $hits = $this->hits[$damager_name][$target_name];
                    $str = str_repeat("§a█", $hits) . str_repeat("§7█", 8 - $hits);
                    $damager->sendTip("§2Infecting §f{$target_name}\n$str");
                    $target->sendTip("§c{$damager_name}§2 is Infecting you ".$str);
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
            $event->getPlayer()->setSpawn(Main::$instance->getRandomSpawnPos());
            $p->teleport(Main::$instance->getRandomSpawnPos());
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
            $event->getPlayer()->setSpawn(Main::$instance->getRandomSpawnPos());
            $p->teleport(Main::$instance->getRandomSpawnPos());
            $this->setInfected($p);
            Main::$instance->getScheduler()->scheduleDelayedTask(new ClosureTask(function (Int $currentTick) use($p) : void{
                $p->addEffect(new EffectInstance(Effect::getEffect(Effect::POISON), 99999, 0, true));
                $p->addEffect(new EffectInstance(Effect::getEffect(Effect::SPEED), 99999, 0, true));
            }), 10);
        }
    }

    public function onBowShoot(EntityShootBowEvent $event){
        $player = $event->getEntity();
        if($player instanceof  Player){
            $name = $player->getName();
            if(isset(Main::$bow_cooldown[$name])){
                $event->setCancelled();
            }else{
                Main::$instance->getScheduler()->scheduleRepeatingTask(new DisplayBowCooldownTask($player), 2);
                Main::$bow_cooldown[$name] = $name;
            }
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
        $event->getPlayer()->setSpawn(Main::$instance->getRandomSpawnPos());
        $event->setDrops([]);
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