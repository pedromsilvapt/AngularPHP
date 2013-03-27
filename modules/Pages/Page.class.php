<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

abstract class Page {
	
	public function canBeShown(){
		return true;
	}
	
	public function head(){
		
	}
	
	public function body(){
		
	}
	
	public function footer(){
		
	}
}