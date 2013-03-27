/* repeatString() returns a string which has been repeated a set number of times */ 
function repeatString(str, num) {
    out = '';
    for (var i = 0; i < num; i++) {
        out += str; 
    }
    return out;
}

/*
dump() displays the contents of a variable like var_dump() does in PHP. dump() is
better than typeof, because it can distinguish between array, null and object.  
Parameters:
  v:              The variable
  howDisplay:     "none", "body", "alert" (default)
  recursionLevel: Number of times the function has recursed when entering nested
                  objects or arrays. Each level of recursion adds extra space to the 
                  output to indicate level. Set to 0 by default.
Return Value:
  A string of the variable's contents 
Limitations:
  Can't pass an undefined variable to dump(). 
  dump() can't distinguish between int and float.
  dump() can't tell the original variable type of a member variable of an object.
  These limitations can't be fixed because these are *features* of JS. However, dump()
*/
function dump(v, howDisplay, recursionLevel) {
    howDisplay = (typeof howDisplay === 'undefined') ? "alert" : howDisplay;
    recursionLevel = (typeof recursionLevel !== 'number') ? 0 : recursionLevel;


    var vType = typeof v;
    var out = vType;

    switch (vType) {
        case "number":
            /* there is absolutely no way in JS to distinguish 2 from 2.0
            so 'number' is the best that you can do. The following doesn't work:
            var er = /^[0-9]+$/;
            if (!isNaN(v) && v % 1 === 0 && er.test(3.0))
                out = 'int';*/
        case "boolean":
            out += ": " + v;
            break;
        case "string":
            out += "(" + v.length + '): "' + v + '"';
            break;
        case "object":
            //check if null
            if (v === null) {
                out = "null";

            }
            //If using jQuery: if ($.isArray(v))
            //If using IE: if (isArray(v))
            //this should work for all browsers according to the ECMAScript standard:
            else if (Object.prototype.toString.call(v) === '[object Array]') {  
                out = 'array(' + v.length + '): {\n';
                for (var i = 0; i < v.length; i++) {
                    out += repeatString('   ', recursionLevel) + "   [" + i + "]:  " + 
                        dump(v[i], "none", recursionLevel + 1) + "\n";
                }
                out += repeatString('   ', recursionLevel) + "}";
            }
            else { //if object    
                sContents = "{\n";
                cnt = 0;
                for (var member in v) {
                    //No way to know the original data type of member, since JS
                    //always converts it to a string and no other way to parse objects.
                    sContents += repeatString('   ', recursionLevel) + "   " + member +
                        ":  " + dump(v[member], "none", recursionLevel + 1) + "\n";
                    cnt++;
                }
                sContents += repeatString('   ', recursionLevel) + "}";
                out += "(" + cnt + "): " + sContents;
            }
            break;
    }

    if (howDisplay == 'body') {
        var pre = document.createElement('pre');
        pre.innerHTML = out;
        document.body.appendChild(pre)
    }
    else if (howDisplay == 'alert') {
        alert(out);
    }

    return out;
}

// Create an application module for our demo.
var app = angular.module('TestApp', ['ngResource']);
 
 app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.when("/home", {
		templateUrl: 'partial/home.html'
	}).when("/report/:reportID", {
		templateUrl: 'partial/report.html', 
		controller: ReportController
	}).when("/help", {
		templateUrl: 'partial/help.html'
	}).otherwise({
		redirectTo: "/home"
	});
}]);

function AppController($scope, $resource) {
	var ReportsList = $resource('resources.php/reportsList/', {}, {});
	
	$scope.reports = ReportsList.get({});
}

function ReportController($scope, $resource, $routeParams){

	$scope.currentSelectedReport = $routeParams.reportID;
	
	var Report = $resource('resources.php/report/:reportID', {reportID: '@id'}, {});
	$scope.report;
	$scope.reportUI = {};
	$scope.details = {};
	$scope.reportArray = [];
	
	$scope.toggleSetCollapse = function(setName){
		if (!(setName in $scope.reportUI)) $scope.reportUI[setName] = {collapse: false};
		
		$scope.reportUI[setName].collapse = !$scope.reportUI[setName].collapse;
	}
	
	$scope.isSetCollapsed = function(setName){
		if (!(setName in $scope.reportUI)) $scope.reportUI[setName] = {collapse: false};
		
		return $scope.reportUI[setName].collapse;
	}
	
	$scope.setDetails = function(setName, description){
		$scope.details = {name: setName, description: description};
	}
	
	$scope.reportExists = function(reportID){
		return reportID in $scope.reports.list;
	}
	
	$scope.runTests = function(){
		$scope.report = Report.get({reportID: $scope.currentSelectedReport}, function(){
			$scope.reportArray = [];
			subArray = [];
			angular.forEach($scope.report, function(value, key){
				subArray = [];
				angular.forEach(value.tests, function(value, key){
					this.push({name: key, number: value.number});
				}, subArray);
				this.push({name: key, number: value.number, children: subArray});
			}, $scope.reportArray);
		});
	}
	
	$scope.anyErrors = function(){
		var re = false;
		angular.forEach($scope.report, function(value, key){
			if (value.failed){
				re = true;
				return true;
			}
		});
		
		return re;
	}
	
	$scope.dump = function(_var){
		return(dump(_var, 'none'));
	}

	$scope.runTests();	
}