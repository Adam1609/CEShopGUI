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
 * @license GNU General Public License v3.0
 */

namespace kenygamer\CEShopGUI;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\Player;
use pocketmine\nbt\tag\IntTag;
use pocketmine\utils\TextFormat;


/**
 * @package kenygamer\CEShopGUI
 * @class EventListener
 */
final class EventListener implements Listener{
	
	/** @var Main */
	private $plugin;
	
	/**
	 * @param Main $plugin
	 */
	public function __construct(Main $plugin){
		$this->plugin = $plugin;
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
	}
	
	public function onInventoryTransaction(InventoryTransactionEvent $event) : void{
		$transaction = $event->getTransaction();
		$inventories = $transaction->getInventories();
		$found = false;
		foreach($inventories as $inventory){
			if(($holder = $inventory->getHolder()) instanceof Player){
				$found = true;
				break;
			}
		}
		if(!$found){
			return;
		}
		if(!($holder instanceof Player)){
			return;
		}
		foreach($transaction->getActions() as $action){
			if($action instanceof SlotChangeAction){
				if(($book = $action->getTargetItem()->getId()) === Item::ENCHANTED_BOOK && count($action->getSourceItem()->getEnchantments()) > 0){
					$nbt = $book->getNamedTag();
					if($nbt->hasTag(Main::TAG_CHANCE)){
						echo "[chance]\n";
						$destroy = mt_rand(0, 99) <= ($chance = $nbt->getTag(Main::TAG_CHANCE));
						var_dump($chance);
						if($destroy){
							$player->getInventory()->removeItem($book->setCount(1));
							$event->setCancelled();
						}
					}
				}
			}
		}
	}

	/**
	 * PlayerInteractEvent listener.
	 *
	 * @param PlayerInteractEvent $event
	 */
	public function onPlayerInteract(PlayerInteractEvent $event) : void{
        $player = $event->getPlayer();
        $item = $event->getItem();
		
        if($item->getId() === Item::BOOK){
        	if($item->getNamedTag()->hasTag(Main::TAG_RARITY, IntTag::class)){
				
        		$rarityData = $item->getNamedTag()->getInt(Main::TAG_RARITY);
				
        		foreach($player->getInventory()->getContents() as $slot){
        			if($slot->getNamedTag()->hasTag(Main::TAG_RARITY, IntTag::class) && $slot->getNamedTag()->getInt(Main::TAG_RARITY) === $rarityData){
						
        				$rarity = $this->plugin->getRarityByData($rarityData);
        				$enchs = [];
						
        				foreach($this->plugin->getEnchantAdapter()->getEnchants() as $enchant){
        					if($enchant->getRarity() === $rarityData){
        						$enchs[] = $enchant->getId();
        					}
        				}
						
						
        				$ench = $enchs[array_rand($enchs)];
        				$level = mt_rand(1, $this->plugin->getEnchantAdapter()->getEnchantById($ench)->getMaxLevel());
						if($this->plugin->successRate){
							$successRate = mt_rand(1, 50);
        		        	$destroyRate = 100 - $successRate;
						}else{
							$destroyRate = 0;
							$successRate = 100;
						}
						$enchantedBook = ItemFactory::get(Item::ENCHANTED_BOOK, 0, $slot->getCount());
        		        $enchantBook = $this->plugin->getEnchantAdapter()->addEnchantment($enchantedBook, $ench, $level, $player);
						$lvl = $this->plugin->getEnchantAdapter()->getRomanNumber($level);
						$enchant = $this->plugin->getEnchantAdapter()->getEnchantById($ench);
						$name = $this->plugin->getEnchantAdapter()->getEnchantName($enchant);
						$nbt = $enchantBook->getNamedTag();
						
						$nbt->setInt(Main::TAG_CHANCE, $successRate);;
						
						$enchantBook->setNamedTag($nbt);
						
								
		        		$enchantBook->setLore(implode(TextFormat::EOL, $this->plugin->translateString("book-lore", $name, $lvl, $successRate, $destroyRate, $this->plugin->getEnchantAdapter()->getEnchantDescription($enchant))));
						$player->sendMessage($this->plugin->translateString("book-redeem", $name, $lvl));
        		        $player->getInventory()->addItem($enchantBook);
        		        $player->getInventory()->removeItem($slot);
						if(!$this->plugin->oneClick){
							break;
						}
        		    }
        		}
        	}
        }
    }
	
	
}