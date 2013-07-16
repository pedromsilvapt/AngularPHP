<?php
namespace AngularPHP\Modules\Resources;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

abstract class DefaultResource {
	use \AngularPHP\Module {
		\AngularPHP\Module::__construct as private __traitConstruct;
	}
	
	protected $httpErrors = array();
	
	private function objectToArray($d) {
		if (is_object($d)) {
			// Gets the properties of the given object with get_object_vars function
			$d = get_object_vars($d);
		}

		if (is_array($d)) {
			// Return array converted to object. Using __FUNCTION__ (Magic constant) for recursive call
			return array_map(array($this, 'objectToArray'), $d);
		} else {
			// Return array
			return $d;
		}
	}
	
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
		if ($this->config('input.action.action') === null){
			//The switch chooses the appropriate action based on the Request Method
			switch($this->config('input.requestMethod')){
				case 'POST':
					$actionName = 'save';
					break;
				case 'GET':
					if ($this->config('input.action.isArray') !== null && $this->config('input.action.isArray')) $actionName = 'query';
					else $actionName = 'get';
					break;
				case 'DELETE':
					$actionName = 'delete';
			}
		} else {
			//Otherwise sets the action to the requested one
			$actionName = $this->config('input.action.action');
		}
		
		return($actionName);
	}
	
	public function executeRequestedAction(){
		//Get's and executes the requested action
		$actionName = $this->getRequestedAction();
		
		return $this->executeAction($actionName);
	}
	
	public function executeAction($actionName){
		//If such action does not exist, exits the function
		if (!$this->actionExists($actionName)) return(false);
		//Otherwise, gets the action's method name.
		$methodName = $this->getActionMethod($actionName);
		
		
		//If there are any custom mapped params to this action, then gets their values
		if ($this->mapActionFunctions() !== false && $this->mapActionFunctions()[$actionName]['arguments']){
			//Declares the params array
			$params = array();
			//Loops through the mapping arguments
			$mappedArguments = $this->mapActionFunctions()[$actionName]['arguments'];
			foreach($this->mapActionFunctions()[$actionName]['arguments'] as $value){
				unset($var);
				unset($default);
				unset($type);
				$hasDefault = false;
				if (is_array($value)) { 
					$var = $value[0];
					$default = $value[1];
					$hasDefault = true;
					if (isset($value[2])) $type = $value[2];
				}
				else $var = $value;
				//Checks if the arguments are declared, and if so save them
				if (isset($this->config('input.url')[$var])) $value = $this->config('input.url')[$var];
				else if (isset($this->config('input.action')[$var])) $value = $this->config('input.action')[$var];
				//Otherwise exits the function
				else {
					if ($hasDefault) $value = $default;
					else return array($var);
				}
				if (isset($type)) $this->root()->setType($value, $type);
				$params[] = $value;
			}
			//Calls the requested action's method with it's parameters
			return call_user_func_array(array($this, $methodName), $params);
		} else {
			return call_user_func(array($this, $methodName));
		}
	}
	
	public function __construct($parent, $moduleID, $moduleName, $moduleType, $config = array()){				
		$this->__traitConstruct($parent, $moduleID, $moduleName, $moduleType, $config);
		//Default configurations
		//Maps each of the GET variables to the $actionParams
		if ($this->config('input.action') === null){
			$action = array();
			foreach($_GET as $key => $value)
				$action[$key] = $value;
			
			$this->config(array('input.action' => $action));
		}
		//Put's the URL information
		if ($this->config('input.url') === null){
			$this->di->uri = '*|URI';
			$this->config(array('input.url' => $this->di->uri->getSegments()));
		}
		//Creates the data array
		if ($this->config('input.data') === null){
			$data = file_get_contents("php://input");
			$data = $this->objectToArray(json_decode($data));
			if (empty($data)) $data = array();
			$this->config(array('input.data' => $data));
		}
		//Set's the default request method
		if ($this->config('input.requestMethod') === null) $this->config(array('input.requestMethod' => $_SERVER['REQUEST_METHOD']));
		
		//$this->addHTTPError(404	);
	}
}