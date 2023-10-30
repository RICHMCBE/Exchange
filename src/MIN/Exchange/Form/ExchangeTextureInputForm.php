<?php

declare(strict_types=1);

namespace MIN\Exchange\Form;

use MIN\Exchange\Entity\ExchangeEntity;
use pocketmine\form\Form;
use pocketmine\item\Item;
use pocketmine\player\Player;
use ryun42680\richdesign\Design;

final readonly class ExchangeTextureInputForm implements Form
{
	public function __construct(
		private ExchangeEntity $entity,
		private Item           $cost1,
		private Item           $cost2,
		private Item           $result
	)
	{
	}

	public function jsonSerialize(): array
	{
		return [
			'type' => 'custom_form',
			'title' => '§lTEXTURE INPUT',
			'content' => [
				[
					'type' => 'input',
					'text' => Design::FORM_TEXT.'보상 아이템의 텍스쳐를 입력해주세요',
					'default' => 'textures/ui/Form/'
				]
			]
		];
	}

	public function handleResponse(Player $player, $data): void
	{
		if($data === null) {
			$player->sendMessage(Design::$prefix_2.'추가가 취소되었습니다');
			return;
		}
		$texture = trim($data[0]);
		if($texture === '') {
			$player->sendMessage(Design::$prefix_2.'텍스쳐를 입력해주세요');
			return;
		}
		$this->entity->addItem($this->cost1,$this->cost2, $this->result, $texture);
		$player->sendMessage(Design::$prefix_2.'추가가 완료되었습니다');
	}
}