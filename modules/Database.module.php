<?php

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class DatabaseModule extends Module {
	
	public $PDO;
	private $dbHost;
	private $dbName;
	private $dbUser;
	private $dbPass;
	private $dbPrefix;
	
	
	public function query(){
		$num_args = func_num_args();
		if ($num_args == 0){
			throw new Exception('Query not defined.');
			exit;
		}
		
		$query = func_get_arg(0);
		$query = str_replace("^", $this->dbPrefix, $query);
		
		$query = $this->PDO->prepare($query);
		
		for($pass=1; $pass < $num_args; $pass++){
			if (is_int(func_get_arg($pass))){
				$query->bindValue($pass, func_get_arg($pass), PDO::PARAM_INT);
			} else {
				$query->bindValue($pass, func_get_arg($pass));
			}
		}
		
		$query->execute();
		
		return $query;
		
	}
	
	public function query_raw($querySQL){
		return $this->PDO->query($querySQL);
	}
	
	public function lastInsertId($name = NULL){
		return($this->PDO->lastInsertId($name));
	}
	
	public function getPDOConnection(){
		return $this->PDO;
	}

	public function __construct(ModulesManager $modulesManager, $dbHost, $dbUser, $dbPass, $dbName, $dbPrefix){
		parent::__construct($modulesManager);
		list(, $this->dbHost, $this->dbUser, $this->dbPass, $this->dbName, $this->dbPrefix) = func_get_args();
		
		$this->PDO = new PDO("mysql:host=".$this->dbHost.";dbname=".$this->dbName."", $this->dbUser, $this->dbPass);
		$this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

}


?>