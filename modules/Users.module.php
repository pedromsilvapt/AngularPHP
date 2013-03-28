<?php

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class UsersModule extends Module {
	
	private $db;
	private $cacheState;
	private $cache;
	private $users;
	static $NULL = null;
	
	public function generatePassword($lenght = 9, $strenght = 0){
		$chars = 'abcdefghijklmnpqrstuvwxyz';
		
		if ($strenght >= 1){
			$chars .= '0123456789';
		}
		if ($strenght >= 2){
			$chars .= '?!#@%$';
		}
		
		$pass = '';
		
		for ($i=0; $i<$lenght; $i++){
			if ($strenght >= 3 and rand(0, 1) == 1){
				$password .= strtoupper($chars[rand(0, len($chars) - 1)]);
			} else {
				$password .= $chars[rand(0, len($chars) - 1)];
			}
		}
		return($password);
	}
	
	public function setCacheState($state){
		if (!is_boolean($state)){
			return(false);
		} 
		
		$this->cacheState = $state;
		
		if (!$state){
			unset($this->cache);
		}
		
		return(true);
	}
	
	public function getCacheState(){
		return($this->cacheState);
	}
	
	public function saveInCache($name, $value){
		$this->cache[$name] = $value;
		return(true);
	}
	
	public function clearCache(){
		unset($this->cache);
	}

	
	public function getIDByUsername($username){
		if (!is_string($username)){
			return(false);
		}
		
		//Querys the database for the specified username
		$query = 'SELECT ID
				  FROM ^users
				  WHERE username = ?
				  LIMIT 1';
		$query = $this->db->query($query, $username);
		
		//If results were found
		if ($query->rowCount() > 0){
			//Returns the first column
			return($query->fetchColumn(0));
		} else {
			//Otherwise returns false
			return(false);
		}
	}
	
	public function usernameAlreadyUsed($username){		
		if (!is_string($username)){
			return(false);
		}
		
		//Checks if there is already any user with that username in the Database
		$query = 'SELECT ID
				  FROM ^users
				  WHERE username = ?
				  LIMIT 1';
		$query = $this->db->query($query, $username);
		
		//Returns if any match was found
		return $query->rowCount() > 0;
	}
	
	public function emailAlreadyUsed($email, $excludeUnverified = false){		
		if (!is_string($email)){
			return(false);
		}
		
		$query = '';
		
		//If it's supposed to check unverified emails too
		if ($excludeUnverified){
			$query = 'SELECT ID
					  FROM ^users
					  WHERE email = ? and activated = 1';
		} else {
			$query = 'SELECT ID
					  FROM ^users
					  WHERE email = ?';
		}
		$query = $this->db->query($query, $email);
		
		return $query->rowCount() > 0;
	}
	
		public function createUser($username, $password, $email, $activate, $sendMail){		
		//If the variable's types aren't correct, exits the function
		if (!is_string($username) or !is_string($password) or !is_string($email) or !is_bool($activate) or !is_bool($sendMail))
			return(false);
		
		//Checks if there is already any registered user with the same username/email.
		$sql = $this->db->query('SELECT ID FROM ^users WHERE username = ? or email = ?', $username, $email);
		
		if ($sql->rowCount() > 0){
			return(false);
		} else {
			
			//Creates the user into the Database
			$salt = Users::generatePassword(5, 1);
			$sql = $this->db->query('INSERT INTO ^users (username, password, salt, activated, activation_code)
							   VALUES (?, ?, ?, ?, ?)', $username, sha1($password.$salt), $salt, $email, $activate, $this->generatePassword(50, 3));
		
			//Returns the ID of the created user
			return($this->db->lastInsertId());
		}
	}
	
	//  !
	public function getUserGroups($usersIDs, $justGroupsIDs = false){
		$Groups = $this->modulesManager->getModule('Groups');
		
		//If only one user is specified
		if (is_integer($usersIDs)){
			//Simply returns it's groups
			return($Groups->getUserGroups($usersIDs, $justGroupsIDs));
		//Otherwise, if it's an array of users
		} elseif (is_array($usersIDs)){
			$usersGroups = Array();
			//Loops through them
			foreach($usersIDs as $userID){
				//And if they are integeres
				if (is_integer($userID)){
					//Add's their groups to the array
					$usersGroups[$userID] = $Groups->getUserGroups($userID, $justGroupsIDs);
				}
			}
			return($usersGroups);
		}
	}
	
	public function userExists($userID){
		$DB = $this->modulesManager->getModule('Database');
		
		if (!is_integer($userID)){
			return(false);
		}
		
		$sql = $DB->query('SELECT username
						   FROM ^users
						   WHERE ID = ?', $userID);
		
		return $sql->rowCount() > 0;
	}
	
	public function userHasPermissions($userID, $permissionsList, $flags = 0){
		$Groups = $this->modulesManager->getModule('Groups');
		
		//Checks if the user exists
		if (!$this->userExists($userID)){
			return(false);
		}
		
		//Checks the parameters consistency
		if (!is_integer($userID) or (!is_string($permissionsList) and !is_array($permissionsList))){
			return(false);
		}
		
		//Get's the user groups
		$userGroups = $this->getUserGroups($userID);
		
		//By default the user doesn't has the permissions
		$return = false;
		
		//Checks each group of the user for the permissions
		foreach ($userGroups as $groupID => $group){
			//If one of them returns positive
			if ($Groups->groupHasPermissions($groupID, $permissionsList, $flags) == true){
				//The user has the permission(s)
				return(true);
			}
		}
		//Otherwise not.
		return(false);
	}
	
	public function getUserPermissions($userID, $permissionsList, $forceRecache = false){
		$Groups = $this->modulesManager->getModule('Groups');
	
		//Checks the function params
		if (!is_integer($userID)){
			return(false);
		}
		
		//Get's the user groups
		$userGroups = $this->getUserGroups($userID, true);
		
		//Get's and returns the permissions
		return($Groups->getPermissions($userGroups, $permissionsList, $forceRecache));
	}
	
	
	
	
	
	public function &loadUser($userID){
		global $DB;
		
		if (!is_integer($userID)){
			return self::$NULL;
		}
		
		$sql = $DB->query('SELECT username FROM ^users WHERE ID = ? LIMIT 1', $userID);
		
		if ($sql->rowCount() == 0){
			return self::$NULL;
		}
		
		$this->users[$userID] = new User($userID);
		return $this->users[$userID];
	}
	
	public function unloadUser($userID){
		if (!is_integer($userID) or empty($this->users[$userID])){
			return(false);
		}
		
		unset($this->users[$userID]);
		return(true);
	}
	
	public function isUserLoaded($userID){
		if (!is_integer($userID)){
			return(false);
		}
		
		return(isset($this->users[$userID]));
	}
	
	public function removeUser($userID){
		global $DB;
		
		if (!is_integer($userID)){
			return(false);
		}
		
		$sql = $DB->query('DELETE FROM ^users WHERE ID = ?', $userID);
		
		if ($sql->rowCount() == 0){
			return(false);
		}
		
		$this->unloadUser($userID);
		return(true);
	}
	
	public function &user($userID, $autoload = false){
		if (!is_integer($userID) or !is_boolean($autoload)){
			return(false);
		}
		
		if ($autoload == true and empty($this->users[$userID])){
			return($this->loadUser($userID));
		} elseif (isset($this->users[$userID])) {
			return($this->users[$userID]);
		} else {
			return(false);
		}
	}
	
	public function __construct(ModulesManager $modulesManager, DatabaseModule $db){
		parent::__construct($modulesManager);
		$this->db = $db;
		
		$this->cacheState = $cacheState;
		$this->cache = Array();
		$this->users = Array();
	}
}

?>