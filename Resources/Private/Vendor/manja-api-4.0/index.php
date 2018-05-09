<?php
/*------------------------------------------------------------------------------
 (c)(r) 2008-2013 IT-Service Robert Frunzke
--------------------------------------------------------------------------------
------------------------------------------------------------------------------*/

// config, connect, login ..
require dirname(__FILE__).'/inc_connect.php';


$req_path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';


$repo = new MjCRepository($server,$tree_id);


$node = $repo->GetNodeByPath($req_path);



if( ! $node->IsFolder() ) {

	// document nodes: deliver thumbnail or full document

	// use explicit error handling
	$server->SetDieOnError(false);
	$server->SetErrorCallback('mj_error_callback2');
	function mj_error_callback2( $die_on_error, $error_code, $error_string ) {
		// don't output anything
	}

	$req_type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'view';
	if( $req_type==='thumb' ) {
		$ctype = $server->GetMediaTypeFromSuffix('png');
		$opts = array(
				'media_id'		=> $node->GetDocumentId(),
				'ctype'			=> $ctype,
				'max_width'		=> 176,
				'max_height'	=> 136,
				'pixel_format'	=> 'RGB',
				'color_profile'	=> 'RGBdefault',
				'page'			=> 1,
				'no_cache'		=> 0,
				'up_scale'		=> 0,
				'progressive'	=> 0,
		);
		if( $server->MediumPreview($opts,true)!==false ) exit; // succceeded

		// first fallback: deliver a "custom preview" instead (if available here)
		if( $server->MediumCustomPreviewGet($opts,true)!==false ) exit; // -> success

		// second fallback: deliver a default icon image or something similar..
		header('Content-Type: image/gif');
		echo base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');

	} else if( $req_type==='view' ) {

		// send doc for viewing in browser
		$server->MediumGet($node->GetDocumentId(),true,null,array('intent'=>'stream'));
		
	} else {
	
		// send doc for download 
		$server->MediumGet($node->GetDocumentId(),true,$node->GetAttribute('filename'),array('intent'=>'download'));

	}

	exit;
}



// folder nodes: deliver a simple UI ... 


/*
 * just an util - returns urlencode()'d & htmlspecialchar()'d
 * URL to a node
 */
function grnml( $node, $type='' ) {
	$url = '?path='.rawurlencode($node->GetPath());
	if( $type!=='' ) $url .= '&type='.rawurlencode($type);
	return htmlspecialchars($url);
}


header( 'Content-Type: text/html; charset=UTF-8' );
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<!DOCTYPE html>
<html>
<head>
<title>Browser 2</title>
<style type="text/css">
body {
	background:#ffffff;
	color:#000000;
	margin:0px 0px;
	padding:0;
	font-size:11px;
	font-family: Arial, Helvetica, sans-serif;
}
header {
	margin:0px 20px 20px 26px;
	min-height: 44px;
}
a {
	text-decoration:none;
}
nav {
	min-width: 140px;
	max-width: 300px;
	margin:0px 20px 20px 20px;
	font-size: 14px;
}
nav a {
	display:block;
	padding: 8px 6px 7px 6px;
	border-bottom:1px solid #dddddd;
}
nav a:first-child {
	border-top:1px solid #dddddd;
}
nav a:hover {
	background:#DDDDDD;
}
nav #files-info {
	margin:15px 20px 5px 6px;
}

