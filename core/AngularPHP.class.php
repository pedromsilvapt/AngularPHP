<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

require(Config::$dirPath.'core\Decorator.class.php');
require(Config::$dirPath.'core\DependencyInjection.class.php');
require(Config::$dirPath.'core\ModulesManager.class.php');
require(Config::$dirPath.'modules\HelperFunctions.php');

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
		$this->dependencyInjector->registerInjectionProvider('AngularPHP', $this);
		
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