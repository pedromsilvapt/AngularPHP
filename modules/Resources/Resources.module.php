<?php
namespace AngularPHP\Modules\Resources;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Resources {
	use \AngularPHP\Module {
		\AngularPHP\Module::__construct as private __traitConstruct;
	}
	
	public function actionActivateResource($params, $resourceName){
		$resource = $this->load('/'.$resourceName, 'Resource', array('input.url' => $params));
		echo $this->encodeOutput($resource->executeRequestedAction());
	}
	
	public function encodeOutput($data){
		return json_encode($data);
	}
	
	public function factoryResource($moduleID, $moduleName, $moduleType, $className, $config = array()){		
		$temp = new $className($this, $moduleID, $moduleName, $moduleType, $config);
		return $temp;
	}
	
	public function __construct($parent, $moduleID, $moduleName, $moduleType, $config = array()){
		$this->__traitConstruct($parent, $moduleID, $moduleName, $moduleType, $config);
		
		//Includes the DefaultResource class
		require_once($this->parent()->getModuleSource('Resources', 'Module')['folder'].'\DefaultResource.class.php');
		
		//Create the Resource type
		$this->registerModulesType('resource', array($this, 'factoryResource'), array('suffixes' => 'resource', 'subnamespace' => 'Resources', 'subclass' => 'DefaultResource'));
		
		//Registers the default resource's directory
		$this->registerModulesDirectory($this->parent()->getModuleSource('Resources', 'Module')['folder'].'\resources', 'Resource');
		 //$this->parent()->getModuleSource('Resources', 'Module')['folder'].'\resources ';
		//Registers a callback for when Routes is loaded
		$this->when('load', '<$|Routes>', function($routes){
			//Registers the action on routes for 
			$routes->addAction('activateResource', array($this, 'actionActivateResource'));			
		});
	}
}

