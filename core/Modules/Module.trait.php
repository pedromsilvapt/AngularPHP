<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

function beginsWith($str, $sub) {
    return(substr($str, 0, strlen($sub)) == $sub);
}

function endsWith($str, $sub) {
    return(substr($str, strlen($str) - strlen($sub)) == $sub);
}

function trimOffFront($off, $str) {
    if(is_numeric($off))
        return substr($str, $off);
    else
        return substr($str, strlen($off));
}

function trimOffEnd($off, $str) {
    if(is_numeric($off))
        return(substr($str, 0, strlen($str) - $off));
    else
        return(substr($str, 0, strlen($str) - strlen($off)));
}


trait Module {
	use Configurable;

	//JUST_FILES = 0;
	//JUST_FOLDERS = 1;
	//FILES_FOLDERS = 2;

	protected $_parent = null;
	protected $_root = null;
	protected $_cacheParent = -1;
	
	protected $_moduleName;
	protected $_moduleType;
	protected $_instanceName;
	
	protected $subModulesTypes = array();
	protected $subModules = array();
	protected $subModulesByType = array();
	protected $allowComplexHierarchies = false;
	protected $subModulesFileSystem = 2;
	protected $currentNamespace;
	
	protected $aliases = array();
	protected $_aliasesAutoIncrement = 0;
	
	protected $di;
	
	protected $watchersAI = 0;
	protected $watchers = array();
	protected $watchersByAction = array();
	protected $watchersCache = array();
	
	//This one is DEPRECATED
	protected $pathRegExpression = "/^(((<?)([a-zA-Z0-9\$=\*\^\-\?#!@&%«»~\"]\|?)*[a-zA-Z0-9](>?))*(:?))*$/";	
	
	protected $subModulesSources = array();
	
	protected function registerModulesSourceType($sourceType, $resolver){
		if (!is_string($sourceType)) return false;
		if ($this->isModulesSourceTypeRegistered($sourceType)) return false;
		if (!is_subclass_of($resolver, 'IModulesProvider')) return false;
		
		$this->subModulesSources[$sourceType] = array(
			'name' => $sourceType,
			'resolver' => $resolver,
		);
		return true;
	}
	
	public function isModulesSourceTypeRegistered($sourceType){
		return isset($this->subModulesSources[$sourceType]);
	}
	//End DEPRECATED
	
	
	protected $moduleNameRegExpression = "[a-zA-Z][a-zA-Z0-9]*";
	protected $flagNameRegExpression = "([a-zA-Z0-9\$=\*\^\-\?#!@&%\\«»~\"])*";
	
	
	public function root() {
		return $this->_root;
	}
	
	public function parent($pos = 0){
		if (!is_integer($pos)) $pos = 0;
		
		for ($i = $this->_cacheParent + 1; $i <= $pos && $this->parent($pos - 1)->parent(); $i++)
				$this->_parent[$pos] = $this->parent($pos - 1)->parent();
		
		if (isset($this->_parent[$pos])) return $this->_parent[$pos];
		else return null;
	}
	
	public function name(){
		return $this->_instanceName;
	}
	
	public function moduleName(){
		return $this->_moduleName;
	}
	
	public function moduleType(){
		return $this->_moduleType;
	}
	
	public function getNamespace(){
		return substr(get_class($this), 0, strrpos(get_class($this), '\\'));;
	}
	
