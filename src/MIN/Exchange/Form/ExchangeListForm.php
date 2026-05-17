<?php

declare(strict_types=1);

namespace MIN\Exchange\Form;

use MIN\Exchange\Entity\ExchangeEntity;
use MIN\Exchange\Exchange;
use naeng\ItemTexture\ItemTexture;
use naeng\MailCore\data\MailInfo;
use naeng\MailCore\MailCore;
use pocketmine\form\Form;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use kim\present\koritemname\KorItemName;
use SOFe\AwaitGenerator\Await;

final class ExchangeListForm implements Form
{
	private readonly array $items;

	public function __construct(
		private readonly Player         $player,
		private readonly ExchangeEntity $entity,
		private readonly int            $categoryIndex
	) {
		$categories = $entity->getCategories();
		$this->items = array_values($categories[$categoryIndex]['items'] ?? []);
	}

	private static function resolveCosts(array $itemData): array
	{
		if (isset($itemData['costs']) && is_array($itemData['costs'])) {
			return $itemData['costs'];
		}
		$costs = [];
		if (!empty($itemData['cost1'])) $costs[] = $itemData['cost1'];
		if (!empty($itemData['cost2'])) $costs[] = $itemData['cost2'];
		return $costs;
	}

	public function jsonSerialize(): array
	{
		$buttons = [];
		$contents = $this->player->getInventory()->getContents();

		foreach ($this->items as $itemData) {
			$result = Exchange::ItemDataDeserialize($itemData['result']);
			$costs = self::resolveCosts($itemData);

			$canExchange = true;
			$costParts = [];
			foreach ($costs as $serialized) {
				$cost = Exchange::ItemDataDeserialize($serialized);
				$has = self::countByType($contents, $cost) >= $cost->getCount();
				if (!$has) $canExchange = false;
				$costName = self::stripColor(KorItemName::translate($cost));
				$costParts[] = ($has ? '§a' : '§c') . $costName . " {$cost->getCount()}개";
			}
			$costText = empty($costParts) ? '§a무료' : implode(', ', $costParts);
			$resultColor = $canExchange ? '§a' : '§c';
			$resultName = self::stripColor(KorItemName::translate($result));

			$texture = class_exists(ItemTexture::class) ? ItemTexture::getItemTexture($result) : null;
			$texture ??= 'textures/items/default';

			$buttons[] = [
				'text' => $resultColor . $resultName . "\n" . $costText,
				'image' => ['type' => 'path', 'data' => $texture]
			];
		}

		return [
			'type' => 'form',
			'title' => 'customUI_RedesignShopCloudForm_교환소',
			'content' => '거래하실 상품을 선택해주세요',
			'buttons' => $buttons
		];
	}

	public function handleResponse(Player $player, $data): void
	{
		if ($data === null || !isset($this->items[$data])) return;

		$itemData = $this->items[$data];
		$result = Exchange::ItemDataDeserialize($itemData['result']);
		$costs = array_map(
			fn($s) => Exchange::ItemDataDeserialize($s),
			self::resolveCosts($itemData)
		);

		$contents = $player->getInventory()->getContents();
		foreach ($costs as $cost) {
			if (self::countByType($contents, $cost) < $cost->getCount()) {
				$player->sendMessage('§r下 아이템이 없어서 거래가 성립되지 않았습니다.');
				return;
			}
		}

		foreach ($costs as $cost) {
			self::removeByType($player->getInventory(), $cost);
		}

		$leftover = $player->getInventory()->addItem($result);
		if (!empty($leftover)) {
			if (class_exists(MailCore::class)) {
				$mailInfo = new MailInfo(
					null,
					(int) $player->getXuid(),
					'교환상점 아이템 지급',
					'인벤토리가 가득 차 교환 결과물을 메일로 발송합니다.',
					0,
					array_values($leftover)
				);
				Await::f2c(fn() => MailCore::getInstance()->send($mailInfo));
				$player->sendMessage('§r丌 거래가 성립되었습니다.');
				$player->sendMessage('§r不 인벤토리가 가득 차 결과물이 메일로 전송되었습니다.');
			} else {
				foreach ($costs as $cost) {
					$player->getInventory()->addItem($cost);
				}
				$player->sendMessage('§r下 인벤토리가 가득 차 거래가 성립되지 않았습니다.');
			}
			return;
		}

		$player->sendMessage('§r丌 거래가 성립되었습니다.');
	}

	private static function stripColor(string $text): string
	{
		return preg_replace('/§[0-9a-fk-orA-FK-OR]/', '', $text);
	}

	/** 타입 ID만으로 보유 수량 합산 (NBT·커스텀 이름 무시) */
	private static function countByType(array $contents, Item $needle): int
	{
		$count = 0;
		foreach ($contents as $item) {
			if ($item->getTypeId() === $needle->getTypeId()) {
				$count += $item->getCount();
			}
		}
		return $count;
	}

	/** 타입 ID 기준으로 필요 수량만큼 제거 */
	private static function removeByType(Inventory $inv, Item $needle): void
	{
		$needed = $needle->getCount();
		foreach ($inv->getContents(true) as $slot => $item) {
			if ($item->isNull() || $item->getTypeId() !== $needle->getTypeId()) continue;
			$take = min($item->getCount(), $needed);
			if ($take >= $item->getCount()) {
				$inv->clear($slot);
			} else {
				$inv->setItem($slot, $item->setCount($item->getCount() - $take));
			}
			$needed -= $take;
			if ($needed <= 0) break;
		}
	}
}
