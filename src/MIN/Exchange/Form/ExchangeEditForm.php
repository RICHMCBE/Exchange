<?php

declare(strict_types=1);

namespace MIN\Exchange\Form;

use MIN\Exchange\Entity\ExchangeEntity;
use MIN\Exchange\Exchange;
use MIN\Exchange\Menu\ExchangeCategoryMenu;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use naeng\ItemTexture\ItemTexture;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\form\Form;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use function in_array;

final class ExchangeEditForm implements Form
{
	public function __construct(private readonly ExchangeEntity $entity) {}

	public function jsonSerialize(): array
	{
		return [
			'type' => 'form',
			'title' => '§lEDIT EXCHANGE',
			'content' => '하실 작업을 선택해주세요',
			'buttons' => [
				['text' => '카테고리 추가'],
				['text' => '카테고리 수정/삭제'],
				['text' => '카테고리 배치 편집'],
			]
		];
	}

	public function handleResponse(Player $player, $data): void
	{
		if ($data === null) return;
		$entity = $this->entity;

		if ($data === 0) {
			$player->sendForm(new ExchangeAddCategoryForm($entity));
			return;
		}

		if ($data === 2) {
			$categories = $entity->getCategories();
			if (count($categories) === 0) {
				$player->sendMessage('§r下 배치할 카테고리가 없습니다.');
				return;
			}
			self::openArrangeInvMenu($player, $entity);
			return;
		}

		$categories = $entity->getCategories();
		if (count($categories) === 0) {
			$player->sendMessage('§r下 카테고리가 없습니다. 먼저 카테고리를 추가해주세요.');
			return;
		}

		$buttons = array_map(
			fn($cat) => ['text' => $cat['name'] . ' §7(' . count($cat['items'] ?? []) . '종)'],
			$categories
		);

		$player->sendForm(new class($entity, $buttons) implements Form {
			public function __construct(
				private readonly ExchangeEntity $entity,
				private readonly array $buttons
			) {}

			public function jsonSerialize(): array
			{
				return [
					'type' => 'form',
					'title' => '§lEDIT EXCHANGE',
					'content' => '수정할 카테고리를 선택해주세요',
					'buttons' => $this->buttons
				];
			}

			public function handleResponse(Player $player, $data): void
			{
				if ($data === null) return;
				$categories = $this->entity->getCategories();
				if (!isset($categories[$data])) return;

				$entity = $this->entity;
				$catIdx = $data;
				$catName = $categories[$catIdx]['name'];

				$player->sendForm(new class($entity, $catIdx, $catName) implements Form {
					public function __construct(
						private readonly ExchangeEntity $entity,
						private readonly int $catIdx,
						private readonly string $catName
					) {}

					public function jsonSerialize(): array
					{
						return [
							'type' => 'form',
							'title' => '§l' . $this->catName,
							'content' => '하실 작업을 선택해주세요',
							'buttons' => [
								['text' => '상품 추가'],
								['text' => '상품 수정'],
								['text' => '§c카테고리 삭제'],
							]
						];
					}

					public function handleResponse(Player $player, $data): void
					{
						if ($data === null) return;
						$entity = $this->entity;
						$catIdx = $this->catIdx;

						if ($data === 0) {
							ExchangeEditForm::openAddItemInvMenu($player, $entity, $catIdx);
						} elseif ($data === 1) {
							ExchangeEditForm::openItemListInvMenu($player, $entity, $catIdx);
						} elseif ($data === 2) {
							$catName = $this->catName;
							$player->sendForm(new class($entity, $catIdx, $catName) implements Form {
								public function __construct(
									private readonly ExchangeEntity $entity,
									private readonly int $catIdx,
									private readonly string $catName
								) {}

								public function jsonSerialize(): array
								{
									return [
										'type' => 'modal',
										'title' => '카테고리 삭제',
										'content' => "§c{$this->catName}§r 카테고리를 정말 삭제하시겠습니까?\n§7삭제 시 해당 카테고리의 모든 상품도 함께 삭제됩니다.",
										'button1' => '§c삭제',
										'button2' => '취소',
									];
								}

								public function handleResponse(Player $player, $data): void
								{
									if ($data !== true) return;
									$this->entity->removeCategory($this->catIdx);
									$player->sendMessage('§r丌 카테고리를 삭제하였습니다.');
								}
							});
						}
					}
				});
			}
		});
	}

