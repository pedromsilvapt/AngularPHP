<?php
namespace AngularPHP\Modules\Routes;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Routes extends \AngularPHP\Module {
	
	private $uri;
	private $routes;
	private $defaultRoute;
	private $actions;
	private $currentRoute;
	
	public static function getDependencies(){
		return(Array('URI'));
	}
	
	
	private function makeArray($var){
		if (!is_array($var)){
			return(Array($var));
		}
		return($var);
	}
	
	private function actionMapToGet($params, $override = false){
		foreach ($params as $key => $value){
			if (!isset($_GET[$key]) || $override){
				$_GET[$key] = $value;
			}
		}
	}
	
	private function actionGoTo($params, $url){
		header('Location: '.$url);
		exit;
	}
	
	private function actionPrint($params, $message){
		echo $message;
	}
	
	private function actionPrintParams($params){
		print_r($params);
	}
	
	private function actionExecuteFunction($params, $function){
		call_user_func_array($function, $params);
	}
	
	
	public function set($setName){
		if (!is_string($setName)) return $this;
		
		$this->chainingInfo['setName'] = $setName;
		return $this;
	}
	
	public function when(){
		//Get's the number of arguments and the argument's array of this function
		$numargs = func_num_args();
		$params = func_get_args();
		
		//Initiates two variables
		$segments = Array();
		$count = 0;
		//Goes through each of the passed arguments to this function
		for ($i = 0; $i < $numargs; $i++) {
			//If it is a string, add's it to the $segments array
			if(is_string($params[$i])){
				$segments[] = $params[$i];
				$count++;
			}
		}
		//If there are more than zero segments
		if ($count > 0 && (!isset($this->chainingInfo['setName']))){
			//Join's them with a /
			$gluedSegments = implode('/', $segments);
			//And then checks if there is not any route with the same path
			if (!isset($this->routes[$gluedSegments])){
				$this->routes[$gluedSegments] = Array('segments' => $segments);
			}
			
			//For chaining purposes, set's the current route to this one
			$this->chainingInfo['currentRoute'] = $gluedSegments;
			$this->chainingInfo['setName'] = $gluedSegments;
		} else if ($count > 0){
			$this->routes[$this->chainingInfo['setName']] = Array('segments' => $segments);
		}
		
		//For chaining purposes, returns the object again
		return($this);
	}
	
	public function doThis($actions){
		//Does some parameters validations
		if ((!is_array($actions) && !is_callable($actions)) || ($this->chainingInfo['currentRoute'] == '' && !isset($this->chainingInfo['setName']))){
			return false;
		}
		if (!isset($this->routes[$this->chainingInfo['currentRoute']]) && !isset($this->routes[$this->chainingInfo['setName']])){
			return false;
		}
		
		if (is_array($actions)){
			//Adds the actions to the actions array of the selected route
			$this->routes[$this->chainingInfo['setName']]['actions'] = $actions;
		} else {
			//The user used a shortcut for the default-defined action function
			$this->routes[$this->chainingInfo['setName']]['actions'] = array('executeFunction' => $actions);
		}
		
		//Clears the current selected chaining route
		$this->chainingInfo['currentRoute'] = '';
		$this->chainingInfo['setName'] = '';
		
		return($this);
	}
	
	public function otherwise($actions){
		//Does some parameters validations
		if (!is_array($actions) && !is_callable($actions)){
			return(false);
		}
		
		if (is_array($actions)){
			//Adds the actions to the actions array of the selected route
			$this->routes[0]['actions'] = $actions;
		} else {
			//The user used a shortcut for the default-defined action function
			$this->routes[0]['actions'] = Array('executeFunction' => $actions);
		}
		
		//Clears the current selected chaining route
		$this->currentRoute = '';
		
		return($this);
	}
	
