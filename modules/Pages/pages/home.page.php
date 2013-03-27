<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class HomePage extends Page {
	
	public function head(){
		echo 1;
	}
	
	public function body(){
		echo 2;
	}
}