<!DOCTYPE html>
<html ng-app="TestApp">
	<head>
		<meta http-equiv="Content-Language" content="pt-PT" />
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="content-type" content="application/xhtml+xml; charset=UTF-8" />

		<script src="../dependencies/js/jquery.js"></script>
		<script src="../dependencies/js/bootstrap.js"></script>
		<script src="../dependencies/js/angular.min.js"></script>
		<script src="../dependencies/js/angular-resource.js"></script>
		<script src="../dependencies/js/underscore.js"></script>
		<script src="./script.js"></script>

		<!--<link href="css/bootstrap.css" rel="stylesheet" media="screen">-->
		<link href="../dependencies/css/bootstrap.css" rel="stylesheet" media="screen" />
		<!--<link href="css/style.css" rel="stylesheet" type="text/css">-->
		<style type="text/css">
			.table tbody + tbody {
				border-top-width: 0px;
			}
			
			.passed-background {
				background-color: #4AFF4A;
				color: #FFFFFF;
				font-weight: 700;
				text-align: center !important;
				vertical-align: middle !important;
			}
			
			.failed-background {
				background-color: #FF7373;
				color: #FFFFFF;
				font-weight: 700;
				text-align: center !important;
				vertical-align: middle !important;
			}
			
			.output-background {
				background-color: #0088FF;
				color: #FFFFFF;
				font-weight: 700;
				width: 20px !important;
				text-align: center !important;
				vertical-align: middle !important;
			}
			
			.clickable {
				cursor: pointer;
			}
			
			.status {
				width:15%;
			}
		</style>
	
		<title>Reports Viewer</title>
	</head>
	<body ng-controller="AppController">
		<div class="navbar navbar-fixed-top">
			<div class="navbar-inner">
				<div class="container">
					<a class="brand" href="#">Reports Viewer</a>
					<ul class="nav">
						<li class="active"><a href="#">Home</a></li>
						<li><a href="#">Report</a></li>
						<li><a href="#">Help</a></li>
					</ul>					
				</div>
			</div>
		</div>
		<div class="container" style="padding-top: 50px;" ng-view>
		
		</div>
	</body>
</head>