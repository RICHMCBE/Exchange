<?php

declare(strict_types=1);

namespace MIN\Exchange\Form;

use MIN\Exchange\Entity\ExchangeEntity;
use pocketmine\form\Form;
use pocketmine\player\Player;
use function trim;

final class ExchangeAddCategoryForm implements Form
{
	public function __construct(private readonly ExchangeEntity $entity) {}

	public function jsonSerialize(): array
	{
		return [
			'type' => 'custom_form',
			'title' => '§lADD CATEGORY',
			'content' => [
				['type' => 'input', 'text' => '카테고리 이름을 입력해주세요']
			]
		];
	}

	public function handleResponse(Player $player, $data): void
	{
		if ($data === null) return;
		$name = trim($data[0]);
		if ($name === '') {
			$player->sendMessage('§r下 카테고리 이름을 입력해주세요.');
			return;
		}
		$this->entity->addCategory($name);
		$player->sendMessage('§r丌 카테고리를 추가하였습니다.');
	}
}
