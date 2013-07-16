<?php
session_start();
define('APPRUNNING', true);

require('..\..\core\AngularPHP.class.php');

//Starts the Application
$init = new AngularPHP\AngularPHP();

//Checks what page the user is seeing
$init->modulesManager()->load('UnitsTesting');
$init->modulesManager()->load('URI');
$init->modulesManager()->load('Routes');

$routes = $init->modulesManager()->load('Routes');
$routes->when('reportsList')->doThis(function(){
	$reportsList = array('list' => array());
	$reportsList['list'][0] = array('id' => 0, 'title' => 'Just some test\'s test');
	$reportsList['list'][1] = array('id' => 1, 'title' => 'Just some test\'s test 1');
	$reportsList['list'][2] = array('id' => 2, 'title' => 'URI Tests');
	$reportsList['list'][3] = array('id' => 3, 'title' => 'Routing Tests');
	
	echo json_encode($reportsList);
});

$routes->when('report', ':reportID')->doThis(function($reportID) use ($init){
	$u = $init->modulesManager()->load('UnitsTesting');
	
	$u->describe('Modules Manager', function() use ($u){
		$te = new AngularPHP\AngularPHP();
		$te->modulesManager()->load('URI');
		$te->modulesManager()->load('Routes');
		$te->modulesManager()->load('Observer');
		
		$u->it('should load 3 modules', function() use ($u, $te){
			$u->expect($te->modulesManager()->isModuleLoaded('URI'))->toBe(true);
			$u->expect($te->modulesManager()->isModuleLoaded('Routes'))->toBe(true);
			$u->expect($te->modulesManager()->isModuleLoaded('Observer'))->toBe(true);
			$u->expect($te->modulesManager()->isModuleLoaded('UnitsTesting'))->toBe(false);
			$u->expect(count($te->modulesManager()->load('')))->toBe(3);
		});
		$u->it('should not have the UnitsTesting module loaded', function() use ($u, $te){
			$u->expect($te->modulesManager()->isModuleLoaded('UnitsTesting'))->toBe(false);
		});
		
		$u->it('should load and unload one module fine', function() use ($u, $te){
			$te->modulesManager()->load('UnitsTesting');
			$u->expect($te->modulesManager()->isModuleLoaded('UnitsTesting'))->toBe(true);
			$te->modulesManager()->unload('UnitsTesting');
			$u->expect($te->modulesManager()->isModuleLoaded('UnitsTesting'))->toBe(false);
		});
		
		$u->it('should configurate correctly the modules', function() use ($u, $te){
			$uri = $te->modulesManager()->load('URI');
			$uri->config(array('io.path' => 'C:'));
			$u->expect($uri->config('io.path'))->toBe('C:');
			$u->expect($uri->config(array('io.path')))->toBe('C:');
			$u->expect($uri->config(array('io.path', 'io.folder' => 'documents')))->toBe('C:');
			$u->expect($uri->config(array('io.folder')))->toBe('documents');
			$u->expect($uri->config(array('io.path')))->toBe('C:');
			$u->expect(count($uri->config(array('io.path', 'io.folder'))['io']))->toBe(2);
		});
	});
	
	$u->describe('URI Tests', function() use ($u, $reportID){
		$te = new AngularPHP\AngularPHP();
		$te->modulesManager()->load('URI', 'Module', array('custom' => '/angular-php2/tools/reports-viewer/resources.php/seg1/seg2/seg4?ola=1'));
		$uri = $te->modulesManager()->load('URI');
		
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
		$te->modulesManager()->load('URI');
		$uri = $te->modulesManager()->load('URI', 'Module');
		
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
	
	$u->describe('Routing Tests', function() use ($u, $init){
		$te = new AngularPHP\AngularPHP();
		$te->modulesManager()->load('URI', 'Module', array('custom' => '/angular-php/tools/reports-viewer/resources.php/seg1/seg2/seg4?ola=1'));
		$te->modulesManager()->load('Routes', 'Module');
		$r = $te->modulesManager()->load('Routes', 'Module');
		
		$debug = array('route' => 0);
		$r->when('seg1', ':seg2', 'seg4')->doThis(function($seg2) use (&$debug){
			$debug['route'] = 1;
		})->when('seg1', 'seg2', 'seg3')->doThis(function() use (&$debug){
			$debug['route'] = 2;		
		})->when('seg1', 'seg4', ':seg3', ':seg5')->doThis(function($seg3, $seg5) use (&$debug){
			$debug['route'] = 3;
		})->set('link1')->when('hard', ':var1')->doThis(function($var1){
			
		})->set('link2')->when('hard', ':var1', ':var2', 'hard2', 'hard3')->doThis(function($var1){
			
		})->set('link3')->when(':var1', ':var2', 'hard')->doThis(function($var1){
			
		})->go();
		
		
		$u->it('should detect the correct route', function() use ($u, $r, &$debug){
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