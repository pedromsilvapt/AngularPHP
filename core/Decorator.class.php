<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Decorator {
	protected static $_globalMethods;
	protected static $_staticMethods;
	protected $_methods;
	
	
	private static function _decorate(&$list, $name, $function, $injectThis = false){
		if (!is_string($name) || !is_callable($function)) return false;
		
		if (!isset($list[$name])) {
			$list[$name] = array('callback' => $function, 'injectThis' => $injectThis);
			return true;
		}
		var_dump(get_called_class());
		return false;
	}
	
	private static function _ifDecorated($list, $names, $callback = null){
		if (is_string($name))
			$names = array($names);
			
		if (!is_array($names)) return false;
		
		foreach($names as $name){
			foreach($list as $subList)
				if (!static::_isDecorated($subList, $name)) continue;
				
			return false;
		}
		
		if ($callback === null) return true;
		elseif (!is_callable($callback)) return false;
		
		return call_user_func($callback);
	}
	
	private static function _ifNotDecorated($list, $names, $callback = null){
		if ($callback === null)
			return !static::_ifDecorated($list, $names, null);
		
		if (!static::_ifDecorated($list, $names, null))
			return call_user_func($callback);
			
		return false;
	}
		
	
	public function decorate($name, $function, $injectThis = false){
		return $this->_decorate($this->_methods, $name, $function, $injectThis);
	}
	
	public static function decorateGlobal($name, $function, $injectThis = false){
		return static::_decorate(static::$_globalMethods[get_called_class()], $name, $function, $injectThis);
	}
	
	public static function decorateStatic($name, $function, $injectSelf = false){
		return $this->_decorate(static::$_staticMethods[get_called_class()], $name, $function, $injectSelf);
	}
	
	public function getDecorationCallback($name, $recursive = true){
		if ($this->getLocalDecorationCallback($name)) return $this->getLocalDecorationCallback($name);
		return static::getGlobalDecorationCallback($name, $recursive);
	}
	
	public function getLocalDecorationCallback($name){
		if (isset($this->_methods[$name])) return $this->_methods[$name];
		return false;
	}
	
	public static function getGlobalDecorationCallback($name, $recursive = true){
		do {
			if (!isset($class)) $class = get_called_class();
			else $class = get_parent_class($class);
			
			if (isset(static::$_globalMethods[$class][$name])) return static::$_globalMethods[$class][$name];
		} while ($class != 'AngularPHP\Decorator' && $recursive);
		
		return false;
	}
	
	public static function getSaticDecorationCallback($name, $recursive = true){
		do {
			if (!isset($class)) $class = get_called_class();
			else $class = get_parent_class($class);
			
			if (isset(static::$_staticMethods[$class][$name])) return static::$_staticMethods[$class][$name];
		} while ($class != 'AngularPHP\Decorator' && $recursive);
		
		return false;
	}
	
	public function isDecorated($name, $recursive = true){
		return $this->getDecorationCallback($name, $recursive) !== false;
	}
	
	public function isLocalDecorated($name){
		return $this->getLocalDecorationCallback($name) !== false;
	}
	
	public static function isGlobalDecorated($name, $recursive = true){
		return static::getGlobalDecorationCallback($name, $recursive);
	}
	
	public static function isStaticDecorated($name, $recursive = true){
		return static::getSaticDecorationCallback($name, $recursive) !== false;
	}
	
	public function ifDecorated($names, $callback = null){
		if (is_string($name))
			$names = array($names);
			
		if (!is_array($names)) return false;
		
		foreach($names as $name){
			if ($this->isDecorated($name)) continue;
				
			return false;
		}
		
		if ($callback === null) return true;
		elseif (!is_callable($callback)) return false;
		
		return call_user_func($callback);
	}
	
	public static function ifStaticDecorated($names, $callback = null){
		if (is_string($name))
			$names = array($names);
			
		if (!is_array($names)) return false;
		
		foreach($names as $name){
			if ($this->isStaticDecorated($name)) continue;
				
			return false;
		}
		
		if ($callback === null) return true;
		elseif (!is_callable($callback)) return false;
		
		return call_user_func($callback);
	}
	
	public function ifNotDecorated($names, $callback = null){
		if ($callback === null)
			return !$this->ifDecorated($names, null);
		
		if (!$this->ifDecorated($names, null))
			return call_user_func($callback);
			
		return false;
	}
	
	public static function _ifNotStaticDecorated($names, $callback = null){
		if ($callback === null)
			return !$this->ifStaticDecorated($names, null);
		
		if (!$this->ifStaticDecorated($names, null))
			return call_user_func($callback);
			
		return false;
	}
	
	public function __call($name, $arguments){
		$callback = $this->getDecorationCallback($name);
		if ($callback !== false){
			if ($callback['injectThis']) {
				$closure = $callback['callback']->bindTo($this, $this);
				return call_user_func_array($closure, $arguments);
			} else return call_user_func_array($callback['callback'], $arguments);
		}
		
		throw new \Exception('Calling to not static decorated method "'.$name.'" in object "'.get_called_class().'"', 404);
	}
	
	public static function __callStatic($name, $arguments){
		$callback = static::getSaticDecorationCallback($name);
		if ($callback !== false){
			if ($callback['injectThis']) {
				$closure = $callback['callback']->bindTo($this, $this);
				return call_user_func_array($closure, $arguments);
			} else return call_user_func_array($callback['callback'], $arguments);
		}
		
		throw new \Exception('Calling to not static decorated method "'.$name.'" in object "'.get_called_class().'"', 404);
	}

}