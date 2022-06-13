<?php
declare(strict_types=1);
/**
 * Manja Server API
 *
 * @package   ManjaWeb
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */

/**
 *
 */
class ManjaServerFactory {

	/**
	 * @param string|null $client_id
	 * @param array $srv_cfg
	 * @param callable|null $error_callback
	 *
	 * @return \ManjaServer
	 */
	public static function CreateManjaServer( string $client_id=null, array $srv_cfg, callable $error_callback=null ) : \ManjaServer {
		// self::LoadDependencies();
		if( $client_id===null ) $client_id = $srv_cfg['client_id'];
		$server = new \ManjaServer($client_id,$srv_cfg['host'],intval($srv_cfg['port'],10));
		$server->ConfigureTimeouts(intval($srv_cfg['server_connect_timeout'],10),intval($srv_cfg['server_stream_timeout'],10));
		$server->SetErrorCallback($error_callback);
		//$server->SetDieOnError(false);
		return $server;
	}

	/**
	 * @param string|null $client_id
	 * @param array $srv_cfg
	 * @param callable|null $error_callback
	 *
	 * @return \ManjaServer
	 */
	public static function CreateAndConnectManjaServer( string $client_id=null, array $srv_cfg, callable $error_callback=null ) : \ManjaServer {
		// self::LoadDependencies();
		if( $client_id===null ) $client_id = $srv_cfg['client_id'];
		$server = self::CreateManjaServer($client_id,$srv_cfg,$error_callback);
		if( !$server->Connect() ) throw new \mjError('failed to connect to manja server: '.$server->GetErrorCode().' ('.$server->GetErrorString().')');
		if( $srv_cfg['use_ssl']==='yes' && !$server->SSL() ) throw new \mjError('failed to switch manja server connection to ssl mode: '.$server->GetErrorCode().' ('.$server->GetErrorString().')');
		return $server;
	}

	// private static function LoadDependencies() {
	// 	//if( !class_exists('mjError',false) ) require __DIR__.'/../error.php';
	// 	//if( !class_exists('mjServerError',false) ) require __DIR__.'/error.php';
	// 	if( !function_exists('mj_json_encode') ) require __DIR__.'/../util/util.php';
	// 	if( !class_exists('mjHttpUtil') ) require __DIR__.'/../util/http.php';
	// }

}
