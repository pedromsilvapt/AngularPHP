<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

require(Config::$dirPath.'core\Decorator.class.php');
require(Config::$dirPath.'core\DependencyInjection.class.php');
require(Config::$dirPath.'core\ModulesManager.class.php');

class AngularPHP {
	
	private $modulesManager;
	private $dependencyInjector;
	
	public function getDependenciesInjector(){
		return $this->dependencyInjector;
	}
	
	public function getModulesManager(){
		return($this->modulesManager);
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
		$this->modulesManager = new ModulesManager($this);
	}
}
?>