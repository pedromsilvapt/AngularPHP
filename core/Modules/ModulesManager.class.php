<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class ModulesManager {
	use Module {
		Module::__construct as private __traitConstruct;
	}
	
	protected function factoryModule($moduleID, $moduleName, $moduleType, $className, $config){
		$temp = new $className($this, $moduleID, $moduleName, $moduleType, $config);
		return $temp;
	}
	
	function __construct(){
		$this->__traitConstruct(null, 'ModulesManager', 'manager');
		
		$this->registerModulesType('module', array($this, 'factoryModule'), ['suffixes' => 'module', 'ownnamespace' => true, 'subnamespace' => 'Modules'], 'C:\wamp\www\angular-php2\modules');
	}
}