	public static function openAddItemInvMenu(Player $player, ExchangeEntity $entity, int $catIdx): void
	{
		$glass = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::YELLOW)->asItem();

		$inv = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
		$inv->setName('ADD ITEM');
		$invInv = $inv->getInventory();

		for ($i = 0; $i < 27; $i++) {
			$invInv->setItem($i, $glass);
		}
		$invInv->setItem(2, VanillaItems::OAK_SIGN()->setCustomName('§r§a조건 아이템'));
		$invInv->setItem(11, VanillaItems::AIR());
		$invInv->setItem(12, VanillaItems::AIR());
		$invInv->setItem(6, VanillaItems::OAK_SIGN()->setCustomName('§r§a결과 아이템'));
		$invInv->setItem(15, VanillaItems::AIR());
		$invInv->setItem(17,
			VanillaBlocks::WOOL()->setColor(DyeColor::LIME)->asItem()
				->setCustomName('§r§a상품 추가하기')
		);

		$inv->setListener(function (InvMenuTransaction $tr) use ($entity, $catIdx): InvMenuTransactionResult {
			$player = $tr->getPlayer();
			$slot = $tr->getAction()->getSlot();
			$invInv = $tr->getAction()->getInventory();

			if ($slot === 17) {
				$cost1 = $invInv->getItem(11);
				$cost2 = $invInv->getItem(12);
				$result = $invInv->getItem(15);

				if ($result->isNull()) {
					$player->sendMessage('§r下 결과 아이템칸을 채워주세요.');
					return $tr->discard();
				}

				$texture = class_exists(ItemTexture::class)
					? (ItemTexture::getItemTexture($result) ?? '')
					: '';
				$costs = array_values(array_filter([$cost1, $cost2], fn($c) => !$c->isNull()));
				$entity->addItemToCategory($catIdx, $costs, $result, $texture);
				$player->sendMessage('§r丌 상품이 추가되었습니다.');
				$player->removeCurrentWindow();
				return $tr->discard();
			}

			return in_array($slot, [11, 12, 15], true) ? $tr->continue() : $tr->discard();
		});

