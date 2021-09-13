<?php

declare(strict_types=1);

namespace Zedstar16\KitPvPEvent;

use de\Fireworks;
use onebone\economyapi\EconomyAPI;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\HandlerList;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\WrittenBook;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use Zedstar16\KitPvPEvent\tasks\PlayerExecTask;
use Zedstar16\KitPvPEvent\tasks\UpdateScoreboardTask;
use Zedstar16\ZedFun\ZedFun;

class Main extends PluginBase
{

    public static $heartbeat = 0;

    public static $data = [];

    public static $event_stage = -1;
    /** @var Main */
    public static $instance = null;

    public const INFECTION_EVENT = 0;
    public const LAST_MAN_STANDING = 1;

    public const PREFIX = "§8§l(§3EVENT§8)§r§7 ";

    public const STAGE_INIT = -1;
    public const STAGE_RUNNING = 0;
    public const STAGE_OVER = 1;

    public const WAIT_TIME = 300;

    public $tp_time = 5;

    public static $event;
    /** @var InfectionEventHandler */
    public $infection_event;
    /** @var LMSEventHandler */
    public $lms_event;

    public $top_infections = [];

    public $top_kills = [];

    public static $near = true;

    public function onLoad()
    {
        GeneratorManager::addGenerator(VoidGenerator::class, "void", true);
    }

    public function onEnable(): void
    {
        self::$instance = $this;
        $this->getLogger()->info("Hello World!");
        $this->getServer()->loadLevel("WaitingZone");
        $this->getServer()->loadLevel("FPS");
        $this->getServer()->loadLevel("CaveMap");
        $this->infection_event = new InfectionEventHandler($this);
        $this->lms_event = new LMSEventHandler($this);
        $this->startTicker();
        $this->getServer()->getPluginManager()->registerEvents($this->infection_event, $this);
        self::$event = self::INFECTION_EVENT;
    }

