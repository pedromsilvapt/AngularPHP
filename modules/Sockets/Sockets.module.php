<?php
namespace AngularPHP\Modules\Sockets;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Sockets extends \AngularPHP\Module {
	private $isServerListening = false;
	
	private $socketsList = array();
	
	public function getSocket($socketName){
		if (!$this->socketExists($socketName)) return false;
		
		return $this->socketsList[$socketName];
	}
	
	public function socketExists($socketName){
		return isset($this->socketsList[$socketName]);
	}
	
	public function removeSocket($socketName){
		if (!$this->socketExists($socketName)) return false;
		
		unset($this->socketsList[$socketName]);
		return true;
	}
	
	public function createSocket($socketName, $listeningAddress = null, $listeningPort = null){
		if (!is_string($socketName) || $this->socketExists($socketName)) return false;
		if ((!is_integer($listeningPort) && $listeningPort !== null)) return false;
		
		$socketsList[$socketName] = $this->appManager->injectDependencies(array('SocketServer', '__construct'), array(
			'listeningAddress' => $listeningAddress,
			'listeningPort' => $listeningPort)
		);
		return $socketsList[$socketName];
	}
	
	public function __construct(\AngularPHP\ModulesManager $modulesManager, \AngularPHP\AngularPHP $appManager){
		parent::__construct($modulesManager);
		$this->appManager = $appManager;
		
		require_once($modulesManager->getModuleDirectory('Sockets').'SocketServer.class.php');
	}
}