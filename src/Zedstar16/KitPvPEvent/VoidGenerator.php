<?php


namespace Zedstar16\KitPvPEvent;


use pocketmine\block\BlockIds;
use pocketmine\level\ChunkManager;
use pocketmine\level\format\Chunk;
use pocketmine\level\generator\Generator;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;

class VoidGenerator extends Generator
{

    /** @var ChunkManager */
    protected $level;
    /** @var Random */
    protected $random;

    /** @phpstan-ignore-next-line */
    public function __construct(array $settings = [])
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "void";
    }

    public function init(ChunkManager $level, Random $random): void
    {
        $this->level = $level;
        $this->random = $random;
    }

    public function generateChunk(int $chunkX, int $chunkZ): void
    {
        /** @phpstan-var Chunk $chunk */
        $chunk = $this->level->getChunk($chunkX, $chunkZ);

        if ($chunkX == 16 && $chunkZ == 16) {
            $chunk->setBlockId(0, 64, 0, BlockIds::GRASS);
        }
    }

    public function getSpawn(): Vector3
    {
        return new Vector3(256, 65, 256);
    }

    public function populateChunk(int $chunkX, int $chunkZ): void
    {
    }

    public function getSettings(): array
    {
        return [];
    }
}