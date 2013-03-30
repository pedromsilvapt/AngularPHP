<?php
//Prevent this file from being requested directly
if (!defined('APPRUNNING')){
	exit;
}

class UnitsTestingModule extends Module {
	
	private $_interceptOutput = true;
	private $_showTrace = false;
	private $tests = array();
	private $testsResults = array();	
	private $exporters = array();
	private $chainingInfo = array();
	
	private function exporterHTML($tests){
		return print_r($tests, true);
	}
	
	private function exporterJSON($tests){
		return json_encode($tests);
	}
	
	private function exporterObject($tests){
		return(new UnitsReport($tests));
	}
	
	private function resetChainingInfo(){
		unset($this->chainingInfo['runName']);
		unset($this->chainingInfo['currentSet']);
		unset($this->chainingInfo['currentTest']);
		unset($this->chainingInfo['inputValue']);
		unset($this->chainingInfo['negate']);
	}
	
	private function executeTest($name, $set, $test, $input, $expectation, $negate){
		if (!isset($this->testsResults[$name])) $this->testsResults[$name] = array();
		if (!isset($this->testsResults[$name][$set])){
			$this->testsResults[$name][$set] = array(
				'tests' => array(),
				'numberPassed' => 0,
				'numberFailed' => 0,
				'number' => count($this->testsResults[$name]) + 1,
				'passed' => true,
				'failed' => false
			);
		}
		if (!isset($this->testsResults[$name][$set]['tests'][$test])){
			$this->testsResults[$name][$set]['tests'][$test] = array(
				'expectations' => array(),
				'numberPassed' => 0,
				'numberFailed' => 0,
				'number' => count($this->testsResults[$name][$set]['tests']) + 1,
				'passed' => true,
				'failed' => false
			);
		}
		
		$this->testsResults[$name][$set]['tests'][$test]['expectations'][] = array(
			'input' => $input,
			'expectation' => $expectation,
			'negate' => $negate,
			'result' => ($input === $expectation) === !$negate
		);
		$this->testsResults[$name][$set]['tests'][$test]['numberPassed'] += ($input === $expectation) === !$negate ? 1 : 0;
		$this->testsResults[$name][$set]['tests'][$test]['numberFailed'] += ($input === $expectation) === !$negate ? 0 : 1;
		
		if (($input === $expectation) !== !$negate){
			$this->testsResults[$name][$set]['tests'][$test]['passed'] = false;
			$this->testsResults[$name][$set]['tests'][$test]['failed'] = true;
			
			$this->testsResults[$name][$set]['passed'] = false;
			$this->testsResults[$name][$set]['failed'] = true;
		}
	}
	
	
	public function interceptOutput($newVal = null){
		if ($newVal === null) return $this->_interceptOutput;
		else if (is_bool($newVal)) $this->_interceptOutput = $newVal;
	}
	
	public function showTrace($newVal = null){
		if ($newVal === null) return $this->_showTrace;
		else if (is_bool($newVal)) $this->_showTrace = $newVal;
	}
	
	public function exporterExists($name){
		return isset($this->exporters[$name]);
	}
	
	public function registerExporter($name, $formatterFunction){
		if (!is_string($name) || !is_callable($formatterFunction, true)) return;
		if ($this->exporterExists($name)) return;
		
		$this->exporters[$name] = $formatterFunction;
	}
	
	public function isSetDefined($setName){
		return isset($this->tests[$setName]);
	}
	
	public function isTestDefined($setName, $testDescription){
		return isset($this->tests[$setName]['tests'][$testDescription]);
	}
	
	public function describe($setName, $setTest){
		if (!is_string($setName) || !is_callable($setTest)) return $this;
		if ($this->isSetDefined($setName)) return $this;
		
		$this->tests[$setName] = array('callback' => $setTest, 'tests' => array());
		
		return $this;
	}
	
	public function it($description, $callable){
		if (!isset($this->chainingInfo['currentSet'])) return $this;
		
		if ($this->isTestDefined($this->chainingInfo['currentSet'], $description)) return $this;
		if (!is_string($description) || !is_callable($callable)) return $this;
		
		$this->tests[$this->chainingInfo['currentSet']]['tests'][$description] = $callable;
		$this->chainingInfo['currentTest'] = $description;
		
		//Stops anything from being printed.
		ob_start();

		set_exception_handler(function($exception){
			echo 'Uncaught exception: '.$exception->getMessage().' in '.$exception->getFile().' line '.$exception->getLine().'\n';
			if ($this->showTrace()){
				echo "Trace: ".$exception->getTraceAsString()."\n";
			}
		});
		
		try {
			call_user_func($callable);
		} catch (Exception $exception) {
			echo 'Uncaught exception: '.$exception->getMessage().' in '.$exception->getFile().' line '.$exception->getLine()." \n";
			if ($this->showTrace()){
				echo "Trace: ".$exception->getTraceAsString()."\n";
			}
		}
		
		//Gets the output
		$output = ob_get_clean();
		//Stores the output
		$this->testsResults[$this->chainingInfo['runName']][$this->chainingInfo['currentSet']]['tests'][$description]['output'] = $output;
		//If it is needed to print it anyway, does it
		if (!$this->interceptOutput()) echo $output;
		
		return $this;
	}
	
	public function expect($value){
		if (!isset($this->chainingInfo['currentTest']) || !isset($this->chainingInfo['currentSet'])) return $this;
		
		$this->chainingInfo['inputValue'] = $value;
		return $this;
	}
	
	public function not(){
		if (!isset($this->chainingInfo['currentTest']) || !isset($this->chainingInfo['currentSet'])) return $this;
		if (!isset($this->chainingInfo['inputValue'])) return $this;
		
		$this->chainingInfo['negate'] = true;
		return $this;
	}
	
	public function toBe($expectedValue){
		if (!isset($this->chainingInfo['currentTest']) || !isset($this->chainingInfo['currentSet'])) return $this;
		if (!isset($this->chainingInfo['inputValue'])) return $this;
		
		$this->executeTest($this->chainingInfo['runName'],
						   $this->chainingInfo['currentSet'],
						   $this->chainingInfo['currentTest'],
						   $this->chainingInfo['inputValue'],
						   $expectedValue,
						   isset($this->chainingInfo['negate']) ? $this->chainingInfo['negate'] : false);
		
		unset($this->chainingInfo['inputValue']);
		unset($this->chainingInfo['negate']);
		
		return $this;
	}
	
	public function run($name){
		$set = '';
		for($i = 1; $i < func_num_args(); $i++){
			$set = func_get_arg($i);
			
			if (!$this->isSetDefined($set))
				continue;
			
			
			$this->chainingInfo['runName'] = $name;
			$this->chainingInfo['currentSet'] = $set;
			
			//Executes the tests
			call_user_func($this->tests[$set]['callback'], $this);
			
			$this->resetChainingInfo();
		}
	}
	
	public function exportReport($name, $formatter){
		if (!isset($this->exporters[$formatter])) return false;
		
		return call_user_func($this->exporters[$formatter], $this->testsResults[$name]);
	}
	
	public function __construct(ModulesManager $modulesManager){
		parent::__construct($modulesManager);
		
		require_once($modulesManager->getModuleDirectory('UnitsTesting').'UnitsReport.class.php');
		
		$this->registerExporter('html', array($this, 'exporterHTML'));
		$this->registerExporter('json', array($this, 'exporterJSON'));
		$this->registerExporter('object', array($this, 'exporterObject'));
	}
}

