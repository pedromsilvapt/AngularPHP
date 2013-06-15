<?php
namespace AngularPHP\Modules\Permissions;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Permissions extends \AngularPHP\Module {
	
	private $db;
	private $cachePermissions = array();
	const HAS_ALL = 0;
	const JUST_ONE = 1;	
	
	public static function getDependencies(){
		return(array('Database'));
	}
	
	public function clearCache(){
		$this->cachePermissions = array();
	}
	
	public function addPermissionsToEntity($entityType, $entitiesID, $permissionsTypes, $forceRecache = false){			
		return $this->updatePermissionsOfEntity($entitiesType, $entitiesID, $permissionsTypes, array(), $forceRecache = false);
	}
	
	public function removePermissionsToEntity($entityType, $entitiesID, $permissionsList, $forceRecache = false){	
		return $this->updatePermissionsOfEntity($entitiesType, $entitiesID, array(), $permissionsTypes, $forceRecache = false);
	}
	
	public function updatePermissionToEntityAssosc($entityType, $entitiesID, $permissionsList, $forceRecache = false){
		$addPermissions = array();
		$removePermissions = array();
		
		foreach($permissionsList as $permission => $state){
			if ($state === true)
				$addPermissions[] = $permission;
			if ($state === false)
				$removePermissions[] = $permission;
		}
		
		return $this->updatePermissionsOfEntity($entityType, $entitiesID, $addPermissions, $removePermissions, $forceRecache);
	}
	
	public function updatePermissionsOfEntity($entityType, $entitiesID, $addPermissions, $removePermissions, $forceRecache = false){
		//Checks the functions params
		if (is_integer($entitiesID))
			$entitiesID = array($entitiesID);
		elseif (!is_array($entitiesID))
			return(false);
		
		if (is_string($addPermissions))
			$addPermissions = array($addPermissions);
		elseif (!is_array($addPermissions))
			return(false);
			
		if (is_string($removePermissions))
			$removePermissions = array($removePermissions);
		elseif (!is_array($removePermissions))
			return(false);
		
		if (!is_string($entityType)) return false;
		
		//Now add's the permissions
		if (count($addPermissions) > 0){
			//Gets the permissions for the current group
			$currentPermissions = $this->getPermissions($entityType, $entitiesID, $addPermissions, $forceRecache);

			$queryParams = array();
			//Begins the construction of the insertion query
			$sql = 'INSERT INTO ^permissions
					  (ID_ENTITY, permission_type, entity_type)
					  VALUES
					  ';
			//Inserts all permissions in each group
			foreach ($entitiesID as $id){
				if (!is_integer($id)) continue;				
				
				//Add's each permission into the database
				foreach ($addPermissions as $permissionType){					
					//Checks if the current permission already exists in the specified group
					if ($currentPermissions[$id][$permissionType] == false){
						//Builds the query string with the current permission
						$sql .= '(?, ?, ?)
								   ';
						$queryParams[] = $id;
						$queryParams[] = $permissionType;
						$queryParams[] = $entityType;
						//Add's the current permission to the permissions cache of the current group
						$this->cachePermissions[$entityType][$id][$permissionType] = true;
					}
				}
			}
			if (count($queryParams) > 0)
				$query = $this->db->queryArr($sql, $queryParams);
		}
		
		if (count($removePermissions) > 0){
			$removingIDs = array();
			//Removes all permissions in each group
			foreach ($entitiesID as $entityID){
				//Checks if the group exists
				if (is_integer($entityID))
					$removingIDs[] = $entityID;
			}
			
			if (count($removingIDs) > 0 && count($removePermissions) > 0){
				$query = 'DELETE FROM ^permissions
						  WHERE entity_type = ? and ID_ENTITY IN('.\AngularPHP\trimOffEnd(1, str_repeat('?,', count($entitiesID))).') and permission_type IN ('.\AngularPHP\trimOffEnd(1, str_repeat('?,', count($removePermissions))).')';
				$params = array_merge(array($entityType), $entitiesID, $removePermissions);
			
				$query = $this->db->queryArr($query, $params);
			}
		}
		
		return(true);
	}
	
