<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Module {
	protected $modulesManager;
	
	public static function getDependencies(){
		return(Array());
	}
	
	public function __construct(ModulesManager $modulesManager){
		$this->modulesManager = $modulesManager;
	}
}