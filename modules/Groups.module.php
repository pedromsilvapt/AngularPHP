<?php
namespace AngularPHP\Modules\Groups;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Groups extends \AngularPHP\Module {
	
	private $db;
	private $users;
	private $groups = array();
	private $usersGroups = array();
	const HAS_ALL = 0;
	const JUST_ONE = 1;	
	
	public function getGroups($forceRecache = false, $associative = false, $orderBy = 'order'){
		//Checks parameters consistency
		if (!is_bool($forceRecache)){
			return(false);
		}
		if ($orderBy !== 'order' && $orderBy !== 'name') return false;
		
		
		//First checks if the groups are cached or a recache if needed. This allows better performance
		if (empty($this->groups) or $forceRecache == true){
			//Retrieves the groups
			$query = 'SELECT ID, name, `order`
					  FROM ^groups
					  ORDER BY `'.$orderBy.'` ASC';
			$query = $this->db->query($query);
			$result = $query->fetchAll(\PDO::FETCH_ASSOC);
			//Adds each one of them to the cache
			foreach($result as $index => $row){
				$this->groups[$row['ID']]['ID'] = $row['ID'];
				$this->groups[$row['ID']]['name'] = $row['name'];
				$this->groups[$row['ID']]['order'] = $row['order'];
			}
		}
		
		//And returns them
		if ($associative)
			return($this->groups);
		else {
			if (!isset($result)){				
				$result = array();
				foreach($this->groups as $id => $row){
					$result[] = $row;
				}
			}
			return($result);
		}
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
	
	public function createGroup($groupName, $afterGroup = -1){
		$DB = $this->modulesManager->getModule('Database');
		$allGroups = $this->getGroups(false, true, 'order');
		
		if ($afterGroup >= 0)
			$currentOrder = 1;
		else {
			$newGroupOrder = 1;
			$currentOrder = 2;
		}
		
		$ids = array();
		$params = array();
		$sql = 'UPDATE ^groups SET `order` = (CASE ID ';
		foreach($allGroups as $key => $value){
			$sql .= 'WHEN ? THEN ? ';
			$params[] = $key;
			$params[] = $currentOrder;
			
			$ids[] = $key;
			
			$allGroups[$key]['order'] = $currentOrder;
			
			if ($key == $afterGroup){
				$currentOrder++;
				$newGroupOrder = $currentOrder;
			}
			
			$currentOrder++;
		}
		
		$sql .= 'ELSE `order` END) ';
		$sql .= 'WHERE `ID` IN (';
		foreach($ids as $id){
			$sql .= '?, ';
			$params[] = $id;
		}
		
		$sql = \AngularPHP\trimOffEnd(2, $sql);
		$sql .= ')';
		
		$query = $this->db->queryArr($sql, $params);
		
		if (!isset($newGroupOrder))
			$newGroupOrder = $currentOrder;
		
		//Inserts the group into the MySQL table
		$query = 'INSERT INTO ^groups (name, `order`)
				  VALUES (?, ?)';
		$query = $DB->query($query, $groupName, (int)$newGroupOrder);
		$id = $DB->getRawConnection()->lastInsertId();
		
		//Refreshes the groups list
		$this->getGroups(true);
		
		return($id);
	}
	
	public function removeGroup($groupID){		
		if (!is_integer($groupID))
			return(false);
		
		if (empty($this->groups))
			$this->getGroups(true);
		
		if (empty($this->groups[$groupID]))
			return(false);
		
		//Deletes all the references from users to this group
		$query = 'DELETE FROM ^users_groups
				  WHERE ID_GROUP = ?';
		$query = $this->db->query($query, $groupID);
		
		//Deletes the permissions based on this group
		$query = 'DELETE FROM ^permissions
				  WHERE ID_ENTITY = ? AND entity_type = \'group\'';
		$query = $this->db->query($query, $groupID);
		
		//Deletes the group from the database
		$query = 'DELETE FROM ^groups
				  WHERE ID = ?';
		$query = $this->db->query($query, $groupID);
		
		unset($this->groups[$groupID]);
		
		$ids = array();
		$params = array();
		$currentOrder = 1;
		$sql = 'UPDATE ^groups SET `order` = (CASE ID ';
		foreach($this->groups as $key => $value){
			$sql .= 'WHEN ? THEN ? ';
			$params[] = $key;
			$params[] = $currentOrder;
			
			$ids[] = $key;
			
			$this->groups[$key]['order'] = $currentOrder;
			$currentOrder++;
		}
		
		$sql .= 'ELSE `order` END) ';
		$sql .= 'WHERE `ID` IN (';
		foreach($ids as $id){
			$sql .= '?, ';
			$params[] = $id;
		}
		
		$sql = \AngularPHP\trimOffEnd(2, $sql);
		$sql .= ')';
		
		$query = $this->db->queryArr($sql, $params);
		
		
		return(true);
	}
	
	public function editGroup($groupID, $newGroupName, $afterGroup = null){
		$groups = $this->getGroups(true, true);
		$groupID = (int)$groupID;
		
		//Makes some verification with the arguments
		if (!is_string($newGroupName))
			return(false);		
		
		//Updates the group on the database
		$query = 'UPDATE ^groups
				  SET name = ?
				  WHERE ID = ?';
		$query = $this->db->query($query, $newGroupName, $groupID);
		
		
		//If it is defined to change the group's order
		if ($afterGroup !== null && isset($this->groups[$groupID])){
			$afterGroup = (int)$afterGroup;
			
			if ($afterGroup >= 0)
				$currentOrder = 1;
			else {
				$newGroupOrder = 1;
				$currentOrder = 2;
			}
			
			$ids = array();
			$params = array();
			$sql = 'UPDATE ^groups SET `order` = (CASE ID ';
			foreach($groups as $key => $value){
				if ($key === $groupID) continue;
				
				$sql .= 'WHEN ? THEN ? ';
				$params[] = $key;
				$params[] = $currentOrder;
				
				$ids[] = $key;
				
				$allGroups[$key]['order'] = $currentOrder;
				
				if ($key == $afterGroup && $afterGroup !== -1){
					$currentOrder++;
					$newGroupOrder = $currentOrder;
				}
				
				$currentOrder++;
			}
			
			//Now builds the part of the query 
			if (!isset($newGroupName)) $newGroupOrder = $currentOrder;
			$params[] = $groupID;
			$params[] = $newGroupOrder;
			$ids[] = $groupID;
			
			$sql .= 'WHEN ? THEN ? ';
			$sql .= 'ELSE `order` END) ';
			$sql .= 'WHERE ID IN ('.\AngularPHP\trimOffEnd(2, str_repeat('?, ', count($ids))).')';
			
			$query = $this->db->queryArr($sql, array_merge($params, $ids));
		}
		
		return(true);
	}
	
	public function groupExists($groupID, $forceRecache = false){
		if (!isset($this->groups) or $forceRecache == true)
			$this->getGroups(true);
		
		//Checks if the params are wrong or the group dowsn't exists
		return (is_integer($groupID) && isset($this->groups[$groupID]));
	}
	
	public function getUserGroups($userID, $justGroupsIDs = false, $forceRecache = false){
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
		
		if (!$Users->usersExist($userID)){
			return(false);
		} else {
			$query = 'SELECT g.ID, g.name, g.`order`
					  FROM ^users_groups AS ug
					  INNER JOIN ^groups AS g
					  ON ug.ID_USER = ? AND g.ID = ug.ID_GROUP';
			$query = $this->db->query($query, (int)$userID);
			
			$userGroups = Array();
			$return = Array();
			
			while ($row = $query->fetch(\PDO::FETCH_ASSOC)){
				if ($justGroupsIDs){
					$return[] = $row['ID'];
				} else {
					$return[$row['ID']]['ID'] = $row['ID'];
					$return[$row['ID']]['name'] = $row['name'];
					$return[$row['ID']]['order'] = $row['order'];
				}
				$userGroups[$row['ID']]['ID'] = $row['ID'];
				$userGroups[$row['ID']]['name'] = $row['name'];
				$userGroups[$row['ID']]['order'] = $row['order'];
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
		
		return($query->fetchAll(\PDO::FETCH_COLUMN, 0));
	}
	
	public function getUsersInGroup($groupID){
		if (!is_integer($groupID))
			return(false);
		
		if (!$this->groupExists($groupID))
			return(false);
		
		$query = 'SELECT u.ID, u.utilizador AS email, u.nome AS name, u.codigo AS code
				  FROM ^users AS u
				  INNER JOIN ^users_groups AS ug
				  ON ug.ID_USER = u.ID and ug.ID_GROUP = ?
				  WHERE u.ID >= 0';
		$query = $this->db->query($query, $groupID);
		
		return($query->fetchAll(\PDO::FETCH_ASSOC));
	}
	
	public function addUsersToGroup($usersID, $groupID){		
		if (empty($this->groups))
			$this->getGroups(true);
		
		if (!is_integer($groupID))
			return false;
		
		if (!is_array($usersID))
			$usersID = array($usersID);
			
		if (!is_array($usersID))
			return false;
		
		$newIDs = array();
		foreach($usersID as $id){
			if (!$this->isUserInGroup($id, $groupID) && !isset($newIDs[$id]) && ((string)(int)$id == $id))
				$newIDs[$id] = true;
		}
		
		if (count($newIDs) > 0){
			$params = array();
			$sql = 'INSERT INTO ^users_groups
					  (ID_USER, ID_GROUP)
					  VALUES
					  ';
					  
			foreach ($newIDs as $key => $true){
				$sql .= '(?, ?), ';
				$params[] = (int)$key;
				$params[] = (int)$groupID;
			}
			$sql = \AngularPHP\trimOffEnd(2, $sql);
			
			$query = $this->db->queryArr($sql, $params);
			$this->getUsersInGroup($groupID);
		}
		
		return(true);
	}
	
	public function removeUsersFromGroup($usersID, $groupID){		
		if (empty($this->groups))
			$this->getGroups(true);
		
		if (!is_integer($groupID))
			return(false);
		
		if (!is_array($usersID))
			$usersID = array($usersID);
		
		
		$sql = 'DELETE FROM ^users_groups
				  WHERE ';
		
		$params = array();
		foreach ($usersID as $id){
			if (((string)(int)$id != $id)) continue;
			$sql .= '(ID_USER = ? and ID_GROUP = ?) or ';
			$params[] = (int)$id;
			$params[] = (int)$groupID;
		}
		$sql = \AngularPHP\trimOffEnd(4, $sql);
		
		if (count($params) > 0)
			$query = $this->db->queryArr($sql, $params);
		
		return(true);
	}

	public function __construct(\AngularPHP\ModulesManager $modulesManager, \AngularPHP\Modules\Database\Database $db, \AngularPHP\Modules\Users\Users $users){
		parent::__construct($modulesManager);
		$this->db = $db;
		$this->users = $users;
	}
}

