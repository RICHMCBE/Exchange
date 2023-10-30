<?php

declare(strict_types=1);

namespace MIN\Exchange\Command;

use MIN\Exchange\Form\ExchangeForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;

final class ExchangeCommand extends Command
{
	public function __construct()
	{
		$this->setPermission(DefaultPermissions::ROOT_OPERATOR);
		parent::__construct('교환상점', '교환상점을 관리하는 폼을 오픈합니다');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		if(!$this->testPermission($sender)) return;
		if(!$sender instanceof Player) return;
		$sender->sendForm(new ExchangeForm());
	}
}