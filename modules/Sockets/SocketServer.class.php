<?php

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class SocketServer {
	private $listeningAddress;
	private $listeningPort;
	private $isServerListening = false;
	
	private $socket;
	private $connections = array();
	private $onList = array();
	
	public function getIsServerListening(){
		return $this->isServerListening;
	}
	
	public function getListeningAddress(){
		return $this->listeningAddress;
	}
	
	public function setListeningAddress($newAddress){
		$this->listeningAddress = $newAddress;
	}
	
	public function getListeningPort(){
		return $this->listeningPort;
	}
	
	public function setListeningPort($newPort){
		$this->listeningPort = $newPort;
	}

	
	private function receiveStream($timeout){
		$reads = $this->connections;
		// get number of connections with new data
		$mod = stream_select($reads, $write, $except, $timeout);
		if ($mod===false) return false;

		foreach ($reads as $read) {
			if ($read === $this->socket) {
				$conn = stream_socket_accept($this->socket);
				$recv = fread($conn, 1024);
				
				if (empty($recv)) continue;
				if (strpos($recv, "GET / ") === 0) {
					echo $recv;
					// serve static html page from memory
					fwrite($conn, "HTTP/1.1 200 OK\r\n". "Connection: close\r\n".
					"Content-Type: text/html; charset=UTF-8\r\n\r\n");
					fwrite($conn, "olá");
						
					stream_socket_shutdown($conn, STREAM_SHUT_RDWR);
				} elseif (strpos($recv, 'GET /msg/') === 0){
					fwrite($conn, "HTTP/1.1 200 OK\r\n". "Connection: close\r\n".
					"Content-Type: text/html; charset=UTF-8\r\n\r\n");
					fwrite($conn, "olá2");
				}
			}
		}
	}
	
	private function getInpuData($read){
		if (!beginsWith($read, 'GET / '))
			return false;
		
		return $read;
	}
	
	private function registerOn($list, $event, $callback){
		if (!is_string($list) || !is_string($event) || !is_callable($callback)) return false;
		
		if (!isset($this->onList[$list])) $this->onList[$list] = array();
		
		if (!isset($this->onList[$list][$event]))
			$this->onList[$list][$event] = array($callback);
		else
			$this->onList[$list][$event][] = $callback;
			
		return true;
	}
	
	
	public function onOpen($callback){
		$this->registerOn('default', 'open', $callback);
		return $this;
	}
	
	public function onClose($callback){
		$this->registerOn('default', 'close', $callback);
		return $this;
	}
	
	public function onMessage($callback){
		$this->registerOn('default', 'message', $callback);
		return $this;
	}
	
	public function onHTTP($callback){
		$this->registerOn('default', 'http', $callback);
		return $this;
	}
	
	public function otherwise($callback){
		$this->registerOn('default', 'otherwise', $callback);
		return $this;
	}
	
	public function always($callback){
		$this->registerOn('default', 'always', $callback);
		return $this;
	}
	
	public function on($event, $callback){
		$this->registerOn('custom', $event, $callback);
		
		return $this;
	}
	
	public function listen($timeout, $listeningAddress = null, $listeningPort = null){
		if (($this->listeningAddress === null && $listeningAddress === null)
			|| ($this->listeningPort === null && $listeningPort === null)) return false;
			
		if ($listeningAddress === null) $listeningAddress = $this->listeningAddress;
		if ($listeningPort === null) $listeningPort = $this->listeningPort;
		
		
		$this->isServerListening = true;
		$this->socket = stream_socket_server('tcp://'.$listeningAddress.':'.$listeningPort.'', $errno, $err) or die($err);
		$this->connections = array($this->socket);
		
		$this->receiveStream($timeout);
		
		$this->isServerListening = false;
		
		return $this;
	}
	
	public function persist($timeoutEach, $timeoutGlobal, $listeningAddress = null, $listeningPort = null){
		if (($this->listeningAddress === null && $listeningAddress === null)
			|| ($this->listeningPort === null && $listeningPort === null)) return false;
		if (!is_integer($timeoutEach) || !is_integer($timeoutGlobal)) return false;
		
		if ($listeningAddress === null) $listeningAddress = $this->listeningAddress;
		if ($listeningPort === null) $listeningPort = $this->listeningPort;
		
		$this->isServerListening = true;
		$this->socket = stream_socket_server('tcp://'.$listeningAddress.':'.$listeningPort.'', $errno, $err) or die($err);
		$this->connections = array($this->socket);
		
		$i = 0;
		while($this->isServerListening && $i < $timeoutGlobal){
			$this->receiveStream($timeoutEach);
			$i++;
		}
		
		$this->isServerListening = false;
		
		return $this;
	}
	
	public function __construct(ModulesManager $modulesManager, $listeningAddress = null, $listeningPort = null){
		$this->modulesManager = $modulesManager;
		$this->listeningAddress = $listeningAddress;
		$this->listeningPort = $listeningPort;
	}

}