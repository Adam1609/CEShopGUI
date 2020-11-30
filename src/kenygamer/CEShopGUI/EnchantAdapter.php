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

use pocketmine\item\Item;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\command\CommandSender;
use pocketmine\utils\Utils;

use DaPigGuy\PiggyCustomEnchants\utils\Utils as PiggyUtils;
use DaPigGuy\PiggyCustomEnchants\CustomEnchantManager as CustomEnchantsNew;
use DaPigGuy\PiggyCustomEnchants\CustomEnchants\CustomEnchants as CustomEnchantsOld;

/**
 * @package kenygamer\CEShopGUI
 * @class EnchantAdapter
 */
final class EnchantAdapter{
	private const VERSION_OLD = "1.0";
	private const VERSION_NEW = "2.0";
	
	/** @var \pocketmine\plugin\Plugin */
	private $api;
	/** @var string|null */
	private $version = null;
	/** @var Main */
	private $plugin;
	
	/**
	 * @param Main $plugin
	 */
	public function __construct(Main $plugin){
		$this->plugin = $plugin;
	}
	
	/**
	 * Loads the enchant adapter.
	 *
	 * @return bool
	 */
	public function load() : bool{
		$this->api = $this->plugin->getServer()->getPluginManager()->getPlugin("PiggyCustomEnchants");
		if(
			$this->api !== null &&
			in_array("DaPigGuy", $this->api->getDescription()->getAuthors())){
			if(version_compare($this->api->getDescription()->getVersion(),self::VERSION_NEW) >= 1){
				
				$this->version = self::VERSION_NEW;
				
			}else{
				$this->version = self::VERSION_OLD;
			}
			return true;
		}
		return false;
	}
	
	/**
	 * Gets the enchant description.
	 *
	 * @param object $enchant
	 * @return string
	 */
	public function getEnchantDescription(object $enchant) : string{
		switch($this->version){
			case self::VERSION_OLD:
				$data = $this->api->enchants[$enchant->getId()] ?? null;
				if($data === null){
					throw new \InvalidArgumentException("Enchant " . $enchant->getId() . " is not a valid custom enchant");
				}
				list($name, $slot, $trigger, $rarity, $maxlevel, $description) = $data;
				return $description;
				break;
			case self::VERSION_NEW:
				return $enchant->getDescription();
				break;
		}
		throw new \BadMethodCallException("EnchantAdapter is not loaded");
	}
	
	/**
	 * Gets the enchant name.
	 *
	 * @param object $enchant
	 * @return string
	 */
	public function getEnchantName(object $enchant) : string{
		switch($this->version){
			case self::VERSION_OLD:
				$data = $this->api->enchants[$enchant->getId()] ?? null;
				if($data === null){
					throw new \InvalidArgumentException("Enchant " . $enchant->getId() . " is not a valid custom enchant");
				}
				list($name, $slot, $trigger, $rarity, $maxlevel, $description) = $data;
				return $name;
				break;
			case self::VERSION_NEW:
				return $enchant->getName();
				break;
		}
		throw new \BadMethodCallException("EnchantAdapter is not loaded");
	}
	
	/**
	 * Gets the roman numeral.
	 *
	 * @param int $integer
	 * @return string
	 */
	public function getRomanNumber(int $integer) : string{
		switch($this->version){
			case self::VERSION_OLD:
				return $this->api->getRomanNumber($integer);
				break;
			case self::VERSION_NEW:
				return PiggyUtils::getRomanNumeral($integer);
				break;
		}
		throw new \BadMethodCallException("EnchantAdapter is not loaded");
	}
	
	/**
	 * Returns the color by rarity.
	 *
	 * @param int $id
	 * @return string
	 * @throws \BadMethodCallException
	 */
	public function getColorByRarity(int $id) : string{
		switch($this->version){
			case self::VERSION_OLD:
				return $this->api->getRarityColor($id);
				break;
			case self::VERSION_NEW:
				return PiggyUtils::getColorFromRarity($id);
				break;
		}
		throw new \BadMethodCallException("EnchantAdapter is not loaded");
	}
	