	public function linkTo($routeName, $args = array()){
		if (!isset($this->routes[$routeName])) return false;
		if (!is_array($args)) return false;
		
		$link = '';
		
		foreach ($this->routes[$routeName]['segments'] as $segment){
			//Checks if this segment is a parameter: if yes, acceps it
			if (substr($segment, 0, 1) == ':'){
				if (isset($args[substr($segment, 1)])){
					$link .= '/'.$args[substr($segment, 1)];
				} else break;
			//Tests if the segment on the URL matches the segment on the route
			} else  {
				$link .= '/'.$segment;
			}
		}
		
		return $link;
	}
	
	public function match(){
		$match = Array();
		//Checks every route for a match
		foreach($this->routes as $key => $value){
			if ($key === 0) continue;
			
			$match = $this->matchesRoute($key);
			//if $match isn't false (match was found) returns an array with the matching route and the params
			if ($match !== false) return(Array('route' => $key, 'params' => $match));
		}
		return(false);
	}
	
	public function matchesRoute($route){		
		//Checks if the route exists
		if (empty($this->routes[$route])) return(false);
		//Quick check to make sure the route has the same size as the URL, and so can eventualy match
		//echo $this->routes[$route]['segments']."<br />".
		if (count($this->routes[$route]['segments']) != $this->uri->getSegmentsCount()) return(false);
		
		//The position is the current position in the URL
		$pos = 0;
		//The params is an array with the values corresponding to the defined variables
		$params = Array();
		//Goes through each segment in the route
		foreach ($this->routes[$route]['segments'] as $segment) {
			//Checks if this segment is a parameter: if yes, acceps it
			if (substr($segment, 0, 1) == ':'){
				//And saves it one the $params array
				$params[substr($segment, 1)] = $this->uri->getSegment($pos);
			//Tests if the segment on the URL matches the segment on the route
			} else if ($segment !== $this->uri->getSegment($pos)) {
				return(false);
			}
			//Increases one position
			$pos++;
		}
		return($params);
	}
	
	public function goRoute($route, $urlParams = null){
		//Goes through all the actions defined by this route
		foreach($this->routes[$route]['actions'] as $action => $actionParams){
			//Checks if there is any function assigned to this callback
			if (isset($this->actions[$action])){
				//Merges the userdefined parameters for this action with the default array with the segments of the URL
				if ($urlParams !== null){
					$params = Array($urlParams);
					$params = array_merge($params, $this->makeArray($actionParams));
				} else {
					$params = array_merge(Array(Array()), $this->makeArray($actionParams));
				}
				//Calls the action's function
				call_user_func_array($this->actions[$action], $params);
			}
		}
	}
	
	public function go(){
		//Get's the matching route with the current URL
		$selectedRoute = $this->match();
		
		//If there is any matching route
		if ($selectedRoute !== false){
			//Executes it with the parameters
			$this->goRoute($selectedRoute['route'], $selectedRoute['params']);
		} else if (isset($this->routes[0])){
			//Otherwise, executes the default route if defined
			$this->goRoute(0);
		}
	}
	
	public function addAction($actionName, $actionCallback){
		//Some validation tests
		if (!is_string($actionName) || isset($this->actions[$actionName]) || (!is_array($actionCallback) && !is_string($actionCallback))){
			return(false);
		}
		
		//Adds the action to the actions array
		$this->actions[$actionName] = $actionCallback;
	}
	
	public function __construct(\AngularPHP\ModulesManager $modulesManager, \AngularPHP\Modules\URI\URI $uri){
		parent::__construct($modulesManager);
		$this->uri = $uri;
		$this->routes = Array();
		$this->defaultRoute = null;
		$this->actions = Array();
		$this->currentRoute = '';
		
		//Adds the main actions
		$this->addAction('executeFunction', array($this, 'actionExecuteFunction'));
		$this->addAction('mapToGet', array($this, 'actionMapToGet'));
		$this->addAction('goTo', array($this, 'actionGoTo'));
		$this->addAction('print', array($this, 'actionPrint'));
		$this->addAction('printParams', array($this, 'actionPrintParams'));
	}
}
