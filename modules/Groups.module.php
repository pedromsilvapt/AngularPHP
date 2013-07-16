<?php
namespace AngularPHP\Modules\Groups;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Groups {
	use \AngularPHP\Module {
		\AngularPHP\Module::__construct as private __traitConstruct;
	}
	
	private $db;
	private $users;
	private $groups = Array();
	private $usersGroups = Array();
	const HAS_ALL = 0;
	const JUST_ONE = 1;	
	
	public function getGroups($forceRecache = false){
		$DB = $this->modulesManager->getModule('Database');
		
		//Checks parameters consistency
		if (!is_bool($forceRecache)){
			return(false);
		}
		
		//First checks if the groups are cached or a recache if needed. This allows better performance
		if (empty($this->groups) or $forceRecache == true){
			//Retrieves the groups
			$query = 'SELECT ID, name
					  FROM ^groups
					  ORDER BY name ASC';
			$query = $DB->query($query);
			
			//Adds each one of them to the cache
			while ($row = $query->fetch(PDO::FETCH_ASSOC)){
				$this->groups[$row['ID']]['ID'] = $row['ID'];
				$this->groups[$row['ID']]['name'] = $row['name'];
			}
		}
		
		//And returns them
		return($this->groups);
	}
	
	public function getGroup($groupID){
		//If the cache is empty, get the groups from the database
		if (empty(self::$groups))
			$this->getGroups();
		
		//Checks the parameters consistency
		if (!is_integer($groupID))
			return(false);
		
		//If the group doesn't exist, returns false
		if (!isset($this->groups[$groupID]))
			return(false);
		
		//Else, returns the requested group
		return($this->groups[$groupID]);
	}
	
	public function createGroup($groupName, $basePermissionsGroup = -1){
		$DB = $this->modulesManager->getModule('Database');
		
		//Inserts the group into the MySQL table
		$query = 'INSERT INTO ^groups (name)
				  VALUES (?)';
		$query = $DB->query($query, $groupName);
		$id = $DB->getPDOConnection()->lastInsertId();
		
		//Checks if is necessary to copy the permissions from any existing group to the new one
		if ($basePermissionsGroup > 0 and isset($this->groups[$basePermissionsGroup])){
			$query = 'SELECT permission_type
					  FROM ^permissions
					  WHERE ID_GROUP = ?';
			$query = $DB->query($query, $basePermissionsGroup);
			
			//Checks if the base group has any permissions
			if ($query->rowcount() > 0){
				//Copies them
				$permissions = $query->fetchAll(PDO::FETCH_ASSOC);
				
				$query = 'INSERT INTO ^permissions
						  (ID_GROUP, permission_type)
						  VALUES
						  ';
				//Generates the query
				//One line per permission
				foreach($permissions as $permission){
					$query .= '('.(integer)$basePermissionsGroup.', "'.$permission['permissionType'].'")
							 ';
				}
				
				$query = $DB->query($query);
			}
			
		}
		
		//Refreshes the groups list
		$this->getGroups(true);
		
		return($id);
	}
	
	public function removeGroup($groupID){
		$DB = $this->modulesManager->getModule('Database');
		
		if (!is_integer($groupID))
			return(false);
		
		if (empty($this->groups))
			self::getGroups(true);
		
		if (empty($this->groups[$groupID]))
			return(false);
		
		//Deletes all the references from users to this group
		$query = 'DELETE FROM ^users_groups
				  WHERE ID_GROUP = ?';
		$query = $DB->query($query, $groupID);
		
		//Deletes the permissions based on this group
		$query = 'DELETE FROM ^permissions
				  WHERE ID_GROUP = ?';
		$query = $DB->query($query, $groupID);
		
		//Deletes the group from the database
		$query = 'DELETE FROM ^groups
				  WHERE ID = ?';
		$query = $DB->query($query, $groupID);
		
		unset($this->groups[$groupID]);
		return(true);
	}
	
	public function groupExists($groupID, $forceRecache = false){
		$DB = $this->modulesManager->getModule('Database');
		
		if (empty($this->$groups) or $forceRecache == true)
			$this->getGroups(true);
		
		//Checks if the params are wrong or the group dowsn't exists
		return (is_integer($groupID) && isset($this->groups[$groupID]));
	}
	
