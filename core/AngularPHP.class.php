<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

require('Config.class.php');
require('Decorator.class.php');
require('DependencyInjection.class.php');
require('Configuration\IConfigurable.interface.php');
require('Configuration\Configurable.trait.php');
require('Modules\Module.trait.php');
require('Modules\ModulesManager.class.php');
require('Modules\DependenciesManager.class.php');
//require('..\Modules\HelperFunctions.php');

class AngularPHP {
	use Configurable;
	
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
		$this->_modulesManager = new ModulesManager(array('io.basePath' => dirname(__DIR__)));
	}
}
?>