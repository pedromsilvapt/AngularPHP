<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class DefaultResource {
	protected $modulesManager;
	protected $resourcesManager;
	protected $requestMethod;
	protected $globalParams;
	protected $actionParams;
	
	
	public function mapActionFunctions(){
		//Maps each action to the corresponding method and the QueryString to the arguments
		//If return's false, then default methods will be used
		//Default methods are named <action-name>Name() and have no arguments
		//The arguments can be accessed through $globalParams and $actionParams
		return(false);
	}
	
	public function getActionMethod($actionName){
		//If the action name is invalid, return false
		if (trim($actionName) === '') return(false);
		//If there is a custom action-to-function map with this action's method specified, use it
		if (is_array($this->mapActionFunctions()) && isset($this->mapActionFunctions()[$actionName]['method']))
			return($this->mapActionFunctions()[$actionName]['method']);
		//Otherwise, just returns the default method name
		else if ($this->mapActionFunctions() === false) return($actionName.'Action');		
	}
	
	public function actionExists($actionName){
		$methodName = $this->getActionMethod($actionName);
		return(method_exists($this, $methodName));
	}
	
	public function getRequestedAction(){
		//This is the requested action name
		$actionName = '';
		
		//If no custom action is defined, then the default ones will be assumed
		if (empty($this->actionParams['action'])){
			//The switch chooses the appropriate action based on the Request Method
			switch($this->requestMethod){
				case 'POST':
					$actionName = 'save';
					break;
				case 'GET':
					if (isset($this->actionParams['isArray']) && $this->actionParams['isArray']) $actionName = 'query';
					else $actionName = 'get';
					break;
				case 'DELETE':
					$actionName = 'delete';
			}
		} else {
			//Otherwise sets the action to the requested one
			$actionName = $this->actionParams['action'];
		}
		
		return($actionName);
	}
	
	public function executeRequestedAction(){
		//Get's and executes the requested action
		$actionName = $this->getRequestedAction();
		$this->executeAction($actionName);
	}
	
	public function executeAction($actionName){
		//If such action does not exist, exits the function
		if (!$this->actionExists($actionName)) return(false);
		//Otherwise, gets the action's method name.
		$methodName = $this->getActionMethod($actionName);
		
		
		//If there are any custom mapped params to this action, then gets their values
		if ($this->mapActionFunctions() !== false && $this->mapActionFunctions()[$actionName]['arguments']){
			//Declares the params array
			$params = Array();
			//Loops through the mapping arguments
			foreach($this->mapActionFunctions()[$actionName]['arguments'] as $key){
				//Checks if the arguments are declared, and if so save them
				if (isset($this->globalParams[$key])) $params[] = $this->globalParams[$key];
				else if (isset($this->actionParams[$key])) $params[] = $this->actionParams[$key];
				//Otherwise exits the function
				else return;
			}
			//Calls the requested action's method with it's parameters
			call_user_func_array(array($this, $methodName), $params);
		} else {
			call_user_func(array($this, $methodName));
		}
	}
	
	public function __construct(ModulesManager $modulesManager, ResourcesModule $resourcesManager, $requestMethod, $globalParams, $actionParams){
		//Makes some initializations and set's some values
		$this->modulesManager = $modulesManager;
		$this->resourcesManager = $resourcesManager;
		$this->requestMethod = $requestMethod;
		$this->globalParams = $globalParams;
		$this->actionParams = $actionParams;
	}
}