	/**
	 * Returns the custom enchant by its name or ID.
	 *
	 * @param string|int $enchant
	 * @return object|null
	 * @throws \InvalidArgumentException
	 */
	public function getEnchant($enchant) : ?object{
		if(is_string($enchant)){
			return $this->getEnchantByName($enchant);
		}
		if(is_int($enchant)){
			return $this->getEnchantById($enchant);
		}
		throw new \InvalidArgumentException("Enchant must be of the type string or int, " . gettype($enchant) . " given");
	}
	/**
	 * Returns the custom enchant by its ID.
	 *
	 * @param string $id
	 * @return object|null
	 * @throws \BadMethodCallException
	 */
	public function getEnchantById(int $id) : ?object{
		switch($this->version){
			case self::VERSION_OLD:
				return CustomEnchantsOld::getEnchantment($id);
				break;
			case self::VERSION_NEW:
				return CustomEnchantsNew::getEnchantment($id);
				break;
		}
		throw new \BadMethodCallException("EnchantAdapter is not loaded");
	}
	
	/**
	 * Returns the custom enchant by its name.
	 *
	 * @param string $name Must use underscores or spaces for name spaces.
	 * @return object|null
	 */
	public function getEnchantByName(string $name) : ?object{
		switch($this->version){
			case self::VERSION_OLD:
				//We change spaces to underscores and Piggy tries to match constant name in upcase.
				return CustomEnchantsOld::getEnchantmentByName(str_replace(" ", "_", $name));
				break;
			case self::VERSION_NEW:
				//We change underscores to spaces and Piggy removes them and checks in lwcase to match enchant name or enchant display name.
				return CustomEnchantsNew::getEnchantmentByName(str_replace("_", " ", $name));
				break;
		}
		throw new \BadMethodCallException("EnchantAdapter is not loaded");
	}
	
	/**
	 * @return \pocketmine\item\enchantment\Enchantment[]
	 * @throws \BadMethodCallException
	 */
	public function getEnchants() : array{
		$enchants = [];
		switch($this->version){
			case self::VERSION_OLD:
				foreach($this->api->enchants as $id => $data){
					list($name, $slot, $trigger, $rarity, $maxlevel, $description) = $data;
					if($enchant = CustomEnchantsOld::getEnchantment($id)){
						$enchants[] = $enchant;
					}
				}
				return $enchants;
				break;
			case self::VERSION_NEW:
				foreach(CustomEnchantsNew::getEnchantments() as $enchant){
					$enchants[] = $enchant;
				}
				return $enchants;
				break;
		}
		throw new \BadMethodCallException("EnchantAdapter is not loaded");
	}
	
	
	/**
     * Apply one or more enchantments to an item.
     *
     * @param Item $item
     * @param array|int $enchants
     * @param array|int $levels
     * @param CommandSender|null $sender
     * @return Item
     */
	public function addEnchantment(Item $item, $enchants, $levels, $check = true, CommandSender $sender = null) : Item{
		$list = [];
		if(is_array($enchants) && is_array($levels)){
			if(count($enchants) !== count($levels)){
				throw new \InvalidArgumentException("The number of levels must be the same as the number of enchants");
			}
			$list = array_combine($enchants, $levels);
			foreach($list as $id => $lvl){
				if($lvl < 0){
					throw new \InvalidArgumentException("Level must be above 0");
				}
			}
			if(!Utils::validateArrayValueType($enchants, function(int $value){}) || 
				!Utils::validateArrayValueType($levels, function(int $value){})){
				throw new \InvalidArgumentException("Argument 2 and argument 3 must be only array of integers");
			}
		}else{
			if(!is_int($enchants) || !is_int($levels)){
				throw new \InvalidArgumentException("Argument 2 and argument 3 must be of the type integer in this condition");
			}
			$list = [$enchants => $levels];
		}
		foreach($list as $enchant => $lvl){
			$enchantment = $this->getEnchantById($enchant);
			if($enchantment === null){
				$this->plugin->getLogger()->warning("Enchant " . $enchant . " not found");
				continue;	
			}
			$item->addEnchantment(new EnchantmentInstance($enchantment), $lvl);
		}
		return $item;
	}
	
}