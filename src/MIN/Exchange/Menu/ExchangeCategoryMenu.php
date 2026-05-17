<?php

declare(strict_types=1);

namespace MIN\Exchange\Menu;

use kim\present\loader\invmenu\customsized\CustomSizedInvMenuHelper;
use MIN\Exchange\Entity\ExchangeEntity;
use MIN\Exchange\Exchange;
use MIN\Exchange\Form\ExchangeListForm; // phpcs:ignore
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\VanillaBlocks;
use pocketmine\player\Player;

final class ExchangeCategoryMenu
{
	public const array DISPLAY_SLOTS = [3, 4, 5, 12, 13, 14, 21, 22, 23];

	public static function open(Player $player, ExchangeEntity $entity): void
	{
		$menu = CustomSizedInvMenuHelper::createMenu(27);
		$menu->setName('§c§1§b' . $entity->getExchangeName());

		$inv = $menu->getInventory();
		$categories = $entity->getCategories();

		foreach (self::DISPLAY_SLOTS as $i => $slot) {
			if (!isset($categories[$i])) break;
			$category = $categories[$i];

			if (!empty($category['icon'])) {
				$icon = Exchange::ItemDataDeserialize($category['icon']);
			} elseif (!empty($category['items'])) {
				$icon = Exchange::ItemDataDeserialize($category['items'][0]['result']);
			} else {
				$icon = VanillaBlocks::CHEST()->asItem();
			}

			$itemCount = count($category['items'] ?? []);
			$icon->setCustomName('§r§e' . $category['name']);
			$icon->setLore(['§r§7상품 ' . $itemCount . '종']);
			$inv->setItem($slot, $icon);
		}

		$menu->setListener(function (InvMenuTransaction $tr) use ($entity): InvMenuTransactionResult {
			$slot = $tr->getAction()->getSlot();
			$slotIndex = array_search($slot, self::DISPLAY_SLOTS, true);

			if ($slotIndex === false) {
				return $tr->discard();
			}

			$categories = $entity->getCategories();
			if (!isset($categories[$slotIndex])) {
				return $tr->discard();
			}

			$player = $tr->getPlayer();
			$player->removeCurrentWindow();
			return $tr->discard()->then(function (Player $player) use ($entity, $slotIndex): void {
				$player->sendForm(new ExchangeListForm($player, $entity, $slotIndex));
			});
		});

		$menu->send($player);
	}
}
