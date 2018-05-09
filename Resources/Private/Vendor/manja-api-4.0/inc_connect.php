<?php
/*------------------------------------------------------------------------------
 (c)(r) 2008-2013 IT-Service Robert Frunzke
--------------------------------------------------------------------------------
- configuration
- create server object, connect & login
------------------------------------------------------------------------------*/

// configuration
$host = '144.76.217.98';
$port = 12353;
$username = 'falk';
$password = 'wp/1Unxc4c';

$tree_id = 1;								// manja supports multiple trees, select the tree to use for browsing
$client_id = 'typo3';						// arbitrary string

$server_connect_timeout = 20;				// default is 20 seconds
$server_stream_timeout = 3600;				// default is 3600s=1h
$use_ssl = false;							// enable ssl encrypted communication to manja server

$use_sessions = true;				// sessions are optional, find example code below

//--------------------------------------------------------------------------------

require dirname(__FILE__).'/inc_util.php';
require dirname(__FILE__).'/inc_manja_server.php';
require dirname(__FILE__).'/inc_manja_repository_model.php';


function mj_error_callback( $die_on_error, $error_code, $error_string ) {
	if( !headers_sent() ) header('Content-Type: text/html; charset=UTF-8');
	echo '<pre style="color:red; font-weight:bold">error: '.$error_code.': '.htmlspecialchars($error_string).'</pre>';
}

// create server object
$server = new ManjaServer( $client_id, $host, $port );

// timeouts - optional
$server->ConfigureTimeouts( $server_connect_timeout, $server_stream_timeout );

// setup error callback - callback may be a function name or an array(object,methodname)
$server->SetErrorCallback( 'mj_error_callback' );

// enable simplified error handling - print message and exit script on any error
$server->SetDieOnError( true );

// connect
$server->Connect();

// enable SSL mode if required
if( $use_ssl ) {
	$server->SSL();
}


// use explicit error handling for login or session resume
$server->SetErrorCallback( null );
$server->SetDieOnError( false );

// authenticate
if( $use_sessions ) {

	// use sessions - a session_id will be tracked in a cookie

	$valid = false;
	$session_id = isset($_COOKIE['mj_session_id']) ? $_COOKIE['mj_session_id'] : '';
	if( $session_id!='' ) {
		// try to resume a session
		if( $server->SessionResume($session_id)!==false ) {
			$valid = true;
			setcookie( 'mj_session_id', $session_id, time()+86400, '/' );
		}
	}
	if( !$valid ) {
		// login
		if( $server->Login($username,$password)===0 ) {
			echo '<pre style="color:red; font-weight:bold">login failed</pre>';
			exit;
		}

		// and create new session
		$tmp = $server->SessionCreate();
		$session_id = $tmp['session_id'];
		setcookie( 'mj_session_id', $session_id, time()+86400, '/' );
	}


} else {

	// no sessions, login for each query

	// login
	if( $server->Login($username,$password)===0 ) {
		echo '<pre style="color:red; font-weight:bold">login failed</pre>';
		exit;
	}

}

// switch back to automatic error handling for further requests
$server->SetErrorCallback( 'mj_error_callback' );
$server->SetDieOnError( true );

