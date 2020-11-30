<?php

declare(strict_types=1);

/**
 *    _____      _____ _                  _____ _    _ _____ 
 *  / ____|    / ____| |                / ____| |  | |_   _|
 *  | |     ___| (___ | |__   ___  _ __ | |  __| |  | | | |  
 *  | |    / _ \\___ \| '_ \ / _ \| '_ \| | |_ | |  | | | |  
 *  | |___|  __/____) | | | | (_) | |_) | |__| | |__| |_| |_ 
 *  \_____\___|_____/|_| |_|\___/| .__/ \_____|\____/|_____|
 *                              | |                        
 *                              |_|                                                               
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author kenygamer
 * @link github.com/kenygamer
 * @copyright
 * @license GNU General Public License v3.0y
 *
 */

namespace kenygamer\CEShopGUI\command;

use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\utils\TextFormat;

use jojoe77777\FormAPI\SimpleForm;
use kenygamer\CEShopGUI\Main;
use muqsit\invmenu\InvMenu;
use onebone\economyapi\EconomyAPI;

/**
 * @class CEShopCommand
 * @namespace kenygamer\CEShopGUI\command
 */
final class TinkererCommand extends Command{
	/** @var Main */
	private $plugin;
	
	/**
	 * @param Main $plugin
	 */
	public function __construct(Main $plugin){
		parent::__construct("tinkerer", "Turns an enchanted book into currency", "/tinkerer");
		$this->setPermission("ceshopgui.command.tinkerer");
		$this->plugin = $plugin;
	}
	
	/**
	 * @param CommandSender $sender
	 * @param string $commandLabel
	 * @param array $args
	 * @return bool
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return true;
		}
		if(!($sender instanceof Player)){
			$sender->sendMessage($this->plugin->translateString("cmd-ingame"));
			return true;
		}
		$book = $sender->getInventory()->getItemInHand();
		$enchantments = $book->getEnchantments();
        if($book->getId() === Item::ENCHANTED_BOOK && count($enchantments) > 0){
			$enchantment = array_shift($enchantments);
			$data = $enchantment->getType()->getRarity();
			$value = $this->plugin->getTinkererPriceList()[$data] ?? 0;
			if($data !== -1){
				switch($this->plugin->priceType){
					case Main::PRICE_TYPE_MONEY:
						EconomyAPI::getInstance()->addMoney($sender, $value);
						break;
					case Main::PRICE_TYPE_EXP:
        				$sender->addXp($exp);
						break;
				}
        		$sender->sendMessage($this->plugin->translateString("tinkerer", $value, $this->plugin->priceType === Main::PRICE_TYPE_MONEY ? "\$" : "EXP"));
        		$sender->getInventory()->removeItem($book->setCount(1));
			}
        }else{
			$sender->sendMessage($this->plugin->translateString("hold-book"));
        }
		return true;
	}
	
}