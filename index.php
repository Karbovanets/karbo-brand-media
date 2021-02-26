<?php
error_reporting(0);
	// MINIXED is a minimal but nice-looking PHP directory indexer.
	// More at https://github.com/lorenzos/Minixed

	// =============================
	// Configuration                
	// =============================
	
	$browseDirectories = true; // Navigate into sub-folders
	$title = 'Promo kit';
	$subtitle = 'Total files: {{files}}, total size: {{size}}'; // Empty to disable
	$showParent = false; // Display a (parent directory) link
	$showDirectories = true;
	$showHiddenFiles = false; // Display files starting with "." too
	$alignment = 'left'; // You can use 'left' or 'center'
	$showIcons = true;
	$dateFormat = 'd.m.y H:i'; // Used in date() function
	$sizeDecimals = 2;
	$robots = 'noindex, nofollow'; // Avoid robots by default
	$showFooter = false; // Display the "Powered by" footer
	
	// =============================
	// =============================
	
	// Who am I?
	$_self = basename($_SERVER['PHP_SELF']);
	$_path = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
	$_total = 0;
	$_total_size = 0;
	
	// Directory browsing
	$_browse = null;
	$_GET['b'] = trim(str_replace('\\', '/', $_GET['b']), '/ ');
	$_GET['b'] = str_replace(array('/..', '../'), '', $_GET['b']); // Avoid going up into filesystem
	if (!empty($_GET['b']) && $_GET['b'] != '..' && is_dir($_GET['b'])) $_browse = $_GET['b'];
	
	// Encoded images generator
	if (!empty($_GET['i'])) {
		header('Content-type: image/png');
		switch ($_GET['i']) {
			case       'asc': exit(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAcAAAAHCAYAAADEUlfTAAAAFUlEQVQImWNgoBT8x4JxKsBpAhUAAPUACPhuMItPAAAAAElFTkSuQmCC'));
			case      'desc': exit(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAcAAAAHCAYAAADEUlfTAAAAF0lEQVQImWNgoBb4j0/iPzYF/7FgCgAADegI+OMeBfsAAAAASUVORK5CYII='));
			case 'directory': exit(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAASklEQVQYlYWPwQ3AMAgDb3Tv5AHdR5OqTaBB8gM4bAGApACPRr/XuujA+vqVcAI3swDYjqRSH7B9oHI8grbTgWN+g3+xq0k6TegCNtdPnJDsj8sAAAAASUVORK5CYII='));
			case      'file': exit(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAPklEQVQYlcXQsQ0AIAhE0b//GgzDWGdjDCJoKck13CsIALi7gJxyVmFmyrsXLHEHD7zBmBbezvoJm4cL0OwYouM4O3J+UDYAAAAASUVORK5CYII='));
		}
	}
	
	// I'm not sure this function is really needed...
	function ls($path, $show_folders = false, $show_hidden = false) {
		global $_self, $_total, $_total_size;
		$ls = array();
		$ls_d = array();
		if (($dh = @opendir($path)) === false) return $ls;
		if (substr($path, -1) != '/') $path .= '/';
		while (($file = readdir($dh)) !== false) {
			if ($file == $_self) continue;
			if ($file == '.' || $file == '..') continue;
			if (!$show_hidden) if (substr($file, 0, 1) == '.') continue;
			$isdir = is_dir($path . $file);
			if (!$show_folders && $isdir) continue;
			$item = array('name' => $file, 'isdir' => $isdir, 'size' => $isdir ? 0 : filesize($path . $file), 'time' => filemtime($path . $file));
			if ($isdir) $ls_d[] = $item; else $ls[] = $item;
			$_total++;
			$_total_size += $item['size'];
		}
		return array_merge($ls_d, $ls);
	}
	
	// Get the list of files
	$items = ls('.' . (empty($_browse) ? '' : '/' . $_browse), $showDirectories, $showHiddenFiles);
	
	// Sort it
	function sortByName($a, $b) { return ($a['isdir'] == $b['isdir'] ? strtolower($a['name']) > strtolower($b['name']) : $a['isdir'] < $b['isdir']); }
	function sortBySize($a, $b) { return ($a['isdir'] == $b['isdir'] ? $a['size'] > $b['size'] : $a['isdir'] < $b['isdir']); }
	function sortByTime($a, $b) { return ($a['time'] > $b['time']); }
	switch (@$_GET['s']) {
		case 'size': $_sort = 'size'; usort($items, 'sortBySize'); break;
		case 'time': $_sort = 'time'; usort($items, 'sortByTime'); break;
		default    : $_sort = 'name'; usort($items, 'sortByName'); break;
	}
	
	// Reverse?
	$_sort_reverse = (@$_GET['r'] == '1');
	if ($_sort_reverse) $items = array_reverse($items);
	
	// Add parent
	if ($showParent && $_path != '/' && empty($_browse)) array_unshift($items, array(
		'name' => '..',
		'isparent' => true,
		'isdir' => true,
		'size' => 0,
		'time' => 0
	));

	// Add parent in case of browsing a sub-folder
	if (!empty($_browse)) array_unshift($items, array(
		'name' => '..',
		'isparent' => false,
		'isdir' => true,
		'size' => 0,
		'time' => 0
	));
	
	// 37.6 MB is better than 39487001
	function humanizeFilesize($val, $round = 0) {
		$unit = array('','K','M','G','T','P','E','Z','Y');
		do { $val /= 1024; array_shift($unit); } while ($val >= 1000);
		return sprintf('%.'.intval($round).'f', $val) . ' ' . array_shift($unit) . 'B';
	}
	
	// Titles parser
	function getTitle($title) {
		global $_path, $_total, $_total_size, $sizeDecimals;
		return str_replace(array('{{path}}', '{{files}}', '{{size}}'), array($_path, $_total, humanizeFilesize($_total_size, $sizeDecimals)), $title);
	}
	
	// Link builder
	function buildLink($changes) {
		global $_self;
		$params = $_GET;
		foreach ($changes as $k => $v) if (is_null($v)) unset($params[$k]); else $params[$k] = $v;
		foreach ($params as $k => $v) $params[$k] = urlencode($k) . '=' . urlencode($v);
		return empty($params) ? $_self : $_self . '?' . implode($params, '&');
	}

?>
<!DOCTYPE HTML>
<html lang="en">
<head>
<!-- Meta, title, CSS, favicons, etc. -->	
<meta charset="UTF-8">
<meta name="robots" content="<?php echo htmlentities($robots) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Promo materials for the  Karbo - cryptographic Digital Exchange Medium p2p exchange network">
<meta name="robots" content="noarchive">
<title>Promo kit for Karbo - Digital Exchange Medium</title>
<title><?php echo htmlentities(getTitle($title)) ?></title>

<!-- Bootstrap core CSS -->
<link href="/css/bootstrap.min.css" rel="stylesheet">

<!-- Karbo CSS -->
<link href="/css/karbo.css" rel="stylesheet">

<!-- Favicons -->
<link rel="shortcut icon" href="/images/favicon.ico">
<link rel="icon" type="image/icon" href="/images/favicon.ico" >
<script src="/js/pace.js"></script>	
	
	<style type="text/css">
		

	
		body#left {
			text-align: left;
		}
		
		h1 {
			font-size: 31px;
			padding: 0 10px;
			margin: 0;
			font-weight: bold;
		}
		
		h4 {
			font-size: 14px;
			padding: 0 10px;
			margin: 10px 0 0;
			color: #999999;
			font-weight: normal;
		}
		
		a {
			text-decoration: none;
		}
		
		.page-content ul#header {	
			margin-top: 20px;
		}
		
		.page-content ul li {
			display: block;
			list-style-type: none;
			overflow: hidden;
			padding: 10px;
		}
		
		.page-content ul li:hover {
			background-color: #f3f3f3;
		}
		
		.page-content ul li .date {
			text-align: center;
			width: 120px;
		}
		
		.page-content ul li .size {
			text-align: right;
			width: 90px;
		}
		
		.page-content ul li .date, ul li .size {
			float: right;
			font-size: 12px;
			display: block;
			color: #666666;
		}
		
		.page-content ul#header li {
			font-size: 11px;
			font-weight: bold;
			border-bottom: 1px solid #cccccc;
		}
		
		.page-content ul#header li:hover {
			background-color: transparent;
		}
		
		.page-content ul#header li * {
			color: #000000;
			font-size: 11px;
		}
		
		.page-content ul#header li a:hover {
			color: #666666;
		}
		
		.page-content ul#header li .asc span, ul#header li .desc span {
			padding-right: 15px;
			background-position: right center;
			background-repeat: no-repeat;
		}
		
		.page-content ul#header li .asc span {
			background-image: url('<?php echo $_self ?>?i=asc');
		}
		
		.page-content ul#header li .desc span {
			background-image: url('<?php echo $_self ?>?i=desc');
		}
		
		.page-content ul li.item {
			border-top: 1px solid #f3f3f3;
		}
		
		.page-content ul li.item:first-child {
			border-top: none;
		}
		
		.page-content ul li.item .name {
			color: #003399;
			font-weight: bold;
		}
		
		.page-content ul li.item .name:hover {
			color: #0066cc;
		}
		
		.page-content ul li.item a:hover {
			text-decoration: underline;
		}
		
		.page-content ul li.item .directory, ul li.item .file {
			padding-left: 20px;
			background-position: left center;
			background-repeat: no-repeat;
		}
		
		.page-content ul li.item .directory {
			background-image: url('<?php echo $_self ?>?i=directory');
		}
		
		.page-content ul li.item .file {
			background-image: url('<?php echo $_self ?>?i=file');
		}
		
		#footer {
			color: #cccccc;
			font-size: 11px;
			margin-top: 40px;
			margin-bottom: 20px;
			padding: 0 10px;
			text-align: left;
		}
		
		#footer a {
			color: #cccccc;
			font-weight: bold;
		}
		
		#footer a:hover {
			color: #999999;
		}
		
	</style>