	public function parseMURL($mURL, $supportGenerics = true, $customGenericFlags = array(), $returnOffset = 0, $throwException = false){
		//First of all checks if the supplied mURL is already an array
		if (is_array($mURL)){
			//For being a valid one, it must at least contain a "segments" member
			if (!isset($mURL['segments']) || !is_array($mURL['segments'])) return false;
			
			//Now, if no offset is supplied, returns the array as it is
			if ($returnOffset == 0) return $mURL;
			//Otherwise, slices it first and only then returns it
			else return array_slice($mURL['segments'], $returnOffset);
		}
		//If not a string, returns false
		if (!is_string($mURL)){
			if ($throwException) throw new Exception('Expected string or array for \'mURL\'.');
			else return false;
		}
		//Also, expects the offset to be an integer
		if (!is_integer($returnOffset)){
			if ($throwException) throw new Exception('Expected integer for \'returnOffset\' value.');
			else return false;
		}
		//And finally expects the customGenericFlags to be an array
		if (!is_array($customGenericFlags)){
			if ($throwException) throw new Exception('Expected array for \'customGenericFlags\' value.');
			else return false;
		}
		
		//Initializes the final array
		$parsed = array('absolute' => true, 'rawInput' => $mURL, 'hasGenerics' => false, 'segments' => array());
		
		if (isset($mURL[0]) && $mURL[0] == '/'){
			$parsed['absolute'] = false;
			$mURL = trimOffFront(1, $mURL);
		}
		
		//First explodes all the modules separated by :
		$matches = explode(':', $mURL);
		
		$i = 0;
		foreach ($matches as $index => $value){		
			//Now that we've checked the value to be minimal valid, we can create it's position in the final array
			$parsed['segments'][$i] = array('rawValue' => $value, 'name' => '', 'generic' => false, 'backwards' => false, 'flags' => array());
						
			//if ($throwException) throw new Exception('MURL Parse error: empty member at position '.$index.'.');
			//else return false;
			
			//Now checks if this a generic type, rather than a defined module name
			if (strlen($value) > 2 && ($value[0] == '<' || $value[strlen($value)-1] == '>')){
				//If it has both the opening and closing character, it keeps fine
				if ($value[0] == '<' && $value[strlen($value)-1] == '>') {
					if ($supportGenerics){
						$parsed['segments'][$i]['generic'] = true;
						$parsed['hasGenerics'] = true;
						//For the name, takes out the < and the > at the beginning and ending
						$parsed['segments'][$i]['name'] = substr($value, 1, -1);
					} else {
						if ($throwException) throw new Exception('MURL Parse error: no generics allowed in this parsing at position '.$index.'.');
						else return false;
					}
				//Otherwise throws an error
				} else {
					if ($throwException) throw new Exception('MURL Parse error: only one < or > found, expecting both or none at position '.$index.'.');
					else return false;
				}
			//Else, it's just a hardcoded module name
			} else {
				$parsed['segments'][$i]['generic'] = false;
				$parsed['segments'][$i]['name'] = $value;
			}
			
			//Now parses the flags by splitting it by each |
			$flags = explode('|', $parsed['segments'][$i]['name']);
			//If only one position is found, it means no flags
			if (!isset($flags[1])){
				$flags = array();				
			} else {
				//Otherwise, set's the name as the last item
				$parsed['segments'][$i]['name'] = $flags[count($flags) - 1];
				//And the flags as the rest
				$flags = array_slice($flags, 0, -1);
			}
			
			//Now goes through each flag
			foreach($flags as $indexFlag => $eachFlag){
				//If it's just duplicated, it's fine, let's just move on
				if (isset($parsed['segments'][$i]['flags'][$eachFlag]) && $parsed['segments'][$i]['flags'][$eachFlag]) continue;
				
				//Now checks if the flag respects the RexEx
				if (preg_match('/\A'.$this->flagNameRegExpression.'\z/', $eachFlag) === 1)
					//If yes, set's this flag as true
					$parsed['segments'][$i]['flags'][$eachFlag] = true;
				else {
					//Otherwise, throws an error
					if ($throwException) throw new Exception('MURL Parse error: unacceptable flag at position '.$indexFlag.', global position '.$index.'.');
					else return false;
				}
			}
			
			//Finally, checks if the module name itself respects the RegEx
			//If no module was supplied
			if ($parsed['segments'][$i]['name'] == '..'){
				$parsed['segments'][$i]['backwards'] = true;
				unset($parsed['segments'][$i]['name']);
			} else if ($parsed['segments'][$i]['name'] == ''){
				$parsed['hasGenerics'] = true;
				$parsed['segments'][$i]['generic'] = true;
			} else if (preg_match('/\A'.$this->moduleNameRegExpression.'\z/', $parsed['segments'][$i]['name']) !== 1){
				//If not, throws an error too
				if ($throwException) throw new Exception('MURL Parse error: unacceptable module name at position '.$index.' value.');
				else return false;
			}
			
			$i++;
		}

		//Now that everything is checked, either returns the full parsed array
		if ($returnOffset == 0) return $parsed;
		else {
			//Or slices it first if an offset has been defined
			$parsed['segments'] = array_slice($parsed['segments'], $returnOffset);
			//And returns it later
			return $parsed;
		}
	}
	
