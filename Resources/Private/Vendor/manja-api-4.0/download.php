<?php
/*------------------------------------------------------------------------------
 (c)(r) 2008-2013 IT-Service Robert Frunzke
--------------------------------------------------------------------------------
------------------------------------------------------------------------------*/

// config, connect, login ..
require dirname(__FILE__).'/inc_connect.php';

// parse request
$media_id = isset($_REQUEST['media_id']) ? $_REQUEST['media_id'] : 0;
$dlf_id = isset($_REQUEST['dlf_id']) ? $_REQUEST['dlf_id'] : 0;

// get a filename based on media id & download format
$filename = $server->GetFilename( $media_id, $dlf_id );
if( $filename===null || $filename==='' ) $filename = 'unknown';

// send file
set_time_limit(0);
$server->MediumDownload($media_id,$dlf_id,true,$filename,array('intent'=>'download'));