main {
	display: flex;
	flex-flow: row wrap;
}
main > nav {
	flex: 0.7 1 auto;
}
main > section {
	flex: 1 0 70%;
	min-width: 360px;
}
#categories {
	padding: 12px 12px 12px 12px;
	border-radius: 24px;
	box-shadow: -1px 1px 6px 1px #dddddd;
}
main #files {
	margin: 0px 4px 0px 4px;
	display: flex;
	flex-flow: row wrap;
	justify-content: center;
	align-items: flex-end;
	align-content: center;
}
.file {
	min-width:  90px;
    max-width: 280px;

	margin: 2px 6px 8px 2px;
	overflow: hidden;
	/*background: #fafafa;*/

	border-radius: 22px;
	box-shadow: -1px 1px 6px 1px #dddddd;
	/*position: relative;*/

	display: flex;
	flex-flow: column nowrap;
	border: 1px solid #c0c0c0;
} 
.thumb {
    /*position: relative;*/
	/* border: 1px solid #c0c0c0; */
	min-height: 116px;
	max-height: 156px;
	margin-bottom: 1px;
	padding: 0px;
	border-bottom-left-radius: 2px;
	border-bottom-right-radius: 2px;
	border-top-left-radius: 22px;
	border-top-right-radius: 22px;
	overflow: hidden;
	/*    box-shadow: 0px 1px 5px 3px #e0dfff;*/
	display: flex;
	align-items: center;
	justify-content: center;
}
a.tl {
	flex: 600 1 18px;
	background: #eaeaea;
}
a.tl:hover {
	background: #dadada;
}
.mdata {
	overflow: hidden;
	min-height: 13px;
	max-height: 39px;
	padding: 2px 3px 2px 3px;
	position: relative;
	text-overflow: ellipsis;
	color: #666666;
	box-shadow: 0px 1px 11px 1px #d8d8d8;
	flex: auto;
	line-height: 12px;
	font-size: 10px;
}
.mdata .id {
    font-size: 9px;
    position: absolute;
    bottom: -1px;
    right: -1px;
    background: #ededed;
    padding: 3px 5px 2px 6px;
    border-top-left-radius: 8px;
}

.menu {
	display: flex;
	flex-flow: row nowrap;
	border-top: 1px solid #cccccc;
	flex: 0 0 18px;
}
.menu a {
    flex: auto;
    padding: 2px 0px 1px 0px;
    text-align: center;
    /* width: 50%; */
    font-size: 10px;
	overflow: hidden;
}
.menu a:hover {
	background: #DDDDDD;
}
</style>
</head>
<body>

	<header>
		<h1><?php
			$breadcrumbs = [];
			$breadcrumbs[] = htmlspecialchars($node->GetPathSegment());
			$pnode = $node;
			while( ($pnode=$pnode->GetParentNode())!==null ) {
				$name = $pnode->GetPathSegment();
				if( $name==='' ) {
					// the path segment of root node is an empty string by definition -> show title instead
					$name = $pnode->GetAttribute('name');
				}
				$breadcrumbs[] = '<a href="'.grnml($pnode).'">'.htmlspecialchars($name).'</a>';
			}
			echo implode(' / ',array_reverse($breadcrumbs));
		?></h1>
	</header>

	<main>
		<nav>
			<div id="categories"><?php
				// add link to parent
				if( $node->GetParentNodeId() ) {
					echo '<a href="'.grnml($node->GetParentNode()).'">..</a>';
				}
				// add links to sub folders
				foreach( $node->GetFolders() as $sub_folder ) {
					echo '<a href="'.grnml($sub_folder).'">'.htmlspecialchars($sub_folder->GetPathSegment()).'</a>';
				}
			?></div>
			<div id="files-info">Dateien: <?php echo $node->GetTotalDocumentCount(); ?></div>
		</nav>

		<section>
			<div id="files"><?php
				foreach( $node->GetDocuments() as $doc ) {
					?>
					<div class="file">
						<a href="<?php echo grnml($doc); ?>" class="tl">
							<span class="thumb"><img src="<?php echo grnml($doc,'thumb'); ?>"></span>
						</a>
						<div class="mdata">
							<?php echo htmlspecialchars($doc->GetAttribute('filename')); ?>
							<span class="id"><?php echo $doc->GetDocumentId(); ?></span>
						</div>
						<div class="menu">
							<a href="<?php echo grnml($doc,'view'); ?>">view</a>
							<a href="<?php echo grnml($doc,'download'); ?>">download</a>
						</div>
					</div>
					<?php
				}
			?></div>
		</section>
	</main>

</body>
</html>
