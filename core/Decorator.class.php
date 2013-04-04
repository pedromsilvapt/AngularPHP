<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Decorator {
	private $_methods;
	
	public function decorate($name, $function){		
		if (!is_string($name) || !is_callable($function)) return false;
		
		if (!isset($this->_methods[$name])) {
			$this->_methods[$name] = $function;
			return true;
		}
		return false;
	}
	
	public function isDecorated($name){
		return isset($this->_methods[$name]);
	}
	
	public function ifDecorated($names, $callback = null){
		if (is_string($name))
			$names = array($names);
			
		if (!is_array($names)) return false;
		
		foreach($names as $name)
			if (!$this->isDecorated($name)) return false;
		
		if ($callback === null) return true;
		elseif (!is_callable($callback)) return false;
		
		return call_user_func($callback);
	}
	
	public function ifNotDecorated($names){
		if (is_string($name))
			$names = array($names);
			
		if (!is_array($names)) return false;
		
		foreach($names as $name)
			if ($this->isDecorated($name)) return false;
		
		if ($callback === null) return true;
		elseif (!is_callable($callback)) return false;
		
		return call_user_func($callback);
	}
	
	public function __call($name, $arguments){
		if (isset($this->_methods[$name]))
			return call_user_func_array($this->_methods[$name], $arguments);
	}
}