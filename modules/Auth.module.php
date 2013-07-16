<?php
namespace AngularPHP\Modules\Auth;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Auth {
	use \AngularPHP\Module {
		\AngularPHP\Module::__construct as private __traitConstruct;
	}
	
	private $db;
	
	public function checkCredentials($username, $password){		
		$query = 'SELECT ID
				  FROM ^users
				  WHERE username = ? and password = SHA1(CONCAT(?, salt))
				  LIMIT 1';
				  
		$query = $this->db->query($query, $username, $password);
		
		if ($query->rowCount() == 0) return(false);
		else return($query->fetchColumn(0));
	}
	
	public function login($username, $password){		
		$userID = $this->checkCredentials($username, $password);
		
		if ($userID === false)
			return(false);
		
		$query = 'SELECT username, email
				  FROM ^users
				  WHERE ID = ?
				  LIMIT 1';
		$query = $$this->db->query($query, $userID);
		$data = $query->fetch(PDO::FETCH_ASSOC);
		
		$_SESSION['isLoggedIn'] = true;
		$_SESSION['userID'] = $userID;
		$_SESSION['username'] = $data['username'];
		$_SESSION['email'] = $data['email'];
		
		return(true);
	}
	
	public function logout(){
		$_SESSION['isLoggedIn'] = false;
		unset($_SESSION['userID']);
		unset($_SESSION['username']);
		unset($_SESSION['email']);
		return(session_destroy());
	}
	
	public function checkLogin(){
		//Not implemented
	}
	
	public function getUserID(){
		if ($this->isLoggedIn()){
			return((int)$_SESSION['userID']);
		} else {
			return(-1);
		}
	}
	
	public function getUserIP(){
		return($_SERVER['REMOTE_ADDR']);
	}
	
	public function isLoggedIn(){
		if (empty($_SESSION['isLoggedIn']) or $_SESSION['isLoggedIn'] !== true){
			return(false);
		} else {
			return(true);
		}
	}
	
	
	public function __construct($parent, $moduleID, $moduleName, $moduleType){
		$this->__traitConstruct($parent, $moduleID, $moduleName, $moduleType);
		
		$this->db = $this->load('Database');
	}
}

