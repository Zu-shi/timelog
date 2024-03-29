<?php if(!isset($active)) $active = ""; ?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Sundial</title>
	<link href="{{ asset('image/favicon.ico') }}" rel="shortcut icon">
	<link href="{{ URL::asset('css/bootstrap.css') }}" rel="stylesheet">
	<link href="{{ URL::asset('css/font-awesome.min.css') }}" rel="stylesheet">
	<link href="{{ URL::asset('css/main.css') }}" rel="stylesheet">
	<script src="{{ URL::asset('js/jquery-2.1.0.min.js') }}"></script>
	<script src="{{ URL::asset('js/bootstrap.min.js') }}"></script>
	<script src="{{ URL::asset('js/main.js') }}"></script>
	<script src="{{ URL::asset('js/sundial-api.js') }}"></script>
	@yield('header')
</head>
<body>
	<div class="navbar navbar-sundial navbar-fixed-top">
		<div class="container">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="/"><i class="fa fa-sun-o"></i> Sundial</a>
			</div>
			<div class="navbar-collapse collapse">
				<ul class="nav navbar-nav">
					<!--<li <?php if ($active == "home") echo"class=active" ?>><a href="/">Home</a></li>-->
					<li <?php if ($active == "addlog") echo"class=active" ?>><a href="/log/add">Add Logs</a></li>
					<li <?php if ($active == "category") echo"class=active" ?>><a href="/log/addCategory">Add Categories</a></li>
					<li <?php if ($active == "addlog_cal") echo"class=active" ?>><a href="/log/addlog_cal">Calendar</a></li>
					<li <?php if ($active == "viewlog") echo"class=active" ?>><a href="/log/view">View Logs</a></li>
					<li <?php if ($active == "viewCat") echo"class=active" ?>><a href="/log/viewCategory">Edit Categories</a></li>
					<!--<li <?php if ($active == "dashboard") echo"class=active" ?>><a href="/dashboard">Dashboard</a></li>-->
				</ul>
				<ul class="nav navbar-nav navbar-right">
					@if(!Auth::check())  
	                    <li><a href="/login"><i class="fa fa-sign-in"></i> Sign In</a></li>  
	                @else
					<li {{ $active == "profile" ? "class='active'" : "" }}>
					<a href="/profile"><i class="fa fa-user">
					</i> {{{ Auth::user()->firstname }}}</a></li>
					<li>
						<a href="/logout">
							<i class="fa fa-sign-out"></i> Log Out
						</a>
					</li>
	                @endif
                </ul>
				
			</div><!--/.navbar-collapse -->
		</div>			
	</div> <!-- /container -->

	<div class="container-page">
		@yield('content')
	</div>

	<hr>
	<footer class="footer">
		<div class="container">
			<p>Sundial &copy; 2014</p>
			<div class="footer-links">
				<a href="/contact">Contact Us</a>
				<a href="/terms">Terms</a>
				<a href="/privacy">Privacy</a>
				<a href="/help">Help</a>
				<a href="/about">About</a>
			</div>
			<p>Handmade with <i class="fa fa-heart fa-3"></i> in the USA</p>
		</div>
	</footer>

<div class="modal fade" id="thisModal" tabindex="-1" role="dialog" aria-labelledby="thisModalLabel" aria-hidden="true">
</div>
</body>
</html>