	public function treatURL($mURL, $throwException = false, $customGenericFlags = array()){
		//If is a string
		if (is_string($mURL)) {
			//Parses the URL
			$mURL = $this->parseMURL($mURL, true, $customGenericFlags, 0, $throwException);
			//If the parsing function returns false, returns false too
			if ($mURL === false) return false;
		//Otherwise, if it is neither a string nor an array, returns false
		} else if (!is_array($mURL)) return false;
		
		//If everything is fine, returns the parsed mURL
		return $mURL;
	}
	
	protected function registerModulesSource($source, $sourceType, $modulesType = null){
		//If only a module type is provided, turn it into a one-element array
		if (is_string($modulesType))
			$modulesType = array(strtolower($modulesType));
		//If it's null, adds the folder to all types
		else if ($modulesType === null)
			$modulesTypes = $this->modulesManager->getModulesType();
		//Otherwise, if the argument isn't an array, exits the function
		else if (!is_array($modulesType)) throw new \Exception('Invalid type for modules type provided.');
		if (!is_string($source)) throw new \Exception('Invalid type for module source provided.');
		
		//Now goes through all the registered types
		foreach($modulesType as $type){
			$this->subModulesTypes[$type][$sourceType][] = $source;
		}
		
		return true;
	}
	
	public function registerModulesDirectory($folderPath, $modulesType = null){
		return $this->registerModulesSource($folderPath, 'folders', $modulesType);
	}
	
	public function registerModuleFile($filePath, $moduleType = null){
		return $this->registerModulesSource($folderPath, 'files', $modulesType);
	}
	
	protected function registerModulesType($moduleType, $factory, $requirements = array(), $defaultFolder = null){
		//First, converts the moduleType to lowercase
		//Note: modules types are case-insensitive
		$moduleType = strtolower($moduleType);
		
		//Now checks if there is already a registered type with this name
		if (isset($this->subModulesTypes[$moduleType])) throw new Exception('Already exists a module type with that name: \''.$moduleType.'\'.');;
		//Some arguments validation
		if (!is_string($moduleType)) throw new \Exception('Expecting string argument module type.');
		if (!is_callable($factory)) throw new \Exception('Expecting callable argument factory.');
		
		//Now registers the validators
		$this->subModulesTypes[$moduleType] = array('typeName' => $moduleType, 'folders' => array(), 'factory' => $factory, 'files' => array(), 'requirements' => array());
		if (is_array($requirements)){
			//Old code
			/*
			if (isset($requirements['suffixes'])) $this->subModulesTypes[$moduleType]['requirements']['suffixes'] = $requirements['suffixes'];
			if (isset($requirements['classes'])) $this->subModulesTypes[$moduleType]['requirements']['classes'] = $requirements['classes'];
			if (isset($requirements['interfaces'])) $this->subModulesTypes[$moduleType]['requirements']['interfaces'] = $requirements['interfaces'];
			if (isset($requirements['traits'])) $this->subModulesTypes[$moduleType]['requirements']['traits'] = $requirements['traits'];
			if (isset($requirements['validator'])) $this->subModulesTypes[$moduleType]['requirements']['validator'] = $requirements['validator'];
			if (isset($requirements['subnamespace'])) $this->subModulesTypes[$moduleType]['requirements']['subnamespace'] = $requirements['subnamespace'];
			*/
			
			//If it is an array, stores it as it is
			$this->subModulesTypes[$moduleType]['requirements'] = $requirements;
		} else if (is_callable($requirements)){
			//If it is only a function, stores it in it's correct place
			$this->subModulesTypes[$moduleType]['requirements']['validator'] = $requirements;
		}
		
		//Finally, if some defaultFolder was specified, creates it too
		if ($defaultFolder !== null && is_string($defaultFolder)) $this->registerModulesDirectory($defaultFolder, $moduleType);
		return true;
	}
	
