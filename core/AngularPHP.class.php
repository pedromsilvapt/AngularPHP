<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

require('Config.class.php');
require(Config::$dirPath.'core\Decorator.class.php');
require(Config::$dirPath.'core\DependencyInjection.class.php');
require(Config::$dirPath.'core\Configuration\IConfigurable.interface.php');
require(Config::$dirPath.'core\Configuration\Configurable.trait.php');
require(Config::$dirPath.'core\Modules\Module.trait.php');
require(Config::$dirPath.'core\Modules\ModulesManager.class.php');
require(Config::$dirPath.'core\Modules\DependenciesManager.class.php');
require(Config::$dirPath.'modules\HelperFunctions.php');

class AngularPHP {
	
	private $_modulesManager;
	private $dependencyInjector;
	
	public function getDependenciesInjector(){
		return $this->dependencyInjector;
	}
	
	public function modulesManager(){
		return($this->_modulesManager);
	}
	
	public function execute($params, $function = null){
		if (is_callable($params)) return $this->dependencyInjector->injectDependencies($params);
		if (is_callable($function) && is_array($params)) return $this->dependencyInjector->injectDependencies($function, $params);
		
		return null;
	}
	
	public function __construct(){
		//Registers the dependency injector
		$this->dependencyInjector = new DependencyInjection();
		
		//Registers the default AppManager Provider
		$this->dependencyInjector->registerInjectionProvider('AngularPHP\AngularPHP', $this);
		
		//Loads the ModulesManager class
		$this->_modulesManager = new ModulesManager($this);
	}
}
?>