<meta property="og:url" content="http://www.karbo.io" />
<meta property="og:type" content="website" />
<meta property="og:title" content="Electronic Karbovanets" />
<meta property="og:description" content="Karbo (Electronic Karbovanets) - digital exchange medium, algorithmic stablecoin" />
<meta property="og:image" content="https://karbo.io/images/karbo_io.jpg" />
<meta property="og:image:type" content="image/png" />
<meta property="og:image:width" content="1092" />
<meta property="og:image:height" content="684" />

<script src="https://kit.fontawesome.com/2058fca1c3.js"></script>
</head>
<body <?php if ($alignment == 'left') echo 'id="left"' ?>>
<script>
  window.fbAsyncInit = function() {
    FB.init({
      appId      : '1631255797202963',
      xfbml      : true,
      version    : 'v2.7'
    });
  };

  (function(d, s, id){
     var js, fjs = d.getElementsByTagName(s)[0];
     if (d.getElementById(id)) {return;}
     js = d.createElement(s); js.id = id;
     js.src = "//connect.facebook.net/en_US/sdk.js";
     fjs.parentNode.insertBefore(js, fjs);
   }(document, 'script', 'facebook-jssdk'));
</script>
<!-- Fixed navbar -->
    <div id="navbar" class="navbar navbar-default navbar-fixed-top" role="navigation">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="/">
			<div id="coinIcon">
				<svg version="1.1" xmlns:x="&ns_extend;" xmlns:i="&ns_ai;" xmlns:graph="&ns_graphs;"
					xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="32px"
					height="32px" viewBox="0 0 206.569 206.568" enable-background="new 0 0 206.569 206.568" xml:space="preserve">
					<!--path fill-rule="evenodd" clip-rule="evenodd" fill="#FFEC00" d="M103.284,0c57.042,0,103.285,46.242,103.285,103.284
								c0,57.042-46.242,103.284-103.285,103.284S0,160.326,0,103.284C0,46.242,46.242,0,103.284,0"/-->
					<g id="navbar-logo-k">			
						<rect x="42.621" y="67.455" fill="#0E0740" width="121.327" height="14.829"/>
						<polygon fill="#0E0740" points="164.002,142.947 103.339,82.284 42.675,142.947 53.162,153.434 103.339,103.256 153.516,153.434 "/>
					</g>
				</svg>
				</div> <strong>Karbo</strong></a>
        </div>
        <div class="navbar-collapse collapse">
          <ul class="nav navbar-nav navbar-right">
            <li>
			  <a href="/download">Download</a>
			</li>
			<li>
			  <a href="https://wallet.karbo.org/">Web Wallet</a>
			</li>
			<li>
              <a href="/use">Use</a>
            </li>
			<li>
              <a href="/pay">Pay</a>
            </li>
			<li>
              <a href="/info">Info</a>
            </li>
			<li>
              <a href="https://explorer.karbo.io/">Explorer</a>
            </li>
			<li class="dropdown">
			  <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-language" aria-hidden="true"></i> <span class="caret"></span></a>
			  <ul class="dropdown-menu" role="menu">
				<li><a href="/"><span class="flag-icon flag-icon-us"></span> English</a></li>
				<li><a href="/ua/"><span class="flag-icon flag-icon-ua"></span> Українська</a></li>
				<li><a href="/ru/"><span class="flag-icon flag-icon-ru"></span> Русский</a></li>
				<li><a href="/pl/"><span class="flag-icon flag-icon-pl"></span> Polski</a></li>
				<li><a href="/es/"><span class="flag-icon flag-icon-es"></span> Español</a></li>
				<li><a href="/cn/"><span class="flag-icon flag-icon-cn"></span> 简体中文</a></li>
				<li><a href="/jp/"><span class="flag-icon flag-icon-jp"></span> 日本語</a></li>
				<li><a href="/kr/"><span class="flag-icon flag-icon-kr"></span> 한국어</a></li>
			  </ul>
			</li>
			<li class="dropdown">
			<a href="#" class="price dropdown-toggle" data-toggle="dropdown"><span id="usd_price"></span> <span id="arrowplace"></span> <span class="caret"></span></a>
			<ul class="dropdown-menu" role="menu">
				<li><a href="#calc" id="btc_price"></a></li>
				<li><a href="#calc" id="cny_price"></a></li>
				<li><a href="#calc" id="eur_price"></a></li>
				<li><a href="#calc" id="jpy_price"></a></li>
				<li><a href="#calc" id="krw_price"></a></li>
				<li><a href="#calc" id="rur_price"></a></li>
				<li><a href="#calc" id="uah_price"></a></li>
			</ul>
			</li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
   </div>