	public function isModuleTypeRegistered($moduleType){
		return isset($this->subModulesTypes[strtolower($moduleType)]);
	}
	
	public function getModulesTypes(){
		$list = array();
		//Just foes through all types
		foreach($this->subModulesTypes as $typeName => $moduleType)
			$list[] = $typeName;
		
		//And then returns the array
		return $list;
	}
	
	public function getModuleSource($moduleName, $moduleType = null, $flags = array()){
		if (!is_string($moduleName)) return false;
		if (!is_array($flags)) return false;
		
		if ($moduleType === null) $moduleType = $this->getModulesTypes();
		else if (is_string($moduleType)) $moduleType = array(strtolower($moduleType));
		else if (!is_array($moduleType)) return false;
		
		$return = null;
		foreach($moduleType as $eachType){
			if (!isset($this->subModulesTypes[$eachType])) continue;
			if (isset($this->subModulesSources[$eachType][$moduleName])) return $this->subModulesSources[$eachType][$moduleName];
			
			foreach($this->subModulesTypes[$eachType]['folders'] as $eachFolder){
				//TODO
				if (isset($requirements['suffixes'])) {
				
				}
				if (file_exists($eachFolder.'\\'.$moduleName.'\\'.$moduleName.'.'.$eachType.'.php') &&
					($this->subModulesFileSystem == 2 || $this->subModulesFileSystem == 1)){
					$return = array(
						'customFolder' => true,
						'moduleName' => $moduleName,
						'moduleType' => $eachType,
						'folder' => $eachFolder.'\\'.$moduleName,
						'file' => $eachFolder.'\\'.$moduleName.'\\'.$moduleName.'.'.$eachType.'.php',
					);
				} else if (file_exists($eachFolder.'\\'.$moduleName.'.'.$eachType.'.php') &&
					($this->subModulesFileSystem == 2 || $this->subModulesFileSystem == 0)){
					$return = array(
						'customFolder' => false,
						'moduleName' => $moduleName,
						'moduleType' => $eachType,
						'folder' => $eachFolder,
						'file' => $eachFolder.'\\'.$moduleName.'.'.$eachType.'.php',
					);
				}

				if (!empty($return)){
					$this->subModulesSources[$eachType][$moduleName] = $return;
					return $return;
				}
			}
		}
		
		foreach($moduleType as $eachType){
			if (!isset($this->subModulesTypes[$eachType])) continue;
			foreach($this->subModulesTypes[$eachType]['files'] as $eachFile){
				$mFN = $moduleName.'.'.$eachType.'.php';
				//If the filename matches the required filename for a module file and if it exists
				if (file_exists($eachFile) && endsWith($eachFile, '\\'.$mFN)){
					//Returns the directory of the file
					$return = array(
						'customFolder' => false,
						'moduleName' => $moduleName,
						'moduleType' => $eachType,
						'folder' => trimOffEnd($mFN, $file),
						'file' => $eachFile,
					);
				}
				
				if (isset($return)){
					$this->subModulesSources[$eachType][$moduleName] = $return;
					return $return;
				}
			}
		}
		
		return false;
	}
	
	public function moduleExists($moduleName, $moduleType = null){
		return $this->getModuleSource($moduleName, $moduleType) !== false;
	}
	
	public function isModuleLoaded($moduleURL, $offset = 0){
		//Parses the mURL
		$moduleURL = $this->treatURL($moduleURL);
		if ($moduleURL === false) return false;
		
		//Calls the function at the root level if neccessary
		if ($moduleURL['absolute'] === true && $this->parent() !== null && $offset === 0) return $this->root()->isModuleLoaded($moduleURL, $moduleType, $offset);
		
		$moduleID = $moduleURL['segments'][$offset]['name'];
		if (!isset($this->subModules[$moduleID])) return false;
		
		if (isset($moduleURL['segments'][$offset + 1])){
			$requestedModule = $this->subModules[$moduleID]['instance'];
			return $requestedModule->isModuleLoaded($moduleURL, $offset + 1);
		} else return true;
	}
	
	public function loadDependencies($dependenciesFile){
		
	}
	
