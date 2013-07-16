<div id="testDetailsDialog" class="modal hide fade">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3>Test: <i>{{details.description}}</i></h3>
	</div>
	<div class="modal-body">
		<table class="table">
			<tr>
				<th>Input</th>
				<th>(Un)Expected Result</th>
				<th>Matches</th>
			</tr>
			<tbody>
				<tr ng-repeat="expectation in report[details.name]['tests'][details.description]['expectations']">
					<td>{{dump(expectation.input)}}</td>
					<td ng-hide="expectation.negate">{{dump(expectation.expectation)}}</td>
					<td ng-show="expectation.negate"><span style="font-weight: bold; color: #FF0000;">&#8800;</span> {{dump(expectation.expectation)}}</td>
					<td ng-class="{'passed-background': expectation.result, 'failed-background': !(expectation.result)}">
						<span ng-show="expectation.result">Passed</span>
						<span ng-show="!expectation.result">Failed</span>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="modal-footer">
		<a href="#" data-dismiss="modal" class="btn">Close</a>
		<a href="#" class="btn btn-primary">Save changes</a>
	</div>
</div>

<div id="testOutputDialog" class="modal hide fade">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h3>Test: <i>{{outputDialog.description}}</i></h3>
	</div>
	<div class="modal-body">
		<pre>{{report[outputDialog.name]['tests'][outputDialog.description].output}}</pre>
	</div>
	<div class="modal-footer">
		<a href="#" data-dismiss="modal" class="btn">Close</a>
		<a href="#" class="btn btn-primary">Save changes</a>
	</div>
</div>

<!--Page-->
<div ng-hide="reportExists(currentSelectedReport)">Não existe o relatório {{currentSelectedReport}}</div>
<div ng-show="reportExists(currentSelectedReport)">
	<h1 style="display: inline; margin-right: 10px;">{{reports.list[currentSelectedReport].title}} <button ng-click="runTests()" type="submit" class="btn btn-success">Run test</button></h1>
	
	<div ng-show="false" class="alert alert-block alert-error fade in">
		<button data-dismiss="alert" class="close" type="button">×</button>
		<h4 class="alert-heading">The tests ran with errors.</h4>
		<p>Look's like some tests did not passed. You can try to run the tests again to check if the errors are corrected.</p>
		<p>
			<a class="btn btn-danger" ng-click="runTests()">Run Again</a> <a href="#" data-dismiss="alert" class="btn">Dismiss</a>
		</p>
    </div>
	
	<table style=" margin-top: 40px;" class="table">
		<tbody ng-repeat="item in reportArray | orderBy: number">
			<tr ng-click="toggleSetCollapse(item.name)" class="clickable" style="height: 50px;">
				<th colspan="3" style="font-size: 30px;  vertical-align: middle;">{{item.name}}</th>
				<td style="font-size: 30px;  vertical-align: middle;" class="status passed-background clickable" ng-show="report[item.name].passed && isSetCollapsed(item.name)">Passed</td>
				<td style="font-size: 30px;  vertical-align: middle;" class="status failed-background clickable" ng-hide="report[item.name].passed || !isSetCollapsed(item.name)">Failed</td>
				<th ng-hide="isSetCollapsed(item.name)" class="status"></th>
			</tr>
			<tr ng-hide="isSetCollapsed(item.name)" ng-repeat="subItem in item.children  | orderBy: number">
				<td style="width: 5%; text-align: right; font-weight: bold;">{{subItem.number}}</td>
				<td>it <i>{{subItem.name}}</i></td>
				<td ng-show="!report[item.name]['tests'][subItem.name].output" class=""></td>
				<td ng-hide="!report[item.name]['tests'][subItem.name].output" ng-click="setOutputTest(item.name, subItem.name)" data-toggle="modal" data-target="#testOutputDialog" class="clickable output-background"><i class=" icon-info-sign icon-white"> </i></td>
				<td ng-click="setDetails(item.name, subItem.name)" data-toggle="modal" data-target="#testDetailsDialog" class="status clickable"
					ng-class="{'passed-background': report[item.name]['tests'][subItem.name].passed, 'failed-background': !(report[item.name]['tests'][subItem.name].passed), 'running-background': running}">
					<span ng-show="report[item.name]['tests'][subItem.name].passed && !running">Passed</span>
					<span ng-show="!report[item.name]['tests'][subItem.name].passed && !running">Failed</span>
					<span ng-show="running">Testing</span>
				</td>
			</tr>
		</tbody>
	</table>
</div>
