<?php
namespace AngularPHP\Modules\Database;
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Database {
	use \AngularPHP\Module {
		\AngularPHP\Module::__construct as private __traitConstruct;
	}
	
	protected $PDO;
	
	
	public function query(){
		$num_args = func_num_args();
		if ($num_args == 0 || !is_string(func_get_arg(0))) throw new Exception('Query not defined.');
		
		$query = func_get_arg(0);
		$query = str_replace($this->config('parser.prefixPlaceholder'), $this->config('db.tablePrefix'), $query);
		
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
	
	public function getRawConnection(){
		return $this->PDO;
	}

	public function __construct($parent, $moduleID, $moduleName, $moduleType, $config = array()){
		//Default configurations
		$this->config(array(
			'db.errorMode' => \PDO::ERRMODE_EXCEPTION,
			'parser.prefixPlaceholder' => '^'
		));
		//Base constructor for modules
		$this->__traitConstruct($parent, $moduleID, $moduleName, $moduleType, $config);
		
		//Instantiates the PDO connection
		if (isset($this->config('db.password')) && isset($this->config('db.username')))
			$this->PDO = new \PDO($this->config('db.dsn'));
		else
			$this->PDO = new \PDO($this->config('db.dsn'), $this->config('db.username'), $this->config('db.password'));
		
		//Sets the error mode attribute
		$this->PDO->setAttribute(\PDO::ATTR_ERRMODE, $this->config('db.errorMode'));
	}

}
