<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class ObserverModule extends Module {
	
	private $observing;
	private $config = array(
		'AUTO_RUN' => false,
		'AUTO_INDEX' => false
	);
	
	public function confExists($var_name){
		return isset($this->config[$var_name]);
	}

	public function getConf($varName){
		if (isset($this->config[$varName])) return($this->config[$varName]);
		return(false);
	}

	public function setConf($varName, $varValue){
		if (isset($this->config[$varName])){
			self::$config[$varName] = $varValue;
			return(true);
		}
		
		return(false);
	}
	
	/*
	* This funcion is used as an action when the Routes module is loaded
	*
	* @param array params - An array passed by the Routes module
	* @param object object - The object attach to this notification
	* @param string event - The name of the event being fired
	* @param array|mixed paramsNotification - The params that need may be passed to the event
	*/
	public function actionNotify($params, $object, $event, $paramsNotification = null){
		//If no aditional parameters are supplied
		if ($paramsNotification == null){
			//Simply calls the notify function
			$this->notify($object, $event);
		//Else if the $paramsNotification is an array
		} else if (is_array($paramsNotification)){
			//Merges the $object, $event and the $paramsNotification array and calls the notify function
			call_user_func_array(array($this, 'notify'), array_merge(Array($object, $event), $paramsNotification));
		//Otherwise, just calls the function with the one special specified parameter
		} else {
			$this->notify($object, $event, $paramsNotification);
		}
	}
	
	/*
	* This funcion is used to attach a listener to a specified event
	*
	* @param object object - The object attach to this notification
	* @param string event - The name of the event being fired
	* @param callback callback - The function to be called when the event is fired
	*/
	public function listenTo($object, $event, $callback){
		//If the $event is not a string, stops the function
		if (!is_string($event)) return(false);
		//Get's an unique hash for the supplied object
		$object = spl_object_hash($object);
		
		//If there is no listener to that $event, instantiates an Array with the callback
		if (!isset($this->observing[$object][$event])){
			$this->observing[$object][$event] = Array($callback);
		//Otherwise, just adds the callback to the end of the array
		} else {
			$this->observing[$object][$event][] = $callback;
		}
	}
	
	/*
	* This funcion is used to de-attach a listener or several listeners of a specified event
	*
	* @param object object - The object you don't want to be notified
	* @param string event - The event you want to mute
	* @param callback optional callback - The callback you want to mute. If null, all listeners are muted from that event of tha object.
	*/
	public function mute($object, $event, $callback = null){
		//Get's an unique hash for the supplied object
		$object = spl_object_hash($object);
	
		if (isset($this->observing[$object][$event])){
			//if no specific callback set, mute's all callbacks
			if ($callback == null){
				$this->observing[$object][$event] = Array();
			//Otherwise, filters the callback's array taking out the specified callback
			} else {
				$this->observing[$object][$event] = array_filter($this->observing[$object][$event], function($item) use ($callback){
					return($item == $callback);
				});
			}
		}
	}
	
	/*
	* This funcion is used to notify the listeners of a specified event
	*
	* @param object object - The object you wan't to notify
	* @param string event - The event you want to notify
	* @param params[] - The rest of the parameters suplied to the funcion will be treated as arguments for the event's and
	* 					passed on to the callback's function.
	*/
	public function notify($object, $event){
		//Get's an unique hash for the supplied object
		$object = spl_object_hash($object);
		
		if (isset($this->observing[$object][$event])){
			//Check user parameters
			$params = array_slice(func_get_args(), 2);
			//Gets the number of parameters
			$numParams = count($params);
			//For each of the event listener
			foreach($this->observing[$object][$event] as $callback){
				//Calls the function
				if ($numParams > 0){
					call_user_func_array($callback, $params);
				} else {
					call_user_func($callback);
				}
			}
		}
	}
	
	public function __construct(ModulesManager $modulesManager){
		parent::__construct($modulesManager);
		$this->observing = Array();
		
		//Adds the main actions
		$this->modulesManager->when('Routes', function(RoutesModule $Routes){
			$Routes->addAction('notify', array($this, 'actionNotify'));
		});
	}
}

?>