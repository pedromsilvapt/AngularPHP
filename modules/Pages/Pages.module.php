<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class PagesModule extends Module {
	
	private $pagesDirectories;
	private $pagesFiles;
	private $appManager;
	
	public static function getDependencies(){
		return(Array());
	}
	
	
	public function actionRequestPage($params, $pageName, $action = null, $customParams = array()){
		$this->requestPage($pageName, $action, array_merge($params, $customParams));
	}
	
	public function registerPagesDirectory($directoryPath){
		//Checks if the parameter is empty or is not a string
		if (empty($directoryPath) || !is_string($directoryPath)) return(false);
		
		//Registers the directory in the array
		$this->pagesDirectories[] = $directoryPath;
	}
	
	public function registerPageFiles($files){
		//Checks if the parameter is empty or isn't either a string nor an array
		if (empty($files) || (!is_string($file) && !is_array($file))) return(false);
		
		//If it's a string, just adds it to the files array
		if (is_string($files)){
			$this->pagesFiles[] = $files;
		} else {
			//Otherwise loops through the array
			foreach($files as $file){
				//And if each $file in the array is a string, adds it to the files array
				if (is_string($file)){
					$this->pagesFiles[] = $file;
				}
			}
		}
		return(true);
	}
	
	public function getPagePath($pageName, $includeFileName = false){
		//ResourceFileName
		$pFN = $pageName.'.page.php';
		
		//First loops throgh the directories
		foreach($this->pagesDirectories as $folder){
			//And checks if each one of them is really a directory and has the file inside
			if (file_exists($folder.$pFN) && is_dir($folder)){
				//If true, returns the current folder's path
				return($folder);
			}
		}
		//If no resource was found in the folders, loops through each file
		foreach($this->pagesFiles as $file){
			//If the filename matches the required filename for a resource file and if it exists
			if (file_exists($file) && endsWith($file, '\\'.$pFN)){
				//Returns the directory of the file
				return(trimOffEnd($pFN, $file));
			}
		}
		//Otherwise, if no resource was found, returns false
		return(false);
	}
	
	public function pageExists($pageName){
		return($this->getPagePath($pageName) !== false);
	}
	
	public function requestPage($pageName, $action = null, $params = array()){
		//Get's the resource's directory and checks if the resource exists
		$directoryPath = $this->getPagePath($pageName);
		if ($directoryPath === false) return(false);
		
		
		//Includes the file with the resource
		require_once($directoryPath.'\\'.ucfirst($pageName).'.page.php');
		
		//Creates a reflection class to check on for the resource
		$pageReflection = new ReflectionClass(ucfirst($pageName).'Page');
		if ($pageReflection->isSubclassOf('Page')){
			//Creates a string with the name of the resource's class
			$pageType = ucfirst($pageName).'Page';
			
			//Creates the custom parameters array
			$arr = array(
				'action' => $action,
				'params' => $params
			);
			
			//Creates a new object of the resource and passes the parameters
			$page = $this->appManager->injectDependencies(array($pageType, '__construct'), $arr);
			//If the page can be shown
			if ($this->appManager->injectDependencies(array($page, 'canBeShown'), $arr)){
				//Executes the default functions
				$this->appManager->injectDependencies(array($page, 'head'), $arr);
				//If any action has been defined, executes the action function and such action exists
				if (method_exists($page, $action.'Action') && $action != null && $action != '')
					$this->appManager->injectDependencies(array($page, $action.'Action'), $arr);
				else
					$this->appManager->injectDependencies(array($page, 'body'), $arr);
				$this->appManager->injectDependencies(array($page, 'footer'), $arr);
			}
			return($page);
		}
		return(false);
	}

	public function __construct(ModulesManager $modulesManager, AngularPHP $appManager){
		parent::__construct($modulesManager);
		$this->appManager = $appManager;
		
		//Makes some initializations
		$this->resourcesDirectories = Array();
		$this->resourcesFiles = Array();
		
		//Includes the DefaultResource class
		require_once($modulesManager->getModuleDirectory('Pages').'Page.class.php');
		
		//Registers the default resource's directory
		$this->registerPagesDirectory($modulesManager->getModuleDirectory('Pages').'pages\\');
		
		//Registers a callback for when Routes is loaded
		$this->modulesManager->when('Routes', function(RoutesModule $routes){
			//Registers the action on routes for 
			$routes->addAction('requestPage', array($this, 'actionRequestPage'));			
		});
	}
}