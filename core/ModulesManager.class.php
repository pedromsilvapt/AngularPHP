<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

function beginsWith($str, $sub) {
    return(substr($str, 0, strlen($sub)) == $sub);
}

function endsWith($str, $sub) {
    return(substr($str, strlen($str) - strlen($sub)) == $sub);
}

function trimOffEnd($off, $str) {
    if(is_numeric($off))
        return(substr($str, 0, strlen($str) - $off));
    else
        return(substr($str, 0, strlen($str) - strlen($off)));
}

class ModulesManager {
	private $appManager;
	private $modules;
	private $modulesDirectories;
	private $modulesCallbacks;
	private $callbacksModules;
	private $lastIndex;
	
	private function refreshModuleCallbacks($moduleName){
		//If there are not registered callbacks for this module, exits
		if (empty($this->modulesCallbacks[$moduleName])) return false;
		
		//Goes trhough all the callbacks defined for this module
		foreach($this->modulesCallbacks[$moduleName] as $index){
			//Takes the module out of the missing list
			unset($this->callbacksModules[$index]['missing'][$moduleName]);
			
			//The checks if this callback has all modules it needs
			if (!count($this->callbacksModules[$index]['missing'])){
				//If yes, call's the defined callback
				$this->appManager->injectDependencies($this->callbacksModules[$index]['callback']);
				//And removes it completly from the list
				unset($this->callbacksModules[$index]);
			}
		}
		
		//now removes the module's callback list
		unset($this->modulesCallbacks[$moduleName]);
	}
		
	public function modulesInjector($classType, $parameterReflection){
		if (endsWith($classType, 'Module') && $classType != 'Module'){
			$moduleName = trimOffEnd('Module', $classType);
			
			$m = $this->getModule($moduleName, !$parameterReflection->isDefaultValueAvailable());
			return $m;
		}
		return false;
	}
	
	public function getModuleDirectory($moduleName, $suffixSlash = true){
		if (empty($moduleName) || !is_string($moduleName) || !is_bool($suffixSlash)){
			return(false);
		}
		//If there is already any cached information about this module's directory, use it
		if (isset($this->modulesDirectories[$moduleName])) return $this->modulesDirectories[$moduleName];
		
		$return = false;
		//Checks if this module uses a custom directory of his own
		if (file_exists(Config::$dirPath.'modules\\'.$moduleName.'\\'.$moduleName.'.module.php')){
			$return = Config::$dirPath.'modules\\'.$moduleName.($suffixSlash ? '\\' : '');
		//or if it is just a single file
		} else if (file_exists(Config::$dirPath.'modules\\'.$moduleName.'.module.php')){
			$return = Config::$dirPath.'modules'.($suffixSlash ? '\\' : '');
		}
		
		//Stores the information on cache
		$this->modulesDirectories[$moduleName] = $return;
		return $return;
	}
	
	public function moduleExists($moduleName){
		return(!($this->getModuleDirectory($moduleName) === false));
	}
	
	public function isModuleLoaded($moduleName){
		return isset($this->modules[$moduleName]);
	}
	
	public function checkForDependencies($moduleType){
		//Calls and saves the dependencies for the module
		$dependencies = call_user_func(array($moduleType, 'getDependencies'));
		//If it's not an array, exists the function
		if (!is_array($dependencies)) return(true);
		
		//Loops through the dependencies and checks if they are not loaded
		foreach($dependencies as $dep)
			if (!$this->isModuleLoaded($dep)) return(false);
		
		return(true);
	}
	
	public function loadDependencies($moduleType, $parentCalls){
		//Get's the directory for this module
		$moduleDirectory = $this->getModuleDirectory($moduleType);
		if ($moduleDirectory === false) return false;
		
		$moduleDFile = $moduleDirectory.$moduleType.'.dependencies.php';
		
		if (file_exists($moduleDFile)){
			//Includes the file
			require_once($moduleDFile);
			
			//Calls and saves the dependencies for the module
			$moduleClass = $moduleType.'Dependencies';
			$dependenciesDeclarator = new $moduleClass();
			$dependencies = $dependenciesDeclarator->getDependencies();
			//If it's not an array, exists the function
			if (!is_array($dependencies)) return(false);
			
			//Defines this module as loaded
			$parentCalls[$moduleType] = true;
			
			//Loops through the dependencies and checks if they are not loaded
			foreach($dependencies as $dep)
				if ((isset($parentCalls[$dep]) && $parentCalls[$dep]) || $this->_loadModuleAs($dep, $dep, $parentCalls) === false) return(false);
		}
		
		return(true);
	}
		
	
	private function _loadModuleAs($moduleName, $baseModuleName, $parentCalls){
		//If the module is already loaded
		if ($this->isModuleLoaded($moduleName)){
			return(false);
		}
		//Gets the module's directory
		$moduleDir = $this->getModuleDirectory($baseModuleName);
		//If there is not such module, exits the function
		if (!$moduleDir){
			return(false);
		}
		//If not all module's dependencies could be loaded, exits the function
		if ($this->loadDependencies($moduleName, $parentCalls) === false) return false;
		
		//Rqeuires the module file
		require_once($moduleDir.$baseModuleName.'.module.php');
		require_once($moduleDir.$moduleName.'.module.php');
		
		//Creates a Reflection object for that module
		$moduleReflection = new ReflectionClass(ucfirst($baseModuleName).'Module');
		//And checks if it inherits the Module class
		if ($moduleReflection->isSubclassOf('Module') && ($moduleName === $baseModuleName || $moduleReflection->isSubclassOf($moduleName.'Module'))){
			$moduleType = $baseModuleName.'Module';
			
			//Creates the module injecting the dependencies
			$this->modules[$moduleName] = $this->appManager->injectDependencies(array($moduleType, '__construct'));
				
			//Calls the callbacks that have been waiting for this module
			$this->refreshModuleCallbacks($moduleName);
				
			return($this->modules[$moduleName]);
		}
		return(false);
	}
	
