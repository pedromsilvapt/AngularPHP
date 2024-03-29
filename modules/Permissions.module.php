<?php
namespace AngularPHP\Modules\Permissions;

//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class Permissions {
	use \AngularPHP\Module {
		\AngularPHP\Module::__construct as private __traitConstruct;
	}
		
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
	
	public function addPermissionToEntity($entityType, $entitiesID, $permissionsTypes, $forceRecache = false){		
		//Checks the functions params
		if (is_integer($entitiesID))
			$entitiesID = array($entitiesID);
		elseif (!is_array($entitiesID))
			return(false);
		
		if (is_string($permissionsTypes))
			$permissionsTypes = array($permissionsTypes);
		elseif (!is_array($permissionsTypes))
			return(false);
		
		if (!is_string($entitiesType)) return false;
		
		$queryParams = array();
		//Inserts all permissions in each group
		foreach ($entitiesID as $id){
			if (!is_integer($id)) continue;
			
			//Gets the permissions for the current group
			$currentPermissions = $this->getPermissions($id, $permissionsTypes, $entityType, $forceRecache);
			
			//Begins the construction of the insertion query
			$sql = 'INSERT INTO ^permissions
					  (ID_ENTITY, permission_type, entity_type)
					  VALUES
					  ';
			
			//Add's each permission into the database
			foreach ($permissionsTypes as $permissionType){					
				//Checks if the current permission already exists in the specified group
				if ($currentPermissions[$id][$permissionType] == false){
					//Builds the query string with the current permission
					$sql .= '(?, ?, ?")
							   ';
					$queryParams[] = $id;
					$queryParams[] = $permissionType;
					$queryParams[] = $entityType;
					//Add's the current permission to the permissions cache of the current group
					$this->cachePermissions[$entitiesID][$id][$permissionType] = true;
				}
			}
			$query = call_user_func_array(array($this->db, 'query'), array_merge(array($sql), $queryParams));
		}
		
		return(true);
	}
	
	public function removePermissionsToEntity($entityType, $entitiesID, $permissionsList, $forceRecache = false){		
		//Checks the functions params
		if (!is_string($entitiesType) || !is_bool($forceRecache))
			return false;
		
		if (is_integer($entitiesID))
			$entitiesID = Array($entitiesID);
		elseif (!is_array($entitiesID))
			return(false);
		
		if (is_string($permissionsList))
			$permissionsList = Array($permissionsList);
		elseif (!is_array($permissionsList))
			return(false);
		
		
		//Removes all permissions in each group
		foreach ($groupsID as $groupID){
			$removingPermissions = Array();
			
			//Gets the permissions for the current group
			$this->getPermissions($groupID, $permissionsTypes);
			//Checks if the group exists
			if (is_integer($groupID)){
				//Removes each permission into the database
				foreach ($permissionsTypes as $permissionType){					
					//Checks if the current permission already exists in the specified group
					//We may be sure that this particular permission is cached because we checked them all in this list before
					if ($this->groups[$groupID]['permissions'][$permissionType] == true){
						//Adds the current permission to the removing permissions list
						$removingPermissions[] = $permissionType;
						//Removes the current permission from the permissions cache of the current group
						$this->groups[$groupID]['permissions'][$permissionType] = false;
					}
				}
				
				$query = 'DELETE FROM ^permissions
						  WHERE ID_GROUP = ? and permission_type IN (?)';
				$query = $this->db->query($query, $groupID, implode(',', $removingPermissions));
			}
		}
		
		return(true);
	}
	
	public function getPermissions($entitiesType, $entitiesID, $permissionsList, $forceRecache = false){		
		//Checks the function params
		if (((!is_integer($entitiesID) && !is_array($entitiesID))) || !is_bool($forceRecache))
			return(false);
		if (!is_string($permissionsList) && !is_array($permissionsList))
			return false;
		if (!is_string($entitiesType))
			return false;
		
		//If the entitiesID is an integer, transforms it into an array
		if (is_integer($entitiesID))
			$entitiesID = Array($entitiesID);
		
		
		//Transforms the string (if exists) in an array
		if (is_string($permissionsList))
			$permissionsList[] = $permissionsList;
		
		//Get's permissions already cached
		$returnedPermissions = Array();
		$unknownPermissions = $permsList;
		
		if ($forceRecache == false){
			//For each input group see's if there is already any information cached
			foreach ($entitiesID as $id){
				//If there is any cache for this entity
				if (isset($this->cachePermissions[$entitiesType][$id])){
					//Goes through all the unkown permissions
					foreach ($unknownPermissions as $permissionName){
						if (isset($this->cachePermissions[$entitiesType][$id][$permissionName]) && (empty($returnedPermissions[$permissionName]) or !$returnedPermissions[$permissionName])){
							$returnedPermissions[$permissionName] = $this->cachePermissions[$entitiesType][$id][$permissionName];
							unset($unknownPermissions[$permissionName]);
						}
					}
				}
			}
		}
		
		//See's if there are any permissions unknow yet
		//If yes, get's them from the database
		if (count($unknownPermissions) > 0){
			$query = 'SELECT ID_ENTITY, permission_type, entity_type
					  FROM ^permissions
					  WHERE ID_ENTITY IN ("'.implode('","', $entitiesID).'") and permission_type IN ("'.implode('","', $unknownPermissions).' and entity_type = ?")
					  ORDER BY permission_type ASC';
			$query = $this->db->query($query, $entitiesType);
			$permissions = Array();
			$permissionsByEntity = Array();
			
			while ($row = $query->fetch(PDO::FETCH_ASSOC)){
				$permissions[$row['permission_type']] = true;
				$permissionsByGroup[$row['ID_ENTITY']][$row['permission_type']] = true;
			}
			
			//Checks what permissions the group has or don't
			foreach($unknownPermissions as $permissionName){
				//This set of groups has the permission
				if (isset($permissions[$permissionName])){
					$returnedPermissions[$permissionName] = true;
					//So goes through all the groups and checks each one
					foreach ($groupID as $id){
						//If they have that permission
						if (isset($permissionsByGroup[$id][$permissionName])){
							$this->cachePermissions[$entitiesType][$id][$permissionName] = true;
						//Or not
						} else {
							$this->cachePermissions[$entitiesType][$id][$permissionName] = false;
						}
					}
				} else {
					$returnedPermissions[$permissionName] = false;					
					//Sets all the groups with false for the permission (none of them had such permission)
					foreach ($groupID as $id){
						$this->cachePermissions[$entitiesType][$id][$permissionName] = false;
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

	public function __construct($parent, $moduleID, $moduleName, $moduleType){
		$this->__traitConstruct($parent, $moduleID, $moduleName, $moduleType);
		
		$this->db = $this->load('Database');
	}
}