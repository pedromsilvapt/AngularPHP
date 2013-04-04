<?php
namespace AngularPHP\Modules\Resources;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Resources extends \AngularPHP\Module {
	
	private $resourcesDirectories;
	private $resourcesFiles;
	
	
	public function actionActivateResource($params, $resourceName){
		$this->requestResource($resourceName, $params);
	}
	
	public function registerResourcesDirectory($directoryPath){
		//Checks if the parameter is empty or is not a string
		if (empty($directoryPath) || !is_string($directoryPath)) return(false);
		
		//Registers the directory in the array
		$this->resourcesDirectories[] = $directoryPath;
	}
	
	public function registerResourceFiles($files){
		//Checks if the parameter is empty or isn't either a string nor an array
		if (empty($files) || (!is_string($file) && !is_array($file))) return(false);
		
		//If ir's a string, just adds it to the files array
		if (is_string($files)){
			$this->resourcesFiles[] = $files;
		} else {
			//Otherwise loops through the array
			foreach($files as $file){
				//And if each $file in the array is a string, adds it to the files array
				if (is_string($file)){
					$this->resourcesFiles[] = $file;
				}
			}
		}
		return(true);
	}
	
	public function getResourceDirectory($resourceName){
		//ResourceFileName
		$rFN = $resourceName.'.resource.php';
		
		//First loops throgh the directories
		foreach($this->resourcesDirectories as $folder){
			//And checks if each one of them is really a directory and has the file inside
			if (file_exists($folder.$rFN) && is_dir($folder)){
				//If true, returns the current folder's path
				return($folder);
			}
		}
		//If no resource was found in the folders, loops through each file
		foreach($this->resourcesFiles as $file){
			//If the filename matches the required filename for a resource file and if it exists
			if (file_exists($file) && endsWith($file, '\\'.$rFN)){
				//Returns the directory of the file
				return(trimOffEnd($rFN, $file));
			}
		}
		//Otherwise, if no resource was found, returns false
		return(false);
	}
	
	public function resourceExists($resourceName){
		return(!($this->getResourceDirectory($resourceName) === false));
	}
	
	public function requestResource($resourceName, $globalParams){
		//Get's the resource's directory and checks if the resource exists
		$directoryPath = $this->getResourceDirectory($resourceName);
		if ($directoryPath === false) return(false);
		
		//Includes the file with the resource
		require_once($directoryPath.'\\'.ucfirst($resourceName).'.resource.php');
		
		//Creates a reflection class to check on for the resource
		$resourceReflection = new \ReflectionClass(ucfirst($resourceName).'Resource');
		if ($resourceReflection->isSubclassOf('DefaultResource')){
			//Creates a string with the name of the resource's class
			$resourceType = $resourceName.'Resource';
			
			//Maps each of the GET variables to the $actionParams
			$actionParams = array();
			foreach($_GET as $key => $value)
				$actionParams[$key] = $value;
			
			//Creates a new object of the resource and passes the parameters
			$resource = $this->appManager->getDependenciesInjector()->injectDependencies(array($resourceType, '__construct'), array(
				'requestMethod' => $_SERVER['REQUEST_METHOD'],
				'globalParams' => $globalParams,
				'actionParams' => $actionParams)
			);
			$resource->executeRequestedAction();
			return(true);
		}
		return(false);
	}
	
	public function __construct(\AngularPHP\ModulesManager $modulesManager, \AngularPHP\AngularPHP $appManager){
		parent::__construct($modulesManager);
		$this->appManager = $appManager;
		
		//Makes some initializations
		$this->resourcesDirectories = Array();
		$this->resourcesFiles = Array();
		
		//Includes the DefaultResource class
		require_once($modulesManager->getModuleDirectory('Resources').'DefaultResource.class.php');
		
		//Registers the default resource's directory
		$this->registerResourcesDirectory($modulesManager->getModuleDirectory('Resources').'resources\\');
		
		//Registers a callback for when Routes is loaded
		$this->modulesManager->when('Routes', function(AngularPHP\Modules\Routes\Routes $routes){
			//Registers the action on routes for 
			$routes->addAction('activateResource', array($this, 'actionActivateResource'));			
		});
	}
}

