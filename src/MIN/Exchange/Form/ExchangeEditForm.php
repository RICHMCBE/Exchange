<?php

declare(strict_types=1);

namespace MIN\Exchange\Form;

use MIN\Exchange\Entity\ExchangeEntity;
use MIN\Exchange\Exchange;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\form\Form;
use pocketmine\inventory\Inventory;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use ryun42680\richdesign\Design;

final class ExchangeEditForm implements Form
{
	private ExchangeEntity $entity;

	public function __construct(ExchangeEntity $entity)
	{
		$this->entity = $entity;
	}

	public function jsonSerialize(): array
	{
		return [
			'type' => 'form',
			'title' => '§lEDIT EXCHANGE',
			'content' => Design::FORM_TEXT.'하실 작업을 선택해주세요',
			'buttons' => [
				['text' => Design::FORM_TEXT.'상품 추가'],
				['text' => Design::FORM_TEXT.'상품 수정'],
			]
		];
	}

	public function handleResponse(Player $player, $data): void
	{
		$entity = $this->entity;
		$sign = LegacyStringToItemParser::getInstance()->parse('160:4');
		if($data === null) return;
		if($data === 0) {
			$inv = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
			for($i = 0; $i < 27; $i++) {
				$inv->getInventory()->setItem($i, $sign);
			}
			$inv->getInventory()->setItem(2, VanillaItems::OAK_SIGN()->setCustomName('§r§a조건 아이템'));
			$inv->getInventory()->setItem(11, VanillaItems::AIR());
			$inv->getInventory()->setItem(12, VanillaItems::AIR());
			$inv->getInventory()->setItem(6, VanillaItems::OAK_SIGN()->setCustomName('§r§a결과 아이템'));
			$inv->getInventory()->setItem(15, VanillaItems::AIR());
			$inv->getInventory()->setItem(17,
				LegacyStringToItemParser::getInstance()->parse('wool:5')
					->setCustomName('§r§a상품 추가하기')
			);

			$inv->setListener(function(InvMenuTransaction $transaction) use ($entity): InvMenuTransactionResult {
				$player = $transaction->getPlayer();
				$slot = $transaction->getAction()->getSlot();
				$inv = $transaction->getAction()->getInventory();
				if($slot !== 11 && $slot !== 12 && $slot !== 15) {
					if($slot === 17) {
						$cost1 = $inv->getItem(11);
						$cost2 = $inv->getItem(12);
						$result = $inv->getItem(15);
						$player->removeCurrentWindow();
						$player->getInventory()->addItem($cost1, $cost2, $result);
						if($result->isNull()) {
							$player->sendMessage(Design::$prefix_2.'§c결과 아이템칸을 채워주세요');
							return $transaction->discard();
						}
						$player->sendForm(new ExchangeTextureInputForm($entity, $cost1, $cost2, $result));
						return $transaction->discard();
					}
					return $transaction->discard();
				}
				return $transaction->continue();
			});
			$inv->setInventoryCloseListener(function(Player $player, Inventory $inventory): void {
			});
			$inv->setName('ADD EXCHANGE');
		} else {
			$inv = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
			$items = [];
			foreach($entity->data as $item) {
				$items[] = Exchange::ItemDataDeserialize($item['result']);
			}
			for($i = 0; $i < 27; $i += 2) {
				$inv->getInventory()->setItem($i, $items[$i / 2] ?? VanillaItems::AIR());
			}
			$inv->setListener(function(InvMenuTransaction $transaction) use ($entity): InvMenuTransactionResult {
				$player = $transaction->getPlayer();
				$slot1 = $transaction->getAction()->getSlot();
				if($transaction->getAction()->getInventory()->getItem($slot1)->isNull()) return $transaction->discard();
				$itemData = $entity->data[$slot1 / 2] ?? null;
				if($itemData === null) return $transaction->discard();
				$cost1 = $itemData['cost1'] !== null ? Exchange::ItemDataDeserialize($itemData['cost1']) : VanillaItems::AIR();
				$cost2 = $itemData['cost2'] !== null ? Exchange::ItemDataDeserialize($itemData['cost2']) : VanillaItems::AIR();
				$result = Exchange::ItemDataDeserialize($itemData['result']);
				$player->removeCurrentWindow();
				return $transaction->discard()->then(function(Player $player) use ($cost1, $cost2, $result, $entity, $slot1): void {
					$inv2 = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
					$inv2_inv = $inv2->getInventory();
					for($i = 0; $i < 27; $i++) {
						$inv2_inv->setItem($i, VanillaItems::AIR());
					}
					$inv2_inv->setItem(2, LegacyStringToItemParser::getInstance()->parse('wool:5')
						->setCustomName('§r§a갯수 증가'));
					$inv2_inv->setItem(11, $cost1);
					$inv2_inv->setItem(20, LegacyStringToItemParser::getInstance()->parse('wool:14')
						->setCustomName('§r§c갯수 감소'));

					$inv2_inv->setItem(3, LegacyStringToItemParser::getInstance()->parse('wool:5')
						->setCustomName('§r§a갯수 증가'));
					$inv2_inv->setItem(12, $cost2);
					$inv2_inv->setItem(21, LegacyStringToItemParser::getInstance()->parse('wool:14')
						->setCustomName('§r§c갯수 감소'));

					$inv2_inv->setItem(6, LegacyStringToItemParser::getInstance()->parse('wool:5')
						->setCustomName('§r§a갯수 증가'));
					$inv2_inv->setItem(15, $result);
					$inv2_inv->setItem(24, LegacyStringToItemParser::getInstance()->parse('wool:14')
						->setCustomName('§r§c갯수 감소'));
					$inv2_inv->setItem(17, LegacyStringToItemParser::getInstance()->parse('wool:5')
						->setCustomName('§r§a상품 수정하기'));
					$inv2_inv->setItem(26, LegacyStringToItemParser::getInstance()->parse('minecraft:barrier')
						->setCustomName('§r§c상품 삭제하기'));
					$inv2->setListener(function(InvMenuTransaction $transaction) use ($entity, $slot1): InvMenuTransactionResult {
						$player = $transaction->getPlayer();
						$slot = $transaction->getAction()->getSlot();
						$inv = $transaction->getAction()->getInventory();
						$cost1 = $inv->getItem(11);
						$cost2 = $inv->getItem(12);
						$result = $inv->getItem(15);
						if($slot === 2) {
							if($cost1->getCount() < $cost1->getMaxStackSize()) {
								$inv->setItem(11, $cost1->setCount($cost1->getCount() + 1));
								return $transaction->discard();
							}
						}
						if($slot === 20) {
							if($cost1->getCount() > 1) {
								$inv->setItem(11, $cost1->setCount($cost1->getCount() - 1));
								return $transaction->discard();
							}
						}
						if($slot === 3) {
							if($cost2->getCount() < $cost2->getMaxStackSize()) {
								$inv->setItem(12, $cost2->setCount($cost2->getCount() + 1));
								return $transaction->discard();
							}
						}
						if($slot === 21) {
							if($cost2->getCount() > 1) {
								$inv->setItem(12, $cost2->setCount($cost2->getCount() - 1));
								return $transaction->discard();
							}
						}
						if($slot === 6) {
							if($result->getCount() < $result->getMaxStackSize()) {
								$inv->setItem(15, $result->setCount($result->getCount() + 1));
								return $transaction->discard();
							}
						}
						if($slot === 24) {
							if($result->getCount() > 1) {
								$inv->setItem(15, $result->setCount($result->getCount() - 1));
								return $transaction->discard();
							}
						}
						if($slot === 17) {
							$cost1 = $inv->getItem(11);
							$cost2 = $inv->getItem(12);
							$result = $inv->getItem(15);
							$transaction->getPlayer()->removeCurrentWindow();
							$entity->editItem($slot1 / 2, $cost1, $cost2, $result);
							$player->sendMessage(Design::$prefix_2.'§a상품을 수정하였습니다');
							return $transaction->discard();
						}
						if($slot === 26) {
							$transaction->getPlayer()->removeCurrentWindow();
							$entity->removeItem($slot1 / 2);
							$player->sendMessage(Design::$prefix_2.'§a상품을 삭제하였습니다');
							return $transaction->discard();
						}
						return $transaction->discard();
					});
					$inv2->setName('EDIT EXCHANGE');
					$inv2->send($player);
				});
			});
			$inv->setName('EDIT EXCHANGE');
		}
		$inv->send($player);
	}
}