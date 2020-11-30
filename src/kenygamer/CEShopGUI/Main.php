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

use pocketmine\plugin\PluginBase;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\utils\Utils;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use kenygamer\CEShopGUI\command\CEShopCommand;
use kenygamer\CEShopGUI\command\TinkererCommand;
use muqsit\invmenu\InvMenuHandler;

/**
 * @package kenygamer\CEShopGUI
 * @class Main
 */
final class Main extends PluginBase{
	/** @var bool */
	public $successRate = false;
	/** @var bool */
	public $enableTinkerer = false;
	/** @var int[] */
	public $tinkererPrices = [];
	/** @var bool */
	public $oneClick = false;
	/** @var int */
	public $filter = -1;
	/** @var string[] */
	public $filterEnchants = [];
	/** @var int */
	public $priceType = -1;
	/** @var int[] {@see self::getPriceList()} */
	private $priceList = [];
	/** @var Config */
	private $lang;
	
	public const TAG_CHANCE = "Chance";
	public const TAG_RARITY = "Rarity";
	
	private const FILTER_INCLUSION = 0;
	private const FILTER_EXCLUSION = 1;
	
	public const PRICE_TYPE_EXP = 0;
	public const PRICE_TYPE_MONEY = 1;
	
	/**
	 * Called when the plugin enables.
	 */
	public function onEnable() : void{
		foreach(["config.yml", "lang.properties"] as $resource){
			$this->saveResource($resource, true);
		}
		$this->lang = new Config($this->getDataFolder() . "lang.properties", Config::PROPERTIES);
		
		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
		if(!class_exists("\\muqsit\\invmenu\\InvMenu")){
			$this->getLogger()->critical("InvMenu is required but not found");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) : void{
			if(!$this->loadConfig()){
				$this->getLogger()->critical("Plugin configuration is not correctly set up. Check the main repository for reference or let the plugin regenerate the default configuration deleting the existing one.");
				$this->getServer()->getPluginManager()->disablePlugin($this);
			}else{
				$commands = [
					new CEShopCommand($this)
				];
				if($this->enableTinkerer){
					$commands[] = new TinkererCommand($this);
				}
				$this->getServer()->getCommandMap()->registerAll("ceshopgui", $commands);
				
				$this->enchantAdapter = new EnchantAdapter($this);
				if(!$this->enchantAdapter->load()){
					$this->getLogger()->error("PiggyCustomEnchants not found");
					$this->getServer()->getPluginManager()->disablePlugin($this);
					return;
				}
				
				new EventListener($this);
			}
		}), 1);
	}
	
	/**
	 * Returns the enchant adapter.
	 * 
	 * @return EnchantAdapter
	 */
	public function getEnchantAdapter() : EnchantAdapter{
		return $this->enchantAdapter;
	}
	
	/**
	 * Loads the configuration.
	 *
	 * @return bool
	 */
	public function loadConfig() : bool{
		if(
			($this->successRate = $this->getConfigKey("success-rate", "bool")) === null ||
			($this->enableTinkerer = $this->getConfigKey("tinkerer.enable", "bool")) === null ||
			($this->tinkererPrices = $this->getConfigKey("tinkerer.prices", "array")) === null ||
			($this->filter = $this->getConfigKey("filter.type", "int")) === null ||
			($this->oneClick = $this->getConfigKey("one-click", "bool")) === null ||
			($this->filterEnchants = $this->getConfigKey("filter.enchants", "array")) === null ||
			($this->priceType = $this->getConfigKey("prices.type", "int")) === null ||
			($this->priceList = $this->getConfigKey("prices.list", "array")) === null){
				
			return false;
		}
		if(($this->tinkerer = $this->getConfigKey("one-click", "bool")) === null){
			
			return false;
		}
		if(count(array_filter(array_keys($this->tinkererPrices), "is_string")) !== count($this->tinkererPrices)){

			return false;
		}
		if(count(array_filter(array_keys($this->priceList), "is_string")) !== count($this->priceList)){
			
			return false;
		}
		if($this->filter !== self::FILTER_INCLUSION && $this->filter !== self::FILTER_EXCLUSION){
			
			return false;
		}
		if($this->priceType === self::PRICE_TYPE_MONEY && $this->getServer()->getPluginManager()->getPlugin("EconomyAPI") === null){
			$this->getLogger()->error("EconomyAPI not found");
			return false;
		}
		return true;
	}
	
	/**
	 * Returns the enchants that show in the GUI. This is after doing 
	 * filtering, if any.
	 *
	 * @return CustomEnchants[]
	 * @throws \RuntimeException
	 */
	public function getShowEnchants() : array{
		$enchants = $this->getEnchantAdapter()->getEnchants();
		/** @var object[] */
		$filterEnchants = [];
		foreach($this->filterEnchants as $enchant){
			$enchantment = $this->getEnchantAdapter()->getEnchant($enchant);
			if($enchantment === null){
				$this->getLogger()->error("Enchant " . $enchant . " does not exist");
				continue;
			}
			$filterEnchants[] = $enchantment;
		}
		switch($this->filter){
			case self::FILTER_INCLUSION:
				return array_filter($enchants, function(object $enchantment) use($filterEnchants) : bool{
					foreach($filterEnchants as $enchant){
						if($enchant->getId() === $enchantment->getId()){
							return true;
						}
					}
					return false;
				});
				break;
			case self::FILTER_EXCLUSION:
				return array_filter($enchants, function(object $enchantment) use($filterEnchants) : bool{
					foreach($filterEnchants as $enchant){
						if($enchant->getId() === $enchantment->getId()){
							return false;
						}
					}
					return true;
				});
				break;
		}
		throw new \RuntimeException("Invalid filter");
	}
	
	/**
	 * Returns the enchant price list.
	 *
	 * @return array<int, int>
	 */
	public function getEnchantPriceList() : array{
		$list = [];
		foreach($this->priceList as $rarity => $price){
			$data = $this->getDataByRarity($rarity);
			if($data !== -1){
				$list[$data] = $price;
			}
		}
		return $list;
	}
	
	/**
	 * Returns the tinkerer price list.
	 *
	 * @return array<int, int>
	 */
	public function getTinkererPriceList() : array{
		$list = [];
		foreach($this->tinkererPrices as $rarity => $price){
			$data = $this->getDataByRarity($rarity);
			if($data !== -1){
				$list[$data] = $price;
			}
		}
		return $list;
	}
	
	/**
	 * Returns the rarity by data.
	 *
	 * @param int $data
	 * @return string
	 */
	public function getRarityByData(int $data) : string{
		$rarity = "Unknown";
		switch($data){
			case Enchantment::RARITY_COMMON:
			    $rarity = "Common";
			    break;
			case Enchantment::RARITY_UNCOMMON:
			    $rarity = "Uncommon";
			    break;
			case Enchantment::RARITY_RARE:
			    $rarity = "Rare";
			    break;
			case Enchantment::RARITY_MYTHIC:
			    $rarity = "Mythic";
			    break;
		}
		return $rarity;
	}
	
	/**
	 * Returns the data by rarity.
	 *
	 * @param string $rarity
	 *
	 * @return int
	 */
	public function getDataByRarity(string $rarity) : int{
		$data = -1;
		switch($rarity){
			case "Common":
			    $data = Enchantment::RARITY_COMMON;
			    break;
			case "Uncommon":
			    $data = Enchantment::RARITY_UNCOMMON;
			    break;
			case "Rare":
			    $data = Enchantment::RARITY_RARE;
			    break;
			case "Mythic":
			    $data = Enchantment::RARITY_MYTHIC;
			    break;
		}
		return $data;
	}
	
	/**
	 * Translate a string with the given parameters.
	 *
	 * @param string $key
	 * @param mixed ...$params 
	 *
	 * @return string
	 */
	public function translateString(string $key, ...$params) : string{
		
		$msg = $this->lang->getNested($key, null);
		if($msg === null){
			$this->getLogger()->error("Language key " . $key . " not found");
			return $key;
		}
		foreach($params as $i => $param){
			$msg = str_replace("{%" . $i . "}", $param, $msg);
		}
		$colors = (new \ReflectionClass(TextFormat::class))->getConstants();
		foreach($colors as $color => $code){
			$msg = str_ireplace("{" . $color . "}", $code, $msg);
		}
		return TextFormat::colorize($msg);
	}
	
	
	/**
	 * Get a config key.
	 *
	 * @param string $key4
	 * @param string $expectedType
	 *
	 * @return mixed|null null if not of expected type
	 */
	public function getConfigKey(string $key, string $expectedType = ""){
	    $value = $this->getConfig()->getNested($key);
	    $expected = $expectedType === "";
	    switch($expectedType){
	    	case "str":
	    	case "string":
	    	    $expected = is_string($value);
	    	    break;
	    	case "bool":
	    	case "boolean":
	    	    $expected = is_bool($value);
	    	    break;
	    	case "int":
	    	case "integer":
	    	    $expected = is_int($value);
	    	    break;
	    	case "float":
	    	    $expected = is_float($value) || is_int($value);
	    	    break;
	    	case "arr":
	    	case "array":
	    	    $expected = is_array($value);
	    	    break;
	    }
	    if(!$expected){
	    	$this->getLogger()->warning("Config key `" . $key . "` not of expected type " . $expectedType);
	    	return null;
	    }
	    return $value;
	}
	
}