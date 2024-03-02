<?php

declare(strict_types=1);

namespace MIN\Exchange\Entity;

use MIN\Exchange\Exchange;
use MIN\Exchange\Form\ExchangeEditForm;
use MIN\Exchange\Form\ExchangeListForm;
use pocketmine\entity\Location;
use pocketmine\entity\Villager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use function in_array;

final class ExchangeEntity extends Villager
{
	private string $exchangeName;

	private Config $config;

	public array $data = [];

	public function __construct(Location $location, ?CompoundTag $nbt = null)
	{
		parent::__construct($location, $nbt);
		$this->exchangeName = $nbt->getString('name');
		$this->setNameTag("§l§r§eSHOP\n§r$this->exchangeName");
		$this->config = new Config(Exchange::getInstance()->getDataFolder().$this->exchangeName.'.yml', Config::YAML);
		$this->data = $this->config->getAll();
	}

	public function attack(EntityDamageEvent $source): void
	{
		if(!$source instanceof EntityDamageByEntityEvent) return;
		$player = $source->getDamager();
		if(!$player instanceof Player) return;
		$source->cancel();
		$item = $player->getInventory()->getItemInHand();
		if($player->isSneaking() && $player->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
			if($item->getTypeId() === ItemTypeIds::WOODEN_AXE) {
				$this->kill();
			} else {
				$player->sendForm(new ExchangeEditForm($this));
			}
		} else {
			if(count($this->data) === 0) {
				$player->sendTitle('§l§c!', '§b해당 교환상점은 아직 준비중입니다');
			} else {
				$this->lookAt($player->getPosition()->asVector3()->add(0, 0.75, 0));
				$player->sendForm(new ExchangeListForm($player, $this));
			}
		}
		parent::attack($source);
	}

	protected function onDispose(): void
	{
		if($this->isAlive()) {
			$this->config->setAll($this->data);
			$this->config->save();
		}
		parent::onDispose();
	}

	protected function onDeath(): void
	{
		Exchange::getInstance()->removeExchange($this->exchangeName);
		parent::onDeath();
	}

	public function saveNBT(): CompoundTag
	{
		$nbt = parent::saveNBT();
		$nbt->setString('name', $this->exchangeName);
		return $nbt;
	}

	public function addItem(Item $cost1, Item $cost2, Item $result, string $texture): void
	{
		$this->data[] = [
			'cost1' => $cost1->isNull() || ($cost1->getTypeId() === VanillaItems::AIR()->getTypeId()) ? null :Exchange::ItemDataSerialize($cost1),
			'cost2' => $cost2->isNull() ? null:Exchange::ItemDataSerialize($cost2),
			'result' => Exchange::ItemDataSerialize($result),
			'texture' => $texture
		];
	}

	public function editItem(int $index, Item $cost1, Item $cost2, Item $result): void
	{
		$this->data[$index]['cost1'] = $cost1->isNull() ? null :Exchange::ItemDataSerialize($cost1);
		$this->data[$index]['cost2'] = $cost2->isNull() ? null:Exchange::ItemDataSerialize($cost2);
		$this->data[$index]['result'] = Exchange::ItemDataSerialize($result);
	}

	public function removeItem(int $index): void
	{
		unset($this->data[$index]);
		$this->data = array_values($this->data);
	}
}