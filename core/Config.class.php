<?php
namespace AngularPHP;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

date_default_timezone_set('Europe/Lisbon');

class Config
{
	//Database Settings
	public static $dbHost = '127.0.0.1';
	public static $dbUser = 'root';
	public static $dbPass = '';
	public static $dbName = 'gamedevelopment';
	public static $dbPrefix = 'gd_';

	//Directory Settings
	public static $dirPath = 'C:\wamp\www\angular-php\\';
	public static $httpPath = 'http://localhost/angular-php';

	//Custom settings
	public static $sitename = 'Game Development';
	public static $slogan = 'Made Easy';
}

