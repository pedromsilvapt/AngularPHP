<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

require(Config::$dirPath.'core\Module.class.php');
require(Config::$dirPath.'core\ModulesManager.class.php');
require(Config::$dirPath.'modules\HelperFunctions.php');

class AngularPHP {
	
	private $modulesManager;
	private $injectionProviders;
	
	public function getModulesManager(){
		return($this->modulesManager);
	}
	
	/**
	* Because it could be that reflection parameter ->getClass() will try to load an class that isn't included yet
	* It could thrown an Exception, the way to find out what the class name is by parsing the reflection parameter
	* God knows why they didn't add getClassName() on reflection parameter.
	* http://stackoverflow.com/questions/4513867/php-reflection-get-method-parameter-type-as-string
	*
	* @param ReflectionParameter $reflectionParameter
	* @return string|null
	*/
	public function resolveParameterClassName(ReflectionParameter $reflectionParameter) {
		if ($reflectionParameter->isArray())
			return null;
		 
		try {
			// first try it on the normal way if the class is loaded then everything should go ok
			if($reflectionParameter->getClass()) 
				return $reflectionParameter->getClass()->name;
		// if the class isnt loaded it throws an exception
		} catch (Exception $exception) {
			// try to resolve it the ugly way by parsing the error message
			$parts = explode(' ', $exception->getMessage(), 3);
			return $parts[1];
		}
	}
	
	public function injectDependencies($function, $defaultParams = array()){
		//Instantiates some global variables
		$params = array();
		$methodR = "";
		$moduleReflection = null;
		
		//If it's just a function
		if (is_string($function) || (is_callable($function) && !is_array($function))){
			$methodR = new ReflectionFunction($function);
		//Or is a class method
		} elseif (is_array($function)){
			//If the array does not comply with the rules for a callable
			if (!isset($function[0]) || !isset($function[1])) return false;
			if ((!is_object($function[0]) && !is_string($function[0])) || !is_string($function[1])) return false;
			
			//Creates a reflection of the module
			$moduleReflection = new ReflectionClass($function[0]);
			//Checks if it's supposed to inject on a constructor or on some ordinary method
			if (is_string($function[0]) && $function[1] === '__construct') $methodR = $moduleReflection->getConstructor();
			else $methodR = $moduleReflection->getMethod($function[1]);
		} else return false;
		
		if (is_object($methodR)){
			$i = 0;
			//Goes trhough all the method's parameters
			foreach($methodR->getParameters() as $rp) {
				$i++;
				//If there is any default parameter defined for this argument use it
				if (isset($defaultParams[$rp->getName()])){
					//By parameter name
					$params[] = $defaultParams[$rp->getName()];
					continue;
				} elseif (isset($defaultParams[$i])){
					//By parameter index
					$params[] = $defaultParams[$i];
					continue;
				}
				
				//Get's the parameter's Type Hint.
				$cn = $this->resolveParameterClassName($rp);
				//If there is one
				if ($cn !== null){
					$result = false;
					if (isset($this->injectionProviders[$cn]) && is_object($this->injectionProviders[$cn])){
						$result = $this->injectionProviders[$cn];
					} else {
						//Now looks through the providers for one that fits
						foreach ($this->injectionProviders as $name => $provider){
							if (!is_callable($provider)) continue;
							
							$result = call_user_func($provider, $cn, $rp);
							//If one is found, stops the search
							if ($result !== false) break;
						}
					}
					//If no provider was found
					if ($result === false){
						//Tries to put a default value
						if ($rp->isDefaultValueAvailable())
							$params[] = $rp->getDefaultValue();
						else
							$params[] = null;
					} else 
						//Injects the resulting object
						$params[] = $result;
				}
			}
		}
		
		//After the parameters are all defined, checks which function to call
		if (is_string($function) || is_callable($function)){
			//If it's a function, calls it and returns it's value
			return call_user_func_array($function, $params);
		} elseif (is_array($function)){
			//If it's a constructor, creates the object and returns the object itself.
			if (is_string($function[0]) && $function[1] == '__construct'){
				return $moduleReflection->newInstanceArgs($params);
			} else {
				//Or if it's an ordinary method, calls it and returns the value
				return call_user_func_array($function, $params);
			}
		}
		
		return false;
	}
	
	public function registerInjectionProvider($name, $provider){
		//Checks the arguments
		if (!is_string($name) || (!is_callable($provider) && !is_object($provider))) return false;
		
		//Registers the provider in the array
		$this->injectionProviders[$name] = $provider;
	}
	
	public function execute($params, $function = null){
		if (is_callable($params)) return $this->injectDependencies($params);
		if (is_callable($function) && is_array($params)) return $this->injectDependencies($function, $params);
		
		return null;
	}
	
	public function __construct(){
		$this->injectionProviders = array();

		//Registers the default AppManager Provider
		$this->registerInjectionProvider('AngularPHP', $this);
		
		//Loads the ModulesManager class
		$this->modulesManager = new ModulesManager($this);
	}
	
	//Old Code - nothing works beneath here
	public function CheckPage(){
		global $URI, $smarty;
		
		$this->pageType = $URI->get_segment(0);
		if (empty($this->pageType))	{
			$this->pageType = 'home';
		}
		
		return $this->pageType;
	}
	
	public function buildPage(){
		global $dir_path, $http_path, $smarty, $Auth, $URI;
		
		if (func_num_args() == 0){
			if (empty($this->pageType)){
				$this->CheckPage();
			}
			$pageType = $this->pageType;
		} else {
				$pageType = func_get_arg(0);
		}
		
		$i = 1;
		
		while ($i < func_num_args()){
			$URI->set_segment($i, func_get_arg($i));
			$i++;
		}
		
		if (!file_exists($dir_path.'pages/'.$pageType.'.page.php')){
			return(false);
		}
		
		require_once($dir_path.'pages/'.$pageType.'.page.php');
		
		$pageReflection = new ReflectionClass($pageType.'_PAGE');
		if ($pageReflection->isSubclassOf('Page')){
			$pageType = $pageType.'_PAGE';
			$page = new $pageType;
			if ($page->isShowable() === true){				
				$page->beforeShow();
				$page->show();
			} else {
				header('HTTP/1.1 403 Forbidden');
			}
		} else {
			header('HTTP/1.1 404 Not Found');
		}
	}
	
	public function checkRequest(){
		global $URI, $dir_path;
		
		if ($URI->get_segment(0) == ''){
			$this->requestType = 'connect';
		} else {
			$this->requestType = $URI->get_segment(0);
		}
		
		return($this->requestType);
	}
	
	public function executeRequest($reqType = ''){
		global $URI, $dir_path;
		
		if ($reqType == ''){
			if (empty($this->requestType)){
				if ($this->checkRequest() === false){
					return(false);
				}
			}
			$reqType = $this->requestType;
		}
		
		if (!file_exists($dir_path.'ajax/'.$reqType.'.ajax.php')){
			header('HTTP/1.1 404 Not Found');
		}
		
		require_once($dir_path.'ajax/'.$reqType.'.ajax.php');
		
		$requestReflection = new ReflectionClass($reqType.'_AJAX');
		if ($requestReflection->isSubclassOf('AJAXRequest')){
			$reqType = $reqType.'_AJAX';
			$request = new $reqType;
			if ($request->isCallable() === true){
				return($request->execute());
			} else {
				header('HTTP/1.1 403 Forbidden');
			}
		} else {
			header('HTTP/1.1 404 Not Found');
		}
	}

}
?>