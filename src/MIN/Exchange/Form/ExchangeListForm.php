<?php

declare(strict_types=1);

namespace MIN\Exchange\Form;

use MIN\Exchange\Entity\ExchangeEntity;
use MIN\Exchange\Exchange;
use pocketmine\form\Form;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use ryun42680\richdesign\Design;

final readonly class ExchangeListForm implements Form
{

	public function __construct(
		private Player         $player,
		private ExchangeEntity $entity
	)
	{
	}

	public function jsonSerialize(): array
	{
		$buttons = [];
		foreach($this->entity->data as $itemData) {
			$cost1 = $itemData['cost1'] !== null ? Exchange::ItemDataDeserialize($itemData['cost1']) : null;
			$cost2 = $itemData['cost2'] !== null ? Exchange::ItemDataDeserialize($itemData['cost2']) : null;
			$result = Exchange::ItemDataDeserialize($itemData['result']);
			$text = [];
			if($cost1 !== null) {
				$color = $this->player->getInventory()->contains($cost1) ? 'a' : 'c';
				$text[] = "§$color {$cost1->getName()} {$cost1->getCount()}개";
			}
			if($cost2 !== null) {
				$color = $this->player->getInventory()->contains($cost2) ? 'a' : 'c';
				$text[] = "§$color {$cost2->getName()} {$cost2->getCount()}개";
			}
			if($cost1 === null && $cost2 === null) {
				$text[] = '§a무료';
			}
			$text = implode(', ', $text);
			$buttons[] = [
				'text' => "{$result->getName()}\n" .$text,
				'image' => [
					'type' => 'path',
					'data' => $itemData['texture']
				]
			];
		}
		return [
			'type' => 'form',
			'title' => 'menu1',
			'content' => Design::FORM_TEXT.'거래하실 상품을 선택해주세요',
			'buttons' => $buttons
		];
	}

	public function handleResponse(Player $player, $data): void
	{
		if($data === null) return;
		$itemData = $this->entity->data[$data];
		$cost1 = $itemData['cost1'] !== null ? Exchange::ItemDataDeserialize($itemData['cost1']) : VanillaItems::AIR();
		$cost2 = $itemData['cost2'] !== null ? Exchange::ItemDataDeserialize($itemData['cost2']) : VanillaItems::AIR();

		$result = Exchange::ItemDataDeserialize($itemData['result']);
		$bool1 = ($itemData['cost1'] === null) || ($player->getInventory()->contains(Exchange::ItemDataDeserialize($itemData['cost1'])));
		if(!$bool1) {
			$player->sendMessage(Design::$prefix_2.'아이템이 없어서 거래가 성립되지 않았습니다');
			return;
		}
		$player->getInventory()->removeItem($cost1);
		$bool2 = ($itemData['cost2'] === null) || ($player->getInventory()->contains(Exchange::ItemDataDeserialize($itemData['cost2'])));
		if(!$bool2) {
			$player->sendMessage(Design::$prefix_2.'아이템이 없어서 거래가 성립되지 않았습니다');
			$player->getInventory()->addItem($cost1);
			return;
		}
		$player->getInventory()->removeItem($cost2);
		$player->getInventory()->addItem($result);
		$player->sendMessage(Design::$prefix_2.'거래가 성립되었습니다');
	}
}