	protected function selectRange($name = null, $isGeneric = false, $genericType = false){
		$selectedModules = array();
		//If this is a fullUnload or there is only a : in the URL, then seelects all the modules
		if ($name === null || $name === ''){
			foreach ($this->subModules as $eachModule)
				$selectedModules[] = $eachModule['instance'];
		//Otherwise, if this segment represents a type
		} else if ($isGeneric === true && $genericType === true){
			//First checks if there are any modules for this type
			if (isset($this->subModulesByType[strtolower($name)])){
				//If Yes, goes through all the types
				foreach ($this->subModulesByType[strtolower($name)] as $eachModuleName){
					//And for each type through all the modules
					foreach ($eachModuleName as $eachModule){
						//And registers each instance of it
						$selectedModules[] = $eachModule;
					}
				}
			}
		//Else, if this is a generic segment
		} else if ($isGeneric === true){
			//Goes through all the types
			foreach ($this->subModulesByType as $eachModuleType){
				//And for each type, checks if there is any module loaded equal to this segment
				if (isset($eachModuleType[$name])){
					//Goes through all the instances of it
					foreach ($eachModuleType[$name] as $eachModule){
						//And registers them
						$selectedModules[] = $eachModule;
					}
				}
			}
		//If none of the above was true, then it means this is the name of an instance
		} else {
			//And as so, looks for it in the modules list
			if (isset($this->subModules[$name])){
				//If any was found, adds it to the selection list
				$selectedModules[] = $this->subModules[$name]['instance'];
			}
		}
		
		return $selectedModules;
	}
	
	public function alias($original, $transformed){
		$original = $this->treatURL($original);
		if ($original === false) return null;
		
		$transformed = $this->treatURL($transformed);
		if ($transformed === false) return null;
		
		$ID = $this->_aliasesAutoIncrement;
		$this->aliases[$ID] = array('original' => $original, 'transformed' => $transformed);
		$this->_aliasesAutoIncrement++;
		
		return function() use ($ID){
			$this->removeAlias($ID);
		};
	}
	
	public function removeAlias($ID){
		if (isset($this->aliases[$ID])) unset($this->aliases[$ID]);
	}
	
	public function retrieveRealURL($mURL){
		$mURL = $this->treatURL($mURL);
		if ($mURL === false) return null;
		
		$found = false;
		$final = $mURL;
		for ($i = 0; $this->parent($i) !== null && $found === false; $i++){
			foreach ($this->aliases as $ID => $eachAlias){
				
			}
		}
	}
	
