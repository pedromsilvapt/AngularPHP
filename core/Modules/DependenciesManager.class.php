<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class DependenciesManager {
	private $_parent;
	private $loadedModules;
	
	public function __get($name){
		return $this->loadDependency($name, $name);
	}
	
	public function __set($name, $value){
		$this->loadDependency($value, $name);
	}
	
	public function __isset($name){
		return isset($this->loadedModules[$name]);
	}
	
	public function __unset($name){
		$this->loadedModules[$name]['promise']();
		unset($this->loadedModules[$name]);
	}
	
	public function loadDependency($mURL, $alias = null){
		if ($alias === null) $alias = $mURL;
		
		if (isset($this->loadedModules[$alias]))
			return($this->loadedModules[$alias]['instance']);
		else {
			$var = $this->_parent->load($mURL);
			if ($var === false || is_array($var)) return null;
			else {
				$this->loadedModules[$alias] = array('instance' => $var);
				$promise = $this->_parent->root()->when('unload', $mURL, function() use ($alias){
					$this->loadedModules[$alias]['promise']();
					unset($this->loadedModules[$alias]);
				});
				
				$this->loadedModules[$alias]['promise'] = $promise;
				return $var;
			}
		}
	}
	
	function __construct($parent){
		$this->_parent = $parent;
	}
}