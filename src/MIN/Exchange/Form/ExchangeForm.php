<?php

declare(strict_types=1);

namespace MIN\Exchange\Form;

use MIN\Exchange\Entity\ExchangeEntity;
use MIN\Exchange\Exchange;
use pocketmine\form\Form;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use ryun42680\richdesign\Design;
use function trim;

final class ExchangeForm implements Form
{
	public function jsonSerialize(): array
	{
		return [
			'type' => 'custom_form',
			'title' => '§lCREATE EXCHANGE',
			'content' => [
				[
					'type' => 'input',
					'text' => Design::FORM_TEXT.'만드실 상점 이름을 입력해주세요'
				]
			]
		];
	}

	public function handleResponse(Player $player, $data): void
	{
		if($data === null) return;
		$name = trim($data[0]);
		if($name === '') {
			$player->sendMessage(Design::$prefix_2.'상점 이름을 입력해주세요');
			return;
		}
		if(Exchange::getInstance()->existsExchange($name)) {
			$player->sendMessage(Design::$prefix_2.'이미 존재하는 상점입니다. 다른 이름을 입력해주세요');
			return;
		}
		$nbt = CompoundTag::create()
			->setString('name', $name);
		$entity = new ExchangeEntity($player->getLocation(), $nbt);
		$entity->spawnToAll();
		$player->sendMessage(Design::$prefix_2.'상점을 생성하였습니다');
	}
}