    public function startTicker()
    {
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function (int $currentTick): void {
                Main::$instance->getScheduler()->scheduleRepeatingTask(new UpdateScoreboardTask(), 1);
                Main::$heartbeat++;
                if (Main::$event === Main::INFECTION_EVENT) {
                    if (Main::$heartbeat === Main::WAIT_TIME) {
                        echo "starting";
                        Main::$event_stage = Main::STAGE_RUNNING;

                        if (Main::$instance->getServer()->getLevelByName("CaveMap") === null) {
                            Main::$instance->getServer()->loadLevel("CaveMap");
                        }
                        $level = $this->getServer()->getLevelByName("CaveMap");
                        $players = $this->getServer()->getOnlinePlayers();
                        shuffle($players);
                        foreach ($players as $key => $p) {                         
                            if (in_array($key, [0, 1])) {
                            //if ($p->getName() === "Zedstar16") {
                                Main::$instance->giveInfectorKit($p);
                                Main::$instance->infection_event->infected[$p->getName()] = $p->getName();
                            } else {
                                Main::$instance->infection_event->surviving[$p->getName()] = $p->getName();
                                Main::$instance->giveSurvivorKit($p);
                            }
                            $p->teleport(new Position(mt_rand(-15, 87), 90, mt_rand(-63, 3), $level));
                        }
                    }
                    if (in_array(Main::$event_stage, [Main::STAGE_RUNNING, Main::STAGE_OVER])) {
                        if(Main::$near) {
                            Main::execAll(function ($player) {
                                if (Main::$instance->infection_event->isInfector($player)) {
                                    $nearest = [];
                                    foreach (Main::$instance->infection_event->surviving as $user) {
                                        $p = Server::getInstance()->getPlayer($user);
                                        if ($p !== null) {
                                            $dist = $player->distance($p);
                                            if (empty($nearest)) {
                                                $nearest = [$user, $dist];
                                            } else {
                                                if ($nearest[1] > $dist) {
                                                    $nearest = [$user, $dist];
                                                }
                                            }
                                        }
                                    }
                                    if (!empty($nearest)) {
                                        $player->sendTip("§aNearest Survivor: §f{$nearest[0]} §6(".round($nearest[1])."m)");
                                    }
                                }
                            });
                        }
                        $top = [];
                        foreach (Main::$instance->infection_event->infections as $username => $infections) {
                            if (empty($top)) {
                                $top = [$username, $infections];
                            } else {
                                if ($top[1] < $infections) {
                                    $top = [$username, $infections];
                                }
                            }
                        }
                        Main::$instance->top_infections = $top;
                    }
                    if (Main::$event_stage === Main::STAGE_OVER) {
                        foreach ($this->getServer()->getOnlinePlayers() as $p) {
                            $p->sendTip("§aEvent Over, Starting Last Man Standing Event...");
                        }
                    }
                } elseif (Main::$event === Main::LAST_MAN_STANDING) {
                    if (Main::$heartbeat === Main::WAIT_TIME) {
                        Main::$event_stage = Main::STAGE_RUNNING;
                        Main::execAll(function ($player) {
                            /** @var Player $player */
                            $player->teleport($player->asVector3()->subtract(0, 20));
                            Main::$instance->lms_event->alive[$player->getName()] = $player->getName();
                        });
                    }
                    if (Main::$event_stage === Main::STAGE_RUNNING) {
                        $top = [];
                        foreach (Main::$instance->lms_event->kills as $username => $kills) {
                            if (empty($top)) {
                                $top = [$username, $kills];
                            } else {
                                if ($top[1] < $kills) {
                                    $top = [$username, $kills];
                                }
                            }
                        }
                        Main::$instance->top_kills = $top;
                    }
                }
            }), 20);
    }

    public function endInfectionEvent()
    {
        self::$event_stage = self::STAGE_OVER;
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick): void {
            Main::$event = Main::LAST_MAN_STANDING;
            Main::$heartbeat = 0;
            Main::$event_stage = Main::STAGE_INIT;
            Main::execAll(function ($player) {
                /** @var Player $player */
                $player->getInventory()->clearAll(true);
                $player->getArmorInventory()->clearAll(true);
                $player->getCursorInventory()->clearAll(true);
                $player->getCraftingGrid()->clearAll(true);
                $player->removeAllEffects();
                $level = Main::$instance->getServer()->getLevelByName("FPS");
                if($level === null){
                    Main::$instance->getServer()->loadLevel("FPS");
                }
                $player->setSpawn(new Position(257.5, 91.5, 256.5, Main::$instance->getServer()->getLevelByName("FPS")));
                $player->teleport(new Position((257.5) + (mt_rand(-10, 10) / 10), 91.5, (256.5) + (mt_rand(-10, 10) / 10), $level));
                Main::$instance->lms_event->alive[$player->getName()] = $player->getName();
            });
            HandlerList::unregisterAll(Main::$instance->infection_event);
            Main::$instance->getServer()->getPluginManager()->registerEvents(Main::$instance->lms_event, Main::$instance);
        }), 20 * 5);
    }

    public function giveInfoBook(Player $p)
    {
        //  api eval $i=$this->getServer()->getPlayer('Zedstar16')->getInventory();$item = $i->getItemInHand();$item->setPageText(1, '§4➤§lThis is a test for how many words fit');$i->setItemInHand($item);
    
        $item = Item::get(Item::WRITTEN_BOOK, 0, 1);
        /** @var WrittenBook $item */
        $item->setTitle(TextFormat::UNDERLINE . "§bEvent Information");
        $item->setAuthor("Ownage");
        $p0 = [
            "§6§lOwnage§9PE§r",
            "§2Infection Event",
            "§5How to play:",
            "§4- §cAfter the event timer reaches §60:00§c you will all be teleported to the event arena",
            "§4- §cYou will §4not §cbe able to use any of your kitpvp items during the infection event",
            "§4- §cAll §6Survivors§c will have a §3Diamond§c set",
            "§4- §cTeaming §2Allowed§c",

        ];
        $p1 = ["§4- §cAll §2Infectors§c will have a §6Gold§c armor set",
            "§4- §cInfectors can be killed, they will respawn at Arena centre",
            "§4- §cSurvivors can only be Infected, not killed",
            "§4- §cTo infect a player, hit them §38 times",
            "§4- §cInfectors have constant §9Speed I",
            "§4- §cSurvivors can get 10s of §9Speed II§c every 20s",];
        $item->setPageText(0, implode("\n", $p0));
        $item->setPageText(1, implode("\n", $p1));
        $p->getInventory()->addItem($item);
    }

    public static function updateScoreboard(Player $player)
    {
        echo "upodate sc {$player->getName()}";
        ScoreFactory::setScore($player, "§l§6Ownage §eEvent");
        foreach (self::getScoreboardLines($player) as $line_number => $line) {
            ScoreFactory::setScoreLine($player, $line_number + 1, $line);
        }
    }

    public static function getScoreboardLines(Player $player)
    {
        $t = explode(":", gmdate("i:s", self::WAIT_TIME - self::$heartbeat));
        $online = count(Server::getInstance()->getOnlinePlayers());
        $money = EconomyAPI::getInstance()->myMoney($player);
        $lines = [
            "§e︱- §6§lGeneral",
            "§e︱ §fOnline §7$online",
        ];
        if (self::$event === self::INFECTION_EVENT) {
            $lines[] = "§e︱- §6§lEvent: §2Infection";
            if (self::$event_stage === self::STAGE_INIT) {
                $lines = array_merge($lines, [
                    "§e︱ §fStarting In §a$t[0]m $t[1]s",
                    "§e︱ §bOpen the book ",
                    "§e︱ §bto view Event Info"
                ]);
            } elseif (in_array(self::$event_stage, [self::STAGE_RUNNING, self::STAGE_OVER])) {
                if (self::$event_stage === self::STAGE_OVER) {
                    $lines[] = "§e︱ §fStatus: §cOver";
                }else{
                    $lines[] = "§e︱ §fSurviving: §a".count(Main::$instance->infection_event->surviving);
                }
                if (self::$instance->infection_event->isInfector($player)) {
                    $infections = self::$instance->infection_event->infections[$player->getName()] ?? 0;
                    $lines[] = "§e︱ §fYour Infections: §a$infections";
                }
                if (!empty(Main::$instance->top_infections)) {
                    $lines[] = "§e︱- §6§lTop Infector: ";
                    $lines[] = "§e︱ §l§e#1§r §a" . Main::$instance->top_infections[0] . " §f-§b " . Main::$instance->top_infections[1];
                }
            }
        } elseif (self::$event === self::LAST_MAN_STANDING) {
            $lines[] = "§e︱ §fMoney §7\$$money";
            $lines[] = "§e︱- §6§lEvent: §bLMS";
            $lines[] = "§e︱- §fAlive: §a" . count(Main::$instance->lms_event->alive);
            if (self::$event_stage === self::STAGE_INIT) {
                $lines = array_merge($lines, [
                    "§e︱ §fStarting In §a$t[0]m $t[1]s",
                    "§e︱ §fGather gear and prepare",
                    "§e︱ §ffor the upcoming battle",
                ]);
            } elseif (in_array(self::$event_stage, [self::STAGE_RUNNING, self::STAGE_OVER])) {
                if (self::$event_stage === self::STAGE_OVER) {
                    $lines[] = "§e︱ §fStatus: §cOver";
                }
                $kills = self::$instance->lms_event->kills[$player->getName()] ?? 0;
                $lines[] = "§e︱ §fYour Kills: §a$kills";

                if (!empty(Main::$instance->top_kills)) {
                    $lines[] = "§e︱- §6§lTop Killer: ";
                    $lines[] = "§e︱ §l§e#1§r §a" . Main::$instance->top_kills[0] . " §f-§b " . Main::$instance->top_kills[1];
                }
            }
        }
        return $lines;
    }


    public function sendFireworks(Player $p)
    {
        try {
            for ($i = 0; $i < 10; $i++) {
                $firework = new Fireworks();
                $color = [Fireworks::COLOR_RED, Fireworks::COLOR_YELLOW, Fireworks::COLOR_GREEN, Fireworks::COLOR_LIGHT_AQUA, Fireworks::COLOR_BLUE, Fireworks::COLOR_PINK, Fireworks::COLOR_DARK_PINK];
                $firework->addExplosion(Fireworks::TYPE_HUGE_SPHERE, $color[mt_rand(0, 6)]);
                $firework->setFlightDuration(1);
                $pos = new Vector3($p->x + mt_rand(-4, 4), $p->y, $p->z + mt_rand(-4, 4));
                $level = $p->getLevel();
                $nbt = Entity::createBaseNBT($pos, new Vector3(0.001, 0.05, 0.001), lcg_value() * 360, 90);
                $entity = Entity::createEntity("FireworksRocket", $level, $nbt, $firework);
                $entity->spawnToAll();
            }
        } catch (\Throwable $th) {}
    }

    public function commandEvent(CommandEvent $event)
    {
        $p = $event->getSender();
        $cmd = explode(" ", $event->getCommand())[0];
        if ($cmd === "zf" && !$p->isOp()) {
            $p->sendMessage("§cYou do not have permission to use this command");
            $event->setCancelled();
        }
        if ($p instanceof Player && !$p->hasPermission("ownagestaff")) {
            if (self::$event_stage === -1 && !in_array($cmd, ["kit", "pv", "enchantmenu", "shop", "echest"])) {
                $p->sendMessage("§cYou cannot run this command before the event has started");
                $event->setCancelled(true);
            }
        }
    }


    public function giveInfectorKit(Player $p)
    {
        $p->getArmorInventory()->clearAll(true);
        $p->getInventory()->clearAll(true);
        $helm = Item::get(Item::GOLD_HELMET);
        $chest = Item::get(Item::GOLD_CHESTPLATE);
        $legs = Item::get(Item::GOLD_LEGGINGS);
        $boots = Item::get(Item::GOLD_BOOTS);
        foreach ([$helm, $chest, $legs, $boots] as $item) {
            /** @var Item $item */
            $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("protection"), 1));
            $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("unbreaking"), 5));
        }
        $axe = Item::get(Item::GOLDEN_HOE);
        $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("unbreaking"), 10));
        $axe->setCustomName("§r§2Infectors §aWand");
        $axe->setLore(["This item is cosmetic and has no purpose whatsoever"]);
        $armorinv = $p->getArmorInventory();
        $armorinv->setHelmet($helm);
        $armorinv->setChestplate($chest);
        $armorinv->setLeggings($legs);
        $armorinv->setBoots($boots);
        $inv = $p->getInventory();
        $inv->addItem($axe);
        $inv->addItem(Item::get(Item::GOLDEN_APPLE, 0, 5));
        $p->addEffect(new EffectInstance(Effect::getEffect(Effect::POISON), 99999, 0, true));
        $p->addEffect(new EffectInstance(Effect::getEffect(Effect::SPEED), 99999, 0, true));
    }

    public function giveSurvivorKit(Player $p)
    {
        $p->getArmorInventory()->clearAll(true);
        $p->getInventory()->clearAll(true);
        $helm = Item::get(Item::DIAMOND_HELMET);
        $chest = Item::get(Item::DIAMOND_CHESTPLATE);
        $legs = Item::get(Item::DIAMOND_LEGGINGS);
        $boots = Item::get(Item::DIAMOND_BOOTS);
        $axe = Item::get(Item::DIAMOND_SWORD);
        $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("unbreaking"), 10));
        $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("sharpness"), 3));
        $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("knockback"), 1));
        $axe->setCustomName("§r§aSurvivors §aShank");
        $axe->setLore(["Use this to kill Infectors"]);
        $speed = Item::get(Item::NETHER_STAR);
        $speed->addEnchantment(new EnchantmentInstance(new Enchantment(255, "", Enchantment::RARITY_COMMON, Enchantment::SLOT_ALL, Enchantment::SLOT_NONE, 1)));
        $speed->setCustomName("§r§fTap for §bSpeed II");
        $armorinv = $p->getArmorInventory();
        $armorinv->setHelmet($helm);
        $armorinv->setChestplate($chest);
        $armorinv->setLeggings($legs);
        $armorinv->setBoots($boots);
        $inv = $p->getInventory();
        $inv->addItem($axe);
        $inv->addItem($speed);
        $inv->addItem(ItemFactory::get(Item::BOW));
        $inv->addItem(ItemFactory::get(Item::ARROW, 0, 20));
        $inv->addItem(ItemFactory::get(Item::SNOWBALL, 0, 8));
        $p->addEffect(new EffectInstance(Effect::getEffect(Effect::REGENERATION), 99999, 255, false));
    }


    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() === "ke") {
            if (isset($args[0])) {
                switch ($args[0]) {
                    case "tpall":
                        foreach ($this->getServer()->getOnlinePlayers() as $p){
                            $p->teleport($sender);
                        }
                        break;
                    case "near":
                        self::$near = false;
                        break;
                    case "data":
                        print_r($this->infection_event->infected);
                        print_r($this->infection_event->surviving);
                        print_r($this->infection_event->infections);
                        print_r($this->lms_event->alive);
                        print_r($this->lms_event->kills);
                        break;
                    case "forcelms":
                        $this->endInfectionEvent();
                        break;
                    case "ui":
                        HandlerList::unregisterAll($this->infection_event);
                        break;
                    case "ul":
                        HandlerList::unregisterAll($this->lms_event);
                        break;
                    case "inf":
                        $this->infection_event = new InfectionEventHandler($this);
                        $this->getServer()->getPluginManager()->registerEvents($this->infection_event, $this);
                        break;
                    case "lms":
                        $this->lms_event = new LMSEventHandler($this);
                        $this->getServer()->getPluginManager()->registerEvents($this->lms_event, $this);
                        break;
                }
            }
        }
        return true;
    }

    public static function execAll(callable $callback, $tick = 1)
    {
        self::$instance->getScheduler()->scheduleRepeatingTask(new PlayerExecTask($callback), $tick);
    }

    public function onDisable(): void
    {
        $data = [];
        $inf = Main::$instance->infection_event;
        $lms = Main::$instance->lms_event;
        $data["infection"] = [
            $inf->surviving,
            $inf->infections,
            $inf->infected,
        ];
        $data["lms"] = [
            $lms->cooldown,
            $lms->cooldowns,
            $lms->kills,
            $lms->alive,
        ];
        $data["event"] = Main::$event;
        $data["heartbeat"] = Main::$heartbeat;
        $data["top_inf"] = Main::$instance->top_infections;
        $data["top_kills"] = Main::$instance->top_kills;
        file_put_contents($this->getDataFolder() . "cache.json", json_encode($data));
        $this->getLogger()->info("Bye");
    }
}