	public function getPermissions($entityType, $entitiesID, $permissionsList, $forceRecache = false){		
		//Checks the function params
		if (((!is_integer($entitiesID) && !is_array($entitiesID))) || !is_bool($forceRecache))
			return(false);
		if (!is_string($permissionsList) && !is_array($permissionsList))
			return false;
		if (!is_string($entityType))
			return false;
		
		//If the entitiesID is an integer, transforms it into an array
		if (is_integer($entitiesID))
			$entitiesID = Array($entitiesID);
		
		if (count($entitiesID) == 0) return false;
		
		//Transforms the string (if exists) in an array
		if (is_string($permissionsList))
			$permissionsList = array($permissionsList);
		
		//Get's permissions already cached
		$returnedPermissions = Array();
		$unknownPermissions = $permissionsList;
		$unknownPermissionsCount = array();
		
		if ($forceRecache == false){
			$idsCount = count($entitiesID);
			//For each input group see's if there is already any information cached
			foreach ($entitiesID as $id){
				//If there is any cache for this entity
				if (isset($this->cachePermissions[$entityType][$id])){
					//Goes through all the unkown permissions
					foreach ($unknownPermissions as $permissionName){
						if (isset($this->cachePermissions[$entityType][$id][$permissionName]) && (empty($returnedPermissions[$id][$permissionName]) or !$returnedPermissions[$id][$permissionName])){
							$returnedPermissions[$id][$permissionName] = $this->cachePermissions[$entityType][$id][$permissionName];

							if (isset($unknownPermissionsCount[$permissionName]))
								$unknownPermissionsCount[$permissionName] += 1;
							else
								$unknownPermissionsCount[$permissionName] = 1;
							
							if ($unknownPermissionsCount[$permissionName] == $idsCount)
								unset($unknownPermissions[$permissionName]);
						}
					}
				}
			}
		}
		
		//See's if there are any permissions unknow yet
		//If yes, get's them from the database
		if (count($unknownPermissions) > 0){
			$sql = 'SELECT ID_ENTITY, permission_type, entity_type
					  FROM ^permissions
					  WHERE ID_ENTITY IN ('.\AngularPHP\trimOffEnd(1, str_repeat('?,', count($entitiesID))).') AND permission_type IN ('.\AngularPHP\trimOffEnd(1, str_repeat('?,', count($unknownPermissions))).') AND entity_type = ?
					  ORDER BY permission_type ASC';
			//"'.implode('","', $entitiesID).'"
			$permsParams = array();
			foreach ($unknownPermissions as $key => $value)
				$permsParams[] = $value;
			
			$query = $this->db->queryArr($sql, array_merge($entitiesID, $permsParams, array($entityType)));
			$permissions = Array();
			$permissionsByEntity = Array();
			
			while ($row = $query->fetch(\PDO::FETCH_ASSOC)){
				$permissions[$row['permission_type']] = true;
				$permissionsByEntity[$row['ID_ENTITY']][$row['permission_type']] = true;
			}
			
			//Checks what permissions the group has or don't
			foreach($unknownPermissions as $permissionName){
				//This set of groups has the permission
				if (isset($permissions[$permissionName])){
					//So goes through all the groups and checks each one
					foreach ($entitiesID as $id){
						$returnedPermissions[$id][$permissionName] = true;
						//If they have that permission
						if (isset($permissionsByEntity[$id][$permissionName])){
							$this->cachePermissions[$entityType][$id][$permissionName] = true;
						//Or not
						} else {
							$this->cachePermissions[$entityType][$id][$permissionName] = false;
						}
					}
				} else {
					//Sets all the groups with false for the permission (none of them had such permission)
					foreach ($entitiesID as $id){
						$returnedPermissions[$id][$permissionName] = false;					
						$this->cachePermissions[$entityType][$id][$permissionName] = false;
					}
				}
			}
		}
		
		return($returnedPermissions);
	}
	
	public function getAllPermissions($entityType, $entityID, $forceRecache = false){		
		if (!is_integer($entityID))
			return false;
		
		//If cache is allowed and there is already information cached, sends it
		if (!$forceRecache && isset($this->cachePermissions[$entityType][$entityID])){
			$returningPermissions = array();
			//Returns all the permissions the entity has
			foreach($this->cachePermissions[$entitiesType][$entityID] as $perm => $value){
				if ($value) $returningPermissions[] = $perm;
			}
		}
		
		//Get's all the permissions from the database
		$query = 'SELECT permission_type
				  FROM ^permissions
				  WHERE ID_ENTITY = ? and entity_type = ?
				  ORDER BY permission_type ASC';
		$query = $this->db->query($query, $entityID, $entityType);
		
		//Get's all the permissions on cache. We will use it later
		if (isset($this->cachePermissions[$entityType][$entityID]))
			$permissions = $this->cachePermissions[$entityType][$entityID];
		
		$returningPermissions = Array();
		
		//Registers all the permissions
		while ($row = $query->fetch(PDO::FETCH_ASSOC)){
			//If the group has such permission, remove it from the temporary array
			if (isset($permissions))
				unset($permissions[$row['permission_type']]);
			
			//Now adds the permission to the cache as true
			$this->cachePermissions[$entityType][$entityID][$row['permission_type']] = true;
			//And to the returning permissions too
			$returningPermissions[] = $row['permission_type'];
		}
		
		if (isset($permissions)){
			//Set's all the permissions that were not on the database to false
			foreach ($permissions as $permission_type => $value){
				$this->cachePermissions[$entityType][$entityID][$permission_type] = false;
			}
		}
		
		//Returns the permission that are on the database
		return($returningPermissions);
	}

	public function entityHasPermissions($entityType, $entityID, $permissionsList, $flags = 0, $forceRecache = false){
		//Checks the parameter consistency
		if ($flags != self::HAS_ALL and $flags != self::JUST_ONE)
			return(false);
		
		if (is_string($permissionsList))
			$permissionsList = Array($permissionsList);
		elseif (!is_array($permissionsList))
			return(false);
		
		//Get's these permissions
		$perms = $this->getPermissions($entityType, $entityID, $permissionsList, $forceRecache);
		
		//Iterates through each of the permissions
		foreach ($permissionsList as $permissionType){
			//If one of the permissions in the list isn't a string and they must be all true
			if (!is_string($permissionType) and $flags == self::HAS_ALL){
				return(false);
			//Else, the permission is a valid string
			} else {
				//It is true and there is only need to be one true
				if ($perms[$permissionType] == true and $flags == self::JUST_ONE){
					return(true);
				//Or it is false and they must be all true
				} elseif ($perms[$permissionType] == false and $flags == self::HAS_ALL) {
					return(false);
				}
			}
		}
		
		if ($flags == self::HAS_ALL)
			return(true);
		elseif ($flags == self::JUST_ONE)
			return(false);
	}

	public function __construct(\AngularPHP\ModulesManager $modulesManager, \AngularPHP\Modules\Database\Database $db){
		parent::__construct($modulesManager);
		$this->db = $db;
	}

}