		$inv->send($player);
	}

	public static function openItemListInvMenu(Player $player, ExchangeEntity $entity, int $catIdx): void
	{
		$categories = $entity->getCategories();
		if (!isset($categories[$catIdx])) return;

		$items = array_values($categories[$catIdx]['items'] ?? []);
		if (count($items) === 0) {
			$player->sendMessage('§r下 수정할 상품이 없습니다.');
			return;
		}

		$listInv = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
		$listInv->setName('EDIT ITEM');

		foreach ($items as $i => $itemData) {
			$listInv->getInventory()->setItem($i, Exchange::ItemDataDeserialize($itemData['result']));
		}

		$listInv->setListener(function (InvMenuTransaction $tr) use ($entity, $catIdx, $items): InvMenuTransactionResult {
			$slot = $tr->getAction()->getSlot();
			if ($tr->getAction()->getInventory()->getItem($slot)->isNull()) {
				return $tr->discard();
			}

			$itemData = $items[$slot] ?? null;
			if ($itemData === null) return $tr->discard();

			$rawCosts = isset($itemData['costs']) && is_array($itemData['costs'])
				? $itemData['costs']
				: array_filter([
					$itemData['cost1'] ?? null,
					$itemData['cost2'] ?? null
				]);
			$costs = array_map(fn($s) => Exchange::ItemDataDeserialize($s), array_values($rawCosts));
			$result = Exchange::ItemDataDeserialize($itemData['result']);

			return $tr->discard()->then(
				function (Player $player) use ($entity, $catIdx, $slot, $costs, $result): void {
					self::openEditItemInvMenu($player, $entity, $catIdx, $slot, $costs, $result);
				}
			);
		});

		$listInv->send($player);
	}

	/** @param Item[] $costs */
	private static function openEditItemInvMenu(
		Player $player,
		ExchangeEntity $entity,
		int $catIdx,
		int $itemIdx,
		array $costs,
		Item $result
	): void {
		$glass = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::YELLOW)->asItem();
		$cost1 = $costs[0] ?? VanillaItems::AIR();
		$cost2 = $costs[1] ?? VanillaItems::AIR();

		$editInv = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
		$editInv->setName('EDIT ITEM');
		$editInvInv = $editInv->getInventory();

		for ($i = 0; $i < 27; $i++) {
			$editInvInv->setItem($i, $glass);
		}

		$editInvInv->setItem(2, VanillaBlocks::WOOL()->setColor(DyeColor::LIME)->asItem()->setCustomName('§r§a갯수 증가'));
		$editInvInv->setItem(11, $cost1);
		$editInvInv->setItem(20, VanillaBlocks::WOOL()->setColor(DyeColor::RED)->asItem()->setCustomName('§r§c갯수 감소'));

		$editInvInv->setItem(3, VanillaBlocks::WOOL()->setColor(DyeColor::LIME)->asItem()->setCustomName('§r§a갯수 증가'));
		$editInvInv->setItem(12, $cost2);
		$editInvInv->setItem(21, VanillaBlocks::WOOL()->setColor(DyeColor::RED)->asItem()->setCustomName('§r§c갯수 감소'));

		$editInvInv->setItem(6, VanillaBlocks::WOOL()->setColor(DyeColor::LIME)->asItem()->setCustomName('§r§a갯수 증가'));
		$editInvInv->setItem(15, $result);
		$editInvInv->setItem(24, VanillaBlocks::WOOL()->setColor(DyeColor::RED)->asItem()->setCustomName('§r§c갯수 감소'));

		$editInvInv->setItem(17, VanillaBlocks::WOOL()->setColor(DyeColor::LIME)->asItem()->setCustomName('§r§a상품 수정하기'));
		$editInvInv->setItem(26, VanillaBlocks::BARRIER()->asItem()->setCustomName('§r§c상품 삭제하기'));

		$adjustSlots = [
			2 => [11, 1],  20 => [11, -1],
			3 => [12, 1],  21 => [12, -1],
			6 => [15, 1],  24 => [15, -1],
		];

		$editInv->setListener(
			function (InvMenuTransaction $tr) use ($entity, $catIdx, $itemIdx, $adjustSlots): InvMenuTransactionResult {
				$player = $tr->getPlayer();
				$slot = $tr->getAction()->getSlot();
				$inv = $tr->getAction()->getInventory();

				if (isset($adjustSlots[$slot])) {
					[$targetSlot, $delta] = $adjustSlots[$slot];
					$item = $inv->getItem($targetSlot);
					if (!$item->isNull()) {
						$newCount = $item->getCount() + $delta;
						if ($newCount >= 1 && $newCount <= $item->getMaxStackSize()) {
							$inv->setItem($targetSlot, $item->setCount($newCount));
						}
					}
					return $tr->discard();
				}

				if ($slot === 17) {
					$costs = array_filter([$inv->getItem(11), $inv->getItem(12)], fn($c) => !$c->isNull());
					$entity->editItemInCategory($catIdx, $itemIdx, array_values($costs), $inv->getItem(15));
					$player->removeCurrentWindow();
					$player->sendMessage('§r丌 상품을 수정하였습니다.');
					return $tr->discard();
				}

				if ($slot === 26) {
					$entity->removeItemFromCategory($catIdx, $itemIdx);
					$player->removeCurrentWindow();
					$player->sendMessage('§r丌 상품을 삭제하였습니다.');
					return $tr->discard();
				}

				return in_array($slot, [11, 12, 15], true) ? $tr->continue() : $tr->discard();
			}
		);

		$editInv->send($player);
	}

	public static function openArrangeInvMenu(Player $player, ExchangeEntity $entity): void
	{
		$categories = $entity->getCategories();
		$glass = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::YELLOW)->asItem();
		$displaySlots = ExchangeCategoryMenu::DISPLAY_SLOTS;
		$customIcons = [];

		// 슬롯 → catIdx 추적 (아이콘이 집어들려 슬롯이 비어도 소유 catIdx 기억)
		$lastCatIdx = [];
		foreach ($displaySlots as $i => $slot) {
			$lastCatIdx[$slot] = isset($categories[$i]) ? $i : -1;
		}

		$menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
		$menu->setName('ARRANGE CATEGORIES');
		$inv = $menu->getInventory();

		for ($i = 0; $i < 27; $i++) {
			$inv->setItem($i, $glass);
		}

		foreach ($displaySlots as $i => $slot) {
			if (!isset($categories[$i])) break;
			$category = $categories[$i];

			if (!empty($category['icon'])) {
				$icon = Exchange::ItemDataDeserialize($category['icon']);
			} elseif (!empty($category['items'])) {
				$icon = Exchange::ItemDataDeserialize($category['items'][0]['result']);
			} else {
				$icon = VanillaBlocks::CHEST()->asItem();
			}

			$nbt = $icon->getNamedTag();
			$nbt->setInt('catIdx', $i);
			$icon = $icon->setNamedTag($nbt);
			$icon->setCustomName($category['name']);
			$icon->setLore(['상품 ' . count($category['items'] ?? []) . '종']);
			$inv->setItem($slot, $icon);
		}

		$menu->setListener(
			function (InvMenuTransaction $tr) use ($displaySlots, $categories, &$customIcons, &$lastCatIdx): InvMenuTransactionResult {
				$slot = $tr->getAction()->getSlot();
				$inv = $tr->getAction()->getInventory();
				$inItem = $tr->getIn();
				$outItem = $tr->getOut();

				if (!in_array($slot, $displaySlots, true)) {
					return $tr->discard();
				}

				$outCatIdx = $outItem->isNull() ? -1 : $outItem->getNamedTag()->getInt('catIdx', -1);

				// 카테고리 아이콘 이동 → 중복 방지 + lastCatIdx 갱신
				if (!$inItem->isNull() && $inItem->getNamedTag()->getTag('catIdx') !== null) {
					$inCatIdx = $inItem->getNamedTag()->getInt('catIdx', -1);
					if ($inCatIdx !== -1) {
						// 다른 슬롯의 같은 catIdx lastCatIdx 무효화 (이동 완료)
						foreach ($lastCatIdx as $s => &$idx) {
							if ($s !== $slot && $idx === $inCatIdx) {
								$idx = -1;
								break;
							}
						}
						unset($idx);
						$lastCatIdx[$slot] = $inCatIdx;

						// 다른 표시 슬롯에 같은 catIdx 아이콘이 있으면 제거
						foreach ($displaySlots as $otherSlot) {
							if ($otherSlot === $slot) continue;
							$oi = $inv->getItem($otherSlot);
							if (!$oi->isNull()
								&& $oi->getNamedTag()->getTag('catIdx') !== null
								&& $oi->getNamedTag()->getInt('catIdx', -1) === $inCatIdx
							) {
								$inv->setItem($otherSlot, VanillaItems::AIR());
								break;
							}
						}
					}
					return $tr->continue();
				}

				// 집어들기(AIR) → lastCatIdx 유지하고 허용
				if ($inItem->isNull()) {
					return $tr->continue();
				}

				// 플레이어 인벤 아이템 → outItem 또는 lastCatIdx로 catIdx 확정
				$targetCatIdx = $outCatIdx !== -1 ? $outCatIdx : ($lastCatIdx[$slot] ?? -1);
				if ($targetCatIdx !== -1) {
					$customIcons[$targetCatIdx] = Exchange::ItemDataSerialize($inItem->setCount(1));

					$nbt = $inItem->getNamedTag();
					$nbt->setInt('catIdx', $targetCatIdx);
					$displayItem = $inItem->setNamedTag($nbt)->setCount(1);
					$displayItem->setCustomName($categories[$targetCatIdx]['name'] ?? '');
					$displayItem->setLore(['상품 ' . count($categories[$targetCatIdx]['items'] ?? []) . '종']);
					$inv->setItem($slot, $displayItem);
					$lastCatIdx[$slot] = $targetCatIdx;
					return $tr->discard();
				}

				return $tr->discard();
			}
		);

		// 닫을 때 자동 저장
		$menu->setInventoryCloseListener(
			function (Player $player, \pocketmine\inventory\Inventory $inventory) use ($entity, $displaySlots, &$customIcons): void {
				$newOrder = [];
				foreach ($displaySlots as $displaySlot) {
					$item = $inventory->getItem($displaySlot);
					if (!$item->isNull() && $item->getNamedTag()->getTag('catIdx') !== null) {
						$catIdx = $item->getNamedTag()->getInt('catIdx', -1);
						if ($catIdx !== -1) {
							$newOrder[] = $catIdx;
						}
					}
				}
				$entity->reorderCategories($newOrder, $customIcons);
				$entity->saveConfig();
				$player->sendMessage('§r丌 카테고리 배치가 저장되었습니다.');
			}
		);

		$menu->send($player);
	}
}
