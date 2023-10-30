<?php

declare(strict_types=1);

namespace MIN\Exchange;

use MIN\Exchange\Command\ExchangeCommand;
use MIN\Exchange\Entity\ExchangeEntity;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use function base64_decode;
use function base64_encode;
use function unlink;

final class Exchange extends PluginBase implements Listener
{
	use SingletonTrait;

	protected function onLoad(): void
	{
		self::setInstance($this);
	}

	protected function onEnable(): void
	{
		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
		EntityFactory::getInstance()->register(ExchangeEntity::class, static function(World $world, CompoundTag $nbt): ExchangeEntity {
			return new ExchangeEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ['ExchangeEntity']);
		$this->getServer()->getCommandMap()->register('exchange', new ExchangeCommand());
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function existsExchange(string $name): bool
	{
		return @file_exists($this->getDataFolder().$name.'.yml');
	}

	public function removeExchange(string $name): void
	{
		@unlink($this->getDataFolder().$name.'.yml');
	}

	public static function ItemDataSerialize(Item $data): string
	{
		return base64_encode((new BigEndianNbtSerializer())->write(new TreeRoot($data->nbtSerialize())));
	}

	public static function ItemDataDeserialize(string $data): Item
	{
		return Item::nbtDeserialize((new BigEndianNbtSerializer())->read(base64_decode($data))->mustGetCompoundTag());
	}
}