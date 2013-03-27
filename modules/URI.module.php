<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class URIModule extends Module {
	
	private $uri;
	private $segments;
	public $inf = 0;
	
	/**
	* Get's the current "pretty" URI from the URL.  It will also correct the QUERY_STRING server var and the $_GET array.
	* It supports all forms of mod_rewrite and the following forms of URL:
	* 
	* http://example.com/index.php/foo (returns '/foo')
	* http://example.com/index.php?/foo (returns '/foo')
	* http://example.com/index.php/foo?baz=bar (returns '/foo')
	* http://example.com/index.php?/foo?baz=bar (returns '/foo')
	* 
	* Similarly using mod_rewrite to remove index.php:
	* http://example.com/foo (returns '/foo')
	* http://example.com/foo?baz=bar (returns '/foo')
	* 
	* @author      Dan Horrigan <http://dhorrigan.com>
	* @copyright   Dan Horrigan
	* @license     MIT License <http://www.opensource.org/licenses/mit-license.php>
	* @param   bool    $prefix_slash   whether to return the uri with a '/' in front
	* @return  string  the uri
	*/
	
	public static function getDependencies(){
		return(Array());
	}
	
	public function calculateURI($uri = null) {
		if (isset($_SERVER['PATH_INFO']) && $uri === null)
		{
			$uri = $_SERVER['PATH_INFO'];
		}
		elseif($uri !== null || isset($_SERVER['REQUEST_URI']))
		{	
			if ($uri === null){
				$uri = $_SERVER['REQUEST_URI'];
			}
			if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0)
			{
				$uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
			}
			elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0)
			{
				$uri = substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
			}

			// This section ensures that even on servers that require the URI to be in the query string (Nginx) a correct
			// URI is found, and also fixes the QUERY_STRING server var and $_GET array.
			if (strncmp($uri, '?/', 2) === 0)
			{
				$uri = substr($uri, 2);
			}
			$parts = preg_split('#\?#i', $uri, 2);
			$uri = $parts[0];
			if (isset($parts[1]))
			{
				$_SERVER['QUERY_STRING'] = $parts[1];
				parse_str($_SERVER['QUERY_STRING'], $_GET);
			}
			else
			{
				$_SERVER['QUERY_STRING'] = '';
				$_GET = array();
			}
			$uri = parse_url($uri, PHP_URL_PATH);
		}
		else
		{
			// Couldn't determine the URI, so just return false
			return false;
		}
    
		// Do some final cleaning of the URI and return it
		$this->uri = str_replace(array('//', '../'), '/', trim($uri, '/'));
		$this->segments = explode("/", $this->uri);
		return($this->uri);
	}
	
	public function getURI($prefixSlash = false){
		return ($prefixSlash ? '/' : '').$this->uri;
	}
	
	public function getSegments(){
		return $this->segments;
	}
	
	public function getSegmentsCount(){
		return(count($this->segments));
	}
	
	public function getSegment($segments_index, $castType = 'string'){		
		if (count($this->segments) <= $segments_index){
			return(false);
		} else {
			
			$var = $this->segments[$segments_index];
			
			switch ($castType) {
				case 'string':
					$var = (string)$var;
					break;
				case 'str':
					$var = (string)$var;
					break;
				case 'integer':
					$var = (integer)$var;
					break;
				case 'int':
					$var = (integer)$var;
					break;
				case 'bool':
					$var = (boolean)$var;
					break;
				case 'boolean':
					$var = (boolean)$var;
					break;
				case 'float':
					$var = (float)$var;
					break;
				case 'double':
					$var = (double)$var;
					break;
				default:
					$var = (string)$var;
			}
			
			return($var);
		}
	}
	
	public function __construct(ModulesManager $modulesManager, $uri = null){
		parent::__construct($modulesManager);
		$this->calculateURI($uri);
	}
}

?>