	public function loadModuleAs($moduleName, $baseModuleName){
		return call_user_func(array($this, '_loadModuleAs'), $moduleName, $baseModuleName, array($moduleName => true));
	}
	
	public function loadModule($moduleName){
		return call_user_func(array($this, '_loadModuleAs'), $moduleName, $moduleName, array($moduleName => true));
		//return call_user_func_array(array($this, '_loadModuleAs'), array_merge(array($moduleName), func_get_args()), array($moduleName => true));
	}
	
	public function getModule($moduleName, $autoload = false){
		if (!$this->isModuleLoaded($moduleName)){
			if ($autoload){
				$this->loadModule($moduleName);
			} else {
				return(false);
			}
		}
		
		return($this->modules[$moduleName]);
	}
	
	public function when($modules, $callback){
		//If the module is not a string nor an array, exits
		if (!is_string($modules) && !is_array($modules)) return;
		
		//If it is a string, converts it to an array
		if (is_string($modules)){
			$modules = Array($modules);
		} else {
			$modules = array_unique($modules);
		}
		
		//Get's the index of the new callbacks
		$index = $this->lastIndex;
		//Goes through all the modules specified
		foreach ($modules as $module){
			//If not a string, skips this module
			if (!is_string($module)) continue;
			
			//If the module is not loaded, adds it to the waiting list
			if (!$this->isModuleLoaded($module)){
				//If there isn't any list created for this callback, creates the Array
				if (empty($missingModules)){
					$missingModules = Array($module => false);
				} else {
					$missingModules[$module] = false;
				}
				
				//If there is no callback registered to this module, creates the array
				if (isset($this->modulesCallbacks[$module])){
					$this->modulesCallbacks[$module][] = $index;
				} else {
					$this->modulesCallbacks[$module] = Array($index);
				}
			}
		}
		
		//If there are any modules unloaded
		if (isset($missingModules)){
			//Stores the callback in the arrays
			$this->callbacksModules[$index] = Array(
				'missing' => $missingModules,
				'callback' => $callback
			);
			//Increments one to the next callback id
			$this->lastIndex++;
		} else {
			//Otherwise, if all modules are loaded, just calls the callback
			$this->appManager->injectDependencies($callback);
		}
	}
	
	public function ifLoaded($modules, $callback){
		if (!is_string($modules) && !is_array($modules) || !is_callable($callback)) return;
		
		if (is_string($modules))
			$modules = Array($modules);
		
		foreach($modules as $module) {
			if (!$this->isModuleLoaded($module)) return;
		}
		
		return $this->appManager->injectDependencies($callback);
	}
	
	public function ifNotLoaded($modules, $callback){
		if (!is_string($modules) && !is_array($modules) || !is_callable($callback)) return;
		
		if (is_string($modules))
			$modules = Array($modules);
		
		foreach($modules as $module)
			if ($this->isModuleLoaded($module)) return;
		
		return $this->appManager->injectDependencies($callback);
	}
	
	function __construct($appManager){
		$this->appManager = $appManager;
		$this->modules = Array();
		$this->modulesDirectories = Array();
		$this->callbacksModules = Array();
		$this->modulesCallbacks = Array();
		$this->lastIndex = 0;
		
		require_once(Config::$dirPath.'core\Module.class.php');
		require_once(Config::$dirPath.'core\DependenciesDeclarator.class.php');
		
		$this->appManager->registerInjectionProvider('ModulesManager', $this);
		$this->appManager->registerInjectionProvider('Modules', array($this, 'modulesInjector'));
	}
}