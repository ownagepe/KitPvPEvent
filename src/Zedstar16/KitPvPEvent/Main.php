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
use pocketmine\level\Location;
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


    public $top_infections = [];

    public $round = 1;

    public static $near = true;
    public static $sex = true;

    public static $bow_cooldown = [];

    public $spawn_positions = [];

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
        $this->getServer()->loadLevel("MuseHalloween");
        $this->spawn_positions = json_decode('[{"x":-36.5554,"y":68.36139999999999,"z":-45.4719},{"x":-45.548,"y":68.36139999999999,"z":-67.3811},{"x":-28.292,"y":68.36139999999999,"z":-65.2055},{"x":-10.7019,"y":64.5114,"z":-51.4709},{"x":16.0118,"y":64.5114,"z":-54.1717},{"x":33.9849,"y":64.5114,"z":-57.2046},{"x":41.8925,"y":59.124700000000004,"z":-78.1307},{"x":24.8375,"y":54.285700000000006,"z":-86.3737},{"x":23.9983,"y":54.2856,"z":-111.5683},{"x":2.9093,"y":54.2856,"z":-129.5989},{"x":-0.2688,"y":54.2856,"z":-148.7519},{"x":29.2257,"y":54.2856,"z":-153.0029},{"x":49.4043,"y":49.3365,"z":-165.1891},{"x":64.6945,"y":49.3365,"z":-186.6037},{"x":89.6092,"y":49.3365,"z":-204.1118},{"x":102.9979,"y":49.3365,"z":-171.2075},{"x":90.827,"y":49.3365,"z":-151.2166},{"x":57.0355,"y":50.836400000000005,"z":-110.2814},{"x":45.7857,"y":50.836400000000005,"z":-91.1212},{"x":18.7678,"y":50.836400000000005,"z":-57.8285},{"x":8.0394,"y":51.961400000000005,"z":-29.2218},{"x":-19.3143,"y":54.1513,"z":-19.6737},{"x":-34.8052,"y":50.3018,"z":-46.0608},{"x":-50.5132,"y":54.1556,"z":-65.9511},{"x":-17.2844,"y":51.4698,"z":-48.7684},{"x":-7.9648,"y":51.4698,"z":-72.1828},{"x":-10.5274,"y":43.845600000000005,"z":-108.7723},{"x":5.2539,"y":43.845600000000005,"z":-128.1435},{"x":36.872,"y":43.845600000000005,"z":-119.7206},{"x":-6.21,"y":68.4198,"z":-126.5214}]', true);
        $this->infection_event = new InfectionEventHandler($this);
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
                    $this->getServer()->getLevelByName("MuseHalloween")->setTime(14000);
                    $t_remaining = Main::WAIT_TIME - Main::$heartbeat;
                    if(($t_remaining <= 10) && ($t_remaining > 0)){
                        foreach (Server::getInstance()->getOnlinePlayers() as $p) {
                            $p->sendTitle("§9Round §b#".Main::$instance->round, "§2Starts In §a$t_remaining secs");
                        }
                    }
                    if (Main::$heartbeat === Main::WAIT_TIME) {
                        Main::$event_stage = Main::STAGE_RUNNING;

                        if (Main::$instance->getServer()->getLevelByName("MuseHalloween") === null) {
                            Main::$instance->getServer()->loadLevel("MuseHalloween");
                        }
                        $level = Server::getInstance()->getLevelByName("MuseHalloween");
                        $players = Server::getInstance()->getOnlinePlayers();
                        shuffle($players);
                        $infectors = [];
                        Main::$instance->infection_event->infected = [];
                        Main::$instance->infection_event->hits = [];
                        Main::$instance->infection_event->surviving = [];
                        Main::$instance->infection_event->infections = [];
                        Main::$instance->top_infections = [];
                        foreach ($players as $key => $p) {
                            $p->setSpawn(Main::$instance->getRandomSpawnPos());
                            if (in_array($key, [0, 1])) {
                                $infectors[] = $p->getName();
                                Main::$instance->giveInfectorKit($p);
                                Main::$instance->infection_event->infected[$p->getName()] = $p->getName();
                            } else {
                                Main::$instance->infection_event->surviving[$p->getName()] = $p->getName();
                                Main::$instance->giveSurvivorKit($p);
                            }
                            $p->teleport(Main::$instance->getRandomSpawnPos());
                        }
                        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                            $player->sendTitle("§2The Infectors Are:", "§a".implode(", ", $infectors));
                        }
                    }
                    if (in_array(Main::$event_stage, [Main::STAGE_RUNNING, Main::STAGE_OVER])) {
                        if (Main::$near) {
                            Main::execAll(function ($player) {
                                $player->setFood(20);
                                if (Main::$instance->infection_event->isInfector($player)) {
                                    $nearest = [];
                                    foreach (Main::$instance->infection_event->surviving as $user) {
                                        $p = Server::getInstance()->getPlayer($user);
                                        $nearest[$p->getName()] = $p->distance($player);
                                    }
                                    if (!empty($nearest)) {
                                        asort($nearest);
                                        $usrname = array_keys($nearest)[0];
                                        $player->sendTip("§aNearest Survivor: §f{$usrname} §6(" . round($nearest[$usrname]) . "m)");
                                    }
                                }
                            });
                        }
                        if(!empty(Main::$instance->infection_event->infections)) {
                            arsort(Main::$instance->infection_event->infections);
                            $infecs = Main::$instance->infection_event->infections;
                            $keys = array_keys($infecs);
                            for($i = 0; $i < 3; $i++){
                                if(isset($keys[$i])){
                                    Main::$instance->top_infections[$i] = [$keys[$i], $infecs[$keys[$i]]];
                                }
                            }
                        }
                    }
                }
            }), 20);

    }

    public function getRandomSpawnPos() : Position{
        $pos = $this->spawn_positions[mt_rand(0, 28)];
        return new Position($pos["x"], $pos["y"], $pos["z"], $this->getServer()->getLevelByName("MuseHalloween"));
    }

    public function endInfectionEvent()
    {
        self::$event_stage = self::STAGE_OVER;
        if($this->round === 3){
            foreach (Server::getInstance()->getOnlinePlayers() as $p) {
                $p->sendTitle("§6Event Over!", "§bThank you for participating");
            }
            return;
        }
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick): void {
            Main::$heartbeat = 285;
            $this->round++;
            Main::$event_stage = Main::STAGE_INIT;
            Main::execAll(function ($player) {
                /** @var Player $player */
                $player->getInventory()->clearAll(true);
                $player->getArmorInventory()->clearAll(true);
                $player->getCursorInventory()->clearAll(true);
                $player->getCraftingGrid()->clearAll(true);
                $player->removeAllEffects();
                $player->teleport(Main::$instance->getRandomSpawnPos());
            });
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
            "§2Zombie Infection Event",
            "§5How to play:",
            "§4- §cAfter the event timer reaches §60:00§c you will all be teleported to the event arena",
            "§4- §cYou will §4not §cbe able to use any of your kitpvp items during the infection event",
            "§4- §cAll §6Survivors§c will have a §3Diamond§c set",
            "§4- §cTeaming §2Allowed§c",

        ];
        $p1 = ["§4- §cAll §2Infectors§c will have a §6Gold§c armor set & zombie head",
            "§4- §cInfectors can be killed, they will respawn at a random position",
            "§4- §cSurvivors can only be Infected, not killed",
            "§4- §cTo infect a player, hit them §38 times",
            "§4- §cInfectors have constant §9Speed I",
            "§4- §cSurvivors can get 10s of §9Speed III§c every 20s",];
        $item->setPageText(0, implode("\n", $p0));
        $item->setPageText(1, implode("\n", $p1));
        $p->getInventory()->addItem($item);
    }

    public static function updateScoreboard(Player $player)
    {
        ScoreFactory::setScore($player, "§l§6Ownage §eEvent");
        foreach (self::getScoreboardLines($player) as $line_number => $line) {
            ScoreFactory::setScoreLine($player, $line_number + 1, $line);
        }
    }

    public static function getScoreboardLines(Player $player)
    {
        $t = explode(":", gmdate("i:s", self::WAIT_TIME - self::$heartbeat));
        $online = count(Server::getInstance()->getOnlinePlayers());
        $lines = [
            "§e︱- §6§lGeneral",
            "§e︱ §fOnline §7$online",
        ];
        if (self::$event === self::INFECTION_EVENT) {
            $lines[] = "§e︱- §6§lEvent: §2Infection";
            $lines[] = "§e︱ §fRound: §b" . Main::$instance->round . "/3";
            if (self::$event_stage === self::STAGE_INIT) {
                $lines = array_merge($lines, [
                    "§e︱ §fStarting In §a$t[0]m $t[1]s",
                    "§e︱ §bOpen the book ",
                    "§e︱ §bto view Event Info"
                ]);
            } elseif (in_array(self::$event_stage, [self::STAGE_RUNNING, self::STAGE_OVER])) {
                if (self::$event_stage === self::STAGE_OVER) {
                    if(Main::$instance->round === 3) {
                        $lines[] = "§e︱ §fStatus: §cOver";
                    }else{
                        $lines[] = "§e︱ §fStatus: §6Next Round Starting...";
                    }
                } else {
                    $lines[] = "§e︱ §fSurviving: §a" . count(Main::$instance->infection_event->surviving);
                }
                if (self::$instance->infection_event->isInfector($player)) {
                    $infections = self::$instance->infection_event->infections[$player->getName()] ?? 0;
                    $lines[] = "§e︱ §fYour Infections: §a$infections";
                }
                if (!empty(Main::$instance->top_infections)) {
                    $lines[] = "§e︱- §6§lTop Infectors: ";
                    $lines[] = "§e︱ §l§e#1§r §a" . Main::$instance->top_infections[0][0] . " §f-§b " . Main::$instance->top_infections[0][1];
                    if(isset(Main::$instance->top_infections[1])){
                        $lines[] = "§e︱ §l§f#2§r §a" . Main::$instance->top_infections[1][0] . " §f-§b " . Main::$instance->top_infections[1][1];
                    }
                    if(isset(Main::$instance->top_infections[2])){
                        $lines[] = "§e︱ §l§6#3§r §a" . Main::$instance->top_infections[2][0] . " §f-§b " . Main::$instance->top_infections[2][1];
                    }
                }
            }
        }
        return $lines;
    }


    public function sendFireworks(Player $p)
    {
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
        $helm = Item::get(Item::MOB_HEAD, 2);
        $chest = Item::get(Item::GOLD_CHESTPLATE);
        $legs = Item::get(Item::GOLD_LEGGINGS);
        $boots = Item::get(Item::GOLD_BOOTS);
        foreach ([$helm, $chest, $legs, $boots] as $item) {
            /** @var Item $item */
            $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("protection"), 1));
            $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("unbreaking"), 5));
        }
        $chest->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("protection"), 3));
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
        $bow = Item::get(Item::BOW);
        $bow->setCustomName("§r§2Infectors §aSniper");
        $inv->addItem($bow);
        $hook = Item::get(Item::FISHING_ROD);
        $hook->setCustomName("§r§2Infectors §aGrappler");
        $hook->addEnchantment(new EnchantmentInstance(new Enchantment(255, "", Enchantment::RARITY_COMMON, Enchantment::SLOT_ALL, Enchantment::SLOT_NONE, 1)));
        $inv->addItem($hook);
        $inv->addItem(Item::get(Item::ARROW, 0, 64));
        $inv->addItem(Item::get(Item::GOLDEN_APPLE, 0, 16));
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
        $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName("knockback"), 2));
        $axe->setCustomName("§r§aSurvivors §aShank");
        $axe->setLore(["Use this to kill Infectors"]);
        $speed = Item::get(Item::NETHER_STAR);
        $speed->addEnchantment(new EnchantmentInstance(new Enchantment(255, "", Enchantment::RARITY_COMMON, Enchantment::SLOT_ALL, Enchantment::SLOT_NONE, 1)));
        $speed->setCustomName("§r§fTap for §bSpeed III");
        $armorinv = $p->getArmorInventory();
        $armorinv->setHelmet($helm);
        $armorinv->setChestplate($chest);
        $armorinv->setLeggings($legs);
        $armorinv->setBoots($boots);
        $inv = $p->getInventory();
        $inv->addItem($axe);
        $inv->addItem($speed);
        $inv->addItem(ItemFactory::get(Item::BOW));
        $inv->addItem(ItemFactory::get(Item::ARROW, 0, 64));
        $inv->addItem(ItemFactory::get(Item::SNOWBALL, 0, 16));
        $inv->addItem(ItemFactory::get(Item::SNOWBALL, 0, 16));
        $p->addEffect(new EffectInstance(Effect::getEffect(Effect::REGENERATION), 99999, 255, false));
    }


    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() === "ke") {
            if (isset($args[0])) {
                switch ($args[0]) {
                    case "end":
                        $this->endInfectionEvent();
                        break;
                    case "newround":
                        $this->round++;
                        break;
                    case "heartbeat":
                        self::$heartbeat = (int)$args[1];
                        break;
                    case "stage":
                        self::$event_stage = (int)$args[1];
                        break;
                    case "transferall":
                        foreach ($this->getServer()->getOnlinePlayers() as $p) {
                            $p->transfer($args[1], $args[2]);
                        }
                        break;
                    case "tpall":
                        foreach ($this->getServer()->getOnlinePlayers() as $p) {
                            $p->teleport($sender);
                        }
                        break;
                    case "near":
                        self::$near = (int)$args[1] === 1;
                        break;
                    case "sex":
                        self::$sex = (int)$args[1] === 1;
                        break;
                    case "data":
                        print_r($this->infection_event->infected);
                        print_r($this->infection_event->surviving);
                        print_r($this->infection_event->infections);
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
        $data["infection"] = [
            $inf->surviving,
            $inf->infections,
            $inf->infected,
            $inf->hits
        ];
        $data["event"] = Main::$event;
        $data["heartbeat"] = Main::$heartbeat;
        $data["top_inf"] = Main::$instance->top_infections;
        file_put_contents($this->getDataFolder() . "cache.json", json_encode($data));
        $this->getLogger()->info("Bye");
    }
}
