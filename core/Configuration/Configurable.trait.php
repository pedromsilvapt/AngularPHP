<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

trait Configurable {	
	protected $_config;

	function config($moduleURL = null, $config = null){
		if ($moduleURL === null && $config === null) return $this->_config;
		
		if ($config === null) {
			$config = $moduleURL;
			if (is_string($config)) $config = array($config);
			
			$return = array();
			$count = 0;
			foreach($config as $key => $value){
				//Is only requesting the value
				if (is_integer($key)){
					$lastNode = &$this->_config;
					$lastStorageNode = &$return;
					$parts = explode('.', $value);
					$found = true;
					foreach($parts as $index => $segment){
						if (isset($lastNode[$segment]) && $found === true)
							$lastNode = &$lastNode[$segment];
						else
							$found = false;	
						
						if (!isset($lastStorageNode[$segment]))
							$lastStorageNode[$segment] = array();
						
						if (!isset($parts[$index + 1])){
							if ($found)
								$lastStorageNode[$segment] = $lastNode;
							else 
								$lastStorageNode[$segment] = null;
						} else
							$lastStorageNode = &$lastStorageNode[$segment];
					}

					$count++;
				} else {
					$lastNode = &$this->_config;
					$parts = explode('.', $key);
					foreach($parts as $index => $segment){
						if (!isset($lastNode[$segment]))
							$lastNode[$segment] = array();
						
						if (isset($parts[$index + 1]))
							$lastNode = &$lastNode[$segment];
						else 
							$lastNode[$segment] = $value;
					}
				}
			}
			if ($count == 1){
				$lastStorageNode = &$return;
				foreach($parts as $index => $segment){
					$lastStorageNode = &$lastStorageNode[$segment];
				}
				return $lastStorageNode;
			} else return $return;
		}
	}
}