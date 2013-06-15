<?php
namespace AngularPHP\Modules\Database;
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Database extends \AngularPHP\Module {
	
	public $PDO;
	private $dbHost;
	private $dbName;
	private $dbUser;
	private $dbPass;
	private $dbPrefix;
	
	
	public function query(){
		$num_args = func_num_args();	
		$query = func_get_arg(0);
		$params = array();
		
		for($i=1; $i < $num_args; $i++)
			$params[] = func_get_arg($i);
		
		return $this->queryArr($query, $params);		
	}
	
	public function queryArr($query, $params){
		$query = str_replace("^", $this->dbPrefix, $query);
		
		$query = $this->PDO->prepare($query);
		
		$i = 1;
		foreach($params as $value){
			if (is_int($value))
				$query->bindValue($i, $value, \PDO::PARAM_INT);
			else 
				$query->bindValue($i, $value);
			
			$i++;
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
	
	public function getRawConnection(){
		return $this->PDO;
	}
	
	public function beginTransaction(){
		$this->PDO->beginTransaction();
	}
	
	public function rollBack(){
		$this->PDO->rollBack();
	}
	
	public function commit(){
		$this->PDO->commit();
	}
	
	public function __construct(\AngularPHP\ModulesManager $modulesManager, $dsn, $username = null, $password = null, $dbPrefix){
		parent::__construct($modulesManager);
		list(, , $this->dbUser, $this->dbPass, $this->dbPrefix) = func_get_args();
		
		if (!isset($username) || !isset($password))
			$this->PDO = new \PDO($dsn);
		else
			$this->PDO = new \PDO($dsn, $this->dbUser, $this->dbPass);
			
		$this->PDO->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}

}


?>