	public function getUserGroups($userID, $justGroupsIDs = false, $forceRecache = false){
		$DB = $this->modulesManager->getModule('Database');
		$Users = $this->modulesManager->getModule('Users');
		
		if (empty($this->groups) or $forceRecache == true){
			$this->getGroups(true);
		}
		
		if (!empty($this->usersGroups[$userID])){
			if ($justGroupsIDs){
				$return = Array();
				foreach ($this->usersGroups[$userID] as $value){
					$return[] = $value['ID'];
				}
				return($return);
			} else {
				return($this->usersGroups[$userID]);
			}
		}
		
		if (!$Users->userExists($userID)){
			return(false);
		} else {
			$query = 'SELECT g.ID, g.name
					  FROM ^users_groups AS ug
					  INNER JOIN ^groups AS g
					  ON ug.ID_USER = ? and g.ID = ug.ID_GROUP';
			$query = $DB->query($query, $userID);
			
			$userGroups = Array();
			$return = Array();
			
			while ($row = $query->fetch(PDO::FETCH_ASSOC)){
				if ($justGroupsIDs){
					$return[] = $row['ID'];
				} else {
					$return[$row['ID']]['ID'] = $row['ID'];
					$return[$row['ID']]['name'] = $row['name'];
				}
				$userGroups[$row['ID']]['ID'] = $row['ID'];
				$userGroups[$row['ID']]['name'] = $row['name'];
			}
			
			
			$this->usersGroups[$userID] = $userGroups;
				
			return($return);
		}
	}
	
	public function isUserInGroup($userID, $groupID){		
		if (!is_integer($userID) or !is_integer($groupID)){
			return(false);
		}
		
		if (empty($this->groups)){
			$this->getGroups(true);
		}
		
		if (empty($this->usersGroups[$userID])){
			$this->getUserGroups($userID);
		}
		
		return(isset($this->usersGroups[$userID][$groupID]));		
	}
	
	public function getUsersIDInGroup($groupID){
		$DB = $this->modulesManager->getModule('Database');
		
		if (!is_integer($groupID))
			return(false);
		
		if (!$this->groupExists($groupID))
			return(false);
		
		$query = 'SELECT D_USER
				  FROM ^users_groups
				  WHERE ID_GROUP = ?';
		$query = $DB->query($query, $groupID);
		
		return($query->fetchAll(PDO::FETCH_COLUMN, 0));
	}
	
	public function getUsersInGroup($groupID){
		$DB = $this->modulesManager->getModule('Database');
		
		if (!is_integer($groupID))
			return(false);
		
		if (!self::groupExists($groupID))
			return(false);
		
		$query = 'SELECT u.ID, u.username, u.email, u.website
				  FROM ^users AS u
				  INNER JOIN ^users_groups AS ug
				  ON ug.ID_USER = u.ID and ug.ID_GROUP = ?';
		$query = $DB->query($query, $groupID);
		
		return($query->fetchAll(PDO::FETCH_ASSOC));
	}
	
	public function addUserToGroup($userID, $groupID){
		$DB = $this->modulesManager->getModule('Database');
		
		if (empty($this->groups))
			$this->getGroups(true);
		
		if (!is_integer($userID) or !is_integer($groupID))
			return(false);
		
		if ($this->isUserInGroup($userID, $groupID))
			return(false);
		
		$query = 'INSERT INTO ^users_groups
				  (ID_USER, ID_GROUP)
				  VALUES
				  (?, ?)';
		$query = $DB->query($query, $userID, $groupID);
		$this->getUserGroups($userID, true);
		
		return(true);
	}
	
	public function removeUserFromGroup($userID, $groupID){
		$DB = $this->modulesManager->getModule('Database');
		
		if (empty($this->groups))
			$this->getGroups(true);
		
		if (!is_integer($userID) or !is_integer($groupID))
			return(false);
		
		if (!$this->isUserInGroup($userID, $groupID))
			return(false);
		
		$query = 'DELETE FROM ^users_groups
				  WHERE ID_USER = ? and ID_GROUP = ?';
		$query = $DB->query($query, $userID, $groupID);
		
		unset($this->usersGroups[$userID][$groupID]);
		
		return(true);
	}

	static public function editGroup($groupID, $newGroupName){
		$DB = $this->modulesManager->getModule('Database');
		
		$this->getGroups();
		
		if (!is_integer($groupID) or !is_string($newGroupName))
			return(false);		
		
		if (!isset(self::$groups[$groupID]))
			return(false);
		
		$query = 'UPDATE ^groups
				  SET name = ?
				  WHERE ID = ?';
		$DB->query($query, $newGroupName, $groupID);
		
		return(true);
	}
	
	public function __construct($parent, $moduleID, $moduleName, $moduleType){
		$this->__traitConstruct($parent, $moduleID, $moduleName, $moduleType);
	
		$this->db = $this->load('Database');
		$this->users = $this->load('Users');
	}
	
}