<!--// Navbar Ends-->

<article class="content">

<!--SUBPAGE HEAD-->

<div class="subpage-head grad breath inverse">
  <div class="container">
    <div class="section-title">
		<h1><?php echo htmlentities(getTitle($title)) ?></h1>
		<h4><?php echo htmlentities(getTitle($subtitle)) ?></h4>
    </div>
  </div>
</div>

<!-- // END SUBPAGE HEAD -->

<div class="container page-content">
  <div class="row">
    <div class="col-md-12 has-margin-bottom">
	<div id="wrapper">
		

		
		<ul id="header">
			
			<li>
				<a href="<?php echo buildLink(array('s' => 'size', 'r' => (!$_sort_reverse && $_sort == 'size') ? '1' : null)) ?>" class="size <?php if ($_sort == 'size') echo $_sort_reverse ? 'desc' : 'asc' ?>"><span>Size</span></a>
				<a href="<?php echo buildLink(array('s' => 'time', 'r' => (!$_sort_reverse && $_sort == 'time') ? '1' : null)) ?>" class="date <?php if ($_sort == 'time') echo $_sort_reverse ? 'desc' : 'asc' ?>"><span>Changed</span></a>
				<a href="<?php echo buildLink(array('s' =>  null , 'r' => (!$_sort_reverse && $_sort == 'name') ? '1' : null)) ?>" class="name <?php if ($_sort == 'name') echo $_sort_reverse ? 'desc' : 'asc' ?>"><span>Title</span></a>
			</li>
			
		</ul>
		
		<ul>
			
			<?php foreach ($items as $item): ?>
				
				<li class="item">
				
					<span class="size"><?php echo $item['isdir'] ? '-' : humanizeFilesize($item['size'], $sizeDecimals) ?></span>
					
					<span class="date"><?php echo (@$item['isparent'] || empty($item['time'])) ? '-' : date($dateFormat, $item['time']) ?></span>
					
					<?php
						if ($item['isdir'] && $browseDirectories && !@$item['isparent']) {
							if ($item['name'] == '..') {
								$itemURL = buildLink(array('b' => substr($_browse, 0, strrpos($_browse, '/'))));
							} else {
								$itemURL = buildLink(array('b' => (empty($_browse) ? '' : (string)$_browse . '/') . $item['name']));
							}
						} else {
							$itemURL = (empty($_browse) ? '' : (string)$_browse . '/') . $item['name'];
						}
					?>
					
					<a href="<?php echo htmlentities($itemURL) ?>" class="name <?php if ($showIcons) echo $item['isdir'] ? 'directory' : 'file' ?>"><?php echo htmlentities($item['name']) . ($item['isdir'] ? ' /' : '') ?></a>
					
				</li>
				
			<?php endforeach; ?>
			
		</ul>
		
		<?php if ($showFooter): ?>
			
			<p id="footer">
				Powered by <a href="https://github.com/lorenzos/Minixed" target="_blank">Minixed</a>, a PHP directory indexer
			</p>
			
		<?php endif; ?>
		
	</div>
	    </div>
    <!--// col md 12--> 
  </div>  
