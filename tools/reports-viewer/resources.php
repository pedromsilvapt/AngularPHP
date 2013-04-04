<?php
session_start();
define('APPRUNNING', true);

require('..\..\core\Config.class.php');
require('..\..\core\AngularPHP.class.php');

//Starts the Application
$init = new AngularPHP\AngularPHP();

//Checks what page the user is seeing
$init->getModulesManager()->loadModule('UnitsTesting');
$init->getModulesManager()->loadModule('URI');
$init->getModulesManager()->loadModule('Routes');

$routes = $init->getModulesManager()->getModule('Routes');
$routes->when('reportsList')->doThis(function(){
	$reportsList = array('list' => array());
	$reportsList['list'][0] = array('id' => 0, 'title' => 'Just some test\'s test');
	$reportsList['list'][1] = array('id' => 1, 'title' => 'Just some test\'s test 1');
	$reportsList['list'][2] = array('id' => 2, 'title' => 'URI Tests');
	$reportsList['list'][3] = array('id' => 3, 'title' => 'Routing Tests');
	
	echo json_encode($reportsList);
});

$debug = array();
$routes->when('report', ':reportID')->doThis(function($reportID) use ($init, $debug){
	$u = $init->getModulesManager()->getModule('UnitsTesting');
	
	$u->describe('Modules Manager', function() use ($u){
		$te = new AngularPHP\AngularPHP();
		$te->getModulesManager()->loadModule('URI');
		$te->getModulesManager()->loadModule('Routes');
		$te->getModulesManager()->loadModule('Observer');
		
		$u->it('should load 3 modules', function() use ($u, $te){
			$u->expect($te->getModulesManager()->isModuleLoaded('URI'))->toBe(true);
			$u->expect($te->getModulesManager()->isModuleLoaded('Routes'))->toBe(true);
			$u->expect($te->getModulesManager()->isModuleLoaded('Observer'))->toBe(true);
			$u->expect($te->getModulesManager()->isModuleLoaded('UnitsTesting'))->toBe(false);
		});
		$u->it('should not have the UnitsTesting module loaded', function() use ($u, $te){
			$u->expect($te->getModulesManager()->isModuleLoaded('UnitsTesting'))->toBe(false);
		});
	});
	
	$u->describe('URI Tests', function() use ($u, $reportID){
		$te = new AngularPHP\AngularPHP();
		$te->getModulesManager()->loadModule('URI', '/angular-php/tools/reports-viewer/resources.php/seg1/seg2/seg4?ola=1');
		$uri = $te->getModulesManager()->getModule('URI');
		
		$u->it('should parse the url correctly with one given', function() use ($u, $uri){
			$u->expect($uri->getURI())->toBe('seg1/seg2/seg4');
			$u->expect($uri->getURI(true))->toBe('/seg1/seg2/seg4');
		});
		
		$u->it('should get the segments correctly', function() use ($u, $uri){
			$u->expect($uri->getSegment(0))->toBe('seg1');
			$u->expect($uri->getSegment(1))->toBe('seg2');
			$u->expect($uri->getSegment(2))->toBe('seg4');
		});
		
		$te = new AngularPHP\AngularPHP();
		$te->getModulesManager()->loadModule('URI');
		$uri = $te->getModulesManager()->getModule('URI');
		
		$u->it('should parse the url correctly when none is given', function() use ($u, $uri, $reportID){
			$u->expect($uri->getURI())->toBe('report/'.$reportID);
			$u->expect($uri->getURI(true))->toBe('/report/'.$reportID);
		});
		
		$u->it('should get the segments correctly too', function() use ($u, $uri, $reportID){
			$u->expect($uri->getSegment(0))->toBe('report');
			$u->expect($uri->getSegment(1))->toBe($reportID);
			$u->expect($uri->getSegment(2))->toBe(false);
			$u->expect(count($uri->getSegments()))->toBe(2);
			
		});
	});
	
	$u->describe('Routing Tests', function() use ($u, $init, $debug){
		$te = new AngularPHP\AngularPHP();
		$te->getModulesManager()->loadModule('URI', '/angular-php/tools/reports-viewer/resources.php/seg1/seg2/seg4?ola=1');
		$te->getModulesManager()->loadModule('Routes');
		$r = $te->getModulesManager()->getModule('Routes');
		
		$debug['route'] = 0;
		$r->when('seg1', ':seg2', 'seg4')->doThis(function($seg2) use ($u, $debug){
			$debug['route'] = 1;
		})->when('seg1', 'seg2', 'seg3')->doThis(function() use ($debug){
			$debug['route'] = 2;		
		})->when('seg1', 'seg4', ':seg3', ':seg5')->doThis(function($seg3, $seg5) use ($debug){
			$debug['route'] = 3;
		})->set('link1')->when('hard', ':var1')->doThis(function($var1){
			
		})->set('link2')->when('hard', ':var1', ':var2', 'hard2', 'hard3')->doThis(function($var1){
			
		})->set('link3')->when(':var1', ':var2', 'hard')->doThis(function($var1){
			
		})->go();
		
		
		$u->it('should detect the correct route', function() use ($u, $r, $debug){
			//echo $debug['route'];
			$u->expect($debug['route'])->toBe(1);
		});
		$u->it('should output correct links', function() use ($u, $r){
			$u->expect($r->linkTo('link1', array('var1' => 2)))->toBe('/hard/2');
			$u->expect($r->linkTo('link2', array('var1' => 2, 'var2' => 1)))->toBe('/hard/2/1/hard2/hard3');
			$u->expect($r->linkTo('link2', array('var1' => 2)))->toBe('/hard/2');
			$u->expect($r->linkTo('link2'))->toBe('/hard');
			$u->expect($r->linkTo('link3', array('var1' => 2, 'var2' => 1)))->toBe('/2/1/hard');
			$u->expect($r->linkTo('link3'))->toBe('');
		});
	});
	
	$error = false;
	switch($reportID){
		case 0:
			$u->run('default', 'Modules Manager');
			break;
		case 1:
			$u->run('default', 'Modules Manager', 'Routing Tests');
			break;
		case 2:
			$u->run('default', 'URI Tests');
			break;
		case 3:
			$u->run('default', 'URI Tests', 'Routing Tests');
			break;
		default:
			$error = true;
			break;
	}
	
	if (!$error) echo $u->exportReport('default', 'json');
});

$routes->go();