<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class DependencyInjection {

	private $injectionProviders;
	private $reflectionsCache;
	
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
	
	private function lookForDependencyOnList($identifier, &$list, $varName = "", $index = -1, $reflection = null){
		if (isset($list[$identifier]) && !is_callable($list[$identifier])){
			return $list[$identifier];
		} else {
			$return = false;
			//Now looks through the providers for one that fits
			foreach ($list as $name => $provider){
				if (!is_callable($provider)) continue;
				
				//If one is found, stops the search
				$return = call_user_func($provider, $identifier, $varName, $index, $reflection);
				if ($return !== false) return $return;
			}
		}
		return null;
	}
	
	private function getMethodsReflection($function){
		$methodR = "";
		$classReflection = null;
	
		//If it's just a function
		if (is_string($function) || (is_callable($function) && !is_array($function))){
			$methodR = new ReflectionFunction($function);
		//Or is a class method
		} elseif (is_array($function)){
			//If the array does not comply with the rules for a callable
			if (!isset($function[0]) || !isset($function[1])) return false;
			if ((!is_object($function[0]) && !is_string($function[0])) || !is_string($function[1])) return false;
			
			//Creates a reflection of the module
			//$classReflection = new ReflectionClass($function[0]);
			//Checks if it's supposed to inject on a constructor or on some ordinary method
			//if (is_string($function[0]) && $function[1] === '__construct') $methodR = $classReflection->getConstructor();
			$methodR = new ReflectionMethod($function[0], $function[1]);
		} else return false;
		
		return array('methodReflection' => $methodR, 'classReflection' => $classReflection);
	}
	
	private function injectParams($function, &$params, $classReflection = null){
		//After the parameters are all defined, checks which function to call
		if (is_string($function) || is_callable($function)){
			//If it's a function, calls it and returns it's value
			return call_user_func_array($function, $params);
		} elseif (is_array($function)){
			//If it's a constructor, creates the object and returns the object itself.
			if (is_string($function[0]) && $function[1] == '__construct'){
				$classReflection = new ReflectionClass($function[0]);
				return $classReflection->newInstanceArgs($params);
			} else {
				//Or if it's an ordinary method, calls it and returns the value
				return call_user_func_array($function, $params);
			}
		}
		return false;
	}
	
	
	public function getDependency($identifier, $varName = "", $index = -1, $reflection = null, $customProviders = array(), $overrideDefaults = false){
		if (!is_string($identifier) || !is_string($varName) || !is_integer($index) || !is_array($customProviders))
			return null;
		
		$resultC = $this->lookForDependencyOnList($identifier, $customProviders, $varName, $index, $reflection);
		//If a custom dependency is found, and it is supposed to have priority over default ones, returns it
		if ($overrideDefaults and $resultC !== false) return $resultC;
		
		return $this->lookForDependencyOnList($identifier, $this->injectionProviders, $varName, $index, $reflection);
	}
	
	public function injectDependencies($function, $customProviders = array(), $overrideDefaults = false){
		return $this->injectDependenciesArgsArray($function, array(), $customProviders, $overrideDefaults);
	}
	
	public function injectDependenciesArgs($function){
		$params = array();
		for ($i = 1; $i < func_num_args(); $i++){
			$parms[] = func_get_arg($i);
		}
		
		return $this->injectDependenciesArgsArray($function, $params);
	}
	
	public function injectDependenciesArgsArray($function, $args = array(), $customProviders = array(), $overrideDefaults = false){
		//Instantiates some global variables
		$params = array();
		$methodR = "";
		$classReflection = null;
		
		$return = $this->getMethodsReflection($function);
		if ($return == false) return false;
		
		$methodR = $return['methodReflection'];
		$classReflection = $return['classReflection'];
		
		if (is_object($methodR)){
			$i = 0;
			$currentParam = 0;
			//Goes trhough all the method's parameters
			foreach($methodR->getParameters() as $rp) {
				$i++;
				
				//Get's the parameter's Type Hint.
				$cn = $this->resolveParameterClassName($rp);
				//If there is one
				if ($cn !== null){
					$result = $this->getDependency($cn, $rp->getName(), $i, $rp, $customProviders, $overrideDefaults);
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
				} else {
					//echo 1;
					if (isset($args[$currentParam])){
						$params[] = $args[$currentParam];
						$currentParam++;
					} else {
						$params[] = null;
					}
				}
			}
		}
		
		return $this->injectParams($function, $params, $classReflection);
	}
	
	public function injectDependenciesArgsAssoc($function, $defaultParams, $customProviders = array(), $overrideDefaults = false){
		//Instantiates some global variables
		$params = array();
		$methodR = "";
		$classReflection = null;
		
		$return = $this->getMethodsReflection($function);
		if ($return == false) return false;
		
		$methodR = $return['methodReflection'];
		$classReflection = $return['classReflection'];
		
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
					$result = $this->getDependency($cn, $rp->getName(), $i, $rp, $customProviders, $overrideDefaults);
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
			
		return $this->injectParams($function, $params, $classReflection);
	}
	
	public function registerInjectionProvider($name, $provider){
		//Checks the arguments
		if (!is_string($name) || (!is_callable($provider) && !is_object($provider))) return false;
		
		//Registers the provider in the array
		$this->injectionProviders[$name] = $provider;
	}
	
	public function __construct(){
		$this->injectionProviders = array();
		$this->reflectionsCache = array();
		
		$this->registerInjectionProvider('DependencyInjection', $this);
	}

}