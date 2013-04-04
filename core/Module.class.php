<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Module extends Decorator{
	protected $modulesManager;
	
	public static function getDependencies(){
		return(Array());
	}
	
	public function __construct(ModulesManager $modulesManager){
		$this->modulesManager = $modulesManager;
	}
}