	public function load($moduleURL, $moduleType = null, $config = array(), $offset = 0){
		//First, parses the given mURL
		$moduleURL = $this->treatURL($moduleURL, true, array('?'));
		if ($moduleURL === false) return false;

		//Now, if it is absolute and the offset is set to zero, calls the function on the root node
		if ($moduleURL['absolute'] === true && $this->parent() !== null && $offset === 0) return $this->root()->load($moduleURL, $moduleType, $config, $offset);
		/*else if ($moduleURL['absolute'] === false && !isset($moduleURL['segments'][0])){
			//Else if, checks if absolute is equal to false and there are no segments (which basically means that
			//the mURL is just a ':')
			//If so, returns all the loaded subModules 
			$allModules = array();
			foreach ($this->subModules as $eachModule)
				$allModules[$eachModule['ID']] = $eachModule['instance'];
				
			return $allModules;
		}*/
		
		$selectedModules = $this->selectRange($moduleURL['segments'][$offset]['name'], $moduleURL['segments'][$offset]['generic'], (isset($moduleURL['segments'][$offset]['flags']['?']) && $moduleURL['segments'][$offset]['flags']['?'] === true));
		
		$moduleName = $moduleURL['segments'][$offset]['name'];
		foreach ($moduleURL['segments'][$offset]['flags'] as $eachFlag => $state){
			if (beginsWith($eachFlag, 'As=') && strlen($eachFlag) > 3) {
				$moduleID = trimOffFront(3, $eachFlag);
				break;
			}
		}
		if (!isset($moduleID)) $moduleID = $moduleName;
		
		$returnModules = array();
		if (isset($moduleURL['segments'][$offset + 1])){
			
			foreach($selectedModules as $eachModule){
				$temp = $eachModule->load($moduleURL, $moduleType, $config, $offset + 1);
				if (is_array($temp))
					$returnModules = array_merge($returnModules, $temp);
				else if ($temp != null)
					$returnModules[] = $temp;
			}
			
			if (isset($moduleURL['hasGenerics']) && $moduleURL['hasGenerics'] === true)
				return $returnModules;
			else if (isset($returnModules[0]))
				return $returnModules[0];
			else
				return null;
		} else {
			if ($moduleType !== null) $moduleType = strtolower($moduleType);
			if ($moduleType !== null && !$this->isModuleTypeRegistered($moduleType)) return false;
			
			$forceNew = isset($moduleURL['segments'][$offset]['flags']['*']) && $moduleURL['segments'][$offset]['flags']['*'] === true;
			foreach($selectedModules as $eachModule){
				$returnModules[] = $this->subModules[$eachModule->name()]['instance'];
			}
			
			if ((!isset($returnModules[0]) || $forceNew) && $moduleURL['segments'][$offset]['name'] !== ''){
				$requestedSource = $this->getModuleSource($moduleName, $moduleType);
				if ($requestedSource === false) return false;
				//Gets the requirements
				$requirements = $this->subModulesTypes[$requestedSource['moduleType']]['requirements'];
				//Checks if the is any sub-namespace defined
				if (isset($requirements['subnamespace']))
					$phpClassPath = $this->getNamespace().'\\'.$requirements['subnamespace'].'\\'.$moduleName;
				else
					$phpClassPath = $this->getNamespace().'\\'.$moduleName;
				
				if (isset($requirements['ownnamespace']) && $requirements['ownnamespace'] === true)
					$phpClassPath .= '\\'.$moduleName;
				
				//Includes the module's file
				include_once $requestedSource['file'];
				
				//Checks if the class exists
				if (!class_exists($phpClassPath, false)) return false;
				//Now checks if there are any classes, interfaces and traits requirements
				$subValidations = array('classes', 'interfaces', 'traits');
				foreach ($subValidations as $type){
					if (isset($requirements[$type])){
						if (is_array($requirements[$type])){
							foreach($requirements[$type] as $eachClass)
								if (!is_subclass_of($phpClassPath, $eachClass)) return false;
						} else if (is_string($requirements[$type])){
							if (!is_subclass_of($phpClassPath, $requirements[$type])) return false;
						}
					}
				}
				
				
				//If there is some custom validator specified, runs it
				if (isset($requirements['validator'])){
					//And gets the results
					$validator = \call_user_func($requirements['validator'], $this, $requestedSource, $requirements, $phpClassPath);
					if ($validator === false) return false;
				}
				
				//If there is already any module with that ID
				if (isset($this->subModules[$moduleID])){
					//Iterates with a counter trying to find a name that has not yet been taken
					$finalModuleID = $moduleID;
					$counter = 1;
					//And does it while there is a module with the current ID
					while(isset($this->subModules[$finalModuleID.$counter])){
						$counter++;
					}
					//Finaly creates the final ID
					$finalModuleID .= $counter;
				//Otherwise 
				} else $finalModuleID = $moduleID;
				
				//Finally, we create the instance by calling a factory
				//serviceID, $serviceName, $serviceType, $className
				$tempVariable = \call_user_func($this->subModulesTypes[$requestedSource['moduleType']]['factory'], 
												$finalModuleID, $moduleName, $requestedSource['moduleType'], $phpClassPath, $config);
				//If the factory fails, returns false too
				if ($tempVariable === false) return false;
				else {
					//Otherwise saves the module on the module's list
					$this->subModules[$finalModuleID] = array('ID' => $finalModuleID, 'name' => $moduleName, 'type' => $requestedSource['moduleType'], 'instance' => $tempVariable);
					
					//Dispatches the load events
					$this->dispatchWatchers($tempVariable, 'load');
					
					//And returns it
					return $tempVariable;
				}
			} else {
				if (isset($moduleURL['hasGenerics']) && $moduleURL['hasGenerics'] === true)
					return $returnModules;
				else if (isset($returnModules[0]))
					return $returnModules[0];
				else
					return null;
			}
		}
	}
	