</div>   
</article>

<!-- FOOTER -->
<footer class="grad1">
   <div class="container">
	 <div class="breath">
        <div class="row">
          <div class="col-lg-4">
			<p>&copy; 2016-2019 <strong>Karbo</strong>. 
		  
			E-mail:&nbsp;dev@karbo.io</p>
			
			<div class="fb-like" data-layout="button_count" data-action="like" data-size="small" data-show-faces="true" data-share="true"></div>
		  </div>
          <div class="col-lg-8">
			<p  class="text-right">
				<a href="/privacy">Privacy policy</a>
				|
				<a href="/terms">Terms and conditions</a> 
				|
				<a href="http://donate.karbo.io/">Donate</a>
				|
				<a href="http://forum.karbo.io/">Forum</a>
			</p>
		  </div>
		</div>
	 </div>
  </div>
</footer>
<!-- END FOOTER -->

<!-- JS and analytics only. --> 
<!-- Bootstrap core JavaScript
================================================== --> 
<!-- Placed at the end of the document so the pages load faster --> 
<script src="/js/jquery.js"></script> 
<script src="/js/bootstrap.min.js"></script> 
<script src="/js/easing.js"></script> 
<script src="/js/rates.js"></script>

<script>
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 80 || document.documentElement.scrollTop > 80) {
    document.getElementById("navbar").classList.add("navbar-grad-reverse");
  } else {
    document.getElementById("navbar").classList.remove("navbar-grad-reverse");
  }
}
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/2.8.0/css/flag-icon.min.css">

</body>
</html>