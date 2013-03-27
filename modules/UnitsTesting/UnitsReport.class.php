<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class UnitsReport {
	
	private $test;
	
	public function hasSet($name){
		return isset($this->test[$name]);
	}
	
	public function getTestsCount($setName){
		if (!isset($this->test[$setName])) return false;
		
		return(count($this->test[$setName]['tests']));
	}
	
	public function getSetsCount(){
		return count($this->test);
	}
	
	public function __construct($source){
		$this->test = $source;
	}
}