	public function unload($moduleURL = null, $offset = 0){
		//fullUnload means if the module itself is unloading, or just it's submodules
		$fullUnload = false;
		//If nothing is put into the $moduleURL, it means that the class is mean to fully unloa
		if ($moduleURL === null) $fullUnload = true;
		
		//If this is not a full unload
		if (!$fullUnload){
			//Now, parses the mURL
			$moduleURL = $this->treatURL($moduleURL);
			//If its invalid, exits the function
			if ($moduleURL === false) return false;
			
			//Now, checks if absolute is true and this function needs to be called in the root node
			if ($moduleURL['absolute'] === true && $this->parent() !== null && $offset === 0) return $this->root()->unload($moduleURL, $offset);
		}
		
		//Checks what modules match the current position in the mURL
		$selectedModules = array();
		//If this is a fullUnload or there is only a : in the URL, then seelects all the modules
		if ($fullUnload || $moduleURL['absolute'] === false && !isset($moduleURL['segments'][0])){
			foreach ($this->subModules as $eachModule)
				$selectedModules[] = $eachModule['instance'];
		//Otherwise, if this segment represents a type
		} else if (isset($moduleURL['segments'][$offset]['flags']['?'])){
			//First checks if there are any modules for this type
			if (isset($this->subModulesByType[strtolower($moduleURL['segments'][$offset]['name'])])){
				//If Yes, goes through all the types
				foreach ($this->subModulesByType[strtolower($moduleURL['segments'][$offset]['name'])] as $eachModuleName){
					//And for each type through all the modules
					foreach ($eachModuleName as $eachModule){
						//And registers each instance of it
						$selectedModules[] = $eachModule;
					}
				}
			}
		//Else, if this is a generic segment
		} else if ($moduleURL['segments'][$offset]['generic'] === true){
			//Goes through all the types
			foreach ($this->subModulesByType as $eachModuleType){
				//And for each type, checks if there is any module loaded equal to this segment
				if (isset($eachModuleType[$moduleURL['segments'][$offset]['name']])){
					//Goes through all the instances of it
					foreach ($eachModuleType[$moduleURL['segments'][$offset]['name']] as $eachModule){
						//And registers them
						$selectedModules[] = $eachModule;
					}
				}
			}
		//If none of the above was true, then it means this is the name of an instance
		} else {
			//And as so, looks for it in the modules list
			if (isset($this->subModules[$moduleURL['segments'][$offset]['name']])){
				//If any was found, adds it to the selection list
				$selectedModules[] = $this->subModules[$moduleURL['segments'][$offset]['name']]['instance'];
			}
		}
		
		//Finally, checks if this is the module just a middle segment
		if (isset($moduleURL[$offset + 1])){
			//If so, calls the unload method with the same mURL and an incremented offset
			foreach ($selectedModules as $eachModule){
				$eachModule->unload($moduleURL, $offset + 1);
			}
		} else {
			//Otherwise, this is(are) the module(s) that is(are) meant to be unloaded 
			//Goes through the selection list
			foreach ($selectedModules as $eachModule){
				//And for each, dispatches the unloading event
				$this->dispatchWatchers($eachModule, 'unload');
				
				//Unloads them and their submodules, and so on  
				$this->subModules[$eachModule->name()]['instance']->unload();
				
				//And finally, takes them out of modules list
				unset($this->subModules[$eachModule->name()]);
				unset($this->subModulesByType[$eachModule->moduleType()][$eachModule->moduleName()][$eachModule->name()]);
			}
			
			//If this is also a full unload
			if ($fullUnload){
				//So, removes it's watchers
				foreach($this->watchersCache as $ID)
					$this->removeWatcher($ID);
			}
		}
		
	}
	
