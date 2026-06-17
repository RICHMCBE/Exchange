<?php

declare(strict_types=1);

namespace MIN\Exchange\Menu;

use kim\present\koritemname\KorItemName;
use kim\present\loader\invmenu\customsized\CustomSizedInvMenuHelper;
use naeng\MailCore\data\MailInfo;
use naeng\MailCore\MailCore;
use MIN\Exchange\Entity\ExchangeEntity;
use MIN\Exchange\Exchange;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

final class ExchangeItemMenu
{
	public const array DISPLAY_SLOTS = [3, 4, 5, 12, 13, 14, 21, 22, 23];

	public static function open(Player $player, ExchangeEntity $entity, int $categoryIndex): void
	{
		$categories = $entity->getCategories();
		if (!isset($categories[$categoryIndex])) return;

		$category = $categories[$categoryIndex];
		$items = array_values($category['items'] ?? []);

		$menu = CustomSizedInvMenuHelper::createMenu(27);
		$menu->setName('§c§1§b' . $category['name']);

		$inv = $menu->getInventory();

		foreach (self::DISPLAY_SLOTS as $i => $slot) {
			if (!isset($items[$i])) break;
			$itemData = $items[$i];
			$result = Exchange::ItemDataDeserialize($itemData['result']);

			$cost1 = $itemData['cost1'] !== null ? Exchange::ItemDataDeserialize($itemData['cost1']) : null;
			$cost2 = $itemData['cost2'] !== null ? Exchange::ItemDataDeserialize($itemData['cost2']) : null;

			$costParts = [];
			if ($cost1 !== null) {
				$color = $player->getInventory()->contains($cost1) ? '§a' : '§c';
				$costParts[] = $color . KorItemName::translate($cost1) . ' ' . $cost1->getCount() . '개';
			}
			if ($cost2 !== null) {
				$color = $player->getInventory()->contains($cost2) ? '§a' : '§c';
				$costParts[] = $color . KorItemName::translate($cost2) . ' ' . $cost2->getCount() . '개';
			}
			if (empty($costParts)) {
				$costParts[] = '§b무료';
			}

			$displayItem = clone $result;
			$displayItem->setCustomName('§r§f' . KorItemName::translate($result) . ' §7' . $result->getCount() . '개');
			$displayItem->setLore(['§r§7비용: ' . implode(', ', $costParts)]);
			$inv->setItem($slot, $displayItem);
		}

		$menu->setListener(function (InvMenuTransaction $tr) use ($items): InvMenuTransactionResult {
			$slot = $tr->getAction()->getSlot();
			$slotIndex = array_search($slot, self::DISPLAY_SLOTS, true);

			if ($slotIndex === false || !isset($items[$slotIndex])) {
				return $tr->discard();
			}

			self::processExchange($tr->getPlayer(), $items[$slotIndex]);
			return $tr->discard();
		});

		$menu->send($player);
	}

	private static function processExchange(Player $player, array $itemData): void
	{
		$cost1 = $itemData['cost1'] !== null ? Exchange::ItemDataDeserialize($itemData['cost1']) : VanillaItems::AIR();
		$cost2 = $itemData['cost2'] !== null ? Exchange::ItemDataDeserialize($itemData['cost2']) : VanillaItems::AIR();
		$result = Exchange::ItemDataDeserialize($itemData['result']);

		if (!$cost1->isNull() && !$player->getInventory()->contains($cost1)) {
			$player->sendMessage('§r下 아이템이 없어서 거래가 성립되지 않았습니다.');
			return;
		}
		if (!$cost2->isNull() && !$player->getInventory()->contains($cost2)) {
			$player->sendMessage('§r下 아이템이 없어서 거래가 성립되지 않았습니다.');
			return;
		}

		if (!$cost1->isNull()) $player->getInventory()->removeItem($cost1);
		if (!$cost2->isNull()) $player->getInventory()->removeItem($cost2);

		$leftover = $player->getInventory()->addItem($result);
		if (!empty($leftover)) {
			if (class_exists(MailCore::class)) {
				$mailInfo = new MailInfo(
					null,
					(int) $player->getXuid(),
					'§M§C§E교환상점 아이템 지급',
					'인벤토리가 가득 차 교환 결과물을 메일로 발송합니다.',
					0,
					array_values($leftover)
				);
				Await::f2c(fn() => MailCore::getInstance()->send($mailInfo));
				$player->sendMessage('§r丌 거래가 성립되었습니다.');
				$player->sendMessage('§r不 인벤토리가 가득 차 결과물이 메일로 전송되었습니다.');
			} else {
				if (!$cost1->isNull()) $player->getInventory()->addItem($cost1);
				if (!$cost2->isNull()) $player->getInventory()->addItem($cost2);
				$player->sendMessage('§r下 인벤토리가 가득 차 거래가 성립되지 않았습니다.');
			}
			return;
		}

		$player->sendMessage('§r丌 거래가 성립되었습니다.');
	}
}
