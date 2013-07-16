<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

interface ModulesProvider {
	function config($moduleURL, $config);
}