<?php

declare(strict_types=1);

namespace MIN\Exchange\Form;

use MIN\Exchange\Entity\ExchangeEntity;
use MIN\Exchange\Exchange;
use naeng\ItemTexture\ItemTexture;
use naeng\MailCore\data\MailInfo;
use naeng\MailCore\MailCore;
use pocketmine\form\Form;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use kim\present\koritemname\KorItemName;
use SOFe\AwaitGenerator\Await;

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

        if(!$cost1->isNull() && !$player->getInventory()->contains($cost1)) {
            $player->sendMessage('§r下 아이템이 없어서 거래가 성립되지 않았습니다.');
            return;
        }
        if(!$cost2->isNull() && !$player->getInventory()->contains($cost2)) {
            $player->sendMessage('§r下 아이템이 없어서 거래가 성립되지 않았습니다.');
            return;
        }

        if(!$cost1->isNull()) $player->getInventory()->removeItem($cost1);
        if(!$cost2->isNull()) $player->getInventory()->removeItem($cost2);

        $leftover = $player->getInventory()->addItem($result);
        if(!empty($leftover)) {
            if(class_exists(MailCore::class)) {
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
                if(!$cost1->isNull()) $player->getInventory()->addItem($cost1);
                if(!$cost2->isNull()) $player->getInventory()->addItem($cost2);
                $player->sendMessage('§r下 인벤토리가 가득 차 거래가 성립되지 않았습니다.');
            }
            return;
        }

        $player->sendMessage('§r丌 거래가 성립되었습니다.');
    }
}