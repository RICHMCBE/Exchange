<?php

declare(strict_types=1);

namespace MIN\Exchange\Entity;

use MIN\Exchange\Exchange;
use MIN\Exchange\Form\ExchangeEditForm;
use MIN\Exchange\Menu\ExchangeCategoryMenu;
use pocketmine\entity\Location;
use pocketmine\entity\Villager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use function array_values;
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
		$this->migrateData();
		$this->setNameTagVisible();
		$this->setNameTagAlwaysVisible();
	}

	private function migrateData(): void
	{
		foreach ($this->data as &$category) {
			if (!is_array($category) || !isset($category['items'])) continue;
			foreach ($category['items'] as &$item) {
				if (isset($item['costs'])) continue;
				$costs = [];
				if (!empty($item['cost1'])) $costs[] = $item['cost1'];
				if (!empty($item['cost2'])) $costs[] = $item['cost2'];
				$item['costs'] = $costs;
				unset($item['cost1'], $item['cost2']);
			}
			unset($item);
		}
		unset($category);
	}

	public function getExchangeName(): string
	{
		return $this->exchangeName;
	}

	public function getCategories(): array
	{
		$this->migrateData();
		return $this->data;
	}

	public function addCategory(string $name): void
	{
		$this->data[] = [
			'name' => $name,
			'icon' => null,
			'items' => []
		];
	}

	public function removeCategory(int $index): void
	{
		unset($this->data[$index]);
		$this->data = array_values($this->data);
	}

	/** @param Item[] $costs */
	public function addItemToCategory(int $catIdx, array $costs, Item $result, string $texture): void
	{
		$serialized = [];
		foreach ($costs as $cost) {
			if (!$cost->isNull()) {
				$serialized[] = Exchange::ItemDataSerialize($cost);
			}
		}
		$this->data[$catIdx]['items'][] = [
			'costs' => $serialized,
			'result' => Exchange::ItemDataSerialize($result),
			'texture' => $texture
		];
	}

	/** @param Item[] $costs */
	public function editItemInCategory(int $catIdx, int $itemIdx, array $costs, Item $result): void
	{
		$serialized = [];
		foreach ($costs as $cost) {
			if (!$cost->isNull()) {
				$serialized[] = Exchange::ItemDataSerialize($cost);
			}
		}
		$this->data[$catIdx]['items'][$itemIdx]['costs'] = $serialized;
		$this->data[$catIdx]['items'][$itemIdx]['result'] = Exchange::ItemDataSerialize($result);
	}

	public function saveConfig(): void
	{
		$this->config->setAll($this->data);
		$this->config->save();
	}

	public function setCategoryIcon(int $catIdx, ?Item $icon): void
	{
		if (!isset($this->data[$catIdx])) return;
		$this->data[$catIdx]['icon'] = $icon !== null ? Exchange::ItemDataSerialize($icon) : null;
	}

	public function removeItemFromCategory(int $catIdx, int $itemIdx): void
	{
		unset($this->data[$catIdx]['items'][$itemIdx]);
		$this->data[$catIdx]['items'] = array_values($this->data[$catIdx]['items']);
	}

	/**
	 * $newOrder = 기존 카테고리 인덱스 배열 (새 순서대로)
	 * 예: [2, 0, 1] → 기존 2번이 0번, 0번이 1번, 1번이 2번으로
	 */
	/**
	 * @param int[]   $newOrder    새 순서의 기존 인덱스 배열
	 * @param array[] $customIcons catIdx => 직렬화된 아이콘 아이템 (선택)
	 */
	public function reorderCategories(array $newOrder, array $customIcons = []): void
	{
		$oldData = $this->data;
		$newData = [];
		foreach ($newOrder as $oldIdx) {
			if (!isset($oldData[$oldIdx])) continue;
			$category = $oldData[$oldIdx];
			if (isset($customIcons[$oldIdx])) {
				$category['icon'] = $customIcons[$oldIdx];
			}
			$newData[] = $category;
		}
		$this->data = array_values($newData);
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
				ExchangeCategoryMenu::open($player, $this);
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
}
