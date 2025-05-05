<?php

declare(strict_types=1);

namespace MIN\Exchange\Form;

use MIN\Exchange\Entity\ExchangeEntity;
use MIN\Exchange\Exchange;
use pocketmine\form\Form;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use naeng\ItemTexture\ItemTexture;
use kim\present\koritemname\KorItemName;

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
                $text[] = "§$color " . KorItemName::translate($cost1) . " {$cost1->getCount()}개";
            }
            if($cost2 !== null) {
                $color = $this->player->getInventory()->contains($cost2) ? 'a' : 'c';
                $text[] = "§$color " . KorItemName::translate($cost2) . " {$cost2->getCount()}개";
            }
            if($cost1 === null && $cost2 === null) {
                $text[] = '§a무료';
            }
            $text = implode(', ', $text);

            // ItemTexture를 사용해 텍스처 동적 로드
            $texture = ItemTexture::getItemTexture($result);
            if ($texture === null) {
                $texture = 'textures/items/default'; // 기본 텍스처
            }

            $buttons[] = [
                'text' => KorItemName::translate($result) . "\n" .$text,
                'image' => [
                    'type' => 'path',
                    'data' => $texture
                ]
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
        if($data === null) return;
        $itemData = $this->entity->data[$data];
        $cost1 = $itemData['cost1'] !== null ? Exchange::ItemDataDeserialize($itemData['cost1']) : VanillaItems::AIR();
        $cost2 = $itemData['cost2'] !== null ? Exchange::ItemDataDeserialize($itemData['cost2']) : VanillaItems::AIR();

        $result = Exchange::ItemDataDeserialize($itemData['result']);
        $bool1 = ($itemData['cost1'] === null) || ($player->getInventory()->contains(Exchange::ItemDataDeserialize($itemData['cost1'])));
        if(!$bool1) {
            $player->sendMessage('§l§6 • §r§7아이템이 없어서 거래가 성립되지 않았습니다');
            return;
        }
        $player->getInventory()->removeItem($cost1);
        $bool2 = ($itemData['cost2'] === null) || ($player->getInventory()->contains(Exchange::ItemDataDeserialize($itemData['cost2'])));
        if(!$bool2) {
            $player->sendMessage('§l§6 • §r§7아이템이 없어서 거래가 성립되지 않았습니다');
            $player->getInventory()->addItem($cost1);
            return;
        }
        $player->getInventory()->removeItem($cost2);
        $player->getInventory()->addItem($result);
        $player->sendMessage('§l§6 • §r§7거래가 성립되었습니다');
    }
}