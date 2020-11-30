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
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\ItemFactory;
use pocketmine\utils\TextFormat;

use onebone\economyapi\EconomyAPI;
use jojoe77777\FormAPI\SimpleForm;
use kenygamer\CEShopGUI\Main;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\InvMenu;

/**
 * @class CEShopCommand
 * @namespace kenygamer\CEShopGUI\command
 */
final class CEShopCommand extends Command{
	private const RANDOM_TAG = "We <3 keny";
	
	/** @var Main */
	private $plugin;
	
	/**
	 * @param Main $plugin
	 */
	public function __construct(Main $plugin){
		parent::__construct("ceshop", "Opens up the custom enchants shop", "/ceshop");
		$this->setPermission("ceshopgui.command.ceshop");
		$this->plugin = $plugin;
	}
	
	/**
	 * @param InventoryTransaction $transaction
	 */
	private function removeInventory(InventoryTransaction $transaction) : void{
		$transaction->getPlayer()->removeWindow($transaction->getAction()->getInventory());
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
		$menu = InvMenu::create(InvMenu::TYPE_HOPPER);
        $menu->setListener(function(InvMenuTransaction $transaction){;
			$discard = $transaction->discard();
			$player = $transaction->getPlayer();
			$itemClicked = $transaction->getItemClicked();
        	$rarity = explode(" ", TextFormat::clean($itemClicked->getName()))[0];
        	($rarity);
        	$data = $this->plugin->getDataByRarity($rarity);
			
        	if($data !== -1){
				$item = ItemFactory::get(Item::BOOK);
        		$item->setCustomName($itemClicked->getName());
        		$nbt = $item->getNamedTag();
        		$nbt->setInt(Main::TAG_RARITY, $data);
        		$nbt->setInt(self::RANDOM_TAG, mt_rand(-2147483647, 2147483647));
        		$item->setNamedTag($nbt);
		
		        if(!$player->getInventory()->canAddItem($item)){
		        	$player->sendMessage($this->plugin->translateString("no-space"));
		        	$this->removeInventory($transaction);
		        	return $discard;
		        }
				
				$price = $this->plugin->getEnchantPriceList()[$data] ?? 0;
				$errors = false;
				switch($this->plugin->priceType){
					case Main::PRICE_TYPE_MONEY:
						if(!EconomyAPI::getInstance()->reduceMoney($player, $price)){
							$this->removeInventory($transaction);
							$player->sendMessage($this->plugin->translateString("not-enough-money", $price, $rarity));
							$errors = true;
							break;
						}
						break;
					case Main::PRICE_TYPE_EXP:
						if($player->getCurrentTotalXp() < $price){
							$this->removeInventory($transaction);
        					$player->sendMessage($this->plugin->translateString("not-enough-exp", $price, $rarity));
        					$errors = true;
							break;
						}
						$player->subtractXp($price);
						break;
				}
				if($errors){
					$this->removeInventory($transaction);
					return $discard;
				}
		        $player->getInventory()->addItem($item);
		    }
			return $discard;
		});
		$menu->setName($this->plugin->translateString("ceshop-title"));

		foreach([Enchantment::RARITY_COMMON, Enchantment::RARITY_UNCOMMON, Enchantment::RARITY_RARE, Enchantment::RARITY_MYTHIC] as $i => $data){
			$rarity = $this->plugin->getRarityByData($data);
			$color = $this->plugin->getEnchantAdapter()->getColorByRarity($this->plugin->getDataByRarity($rarity));
			
			$book = ItemFactory::get(Item::BOOK);
			$book->setCustomName($this->plugin->translateString("book-name", $color, $rarity))->
				setLore(
					implode(TextFormat::EOL, $this->plugin->translateString("book-buy-lore", $this->plugin->getEnchantPriceList()[$data], $this->plugin->priceType === Main::PRICE_TYPE_EXP ? "EXP" : "Money"))
				);
			$menu->getInventory()->setItem($i, $book);
		}
		$menu->send($sender);
		return true;
	}
	
}