	public function dispatchWatchers($senders = null, $action){
		if ($senders === null){
			$senders = array();
		} else if (!is_array($senders)) {
			$senders = array($senders);
		}
		if ($this->parent() !== null){
			$senders[] = $this;
			return $this->parent()->dispatchWatchers($senders, $action);
		}
		
		$childsNumber = count($senders);
		if (!isset($this->watchersByAction[$action])) return;
		foreach ($this->watchersByAction[$action] as $key => $state){
			$watch = $this->watchers[$key];
			if (!isset($watch['members'])){
				call_user_func($watch['callback'], $senders[0]);
			} else {
				foreach ($watch['members'] as $mURL){
					$mURLPartsCount = count($mURL['segments']);
					if ($mURLPartsCount != $childsNumber) break;
					
					$params = array();
					$matches = true;
					for ($i = 0; $i < $mURLPartsCount; $i++){
						$value = null;
						$svalue = $mURL['segments'][$i]['name'];
						if (isset($mURL['segments'][$i]['flags']['?'])){ 
							$value = strtolower($senders[$childsNumber - $i - 1]->moduleType());
							$svalue = strtolower($mURL['segments'][$i]['name']);
						} else if ($mURL['segments'][$i]['generic'] === true) 
							$value = $senders[$childsNumber - $i - 1]->moduleName();
						else 
							$value = $senders[$childsNumber - $i - 1]->name();
						
						if ($value != $svalue) {
							$matches = false;
							break;
						}
						
						if (isset($mURL['segments'][$i]['flags']['$'])){
							$params[] = $senders[$childsNumber - $i - 1];
						}
					}
					
					if ($matches){						
						call_user_func_array($watch['callback'], $params);
					}
				}
			}
		}
	}
	
	public function when($eventType, $moduleURLs, $callback, $function = true){
		//If this is the root node
		if ($this->parent() === null){
			//Starts checking the arguments of the function
			if (!is_string($eventType)) return false;
			//Checks if no module was specified
			if (is_callable($moduleURLs)){
				//This means that the callback is actually in place of the moduleURLs
				$callback = $moduleURLs;
				unset($moduleURLs);
			} else {
				//Otherwise, just parses the module URL
				$moduleURLs = $this->treatURL($moduleURLs);
				if ($moduleURLs === false) return false;
			}
			
			//Now starts by creating the array that will contain the information about this watcher
			$temp = array();
			//Sets the action and the callback
			$temp['action'] = $eventType;
			$temp['callback'] = $callback;
			//Now, if there was any specified module
			if (isset($moduleURLs)){
				//Put's it on the members too
				$temp['members'] = array($moduleURLs);
			}
			
			//now is time to actually save the watcher
			$ID = $this->watchersAI;
			$this->watchers[$this->watchersAI] = $temp;
			//Also, for optimization purposes, saves it's reference on a specific array for this action
			if (!isset($this->watchersByAction[$eventType])) $this->watchersByAction[$eventType] = array();
			$this->watchersByAction[$eventType][$this->watchersAI] = true;
			
			//And increases the Auto Increment for the watchers
			$this->watchersAI++;			
		} else {
			//Otherwise, this is not a root node, and as such, calls the function in the root
			$ID = $this->root()->when($eventType, $moduleURLs, $callback, false);
			//And saves the reference in this node
			$this->watchersCache[$ID] = $ID;
		}
		
		//Now, finally sees what to return
		if ($function === true){
			//Either an anonymous function that when executed, removes the watcher
			return function() use ($ID){
				$this->removeWatcher($ID);
			};
		//Or simply the identification of the watcher
		} else return $ID;
	}
	
	public function removeWatcher($ID){
		//Checks if this is not the root node
		if ($this->parent() !== null){
			//Removes the watcher in the root's list
			$this->root()->removeWatcher($ID);
			//Removes it too in this class
			unset($this->watchersCache[$ID]);
			//And then simply exits the function
			return;
		}
		
		//Otherwise, if this is the root node, removes watcher from the list
		unset($this->watchersByAction[$this->watchers[$ID]['action']][$ID]);
		unset($this->watchers[$ID]);
	}
	
	
	function __construct($parent = null, $moduleID, $moduleName = null, $moduleType = null, $config = array()){
		$this->_parent[0] = $parent;
		if ($parent === null) $this->_root = $this;
		else $this->_root = $this->parent()->root();
		
		$this->_instanceName = $moduleID;		
		$this->_moduleName = $moduleName;		
		$this->_moduleType = $moduleType;
		
		$this->config($config);
		
		$this->di = new DependenciesManager($this);
	}
}
	