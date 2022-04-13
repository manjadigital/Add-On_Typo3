<?php
declare(strict_types=1);
/**
 * Manja Server API
 *
 * @package   ManjaWeb
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */


/**
 * Manja Server Communication Object
 *
 */
class ManjaServer {

	/**
	 * @var resource|null
	 */
	private $ctx = null;

	/**
	 * @var resource|null
	 */
	private $fp = null;

	/**
	 * none, idle, error
	 *
	 * @var string
	 */
	private $stream_state = 'none';

	/**
	 * @var string
	 */
	private $host;

	/**
	 * @var int
	 */
	private $port;

	/**
	 * @var string
	 */
	private $error_code = '';

	/**
	 * @var string
	 */
	private $error_string = '';

	/**
	 * set to true to let the server class throw or die on error (and display detailed error messages).
	 * - depends on actual error_callback implementation,
	 * - throw_or_die_on_error is passed as first argument to error_callback.
	 *
	 * @var bool
	 */
	private $throw_or_die_on_error = false;

	/**
	 * @var int
	 */
	private $cfg_server_connect_timeout = 20;

	/**
	 * @var int
	 */
	private $cfg_server_stream_timeout = 3600;

	/**
	 * @var string
	 */
	private $cfg_client_id;

	/**
	 * @var callback|null
	 */
	private $error_callback = null;

	/**
	 * string "minor.major.patch", e.g. "4.0.58"
	 *
	 * @var string|null
	 */
	private $server_version = null;

	/**
	 * @var array
	 */
	private $server_features = [];			// array( feat=>feat, ... ), e.g. ssl, i18n, versioning, automation

	/**
	 * @var string|null
	 */
	private $connected_username = null;

	/**
	 * @var array|null
	 */
	private $connected_user_relogin_info = null;

	/**
	 * @var string|null
	 */
	private $connected_session_id = null;
	/**
	 * @var int|null
	 */
	private $connected_session_expiry_ts = null;
	/**
	 * @var int|null
	 */
	private $connected_user_id = null;

	/**
	 * @var bool
	 */
	private $connected_ssl_active = false;

	/**
	 * @var array
	 */
	private $connected_ssl_ctx_opts = [];


	/**
	 * constructor
	 *
	 * @param string $client_id
	 * @param string $host
	 * @param int $port
	 */
	public function __construct( string $client_id, string $host, int $port ) {
		// set connection data
		$this->cfg_client_id = $client_id;
		$this->host = $host;
		$this->port = $port;
		if( $this->port===0 ) $this->port = 12345;
	}

	/**
	 * destructor
	 */
	public function __destruct() {
		//$this->ctx = null;
		//$this->fp = null;
		$this->Disconnect();
		$this->error_callback = null;
	}


	/**
	 * enable/disable throw_or_die_on_error functionality
	 *
	 * @param bool $enabled
	 */
	public function SetThrowOnError( bool $enabled ) {
		$this->throw_or_die_on_error = $enabled;
	}


	/**
	 * Alias of SetThrowOnError.
	 *
	 * @param bool $enabled
	 */
	public function SetDieOnError( bool $enabled ) {
		$this->throw_or_die_on_error = $enabled;
	}

	/**
	 *
	 * @return bool
	 */
	public function GetThrowOnError() : bool {
		return $this->throw_or_die_on_error;
	}

	/**
	 * Alias of GetThrowOnError.
	 *
	 * @return bool
	 */
	public function GetDieOnError() : bool {
		return $this->throw_or_die_on_error;
	}


	/**
	 *
	 * @param callable|null $callback		- function( bool $throw_or_die, string $code, string $string )
	 */
	public function SetErrorCallback( callable $callback=null ) {
		$this->error_callback = $callback;
	}


	/**
	 *
	 * @return string
	 */
	public function GetErrorCode() : string {
		return $this->error_code;
	}

	/**
	 *
	 * @return string
	 */
	public function GetErrorString() : string {
		return $this->error_string;
	}

	/**
	 *
	 * @return string
	 */
	public function GetHost() : string {
		return $this->host;
	}

	/**
	 *
	 * @return int
	 */
	public function GetPort() : int {
		return $this->port;
	}

	/**
	 *
	 * @return string|null
	 */
	public function GetConnectedServerVersion() : ?string {
		return $this->server_version;
	}

	/**
	 *
	 * @return string|null
	 */
	public function GetConnectedUsername() :?string {
		return $this->connected_username;
	}

	/**
	 *
	 * @return string|null
	 */
	public function GetConnectedSessionId() : ?string {
		return $this->connected_session_id;
	}

	/**
	 *
	 * @return int|null
	 */
	public function GetConnectedSessionExpiryTS() : ?int {
		return $this->connected_session_expiry_ts;
	}

	/**
	 *
	 * @return int|null
	 */
	public function GetConnectedUserId() : ?int {
		return $this->connected_user_id;
	}

	/**
	 * Configure connect & stream timeouts of manja server connection
	 *
	 * @param int $server_connect_timeout
	 * @param int $server_stream_timeout
	 */
	public function ConfigureTimeouts( int $server_connect_timeout, int $server_stream_timeout ) {
		$this->cfg_server_connect_timeout = $server_connect_timeout;
		$this->cfg_server_stream_timeout = $server_stream_timeout;
	}

	/**
	 * Connect to manja server, return true on success, false on error, sets error states on error
	 *
	 * @return bool
	 */
	public function Connect() : bool {
		if( $this->host==='localhost' || $this->host==='127.0.0.1' ) {
			$host = '127.0.0.1';
		} else {
			// fsockopen does not return a valid code for dns resolution errors, so check for dns errors first:
			$hosts = @gethostbynamel( $this->host );
			if( $hosts===false ) return $this->_error('dns/1','unknown host');
			$host = !empty($hosts) ? $hosts[0] : $this->host;
		}
		// then connect
		$errno = 0; $errstr = '';

		// TCP_NODELAY
		// $stream_opts = null;
		//if( PHP_VERSION_ID >= 70100 ) $stream_opts = array( 'socket'=>array( 'tcp_nodelay'=>true ) );
		// $this->ctx = stream_context_create($stream_opts);

		$this->ctx = stream_context_create();
		$this->fp = @stream_socket_client( 'tcp://'.$host.':'.$this->port, $errno, $errstr, $this->cfg_server_connect_timeout, STREAM_CLIENT_CONNECT, $this->ctx );
		if( $this->fp===false ) return $this->_error('sys/'.$errno,$errstr);
		// set read/write timeout
		stream_set_timeout( $this->fp, $this->cfg_server_stream_timeout );
		// stream is up and waiting for commands
		$this->stream_state = 'idle';
		// check handshake - "manja <version> <plain> <ssl>"
		if( ($line=stream_get_line($this->fp,127,"\n"))===false ) return $this->_error('pro/1','not a manja server or server busy (1)',true,true);
		$x = explode(' ',$line);
		if( array_shift($x)!=='manja' ) return $this->_error('pro/1','not a manja server or server busy (2)',true,true);
		$this->server_version = array_shift($x);
		if( ($p=strpos($this->server_version,'-'))!==false ) $this->server_version = substr($this->server_version,0,$p);
		// get & extract feature list
		$this->server_features = array_combine($x,$x);
		// alright
		return true;
	}

	/**
	 * Enter SSL connection mode to manja server
	 *
	 * @param array $ssl_ctx_opts
	 * @return bool
	 */
	public function SSL( array $ssl_ctx_opts=[] ) : bool {
		/* example for advanced ssl verification options - see documentation for details: http://php.net/manual/en/context.ssl.php
			$ssl_ctx_opts = array(
				// verification options:
				'verify_peer'			=> true,
				'allow_self_signed'		=> true,
				//'cafile'				=> '/path/to/ca.pem',
				//'capath'				=> '/path/to/ca_dir',
				//'verify_depth'		=> 1,
				//'local_cert'			=> '/path/to/client_cert.pem',
				//'passphrase'			=> 'optional passphrase',
			);
		*/
		$tmp = $this->_cmd('ssl');
		if( $tmp===false ) return false;
		// NOTE: default for verify_peer changed in PHP 5.6.x - we revert that change:
		// (see http://php.net/manual/de/migration56.openssl.php)
		if( !isset($ssl_ctx_opts['verify_peer']) ) $ssl_ctx_opts['verify_peer'] = false;
		if( !empty($ssl_ctx_opts) ) {
			stream_context_set_option($this->ctx,array('ssl'=>$ssl_ctx_opts));
		}
		$crypto_types = STREAM_CRYPTO_METHOD_TLS_CLIENT;
		// in PHP >= 5.6.7 STREAM_CRYPTO_METHOD_TLS_CLIENT was redefined and excludes TLSv1.1 and TLS v1.2 - revert that change:
		if( defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') ) $crypto_types = $crypto_types|STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
		if( !stream_socket_enable_crypto($this->fp,true,$crypto_types) ) return $this->_error('ssl/1','failed to initiate ssl mode',true,true);
		$this->connected_ssl_active = true;
		$this->connected_ssl_ctx_opts = (array)$ssl_ctx_opts;
		return true;
	}

	/**
	 * Login at manja server
	 *
	 * @param string $username
	 * @param string|null $password
	 * @param string|null $remote_host
	 * @param int|null $remote_port
	 * @param string|null $login_token
	 * @param array|null $xinfo
	 *
	 * @return int
	 */
	public function Login( string $username, string $password=null, string $remote_host=null, int $remote_port=null, string $login_token=null, array $xinfo=null ) : int {
		// login
		$par = array('user'=>$username,'pass'=>$password,'client_id'=>$this->cfg_client_id);
		if( $remote_host!==null ) $par['remote_host'] = $remote_host;
		if( $remote_port!==null ) $par['remote_port'] = $remote_port;
		if( $login_token!=null ) $par['token'] = $login_token;
		if( $xinfo!==null ) {
			foreach( $xinfo as $k=>$v ) $par[$k] = $v;
		}
		if( ($result=$this->_cmd('login',$par))===false ) {
			$this->_error('auth/2','login failed');
			$this->Disconnect();
			return 0;
		}
		$this->connected_username = $username;
		$this->connected_user_relogin_info = array('username'=>$username,'password'=>$password,'remote_host'=>$remote_host,'remote_port'=>$remote_port,'login_token'=>$login_token);
		$this->connected_user_id = (int)$result['user_id'];
		return $this->connected_user_id;
	}

	/**
	 * Invalidate the current login and/or session
	 *
	 * @param string|null $new_client_id	optional: use this client_id for next login or session resume
	 *
	 * @return array|false
	 */
	public function Init( ?string $new_client_id=null ) {
		$this->connected_username = null;
		$this->connected_user_relogin_info = null;
		$this->connected_session_id = null;
		$this->connected_session_expiry_ts = null;
		$this->connected_user_id = null;
		$r = $this->_cmd('init');
		if( $new_client_id!==null ) $this->cfg_client_id = $new_client_id;
		return $r;
	}

	/**
	 * No op command (used to avoid timeouts)
	 *
	 * @return array|false
	 */
	public function NoOp() {
		return $this->_cmd('noop');
	}

	/**
	 * Disconnect from server
	 */
	public function Disconnect() {
		if( $this->fp!==null && $this->fp!==false ) {
			// let server close the connection whenever possible
			if( $this->stream_state==='idle' ) {
				//throw new mjError('DISCONNECT');
				// but do not wait forever on the close
				@stream_set_timeout( $this->fp, 3 );
				@fwrite($this->fp,"exit\n\n",6);
				$this->stream_state = 'none';
			}
			// and add an optional close on client side (close streams in error-states too, error-state may be result of a communication error with still valid connection)
			@fclose($this->fp);
			$this->fp = null;
		}
		$this->ctx = null;
	}

	/**
	 * Get list of features supported by connected manja server
	 *
	 * @return array
	 */
	public function GetFeatures() : array {
		return $this->server_features;
	}

	/**
	 * Check whether connected manja server provides a given feature
	 *
	 * @param string $feature
	 *
	 * @return bool
	 */
	public function HasFeature( string $feature ) : bool {
		return isset($this->server_features[$feature]);
	}


	//--------------------------------------------------------------------------
	// COMMANDS
	//--------------------------------------------------------------------------

	public function CommandsList() {
		$tmp = $this->_cmd('commands list');
		if( $tmp===false ) return false;
		return explode(',',$tmp['commands']);
	}

	public function SessionCreate() {
		$tmp = $this->_cmd('session create');
		if( $tmp===false ) return false;
		$this->connected_session_id = $tmp['session_id'];
		if( isset($tmp['timeout']) ) $tmp['timeout'] = intval($tmp['timeout'],10);
		$this->connected_session_expiry_ts = time()+$tmp['timeout'];
		return $tmp;
	}

	public function SessionResume( string $sid, bool $get_data=false, bool $get_roles=false, bool $do_not_touch=false, string $remote_host=null, int $remote_port=null ) {
		$par = array('session_id'=>$sid,'client_id'=>$this->cfg_client_id);
		if( $get_data ) $par['get_data'] = '1';
		if( $get_roles ) $par['get_roles'] = '1';
		if( $do_not_touch ) $par['do_not_touch'] = '1';
		if( $remote_host!==null ) $par['remote_host'] = $remote_host;
		if( $remote_port!==null ) $par['remote_port'] = (string)$remote_port;
		$tmp = $this->_cmd('session resume',$par);
		if( $tmp===false ) return false;
		$this->connected_username = $tmp['login'];
		$this->connected_user_id = (int)$tmp['user_id'];
		$this->connected_session_id = $sid;
		if( $get_data ) {
			$data = [];
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='d' && isset($k[1]) && $k[1]==='.' ) $data[substr($k,2)] = $v;
			}
			$tmp['session_data'] = $data;
		}
		if( $get_roles && isset($tmp['roles']) ) {
			// build key=value list where key and value are the role (makes some things easier..)
			$r = x_explode(',',$tmp['roles']);
			$tmp['roles'] = isset($r[0]) ? array_combine($r,$r) : [];
		}
		if( isset($tmp['timeout']) ) $tmp['timeout'] = intval($tmp['timeout'],10);
		$this->connected_session_expiry_ts = time()+$tmp['timeout'];
		return $tmp;
	}

	public function SessionSet( array $values ) {
		return $this->_cmd('session set',$values);
	}
	public function SessionGet( array $keys=null ) {
		if( $keys===null ) return $this->_cmd('session get');
		else return $this->_cmd('session get',array('parameters'=>implode(',',$keys)));
	}
	public function SessionExit() {
		$tmp = $this->_cmd('session exit');
		if( $tmp===false ) return false;
		$this->connected_session_id = null;
		$this->connected_session_expiry_ts = null;
		return $tmp;
	}

	public function LicenseGet() {
		$tmp = $this->_cmd('license get');
		if( is_array($tmp) && isset($tmp['license']) ) return $tmp['license'];
		return false;
	}

	public function RolesGet() {
		$tmp = $this->_cmd('roles get');
		if( is_array($tmp) && isset($tmp['roles']) ) {
			// build key=value list where key and value are the role (makes some things easier..)
			$r = x_explode(',',$tmp['roles']);
			return isset($r[0]) ? array_combine($r,$r) : [];
		}
		return false;
	}

	public function UtilNowGet( bool $utc=true, string $interval1=null, string $interval2=null ) {
		$par = array( 'utc'=>$utc?'1':'0' );
		if( $interval1!==null ) $par['interval1'] = $interval1;
		if( $interval2!==null ) $par['interval2'] = $interval2;
		$tmp = $this->_cmd('util now get',$par);
		if( is_array($tmp) && isset($tmp['result']) ) return $tmp['result'];
		return false;
	}

	public function UtilDatetimeIntervalCompute( string $dt, string $interval1=null, string $interval2=null ) {
		$par = array( 'dt'=>$dt );
		if( $interval1!==null ) $par['interval1'] = $interval1;
		if( $interval2!==null ) $par['interval2'] = $interval2;
		$tmp = $this->_cmd('util datetime interval compute',$par);
		if( is_array($tmp) && isset($tmp['result']) ) return $tmp['result'];
		return false;
	}


	// TODO: refactoring of IndexSearch & IndexSearch2

	public function IndexSearch( string $fulltext, bool $fulltext_match_partial=false, $result_list_id=0, $work_list_id=0, $meta_ids=null, string $dominant_color=null, float $dominant_color_limit=null, array $filters=null, array $similar_to_media_ids=null, bool $fulltext_match_category_titles=false ) {
		$par = array( 'fulltext'=>$fulltext,
					  'fulltext_match_partial'=>$fulltext_match_partial?'1':'0',
					  'fulltext_match_category_titles'=>$fulltext_match_category_titles?'1':'0',
					  'result_list_id'=>$result_list_id,
					  'work_list_id'=>$work_list_id );
		if( is_array($meta_ids) ) $par['meta_ids'] = implode(',',$meta_ids);
		else if( is_string($meta_ids) ) $par['meta_ids'] = $meta_ids;
		if( $dominant_color!==null ) {
			$par['dominant_color'] = $dominant_color;
			$par['dominant_color_limit'] = $dominant_color_limit;
		}
		if( $filters!==null ) $par = $filters+$par;		//array_merge( $par, $filters );
		if( $similar_to_media_ids!==null ) $par['similar_to_media_ids'] = implode(',',$similar_to_media_ids);
		return $this->_cmd( 'index search', $par );
	}

	public function IndexSearch2( string $fulltext, bool $fulltext_match_partial=false, $result_list_id=0, $work_list_id=0, $meta_ids=null, string $dominant_color=null, float $dominant_color_limit=null, array $filters=null, array $similar_to_media_ids=null, array $in_categories, array $in_trees, array $not_in_trees, array $conjunct_mode_trees=[], bool $fulltext_match_category_titles=false ) {
		$par = array( 'fulltext'=>$fulltext,
					  'fulltext_match_partial'=>$fulltext_match_partial?'1':'0',
					  'fulltext_match_category_titles'=>$fulltext_match_category_titles?'1':'0',
					  'result_list_id'=>$result_list_id,
					  'work_list_id'=>$work_list_id,
					  'filter_categories'=>'1',
					  'in_categories'=>implode(',',$in_categories),
					  'in_trees'=>implode(',',$in_trees),
					  'not_in_trees'=>implode(',',$not_in_trees),
					  'conjunct_mode_trees'=>implode(',',$conjunct_mode_trees) );
		if( is_array($meta_ids) ) $par['meta_ids'] = implode(',',$meta_ids);
		else if( is_string($meta_ids) ) $par['meta_ids'] = $meta_ids;
		if( $dominant_color!==null ) {
			$par['dominant_color'] = $dominant_color;
			$par['dominant_color_limit'] = $dominant_color_limit;
		}
		if( $filters!==null ) $par = $filters+$par;		//array_merge( $par, $filters );
		if( $similar_to_media_ids!==null ) $par['similar_to_media_ids'] = implode(',',$similar_to_media_ids);
		return $this->_cmd( 'index search', $par );
	}


	public function IndexSearch3( string $expression, $result_list_id=0, $work_list_id=0, $meta_ids=null, string $dominant_color=null, float $dominant_color_limit=null, array $filters=null, array $similar_to_media_ids=null, bool $fulltext_match_category_titles=false ) {
		$par = array( 'expression'=>$expression,
					  'fulltext_match_category_titles'=>$fulltext_match_category_titles?'1':'0',
					  'result_list_id'=>$result_list_id,
					  'work_list_id'=>$work_list_id );
		if( is_array($meta_ids) ) $par['meta_ids'] = implode(',',$meta_ids);
		else if( is_string($meta_ids) ) $par['meta_ids'] = $meta_ids;
		if( $dominant_color!==null ) {
			$par['dominant_color'] = $dominant_color;
			$par['dominant_color_limit'] = $dominant_color_limit;
		}
		if( $filters!==null ) $par = $filters+$par;		//array_merge( $par, $filters );
		if( $similar_to_media_ids!==null ) $par['similar_to_media_ids'] = implode(',',$similar_to_media_ids);
		return $this->_cmd( 'index search', $par );
	}

	public function IndexSearch4( string $expression, $result_list_id=0, $work_list_id=0, $meta_ids=null, string $dominant_color=null, float $dominant_color_limit=null, array $filters=null, array $similar_to_media_ids=null, array $in_categories, array $in_trees, array $not_in_trees, array $conjunct_mode_trees=[], bool $fulltext_match_category_titles=false ) {
		$par = array( 'expression'=>$expression,
					  'fulltext_match_category_titles'=>$fulltext_match_category_titles?'1':'0',
					  'result_list_id'=>$result_list_id,
					  'work_list_id'=>$work_list_id,
					  'filter_categories'=>'1',
					  'in_categories'=>implode(',',$in_categories),
					  'in_trees'=>implode(',',$in_trees),
					  'not_in_trees'=>implode(',',$not_in_trees),
					  'conjunct_mode_trees'=>implode(',',$conjunct_mode_trees) );
		if( is_array($meta_ids) ) $par['meta_ids'] = implode(',',$meta_ids);
		else if( is_string($meta_ids) ) $par['meta_ids'] = $meta_ids;
		if( $dominant_color!==null ) {
			$par['dominant_color'] = $dominant_color;
			$par['dominant_color_limit'] = $dominant_color_limit;
		}
		if( $filters!==null ) $par = $filters+$par;		//array_merge( $par, $filters );
		if( $similar_to_media_ids!==null ) $par['similar_to_media_ids'] = implode(',',$similar_to_media_ids);
		return $this->_cmd( 'index search', $par );
	}



	public function IndexSearchExpressionParse( string $fulltext, bool $fulltext_match_partial=false ) {
		$par = array( 'fulltext'=>$fulltext, 'fulltext_match_partial'=>$fulltext_match_partial?'1':'0' );
		return $this->_cmd( 'index search expression parse', $par );
	}


	public function IndexListFilter( $result_list_id, $work_list_id, array $in_categories, array $in_trees, array $not_in_trees, array $conjunct_mode_trees=[] ) {
		$par = array( 'result_list_id'=>$result_list_id, 'work_list_id'=>$work_list_id, 'in_categories'=>implode(',',$in_categories), 'in_trees'=>implode(',',$in_trees), 'not_in_trees'=>implode(',',$not_in_trees), 'conjunct_mode_trees'=>implode(',',$conjunct_mode_trees) );
		return $this->_cmd( 'index list filter', $par );
	}

	public function IndexListCounts( $list_id ) {
		$tmp = $this->_cmd( 'index list counts', array('list_id'=>$list_id) );
		if( $tmp===false ) return false;
		$assigned_to_categories = [];
		$unassigned_to_trees = [];
		foreach( $tmp as $k=>$v ) {
			if( strncmp($k,'assigned.',9)===0 ) {
				$assigned_to_categories[substr($k,9)] = x_explode(',',$v);
			} else if( strncmp($k,'unassigned.',11)===0 ) {
				$unassigned_to_trees[substr($k,11)] = x_explode(',',$v);
			}
		}
		return array( 'assigned'=>$assigned_to_categories, 'unassigned'=>$unassigned_to_trees );
	}

	public function IndexListGet( $list_id, $offset, $count, array $meta_ids=null, bool $filter_deleted=false, bool $with_categories=false, bool $with_relevance=false, bool $with_visibility_status=false ) {
		$par = array( 'list_id'=>$list_id, 'offset'=>$offset, 'count'=>$count );
		if( $filter_deleted ) {
			if( is_array($meta_ids) ) $meta_ids[] = -1;
			else $meta_ids = array(-1);
		}
		if( is_array($meta_ids) && isset($meta_ids[0]) ) $par['meta_ids'] = implode(',',array_keys(array_flip($meta_ids)));
		if( $with_categories ) $par['with_categories'] = '1';
		if( $with_relevance ) $par['with_relevance'] = '1';
		if( $with_visibility_status ) $par['with_visibility_status'] = '1';
		$tmp = $this->_cmd( 'index list get', $par );
		if( $tmp===false ) return false;
		// index media-ids by position in global list
		$mids = [];
		$relevance = [];
		if( ($rc=(int)$tmp['result_count']) > 0 ) {
			$idx = $offset;
			foreach( x_explode(',',$tmp['result']) as $id ) $mids[$idx++] = $id;
			if( $with_relevance ) {
				$idx = $offset;
				foreach( x_explode(',',$tmp['relevance']) as $val ) $relevance[$idx++] = $val;
			}
		}
		unset($tmp['result_count']);
		unset($tmp['result']);
		unset($tmp['relevance']);
		$meta = [];
		$categories = [];
		if( $meta_ids!==null && $with_categories ) {
			// meta-data & categories
			foreach( $tmp as $k=>$v ) {
				if( $k[1]==='.' ) {
					if( $k[0]==='m' ) {
						$x = explode('.',$k,4);
						$meta[$x[1]][$x[2]][$x[3]] = $v;
					} else if( $k[0]==='c' ) {
						$x = explode('.',$k,3);
						$categories[$x[1]][$x[2]] = strpos($v,',')===false ? $v : x_explode(',',$v);
					}
				}
			}
		} else if( $meta_ids!==null ) {
			// meta-data
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='m' && $k[1]==='.' ) {
					$x = explode('.',$k,4);
					$meta[$x[1]][$x[2]][$x[3]] = $v;
				}
			}
		} else if( $with_categories ) {
			// categories
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='c' && $k[1]==='.' ) {
					$x = explode('.',$k,3);
					$categories[$x[1]][$x[2]] = x_explode(',',$v);
				}
			}
		}
		$visibility_status = [];
		if( $with_visibility_status ) {
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='v' && $k[1]==='s' && $k[2]==='.' ) {
					//$x = explode('.',$k,2);
					//$visibility_status[$x[1]] = $v;
					$x1 = substr($k,3);
					$visibility_status[$x1] = $v;
				}
			}
		}
		if( $filter_deleted ) {
			$filtered_mids = [];
			foreach( $mids as $mid ) if( isset($meta[$mid]) ) $filtered_mids[] = $mid;
			$mids = $filtered_mids;
		}
		return array(	'result_count' => $rc,
						'media_ids' => $mids,
						'relevance' => $relevance,
						'meta' => $meta,
						'categories' => $categories,
						'visibility_status' => $visibility_status );
	}

	public function IndexListSort( int $list_id, $criteria ) {
		$par = [ 'list_id'=>$list_id ];
		if( is_string($criteria) ) $criteria = x_explode(',',$criteria);
		$idx = 1;
		foreach( $criteria as $c ) {
			if( ($c=trim($c))!=='' ) {
				$par['criteria.'.$idx] = $c;
				++$idx;
			}
		}
		return $this->_cmd( 'index list sort', $par );
	}

	public function IndexListCreate( string $session_id=null, array $media_ids=null ) {
		$par = [];
		if( $session_id!==null ) $par['session_id'] = $session_id;
		if( $media_ids!==null ) $par['media_ids'] = implode(',',$media_ids);
		return $this->_cmd( 'index list create', $par );
	}


	public function IndexMetaSuggestionsGet( $meta_id, $cur_count, $freq_count, $char_limit ) {
		$tmp = $this->_cmd( 'index meta suggestions get', array( 'meta_id'=>$meta_id, 'cur_count'=>$cur_count, 'freq_count'=>$freq_count, 'char_limit'=>$char_limit ) );
		if( $tmp===false ) return false;
		return $this->_parse_numeric_result( $tmp );
	}

	public function IndexSearchSuggestionsGet( $meta_ids, $count, $char_limit, $pattern, $fulltext_match_category_titles=false ) {
		if( is_array($meta_ids) ) $meta_ids = implode(',',$meta_ids);
		$par = array( 'meta_ids'=>$meta_ids, 'fulltext_match_category_titles'=>$fulltext_match_category_titles?'1':'0', 'count'=>$count, 'char_limit'=>$char_limit, 'pattern'=>$pattern );
		$tmp = $this->_cmd( 'index search suggestions get', $par );
		if( $tmp===false ) return false;
		return $this->_parse_numeric_result( $tmp );
	}

	public function IndexMetaValuesGet( $meta_id ) {
		$tmp = $this->_cmd( 'index meta values get', array('meta_id'=>$meta_id) );
		if( $tmp===false ) return false;
		return $this->_parse_numeric_result( $tmp );
	}

	/**
	 * Get content stream of a file in original format
	 *
	 * @param string|int $media_id
	 * @param bool $stream
	 * @param string|null $filename
	 * @param array $options
	 *
	 * @return array|false
	 */
	public function MediumGet( $media_id, bool $stream=true, string $filename=null, array $options=[] ) {
		$options['media_id'] = $media_id;
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'medium get', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename);
	}

	/**
	 * Get content stream of a file in a specific download format
	 *
	 * @param string|int $media_id
	 * @param int $dlf_id
	 * @param bool $stream
	 * @param string|null $filename
	 * @param array $options
	 *
	 * @return array|false
	 */
	public function MediumDownload( $media_id, int $dlf_id, bool $stream=true, string $filename=null, array $options=[] ) {
		$options['media_id'] = $media_id;
		$options['dlf_id'] = $dlf_id;
		if( $stream ) $this->_stream_parse_request($options);
		if( $filename!==null ) $options['filename'] = $filename;
		$tmp = $this->_cmd( 'medium download', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename);
	}

	/**
	 * Get byte size of content stream of a file in a specific download format
	 *
	 * @param string|int $media_id
	 * @param int $dlf_id
	 * @return int|bool
	 */
	public function MediumDownloadSize( $media_id, int $dlf_id ) {
		$options = array('media_id'=>$media_id,'dlf_id'=>$dlf_id);
		$tmp = $this->_cmd( 'medium download size', $options );
		return $tmp;
	}

	/**
	 * Get content stream of a file in a specific preview format
	 *
	 * @param array $options
	 * @param bool $stream
	 * @param string|null $filename
	 *
	 * @return array|false
	 */
	public function MediumPreview( array $options, bool $stream=true, $filename=null ) {
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'medium preview', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename);
	}

	public function MediumDimensions( $media_id, int $page, string $box=null ) {
		$opts = array('media_id'=>$media_id,'page'=>$page);
		if( $box!==null ) $opts['box'] = $box;
		return $this->_cmd( 'medium dimensions', $opts );
	}

	public function MediumUpload( string $filepath, $media_id=null, $cat_id_tree_1=1, string $filename='', $new_media_id=null ) {
		$filename = \Normalizer::normalize($filename);
		$opts = array( 'filename'=>$filename );
		if( $media_id===null ) {
			$opts['cat_id'] = is_array($cat_id_tree_1) ? implode(',',$cat_id_tree_1) : (string)$cat_id_tree_1;
			if( $new_media_id!==null ) $opts['new_media_id'] = $new_media_id;
		} else {
			$opts['media_id'] = $media_id;
		}
		return $this->_upload_from_file_cmd( 'medium upload', $opts, $filepath );
	}

	public function MediumUploadFromStream( $input_stream=null, int $input_size=-1, $media_id=null, $cat_id_tree_1=1, string $filename='', $new_media_id=null ) {
		$filename = \Normalizer::normalize($filename);
		$opts = array( 'filename'=>$filename );
		if( $media_id===null ) {
			$opts['cat_id'] = $cat_id_tree_1;
			if( $new_media_id!==null ) $opts['new_media_id'] = $new_media_id;
		} else {
			$opts['media_id'] = $media_id;
		}
		return $this->_upload_from_stream_cmd('medium upload',$opts,$input_stream,$input_size);
	}


	/**
	 * Get XMP data stream of a file
	 *
	 * @param string|int $media_id
	 * @param bool $stream
	 * @param string|null $filename
	 * @param array $options
	 *
	 * @return array|false
	 */
	public function MediumXmpGet( $media_id, bool $stream=true, string $filename=null, array $options=[] ) {
		$options['media_id'] = $media_id;
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'medium xmp get', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename);
	}

	public function MediumXmpUpload( $media_id, string $filepath, bool $persist_as_sidecar_file, bool $do_not_create_version=false ) {
		$opts = array( 'media_id'=>$media_id, 'persist_as_sidecar_file'=>$persist_as_sidecar_file?'1':'0', 'do_not_create_version'=>$do_not_create_version?'1':'0' );
		return $this->_upload_from_file_cmd( 'medium xmp upload', $opts, $filepath );
	}

	public function MediumCustomPreviewUpload( $media_id, string $filepath, $cp_id=0, string $filename='' ) {
		$filename = Normalizer::normalize($filename);
		$opts = array( 'media_id'=>$media_id, 'cp_id'=>$cp_id, 'filename'=>$filename );
		return $this->_upload_from_file_cmd( 'medium custom preview upload', $opts, $filepath );
	}

	public function MediumCustomPreviewRemove( $media_id, $cp_id=0 ) {
		return $this->_cmd( 'medium custom preview remove', array( 'media_id'=>$media_id, 'cp_id'=>$cp_id ) );
	}

	/**
	 * Get custom preview stream of a file
	 *
	 * @param array $options
	 * @param bool $stream
	 * @param string|null $filename
	 *
	 * @return array|false
	 */
	public function MediumCustomPreviewGet( array $options, bool $stream=true, string $filename=null ) {
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'medium custom preview get', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename,60);
	}


	public function MediumXFDFInfo( $media_id, int $version=0, bool $with_xfdf=false, bool $with_media_mtime=false ) {
		$opts = array( 'media_id'=>$media_id );
		if( $version>0 ) $opts['version'] = $version;
		if( $with_xfdf ) $opts['with_xfdf'] = '1';
		if( $with_media_mtime ) $opts['with_media_mtime'] = '1';
		$tmp = $this->_cmd('medium xfdf info',$opts);
		return $tmp;
	}

	public function MediaXFDFInfo( array $media_ids, bool $with_xfdf=false, bool $with_mtime=false, bool $with_media_mtime=false ) {
		$opts = array( 'media_ids'=>implode(',',$media_ids) );
		if( $with_xfdf ) $opts['with_xfdf'] = '1';
		if( $with_mtime ) $opts['with_mtime'] = '1';
		if( $with_media_mtime ) $opts['with_media_mtime'] = '1';
		$tmp = $this->_cmd('media xfdf info',$opts);
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}


	/**
	 * @deprecated 4.2.4 - use MediumXFDFGet2() instead with streaming interface
	 */
	public function MediumXFDFGet( $media_id, int $version=0, int $if_modified_since=null, $if_none_match=null, bool $set_empty_to_default_xfdf=false ) {
		$opts = array( 'media_id'=>$media_id );
		if( $version>0 ) $opts['version'] = $version;
		if( $if_modified_since!==null && $if_modified_since>0 ) $opts['if_modified_since'] = $if_modified_since;
		if( $if_none_match!==null ) {
			if( $if_none_match===true ) $opts['if_none_match'] = '*';
			else $opts['if_none_match'] = implode(',',$if_none_match);
		}
		if( $set_empty_to_default_xfdf ) $opts['set_empty_to_default_xfdf'] = '1';
		$tmp = $this->_cmd('medium xfdf get',$opts);
		return $tmp;
	}

	public function MediumXFDFGet2( $media_id, int $version=0, bool $set_empty_to_default_xfdf=false, bool $stream=true, string $filename=null ) {
		$options = array( 'media_id'=>$media_id, 'stream'=>'1' );
		if( $stream ) $this->_stream_parse_request($options);
		if( $version>0 ) $options['version'] = $version;
		if( $set_empty_to_default_xfdf ) $options['set_empty_to_default_xfdf'] = '1';
		$tmp = $this->_cmd('medium xfdf get',$options);
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename,60);
	}

	public function MediumXFDFPut( $media_id, string $xfdf, bool $update_embedded=false ) {
		$opts = array( 'media_id'=>$media_id, 'xfdf'=>$xfdf );
		if( $update_embedded ) $opts['update_embedded'] = '1';
		$tmp = $this->_cmd('medium xfdf put',$opts);
		return $tmp;
	}

	public function MediumXFDFMerge( $media_id, string $action, $annotation_id, $parent_author_id, string $xfdf, bool $update_embedded=false ) {
		$opts = array( 'media_id'=>$media_id, 'action'=>$action, 'annotation_id'=>$annotation_id, 'parent_author_id'=>$parent_author_id, 'xfdf'=>$xfdf );
		if( $update_embedded ) $opts['update_embedded'] = '1';
		$tmp = $this->_cmd('medium xfdf merge',$opts);
		return $tmp;
	}

	public function MediumClone( $from_media_id, $to_media_id=null, ?int $to_cat_id=null, ?string $to_filename=null ) {//, $delete_from=false ) {
		$opts = array( 'from_media_id'=>$from_media_id );
		if( $to_media_id!==null ) $opts['to_media_id'] = $to_media_id;
		if( $to_cat_id!==null ) $opts['to_cat_id'] = $to_cat_id;
		if( $to_filename!==null ) $opts['to_filename'] = $to_filename;
		//if( $delete_from ) $opts['delete_from'] = '1';
		$tmp = $this->_cmd('medium clone',$opts);
		return $tmp;
	}

	public function MediumStructureInfo( $media_id, int $page=0, string $box=null, array $options=[] ) {
		$opts = $options;
		$opts['media_id'] = $media_id;
		if( $page>0 ) $opts['page'] = $page;
		if( $box!==null ) $opts['box'] = $box;
		$tmp = $this->_cmd( 'medium structure info', $opts );
		if( $tmp===false ) return false;
		$result = [];
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,5); // key parts
			if( $kp[0]==='p' ) {
				$pageno = $kp[1];
				if( !isset($result[$pageno]) ) $result[$pageno] = array( 'page'=>$pageno, 'media_box'=>'', 'ref_box'=>'', 'elements'=>[] );
				switch( $kp[2] ) {
				case 'ref_box':
					$result[$pageno]['ref_box'] = x_explode2floats(' ',$v);
					break;
				case 'media_box':
					$result[$pageno]['media_box'] = x_explode2floats(' ',$v);
					break;
				case 'e':
					$et = $kp[4];
					if( $et==='b' ) {
						$et = 'bbox';
						$v = x_explode2floats(' ',$v);
					} else if( $et==='t' ) {
						$et = 'type';
					}
					$result[$pageno]['elements'][$kp[3]][$et] = $v;
					break;
				}
			}
		}
		return $result;
	}

	public function MediaStructureCompare( $media_id_a, $media_id_b, int $page_a, int $page_b, string $box_a=null, string $box_b=null, array $options=[] ) {
		$opts = array('media_id_a'=>$media_id_a,'media_id_b'=>$media_id_b,'page_a'=>$page_a,'page_b'=>$page_b,'box_a'=>$box_a,'box_b'=>$box_b) + $options;
		$tmp = $this->_cmd( 'media structure compare', $opts );
		if( $tmp===false ) return false;
		/* result structure ..
		array(
				'npages_different' => bool,
				'document_a'       => array( 'npages'=>int ),
				'document_b'       => array( 'npages'=>int ),
				'page_diffs'       => array(
						'<page_a>:<page_b>' => array(
								'media_box_different' => bool,
								'ref_box_different'   => bool,
								'ndifferences'        => int,
								'info_a'              => array( 'nelements'=>int, 'media_box'=>bbox, 'ref_box'=>bbox ),
								'info_b'              => array( 'nelements'=>int, 'media_box'=>bbox, 'ref_box'=>bbox ),
								'elem_diffs'          => array(
										idx => array(
												'd'      => string,		// A, B, A+B, AtB
												't'      => string,		// path, image, text_run, ...
												'bbox_a' => bbox,
												'bbox_b' => bbox,
										),
										...
								)
						),
						...
				)
		);*/
		$result = array(
				'npages_different'	=> $tmp['dd.npages_different']==='1',
				'document_a'		=> array( 'npages'=>$tmp['dd.a.npages'] ),
				'document_b'		=> array( 'npages'=>$tmp['dd.b.npages'] ),
		);
		$page_diffs = [];
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,5); // key parts
			if( $kp[0]==='pd' ) { // pd.
				$pd_key = $kp[1]; // pd.<page_a>:<page_b>.
				if( !isset($page_diffs[$pd_key]) ) $page_diffs[$pd_key] = array('elem_diffs'=>[]);
				switch( $kp[2] ) {
				case 'media_box_different':
					$page_diffs[$pd_key]['media_box_different'] = $v==='1';
					break;
				case 'ref_box_different':
					$page_diffs[$pd_key]['ref_box_different'] = $v==='1';
					break;
				case 'ndifferences':
					$page_diffs[$pd_key]['ndifferences'] = $v;
					break;
				case 'a':
					$page_diffs[$pd_key]['info_a'][$kp[3]] = $kp[3]==='media_box' || $kp[3]==='ref_box' ? x_explode2floats(' ',$v) : $v;
					break;
				case 'b':
					$page_diffs[$pd_key]['info_b'][$kp[3]] = $kp[3]==='media_box' || $kp[3]==='ref_box' ? x_explode2floats(' ',$v) : $v;
					break;
				case 'ed':
					$page_diffs[$pd_key]['elem_diffs'][(int)$kp[3]][$kp[4]] = $kp[4]==='bbox_a' || $kp[4]==='bbox_b' ? x_explode2floats(' ',$v) : $v;
					break;
				}
			}
		}
		$result['page_diffs'] = $page_diffs;
		return $result;
	}

	public function MediaInfo( array $media_ids, ?array $meta_ids=null ) {
		$p = array( 'media_ids'=>implode(',',$media_ids) );
		if( is_array($meta_ids) && !empty($meta_ids) ) $p['meta_ids'] = implode(',',$meta_ids);
		else if( $meta_ids!==null ) $p['meta_ids'] = $meta_ids;
		$tmp = $this->_cmd( 'media info', $p );
		if( $tmp===false ) return false;
		$m = [];
		$c = [];
		$vn = [];
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,4); // key parts
			if( $k[0]==='m' ) $m[$kp[1]][$kp[2]][$kp[3]] = $v;
			else if( $k[0]==='c' ) $c[$kp[1]][$kp[2]] = x_explode(',',$v);
			else if( $k[0]==='v' ) $vn[$kp[1]] = $v;
		}
		$this->_sort_metadata_values($m);
		return array('meta'=>$m,'categories'=>$c,'versions'=>$vn);
	}

	public function MediaMetaList( array $media_ids, ?array $meta_ids=null ) {
		$p = array( 'media_ids'=>implode(',',$media_ids) );
		if( is_array($meta_ids) && !empty($meta_ids) ) $p['meta_ids'] = implode(',',$meta_ids);
		else if( $meta_ids!==null ) $p['meta_ids'] = $meta_ids;
		//throw new Exception('media meta list (media_ids='.implode(',',$media_ids).'; meta_ids='.implode(',',$meta_ids).')');
		$tmp = $this->_cmd( 'media meta list', $p );
		if( $tmp===false ) return false;
		$m = [];
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,3); // key parts
			$m[$kp[0]][$kp[1]][$kp[2]] = $v;
		}
		$this->_sort_metadata_values($m);
		return $m;
	}

	public function MediaCategoriesList( array $media_ids ) {
		$tmp = $this->_cmd( 'media categories list', array('media_ids'=>implode(',',$media_ids)) );
		if( $tmp===false ) return false;
		$c = [];
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,2); // key parts
			$c[$kp[0]][$kp[1]] = x_explode(',',$v);
		}
		return $c;
	}

	public function MediaUpdate( array $media_ids, array $meta, array $categories, bool $do_not_create_version=false ) {
		$p = array( 'media_ids'=>implode(',',$media_ids) );
		if( $do_not_create_version ) $p['do_not_create_version'] = '1';
		foreach( $meta as $meta_id => $info ) {
			foreach( $info as $listpos => $data ) {
				$p['meta.'.$meta_id.'.'.$listpos] = $data;
			}
		}
		foreach( $categories as $tree_id => $cat_ids ) {
			$p['cat.'.$tree_id] = is_array($cat_ids) ? implode(',',$cat_ids) : (string)$cat_ids;
		}
		return $this->_cmd( 'media update', $p );
	}

	public function MediaMetaAdd( array $media_ids, array $meta, bool $filter_duplicates=false ) {
		$p = array( 'media_ids'=>implode(',',$media_ids) );
		foreach( $meta as $meta_id => $info ) {
			foreach( $info as $idx => $data ) {
				$p['meta.'.$meta_id.'.'.$idx] = $data;
			}
		}
		if( $filter_duplicates ) $p['filter_duplicates'] = '1';
		return $this->_cmd( 'media meta add', $p );
	}

	public function MediaCategoriesAdd( array $media_ids, array $categories ) {
		$p = array( 'media_ids'=>implode(',',$media_ids) );
		foreach( $categories as $tree_id => $cat_ids ) {
			$p['cat.'.$tree_id] = is_array($cat_ids) ? implode(',',$cat_ids) : (string)$cat_ids;
		}
		return $this->_cmd( 'media categories add', $p );
	}

	public function MediaStatus( array $media_ids ) {
		$tmp = $this->_cmd( 'media status', array('media_ids'=>implode(',',$media_ids)) );
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function MediaDelete( array $media_ids, bool $rebuild_temp_file_references=false, bool $defered=false ) {
		$p = array('media_ids'=>implode(',',$media_ids));
		if( $rebuild_temp_file_references ) $p['rebuild_temp_file_references'] = '1';
		if( $defered ) $p['defered'] = '1';
		$tmp = $this->_cmd( 'media delete', $p );
		if( $tmp===false ) return false;
		$tmp['media_ids'] = isset($tmp['media_ids']) ? x_explode(',',$tmp['media_ids']) : [];
		return $tmp;
	}

	public function MediaDownloadsList( array $media_ids, int $flt_user_id=0 ) {
		$p = array('media_ids'=>implode(',',$media_ids));
		$p['flt_user_id'] = $flt_user_id;
		$tmp = $this->_cmd( 'media downloads list', $p );
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function MediaPluginList( string $suffix=null ) {
		$par = $suffix===null ? [] : array('suffix'=>$suffix);
		$tmp = $this->_cmd('media plugin list',$par);
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function MetaPluginList( string $name=null ) {
		$par = $name===null ? [] : array('name'=>$name);
		$tmp = $this->_cmd('meta plugin list',$par);
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}


	/**
	 * retrieve list of metadata groups
	 *
	 * @return array|false
	 */
	public function MetaGroupsList() {
		$tmp = $this->_cmd('meta groups list');
		if( $tmp===false ) return false;
		$tmp1 = $this->_parse_dot_syntax($tmp);
		foreach( $tmp1 as $id=>&$info ) {
			$info['id'] = (int)$id;
			$info['auto'] = (int)$info['auto'];
			$info['user'] = (int)$info['user'];
			$info['sort'] = (int)$info['sort'];
		}
		unset($info);
		uasort($tmp1,function($a,$b){
			return $a['sort']===$b['sort'] ? 0 : ( $a['sort']<$b['sort'] ? -1 : +1 );
		});
		return $tmp1;
	}

	public function MetaGroupMove( int $group_id, int $after_id ) {
		return $this->_cmd('meta group move',array('group_id'=>$group_id,'after_id'=>$after_id));
	}

	public function MetaGroupAdd( string $group_name ) {
		return $this->_cmd('meta group add',array('group_name'=>$group_name));
	}

	public function MetaGroupUpdate( int $group_id, string $group_name ) {
		return $this->_cmd('meta group update',array('group_id'=>$group_id,'group_name'=>$group_name));
	}

	public function MetaGroupDelete( int $group_id ) {
		return $this->_cmd('meta group delete',array('group_id'=>$group_id));
	}


	public function MetaDefsList( ?array $meta_ids=null, ?int $group_id=null, bool $with_options=false, bool $cvt_values_to_int=false ) {
		$p = array('options'=>$with_options?'1':'');
		if( $meta_ids!==null ) $p['meta_ids'] = implode(',',$meta_ids);
		if( $group_id!==null ) $p['group_id'] = $group_id;
		$tmp = $this->_cmd('meta defs list',$p);
		if( $tmp===false ) return false;
		$int_keys = ['id','group','type','list','relevance','auto','user','intr','sort','usre','rdup'];
		if( $with_options ) {
			$r = [];
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,3);
				if( isset($ka[1]) ) {
					$meta_id = $ka[1];
					if( ($ka0=$ka[0])!=='o' ) $r[$meta_id][$ka0] = $v;		// key.<meta_id>
					else $r[$meta_id]['options'][$ka[2]] = $v;				// o.<meta_id>.<opt_name>
				}
			}
			if( $cvt_values_to_int ) {
				foreach( $r as $meta_id=>&$v ) {
					// add an 'id' key
					$v['id'] = $meta_id;
					// add empty options array, if it does not exist yet
					if( !isset($v['options']) ) $v['options'] = [];
					// cast values of some selected keys to int ..
					foreach( $int_keys as $vk ) $v[$vk] = (int)$v[$vk];
				}
			} else {
				foreach( $r as $meta_id=>&$v ) {
					// add an 'id' key
					$v['id'] = $meta_id;
					// add empty options array, if it does not exist yet
					if( !isset($v['options']) ) $v['options'] = [];
				}
			}
			//unset($v);
			return $r;
		}
		if( $cvt_values_to_int ) return $this->_parse_dot_syntax2($tmp,array_flip($int_keys));
		return $this->_parse_dot_syntax($tmp);
	}

	public function MetaDefMove( int $meta_id, int $after_id ) {
		return $this->_cmd('meta def move',array('meta_id'=>$meta_id,'after_id'=>$after_id));
	}

	public function MetaDefOptionsSet( int $meta_id, array $options ) {
		$p = array('meta_id'=>$meta_id);
		foreach( $options as $k=>$v ) $p['o.'.$k] = $v;
		return $this->_cmd('meta def options set',$p);
	}

	public function MetaDefAdd( int $group_id, string $name, int $type, bool $is_list, bool $remove_duplicates, int $relevance ) {
		$p = [
			'group_id' => $group_id,
			'name' => $name,
			'type' => $type,
			'is_list' => $is_list ? '1' : '',
			'remove_duplicates' => $remove_duplicates ? '1' : '',
			'relevance' => $relevance,
		];
		return $this->_cmd('meta def add',$p);
	}

	public function MetaDefDelete( int $meta_id ) {
		return $this->_cmd('meta def delete',array('meta_id'=>$meta_id));
	}

	public function MetaDefUpdateGroup( int $meta_id, int $group_id ) {
		return $this->_cmd('meta def update group',array('meta_id'=>$meta_id,'group_id'=>$group_id));
	}


	public function MetaModifiedGet() {
		return $this->_cmd('meta modified get');
	}

	public function MetaSearch( int $meta_id, $value, $op='e' ) {
		$p = array( 'meta_id'=>$meta_id, 'value'=>$value, 'op'=>$op );
		$tmp = $this->_cmd('meta search',$p);
		if( $tmp===false ) return false;
		if( !isset($tmp['media_ids']) || !isset($tmp['media_ids'][0]) ) return [];
		return x_explode(',',$tmp['media_ids']);
	}

	/**
	 * @param int $user_id
	 * @param array|string|null $parameters
	 *
	 * @return array|false|string
	 */
	public function UserdataGet( int $user_id=0, $parameters=null ) {
		$p = [];
		if( $user_id ) $p['user_id'] = $user_id;
		if( $parameters===null ) {
			// get all keys
			return $this->_cmd('userdata get',$p);
		}
		if( is_array($parameters) ) {
			// fixed list of keys
			$p['parameters'] = implode(',',$parameters);
			return $this->_cmd('userdata get',$p);
		}
		// get single key only
		$p['parameters'] = $parameters;
		$tmp = $this->_cmd('userdata get',$p);
		if( $tmp===false ) return false;
		return isset($tmp[$parameters]) ? $tmp[$parameters] : '';
	}

	/**
	 * @param int $user_id
	 * @param array|string|null $parameters
	 *
	 * @return array|false|string
	 */
	public function UserdataGetByLogin( string $login, $parameters=null ) {
		$p = [];
		$p['login'] = $login;
		if( $parameters===null ) {
			// get all keys
			return $this->_cmd('userdata get',$p);
		}
		if( is_array($parameters) ) {
			// fixed list of keys
			$p['parameters'] = implode(',',$parameters);
			return $this->_cmd('userdata get',$p);
		}
		// get single key only
		$p['parameters'] = $parameters;
		$tmp = $this->_cmd('userdata get',$p);
		if( $tmp===false ) return false;
		return isset($tmp[$parameters]) ? $tmp[$parameters] : '';
	}

	public function UserdataGetLike( int $user_id=0, string $pattern='%' ) {
		$p = [];
		if( $user_id ) $p['user_id'] = $user_id;
		$p['parameters_like'] = $pattern;
		return $this->_cmd('userdata get',$p);
	}

	public function UserdataGetLike2( int $user_id=0, ?array $parameters=null, string $pattern='%' ) {
		$p = [];
		if( $user_id ) $p['user_id'] = $user_id;
		if( is_array($parameters) ) $p['parameters'] = implode(',',$parameters);
		$p['parameters_like'] = $pattern;
		return $this->_cmd('userdata get',$p);
	}

	public function UserdataSet( int $user_id=0, array $key_value_pairs=[] ) {
		$p = $key_value_pairs;
		if( $user_id ) $p['user_id'] = $user_id;
		return $this->_cmd('userdata set',$p);
	}


	public function UserActiveCount( int $user_id=0 ) {
		$tmp = $this->_cmd('user active count',['user_id'=>$user_id]);
		if( $tmp===false ) return false;
		return $tmp['count'];
	}


	public function ClientdataGet( $parameters=null, string $client_id=null, bool $lock_keys=false ) {
		if( $client_id===null ) $client_id = $this->cfg_client_id;
		$p = array( 'client_id'=>$client_id );
		if( $lock_keys ) $p['lock_keys'] = '1';
		if( is_array($parameters) ) {
			$p['parameters'] = implode(',',$parameters);
			return $this->_cmd('clientdata get',$p);
		} else if( $parameters===null ) {
			return $this->_cmd('clientdata get',$p);
		} else { // single parameter
			$p['parameters'] = $parameters;
			$tmp = $this->_cmd('clientdata get',$p);
			if( $tmp===false ) return false;
			return isset($tmp[$parameters]) ? $tmp[$parameters] : '';
		}
	}

	public function ClientdataSet( array $key_value_pairs=[], string $client_id=null, bool $lock_keys=false ) {
		if( $client_id===null ) $client_id = $this->cfg_client_id;
		$p = $key_value_pairs;
		$p['client_id'] = $client_id;
		if( $lock_keys ) $p['lock_keys'] = '1';
		return $this->_cmd('clientdata set',$p);
	}

	public function ClientdataRemove( array $parameters, string $client_id=null ) {
		if( $client_id===null ) $client_id = $this->cfg_client_id;
		return $this->_cmd('clientdata remove',array('client_id'=>$client_id,'parameters'=>implode(',',$parameters)));
	}








	public function NotificationsSeen( array $id_list, int $user_id=0 ) {
		return $this->_cmd( 'notification seen', array('user_id'=>$user_id, 'id_list'=>implode(',', $id_list)) );
	}

	public function NotificationList( int $user_id=0, int $last_id=0 ) {
		$tmp = $this->_cmd( 'notification list', array('user_id'=>$user_id,'last_id'=>$last_id) );
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function NotificationInsert( int $rcpt_user_id, string $text, string $interval='' ) {
		return $this->_cmd( 'notification insert', array('rcpt_user_id'=>$rcpt_user_id,'text'=>$text,'interval'=>$interval) );
	}


	public function MessageGetConfiguration( string $message_def_id ) {
		$tmp = $this->_cmd( 'message get configuration',array('message_def_id'=>$message_def_id) );
		if( $tmp===false ) return false;
		$tmp['send'] = json_decode($tmp['send'],true);
		$tmp['master_template'] = json_decode($tmp['master_template'],true);
		$tmp['user_can_disable'] = $tmp['user_can_disable']==='1';
		return $tmp;
	}

	public function MessageSetConfiguration( string $message_def_id, string $senders, bool $user_can_disable, string $masters ) {
		return $this->_cmd( 'message set configuration',array('message_def_id'=>$message_def_id,'senders'=>$senders,'user_can_disable'=>$user_can_disable,'master_template'=>$masters) );
	}

	public function UserMessageGetDisabled( string $message_def_id, int $user_id ) : bool {
		$tmp = $this->_cmd( 'user message get disabled', array('user_id'=>$user_id, 'message_def_id'=>$message_def_id) );
		if( $tmp===false ) return false;
		return $tmp['disabled']==='1';
	}

	public function UserMessageSetDisabled( string $message_def_id, int $user_id, bool $disabled ) {
		return $this->_cmd( 'user message set disabled', array('user_id'=>$user_id, 'message_def_id'=>$message_def_id, 'disabled'=>$disabled) );
	}

	public function MailQueueTaskAdd( string $rcpt_email, string $subject, string $contents_str, ?string $reply_to=null ) : bool {
		$tmp = $this->_cmd( 'mailqueue task add', array('rcpt'=>$rcpt_email, 'subject'=>$subject, 'contents'=>$contents_str, 'reply'=>$reply_to===null?'':$reply_to) );
		if( $tmp===false ) return false;
		return true;
	}

	public function MailQueueListGet( int $slice_size, int $restart_limit, string $wait_iv, string $unlock_iv, int $unlock_per_slice_limit, bool $lock_tasks ) {
		$tmp = $this->_cmd( 'mailqueue task list', array('slice_size'=>$slice_size, 'restart_limit'=>$restart_limit, 'wait_iv'=>$wait_iv, 'unlock_iv'=>$unlock_iv, 'unlock_per_slice_limit'=>$unlock_per_slice_limit, 'lock_tasks'=>$lock_tasks) );
		if( $tmp===false ) return false;
		return $this->_parse_index_syntax($tmp);
	}

	public function MailQueueTaskDone( int $task_id ) {
		return $this->_cmd( 'mailqueue task done', array('task_id'=>$task_id) );
	}

	public function MessageStoreInArchive(int $user_id, string $user_text, string $type, string $locale, string $body, string $subject, string $target_text, ?int $target_id=null) {
		return $this->_cmd( 'message archive insert', array(
			'user_id' => $user_id, 'user_text' => $user_text,
			'message_type' => $type, 'locale' => $locale,
			'message_body' => $body, 'message_subject' => $subject,
			'target_id' => $target_id??'', 'target_text' => $target_text)
		);
	}

	public function MessageArchiveList(int $user_id, string $direction, string $dateFrom, string $dateTo, string $searchTerm, int $offset, int $limit, string $sortby, string $sort_direction) {
		$tmp = $this->_cmd( 'message archive list', array(
			'user_id' => $user_id, 'direction' => $direction,
			'dateFrom' => $dateFrom, 'dateTo' => $dateTo, 'searchTerm' => $searchTerm,
			'offset' => $offset, 'limit' => $limit, 'sortby' => $sortby, 'sort_direction' => $sort_direction
		));
		if( $tmp===false ) return false;
		return $this->_parse_index_syntax($tmp);
	}

	public function MessageArchiveGet(int $message_archive_id) {
		return $this->_cmd( 'message archive get', array('message_id' => $message_archive_id));
	}

	public function RightsMediaGet( array $media_ids, int $action, bool $bool_result=false ) {
		$tmp = $this->_cmd('rights media get',array('media_ids'=>implode(',',$media_ids),'action'=>$action));
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		if( $bool_result ) {
			foreach( $tmp as &$inf ) $inf = $inf['allowed']==='1';
		}
		return $tmp;
	}

	public function RightsMediaGet2( array $media_ids, array $actions ) {
		$r = [];
		foreach( $actions as $action ) {
			if( ($tmp=$this->RightsMediaGet($media_ids,intval($action)))===false ) return false;
			$ra = [];
			foreach( $tmp as $mid=>$inf ) if( $inf['allowed']==='1' ) $ra[] = $mid;
			$r[$action] = $ra;
		}
		return $r;
	}

	public function RightsMediumGet( $media_id, int $action ) {
		$tmp = $this->_cmd('rights media get',array('media_ids'=>$media_id,'action'=>$action));
		if( $tmp===false ) return false;
		$k = 'allowed.'.$media_id;
		return isset($tmp[$k]) && $tmp[$k]==='1';
	}

	public function UserRightsMediaGet( int $user_id, array $media_ids, int $action, bool $bool_result=false ) {
		$tmp = $this->_cmd('user rights media get',array('user_id'=>$user_id,'media_ids'=>implode(',',$media_ids),'action'=>$action));
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		if( $bool_result ) {
			foreach( $tmp as &$inf ) $inf = $inf['allowed']==='1';
		}
		return $tmp;
	}

	public function GroupRightsMediaGet( int $group_id, array $media_ids, int $action, bool $bool_result=false ) {
		$tmp = $this->_cmd('group rights media get',array('group_id'=>$group_id,'media_ids'=>implode(',',$media_ids),'action'=>$action));
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		if( $bool_result ) {
			foreach( $tmp as &$inf ) $inf = $inf['allowed']==='1';
		}
		return $tmp;
	}

	public function RightsCategoriesGet( array $cat_ids, int $action, bool $bool_result=false ) {
		$tmp = $this->_cmd('rights categories get',array('cat_ids'=>implode(',',$cat_ids),'action'=>$action));
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		if( $bool_result ) {
			foreach( $tmp as &$inf ) $inf = $inf['allowed']==='1';
		}
		return $tmp;
	}
	public function RightsListGet( $list_id, $offset, $count, int $action ) {
		$tmp = $this->_cmd('rights list get',array('list_id'=>$list_id,'offset'=>$offset,'count'=>$count,'action'=>$action));
		if( $tmp===false ) return false;
		if( isset($tmp['allowed']) ) $tmp['allowed'] = x_explode(',',$tmp['allowed']);
		return $tmp;
	}
	public function RightsListGet2( $list_id, $offset, $count, array $actions ) {
		$tmp = $this->_cmd('rights list get 2',array('list_id'=>$list_id,'offset'=>$offset,'count'=>$count,'actions'=>implode(',',$actions)));
		if( $tmp===false ) return false;
		$r = [];
		foreach( $tmp as $k=>$v ) {
			$k = explode('.',$k,2);
			if( $k[0]==='allowed' ) $r[$k[1]] = x_explode(',',$v);
		}
		return $r;
	}
	public function RightsTreeGet( int $action ) {
		$tmp = $this->_cmd('rights tree get',array('action'=>$action));
		if( $tmp===false ) return false;
		$tmp['categories'] = x_explode(',',$tmp['categories']);
		return $tmp;
	}


	public function ArchiveCreate( array $media_items, int $lifetime, string $intent='' ) {
		$media_ids = [];
		$dlf_ids = [];
		$filenames = [];
		foreach( $media_items as &$info ) {
			$media_ids[] = $info['media_id'];
			$dlf_ids[]   = $info['dlf_id'];
			$filenames[] = $info['filename'];
		}
		$options = array(	'media_ids'  => implode(',',$media_ids),
							'dlf_ids'    => implode(',',$dlf_ids),
							'filenames'  => implode(':',$filenames),
							'lifetime'   => $lifetime,
							'intent'     => $intent );
		return $this->_cmd('archive create',$options);
	}

	public function ArchiveReady( string $archive_id ) {
		return $this->_cmd('archive ready',array('archive_id'=>$archive_id));
	}

	/**
	 * Get data stream of an archive
	 *
	 * @param string $archive_id
	 * @param bool $stream
	 * @param string|null $filename
	 *
	 * @return array|false
	 */
	public function ArchiveGet( string $archive_id, bool $stream=true, ?string $filename=null ) {
		$options = array('archive_id'=>$archive_id);
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'archive get', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename);
	}

	public function DownloadFormatList( bool $with_options, ?int $filter_media_class=null ) {
		$tmp = $this->_cmd('download format list',array('options'=>$with_options?'1':''));
		if( $tmp===false ) return false;
		$r = [];
		$ktrans = array( 'mc'=>'media_class', 'so'=>'sort', 'aa'=>'acl_action', 'fo'=>'format', 'na'=>'name', 'fn'=>'filename' );
		foreach( $tmp as $k=>$v ) {
			$ka = explode('.',$k,3);										// xx.<dlf_id> or o.<dlf_id>.<opt_name>
			if( isset($ka[1]) ) {
				$dlf_id = $ka[1];
				if( $ka[0]==='o' ) $r[$dlf_id]['options'][$ka[2]] = $v;		// o.<dlf_id>.<opt_name>
				else $r[$dlf_id][$ktrans[$ka[0]]] = $v;						// xx.<dlf_id>
			}
		}
		foreach( $r as $k=>&$v ) {
			// add dlf_id in 'id' key
			$v['id'] = $k;
			// add empty options array, if it does not exist yet
			if( $with_options && !isset($v['options']) ) $v['options'] = [];
		}
		unset($v);
		if( $filter_media_class!==null ) {
			$r2 = [];
			foreach( $r as $k=>&$v ) {
				if( $v['media_class']==$filter_media_class ) $r2[$k] = $v;
			}
			//unset($v);
			return $r2;
		}
		return $r;
	}

	public function DownloadFormatsActionsList() {
		$tmp = $this->_cmd('download formats actions list');
		if( $tmp===false ) return false;
		return array_cast_to_int2(x_explode(',',$tmp['actions']));
	}
	public function DownloadFormatGet( int $dlf_id, bool $with_options ) {
		$tmp = $this->_cmd('download format get',array('dlf_id'=>$dlf_id,'options'=>$with_options?'1':''));
		if( $tmp===false ) return false;
		$r = [];
		foreach( $tmp as $k=>$v ) {
			$ka = explode('.',$k,2);
			if( isset($ka[1]) ) $r['options'][$ka[1]] = $v;
			else $r[$k] = $v;
		}
		return $r;
	}
	public function DownloadFormatAdd( array $dlf ) {
		$para = [];
		foreach( $dlf as $k=>$v ) {
			if( $k==='options' ) {
				if( is_array($v) ) {
					foreach( $v as $k2=>$v2 ) $para['o.'.$k2] = $v2;
				}
			} else {
				$para[$k] = $v;
			}
		}
		return $this->_cmd('download format add',$para);
	}
	public function DownloadFormatUpdate( int $dlf_id, array $dlf ) {
		$para = array( 'dlf_id'=>$dlf_id );
		foreach( $dlf as $k=>$v ) {
			if( $k==='options' ) {
				if( is_array($v) ) {
					foreach( $v as $k2=>$v2 ) $para['o.'.$k2] = $v2;
				}
			} else {
				$para[$k] = $v;
			}
		}
		return $this->_cmd('download format update',$para);
	}
	public function DownloadFormatDelete( int $dlf_id ) {
		return $this->_cmd('download format delete',array('dlf_id'=>$dlf_id));
	}


	public function ColorProfileList() {
		$tmp = $this->_cmd('color profile list');
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		uasort($tmp,function($a,$b){
			return strcoll($a['name'],$b['name']);
		});
		return $tmp;
	}
	public function ColorIntentList() {
		$tmp = $this->_cmd('color intent list');
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}



	public function PreviewFormatList( bool $with_options, int $filter_media_class=null ) {
		$tmp = $this->_cmd('preview format list',array('options'=>$with_options?'1':''));
		if( $tmp===false ) return false;
		$r = [];
		$ktrans = array( 'mc'=>'media_class', 'so'=>'sort', 'fo'=>'format', 'na'=>'name' );
		foreach( $tmp as $k=>$v ) {
			$ka = explode('.',$k,3);
			if( isset($ka[1]) ) {
				$pf_id = $ka[1];
				if( $ka[0]==='o' ) $r[$pf_id]['options'][$ka[2]] = $v;
				else $r[$pf_id][isset($ktrans[$ka[0]])?$ktrans[$ka[0]]:$ka[0]] = $v;
			}
		}
		foreach( $r as $k=>&$v ) {
			// add pf_id in 'id' key
			$v['id'] = $k;
			// add empty options array, if it does not exist yet
			if( $with_options && !isset($v['options']) ) $v['options'] = [];
		}
		unset($v);
		if( $filter_media_class!==null ) {
			$r2 = [];
			foreach( $r as $k=>&$v ) {
				if( $v['media_class']==$filter_media_class ) $r2[$k] = $v;
			}
			//unset($v);
			return $r2;
		}
		return $r;
	}
	public function PreviewFormatGet( int $pf_id, bool $with_options ) {
		$tmp = $this->_cmd('preview format get',array('pf_id'=>$pf_id,'options'=>$with_options?'1':''));
		if( $tmp===false ) return false;
		$r = [];
		foreach( $tmp as $k=>$v ) {
			$ka = explode('.',$k,2);
			if( isset($ka[1]) ) $r['options'][$ka[1]] = $v;
			else $r[$k] = $v;
		}
		return $r;
	}

	public function PreviewFormatAdd( array $pf ) {
		$para = [];
		foreach( $pf as $k=>$v ) {
			if( $k==='options' ) {
				if( is_array($v) ) {
					foreach( $v as $k2=>$v2 ) $para['o.'.$k2] = $v2;
				}
			} else {
				$para[$k] = $v;
			}
		}
		return $this->_cmd('preview format add',$para);
	}

	public function PreviewFormatUpdate( int $pf_id, array $pf ) {
		$para = array( 'pf_id'=>$pf_id );
		foreach( $pf as $k=>$v ) {
			if( $k==='options' ) {
				if( is_array($v) ) {
					foreach( $v as $k2=>$v2 ) $para['o.'.$k2] = $v2;
				}
			} else {
				$para[$k] = $v;
			}
		}
		return $this->_cmd('preview format update',$para);
	}

	public function PreviewFormatDelete( int $pf_id ) {
		return $this->_cmd('preview format delete',array('pf_id'=>$pf_id));
	}


	public function MediumPreviewFormatList( $media_id, bool $with_options=false, int $filter_media_class=null ) {
		$par = array('media_id'=>$media_id,'options'=>$with_options?'1':'');
		if( $filter_media_class!==null ) $par['flt_media_class'] = $filter_media_class;
		$tmp = $this->_cmd('medium preview format list',$par);
		if( $tmp===false ) return false;
		$ktrans = array(	'mc'=>'media_class', 'so'=>'sort', 'fs'=>'file_size', 'fo'=>'format', 'na'=>'name',
							'vw'=>'vid_width', 'vh'=>'vid_height', 'vd'=>'vid_duration', 'vf'=>'vid_framerate',
							'pw'=>'pic_width', 'ph'=>'pic_height', 'pt'=>'pic_time', 'pr'=>'pic_pre_rotated',
							'ad'=>'aud_duration', 'ac'=>'aud_channels', 'as'=>'aud_samplerate' );
		$r = [];
		if( $with_options ) {
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,3);
				if( isset($ka[1]) ) {
					$ka0 = $ka[0];
					if( $ka0==='o' ) $r[$ka[1]]['options'][$ka[2]] = $v;
					else if( $ka0==='e' ) $r[$ka[1]][$ka[2]] = $v;
					else $r[$ka[1]][isset($ktrans[$ka0])?$ktrans[$ka0]:$ka0] = $v;
				}
			}
			// add empty options array, if it does not exist yet
			foreach( $r as $k=>&$v ) {
				if( !isset($v['options']) ) $v['options'] = [];
			}
			unset($v);
		} else {
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,3);
				if( isset($ka[1]) ) {
					$ka0 = $ka[0];
					if( $ka0==='e' ) $r[$ka[1]][$ka[2]] = $v;
					else $r[$ka[1]][isset($ktrans[$ka0])?$ktrans[$ka0]:$ka0] = $v;
				}
			}
		}
		return $r;
	}

	public function MediaPreviewFormatList( array $media_ids, bool $with_options=false, int $filter_media_class=null ) {
		// mj 2.4+
		$par = array('media_ids'=>implode(',',$media_ids),'options'=>$with_options?'1':'');
		if( $filter_media_class!==null ) $par['flt_media_class'] = $filter_media_class;
		$tmp = $this->_cmd('media preview format list',$par);
		if( $tmp===false ) return false;
		$ktrans = array(	'mc'=>'media_class', 'so'=>'sort', 'fs'=>'file_size', 'fo'=>'format', 'na'=>'name',
							'vw'=>'vid_width', 'vh'=>'vid_height', 'vd'=>'vid_duration', 'vf'=>'vid_framerate',
							'pw'=>'pic_width', 'ph'=>'pic_height', 'pt'=>'pic_time',
							'ad'=>'aud_duration', 'ac'=>'aud_channels', 'as'=>'aud_samplerate' );
		$r = [];
		if( $with_options ) {
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,4);
				if( isset($ka[1]) ) {
					$ka0 = $ka[0];
					if( $ka0==='o' ) $r[$ka[2]][$ka[1]]['options'][$ka[3]] = $v;
					else if( $ka0==='e' ) $r[$ka[2]][$ka[1]][$ka[3]] = $v;
					else $r[$ka[2]][$ka[1]][isset($ktrans[$ka0])?$ktrans[$ka0]:$ka0] = $v;
				}
			}
			// add empty options array, if it does not exist yet
			foreach( $r as &$fs ) {
				foreach( $fs as $k=>&$v ) {
					if( !isset($v['options']) ) $v['options'] = [];
				}
				unset($v);
			}
			unset($fs);
		} else {
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,4);
				if( isset($ka[1]) ) {
					$ka0 = $ka[0];
					if( $ka0==='e' ) $r[$ka[2]][$ka[1]][$ka[3]] = $v;
					else $r[$ka[2]][$ka[1]][isset($ktrans[$ka0])?$ktrans[$ka0]:$ka0] = $v;
				}
			}
		}
		return $r;
	}

	public function MediumPreviewProgressGet( $media_id ) {
		$tmp = $this->_cmd('medium preview progress get',array('media_id'=>$media_id));
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function MediumPrioritizeProcessing( $media_id ) {
		return $this->_cmd('medium prioritize processing',array('media_id'=>$media_id));
	}

	/**
	 * @param int $cat_id
	 * @param string|null $child_name_match
	 * @param bool|string $compact_result	true, false or 'skip-mod-crd'
	 *
	 * @return array|false
	 */
	public function FolderCategoryList( int $cat_id, string $child_name_match=null, $compact_result=false ) {
		$par = array('cat_id'=>$cat_id,'compact_format'=>'1');
		if( $child_name_match!==null ) $par['child_name_match'] = $child_name_match;
		if( $compact_result==='skip-mod-crd' ) $par['skip_modified_and_created'] = '1';
		$tmp = $this->_cmd('folder category list',$par);
		if( $tmp===false ) return false;
		return self::_parse_dot_syntax_and_prepare_category_node_list($tmp,$compact_result);
	}

	/**
	 * @param int $cat_id
	 * @param bool|string $compact_result	true, false or 'skip-mod-crd'
	 *
	 * @return array|false
	 */
	public function FolderGet( int $cat_id, $compact_result=false ) {
		$tmp = $this->_cmd('folder get',array('cat_id'=>$cat_id,'compact_format'=>'1'));
		if( $compact_result==='skip-mod-crd' ) $par['skip_modified_and_created'] = '1';
		if( $tmp===false ) return false;
		$tmp1 = self::_parse_dot_syntax_and_prepare_category_node_list($tmp,$compact_result);
		return isset($tmp1[$cat_id]) ? $tmp1[$cat_id] : false;
	}

	/**
	 * @param int|array $cat_id		either single category id or array of category ids
	 * @param int $offset
	 * @param int $count
	 * @param array|null $meta_ids
	 *
	 * @return array|false
	 */
	public function FolderMediaList( $cat_id, int $offset, int $count, array $meta_ids=null ) {
		$par = array( 'offset'=>$offset, 'count'=>$count );
		if( is_array($cat_id) ) $par['cat_ids'] = implode(',',$cat_id);
		else $par['cat_id'] = $cat_id;
		if( is_array($meta_ids) && !empty($meta_ids) ) $par['meta_ids'] = implode(',',$meta_ids);
		$tmp = $this->_cmd( 'folder media list', $par );
		if( $tmp===false ) return false;
		// index media-ids by position in global list
		$mids = [];
		if( (int)$tmp['result_count'] > 0 ) {
			$idx = $offset;
			foreach( explode(',',$tmp['result']) as $id ) $mids[$idx++] = $id;
		}
		// meta-data
		$m = [];
		foreach( $tmp as $k=>$v ) {
			if( $k[0]==='m' && $k[1]==='.' ) {
				$x = explode('.',$k,4);
				$m[$x[1]][$x[2]][$x[3]] = $v;
			}
		}
		$this->_sort_metadata_values($m);
		return array(	'result_count' => $tmp['result_count'],
						'media_ids' => $mids,
						'meta' => $m );
	}

	public function FolderMediaGet( int $cat_id, string $filename, array $meta_ids=null ) {
		$par = array( 'cat_id'=>$cat_id, 'filename'=>$filename );
		if( is_array($meta_ids) && !empty($meta_ids) ) $par['meta_ids'] = implode(',',$meta_ids);
		$tmp = $this->_cmd( 'folder media get', $par );
		if( $tmp===false ) return false;
		$mids = [];
		if( (int)$tmp['result_count'] > 0 ) $mids = explode(',',$tmp['result']);
		// meta-data
		$m = [];
		foreach( $tmp as $k=>$v ) {
			if( $k[0]==='m' && $k[1]==='.' ) {
				$x = explode('.',$k,4);
				$m[$x[1]][$x[2]][$x[3]] = $v;
			}
		}
		$this->_sort_metadata_values($m);
		return array(	'result_count' => $tmp['result_count'],
						'media_ids' => $mids,
						'meta' => $m );
	}




	//--------------------------------------------------------------------------
	// VERSIONING COMMANDS
	//--------------------------------------------------------------------------

	public function VersionMediumListVersions( $media_id ) {
		$tmp = $this->_cmd('version medium list versions',array('media_id'=>$media_id));
		if( $tmp===false ) return false;
		if( isset($tmp['versions']) ) $tmp['versions'] = array_cast_to_int2(x_explode(',',$tmp['versions']));
		return $tmp;
	}

	/**
	 * Get content stream of a file in a specified version
	 *
	 * @param string|int $media_id
	 * @param int $version
	 * @param bool $stream
	 * @param string|null $filename
	 * @param array $options
	 *
	 * @return array|false
	 */
	public function VersionMediumGet( $media_id, int $version, bool $stream=true, string $filename=null, array $options=[] ) {
		$options['media_id'] = $media_id;
		$options['version'] = $version;
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'version medium get', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename);
	}

	/**
	 * Get XMP data stream of a file in a specified version
	 *
	 * @param string|int $media_id
	 * @param int $version
	 * @param bool $stream
	 * @param string|null $filename
	 * @param array $options
	 *
	 * @return array|false
	 */
	public function VersionMediumXmpGet( $media_id, int $version, bool $stream=true, string $filename=null, array $options=[] ) {
		$options['media_id'] = $media_id;
		$options['version'] = $version;
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'version medium xmp get', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename);
	}

	public function VersionMediumInfo( $media_id, int $version, $meta_ids=null ) {
		$p = array('media_id'=>$media_id,'version'=>$version);
		if( is_array($meta_ids) && !empty($meta_ids) ) $p['meta_ids'] = implode(',',$meta_ids);
		else if( $meta_ids!==null ) $p['meta_ids'] = $meta_ids;
		$tmp = $this->_cmd('version medium info',$p);
		if( $tmp===false ) return false;
		$m = [];
		$c = [];
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,3); // key parts
			if( $k[0]==='m' ) $m[$kp[1]][$kp[2]] = $v;
			else if( $k[0]==='c' ) $c[$kp[1]] = x_explode(',',$v);
		}
		$this->_sort_metadata_values1($m);
		return array('meta'=>$m,'categories'=>$c,'is_latest_version'=>isset($tmp['is_latest_version'])&&$tmp['is_latest_version']==='1');
	}

	/**
	 * Get content stream of a file in a specified version in a specific preview format
	 *
	 * @param array $options
	 * @param bool $stream
	 * @param string|null $filename
	 *
	 * @return array|false
	 */
	public function VersionMediumPreview( array $options, bool $stream=true, string $filename=null ) {
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'version medium preview', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename);
	}

	/**
	 * Get custom preview stream of a file in a specified version
	 *
	 * @param array $options
	 * @param bool $stream
	 * @param string|null $filename
	 *
	 * @return array|false
	 */
	public function VersionMediumCustomPreviewGet( array $options, bool $stream=true, string $filename=null ) {
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'version medium custom preview get', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename,60);
	}

	public function VersionMediumPromoteVersion( $media_id, int $version, string $comment=null ) {
		$para = array( 'media_id'=>$media_id, 'version'=>$version );
		if( $comment!==null ) $para['version_comment'] = $comment;
		$tmp = $this->_cmd( 'version medium promote version', $para );
		return $tmp===false ? false : true;
	}

	public function VersionMediumDimensions( $media_id, int $version, int $page, string $box=null ) {
		$opts = array('media_id'=>$media_id,'version'=>$version,'page'=>$page);
		if( $box!==null ) $opts['box'] = $box;
		return $this->_cmd( 'version medium dimensions', $opts );
	}


	public function VersionMediaStructureCompare( $media_id_a, $media_id_b, int $version_a, int $version_b, int $page_a, int $page_b, string $box_a=null, string $box_b=null, array $options=[] ) {
		$opts = array('media_id_a'=>$media_id_a,'media_id_b'=>$media_id_b,'version_a'=>$version_a,'version_b'=>$version_b,'page_a'=>$page_a,'page_b'=>$page_b,'box_a'=>$box_a,'box_b'=>$box_b) + $options;
		$tmp = $this->_cmd( 'version media structure compare', $opts );
		if( $tmp===false ) return false;
		// result structure: exactly as in MediaStructureCompare() !
		$result = array(
				'npages_different'	=> $tmp['dd.npages_different']==='1',
				'document_a'		=> array( 'npages'=>$tmp['dd.a.npages'] ),
				'document_b'		=> array( 'npages'=>$tmp['dd.b.npages'] ),
		);
		$page_diffs = [];
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,5); // key parts
			if( $kp[0]==='pd' ) { // pd.
				$pd_key = $kp[1]; // pd.<page_a>:<page_b>.
				if( !isset($page_diffs[$pd_key]) ) $page_diffs[$pd_key] = array('elem_diffs'=>[]);
				switch( $kp[2] ) {
				case 'media_box_different':
					$page_diffs[$pd_key]['media_box_different'] = $v==='1';
					break;
				case 'ref_box_different':
					$page_diffs[$pd_key]['ref_box_different'] = $v==='1';
					break;
				case 'ndifferences':
					$page_diffs[$pd_key]['ndifferences'] = $v;
					break;
				case 'a':
					$page_diffs[$pd_key]['info_a'][$kp[3]] = $kp[3]==='media_box' || $kp[3]==='ref_box' ? x_explode2floats(' ',$v) : $v;
					break;
				case 'b':
					$page_diffs[$pd_key]['info_b'][$kp[3]] = $kp[3]==='media_box' || $kp[3]==='ref_box' ? x_explode2floats(' ',$v) : $v;
					break;
				case 'ed':
					$page_diffs[$pd_key]['elem_diffs'][(int)$kp[3]][$kp[4]] = $kp[4]==='bbox_a' || $kp[4]==='bbox_b' ? x_explode2floats(' ',$v) : $v;
					break;
				}
			}
		}
		$result['page_diffs'] = $page_diffs;
		return $result;
	}

	//--------------------------------------------------------------------------
	// (ADVISORY) LOCK COMMANDS
	//--------------------------------------------------------------------------

	public function LockAcquire( $media_id ) {
		return $this->_cmd( 'lock acquire', array('media_id'=>$media_id) );
	}

	public function LockRelease( $media_id ) {
		return $this->_cmd( 'lock release', array('media_id'=>$media_id) )===false ? false : true;
	}

	public function LockGet( $media_id ) {
		return $this->_cmd( 'lock get', array('media_id'=>$media_id) );
	}

	public function LockReleaseAll() {
		return $this->_cmd( 'lock release all' )===false ? false : true;
	}

	//--------------------------------------------------------------------------
	// ADMIN COMMANDS
	//--------------------------------------------------------------------------

	public function GroupList() {
		$tmp = $this->_cmd('group list');
		if( $tmp===false ) return false;
		$groups = $this->_parse_dot_syntax($tmp);
		foreach( $groups as $k=>&$v ) {
			$v['roles'] = x_explode(',',$v['roles']);
			$v['group_id'] = $k;
		}
		//unset($v);
		ksort($groups);
		return $groups;
	}
	public function GroupAdd( string $name, array $roles ) {
		$roles = implode(',',$roles);
		return $this->_cmd('group add',array('name'=>$name,'roles'=>$roles));
	}
	public function GroupDelete( int $group_id ) {
		return $this->_cmd('group delete',array('group_id'=>$group_id));
	}
	public function GroupUpdate( int $group_id, string $name, array $roles ) {
		$roles = implode(',',$roles);
		return $this->_cmd('group update',array('group_id'=>$group_id,'name'=>$name,'roles'=>$roles));
	}


	/**
	 *
	 * @param array|null|true $userdata
	 * @param bool $with_roles
	 * @param array|null $selected_user_ids
	 * @param array|null $selected_group_ids=null
	 * @param bool|null $filter_active					null: dont filter; true: active users only; false: inactive users only
	 *
	 * @return array|false
	 */
	public function UserList( $userdata=null, bool $with_roles=false, ?array $selected_user_ids=null, ?array $selected_group_ids=null, ?bool $filter_active=null ) {
		$opts = [];
		if( $userdata!==null && $userdata!==false ) $opts['userdata'] = $userdata===true ? '1' : implode(',',$userdata);
		if( $with_roles===true ) $opts['with_roles'] = '1';
		if( $selected_user_ids!==null ) $opts['user_ids'] = implode(',',$selected_user_ids);
		if( $selected_group_ids!==null ) $opts['group_ids'] = implode(',',$selected_group_ids);
		$tmp = $this->_cmd('user list',$opts);
		if( $tmp===false ) return false;
		$users = $this->_parse_dot_syntax($tmp);
		foreach( $users as $k=>&$v ) {
			$v['active'] = $v['active']==='1';
			$v['session_temporary'] = $v['session_temporary']==='1';
			$v['groups'] = x_explode(',',(string)$v['groups']);
			$v['user_id'] = $k;
		}
		unset($v);
		if( $filter_active!==null ) {
			$tmp_users = $users;
			$users = [];
			foreach( $tmp_users as $k=>$v ) {
				if( $v['active']===$filter_active ) $users[$k] = $v;
			}
		}
		if( $with_roles===true ) {
			foreach( $users as $k=>&$v ) $v['roles'] = x_explode(',',$v['roles']);
			unset($v);
		}
		if( $userdata!==null ) {
			// login, last_login, groups, expires, active, userdata=>array(..)
			foreach( $users as $k=>&$v ) {
				$u = [];
				$ud = [];
				foreach( $v as $ik=>$iv ) {
					$t0 = explode(':',$ik,2);
					if( $t0[0]==='ud' ) $ud[$t0[1]] = $iv;
					else $u[$ik] = $iv;
					// if( strncmp($ik,'ud:',3)===0 ) $ud[substr($ik,3)] = $iv;
					// else $u[$ik] = $iv;
				}
				$u['userdata'] = $ud;
				$v = $u;
			}
			//unset($v);
		}
		return $users;
	}

	/**
	 * Retrieves list of users, or (when permissions disallow) retrieve at least a list containing the currently connected user.
	 *
	 * @param array|null|true $userdata
	 * @param bool $with_roles
	 * @param array|null $selected_user_ids
	 * @param array|null $selected_group_ids=null
	 *
	 * @return array
	 */
	public function UserListWithDefaultToSelf( $userdata=null, bool $with_roles=false, ?array $selected_user_ids=null, ?array $selected_group_ids=null ) : array {
		$users = $this->UserList($userdata,$with_roles,$selected_user_ids,$selected_group_ids);
		if( $users===false ) {
			$self = $this->UserGet($this->connected_user_id,$with_roles);
			if( $userdata!==null && $userdata!==false ) $self['userdata'] = $this->UserdataGet($this->connected_user_id,$userdata===true?null:$userdata);
			$users = [ $this->connected_user_id => $self ];
		}
		return $users;
	}

	/**
	 *
	 * @param string $fulltext
	 * @param bool $match_partial
	 * @param array|null|true $userdata
	 * @param bool $with_roles
	 * @param string|null $on_single_userdata
	 * @param bool $match_whole_userdata_field
	 *
	 * @return array|false
	 */
	public function UserSearch( string $fulltext, bool $match_partial=false, $userdata=null, bool $with_roles=false, ?string $on_single_userdata=null, bool $match_whole_userdata_field=false ) {
		$opts = array('fulltext'=>$fulltext);
		if( $match_partial===true ) $opts['match_partial'] = '1';
		if( $userdata!==null && $userdata!==false ) $opts['userdata'] = $userdata===true ? '1' : implode(',',$userdata);
		if( $with_roles===true ) $opts['with_roles'] = '1';
		if( $on_single_userdata!==null ) $opts['on_single_userdata'] = $on_single_userdata;
		if( $match_whole_userdata_field===true ) $opts['match_whole_userdata_field'] = '1';
		$tmp = $this->_cmd('user search',$opts);
		if( $tmp===false ) return false;
		$users = $this->_parse_dot_syntax($tmp);
		foreach( $users as $k=>&$v ) {
			$v['active'] = $v['active']==='1';
			$v['session_temporary'] = $v['session_temporary']==='1';
			$v['groups'] = x_explode(',',(string)$v['groups']);
			$v['user_id'] = $k;
		}
		//unset($v);
		if( $with_roles===true ) {
			foreach( $users as $k=>&$v ) $v['roles'] = x_explode(',',$v['roles']);
			//unset($v);
		}
		if( $userdata!==null ) {
			// login, last_login, groups, expires, active, userdata=>array(..)
			foreach( $users as $k=>&$v ) {
				$u = [];
				$ud = [];
				foreach( $v as $ik=>$iv ) {
					$t = explode(':',$ik,2);
					if( isset($t[1]) && $t[0]==='ud' ) $ud[$t[1]] = $iv;
					else $u[$ik] = $iv;
				}
				$u['userdata'] = $ud;
				$v = $u;
			}
			//unset($v);
		}
		if( $on_single_userdata!==null && version_compare($this->server_version,'4.0.58','<') ) {
			// compatibility stuff ..
			if( $match_partial===true || $match_whole_userdata_field!==true || ( $userdata===null || ( is_array($userdata) && !in_array('email',$userdata) ) ) ) {
				return $this->_error('sys/999','request not supported on server version and emulation not possible');
			}
			$users2 = [];
			$fv = mb_strtolower($fulltext,'UTF-8');
			foreach( $users as $k=>&$v ) {
				if( isset($v['userdata'][$on_single_userdata]) && mb_strtolower($v['userdata'][$on_single_userdata],'UTF-8')===$fv ) $users2[$k] = $v;
			}
			//unset($v);
			$users = $users2;
		}

		return $users;
	}
	public function UserGet( int $user_id, bool $with_roles=false ) {
		$user = $this->_cmd('user get',['user_id'=>$user_id]);
		if( $user===false ) return false;
		$user['active'] = $user['active']==='1';
		$user['session_temporary'] = $user['session_temporary']==='1';
		$user['groups'] = x_explode(',',$user['groups']);
		if( $with_roles ) $user['roles'] = x_explode(',',$user['roles']);
		return $user;
	}
	public function UserGetByLogin( string $login ) {
		$user = $this->_cmd('user get',['login'=>$login]);
		if( $user===false ) return false;
		$user['active'] = $user['active']==='1';
		$user['session_temporary'] = $user['session_temporary']==='1';
		$user['groups'] = x_explode(',',$user['groups']);
		return $user;
	}
	public function UserExists( int $user_id ) {
		return $this->_cmd('user exists',['user_id'=>$user_id]);
	}
	public function UserExistsByLogin( string $login ) {
		return $this->_cmd('user exists',['login'=>$login]);
	}
	public function UserExistsByUserdataValue( string $userdata_name, string $userdata_value ) {
		return $this->_cmd('user exists',array('userdata_name'=>$userdata_name,'userdata_value'=>$userdata_value));
	}
	public function UserAdd( string $login, string $pass, $expires, $groups, $active=true ) {
		$opts = array('login'=>$login,'pass'=>$pass,'expires'=>$expires,'active'=>$active?'1':'0','groups'=>implode(',',$groups));
		return $this->_cmd('user add',$opts);
	}
	public function UserDelete( int $user_id ) {
		return $this->_cmd('user delete',['user_id'=>$user_id]);
	}
	public function UserUpdate( int $user_id, string $login, $expires, $groups, $active ) {
		return $this->_cmd('user update',array('user_id'=>$user_id,'login'=>$login,'expires'=>$expires,'active'=>$active?'1':'0','groups'=>implode(',',$groups)));
	}
	public function UserPassword( int $user_id, string $pass ) {
		return $this->_cmd('user password',array('user_id'=>$user_id,'pass'=>$pass));
	}
	public function UserLoginCheck( string $login, string $pass ) {
		$tmp = $this->_cmd('user login check',array('user'=>$login,'pass'=>$pass));
		if( $tmp===false ) return false;
		return $tmp['user_id'];
	}
	public function UserSessionCreate( int $user_id, string $remote_host=null, int $remote_port=null, array $xinfo=null ) {
		$par = ['user_id'=>$user_id];
		if( $remote_host!==null ) $par['remote_host'] = $remote_host;
		if( $remote_port!==null ) $par['remote_port'] = $remote_port;
		if( $xinfo!==null ) {
			foreach( $xinfo as $k=>$v ) $par[$k] = $v;
		}
		return $this->_cmd('user session create',$par);
	}
	public function UserSessionCreateByLogin( string $login, string $remote_host=null, int $remote_port=null, array $xinfo=null ) {
		$par = ['login'=>$login];
		if( $remote_host!==null ) $par['remote_host'] = $remote_host;
		if( $remote_port!==null ) $par['remote_port'] = $remote_port;
		if( $xinfo!==null ) {
			foreach( $xinfo as $k=>$v ) $par[$k] = $v;
		}
		return $this->_cmd('user session create',$par);
	}
	public function UserLoginTokenCreate( int $user_id ) {
		return $this->_cmd('user login token create',['user_id'=>$user_id]);
	}
	public function UserLoginTokenCreateByLogin( string $login ) {
		return $this->_cmd('user login token create',['login'=>$login]);
	}

	public function UserAvatarUpload( int $user_id, string $filepath ) {
		$opts = ['user_id'=>$user_id];
		return $this->_upload_from_file_cmd('user avatar upload',$opts,$filepath);
	}
	public function UserAvatarRemove( int $user_id ) {
		return $this->_cmd('user avatar remove',['user_id'=>$user_id]);
	}
	public function UserAvatarInfo( int $user_id ) {
		return $this->_cmd('user avatar info',['user_id'=>$user_id]);
	}
	public function UserAvatarGet( array $options, bool $stream=true, string $filename=null ) {
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd('user avatar get',$options);
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename,60);
	}


	public function ACLItemList( int $acl_id=null, int $cat_id=null, $media_id=null ) {
		$p = [];
		if( $acl_id!==null ) $p['acl_id'] = $acl_id;
		else if( $cat_id!==null ) $p['cat_id'] = $cat_id;
		else if( $media_id!==null ) $p['media_id'] = $media_id;
		$tmp = $this->_cmd('acl item list',$p);
		if( $tmp===false ) return false;
		$acl_id = $tmp['acl_id'];
		unset($tmp['acl_id']);
		$acl_items = $this->_parse_dot_syntax($tmp);
		foreach( $acl_items as &$v ) $v['order'] = (int)$v['order'];
		unset($v);
		uasort($acl_items,function($a,$b){
			return $a['order']===$b['order'] ? 0 : ( $a['order']<$b['order'] ? -1 : +1 );
		});
		return array('acl_id'=>$acl_id,'acl_items'=>$acl_items);
	}
	public function ACLItemAdd( int $acl_id=null, int $cat_id=null, $media_id, int $apply_to_group_id=null, int $apply_to_user_id=null, bool $allow_deny, int $action ) {
		$p = [];
		if( $acl_id!==null ) $p['acl_id'] = $acl_id;
		else if( $cat_id!==null ) $p['cat_id'] = $cat_id;
		else if( $media_id!==null ) $p['media_id'] = $media_id;
		if( $apply_to_group_id ) $p['group_id'] = $apply_to_group_id;
		else if( $apply_to_user_id ) $p['user_id'] = $apply_to_user_id;
		$p['allow_deny'] = $allow_deny;
		$p['action'] = $action;
		return $this->_cmd('acl item add',$p);
	}
	public function ACLItemDelete( int $acl_item_id ) {
		return $this->_cmd('acl item delete',array('acl_item_id'=>$acl_item_id));
	}
	public function ACLItemMove( int $acl_item_id, int $after_id, int $target_acl_id ) {
		return $this->_cmd('acl item move',array('acl_item_id'=>$acl_item_id,'after_id'=>$after_id,'target_acl_id'=>$target_acl_id));
	}
	public function ACLDelete( int $acl_id=null, int $cat_id=null, $media_id=null ) {
		$p = [];
		if( $acl_id!==null ) $p['acl_id'] = $acl_id;
		else if( $cat_id!==null ) $p['cat_id'] = $cat_id;
		else if( $media_id!==null ) $p['media_id'] = $media_id;
		return $this->_cmd('acl delete',$p);
	}
	public function ACLCopy( $from_media_id, array $to_media_ids ) {
		return $this->_cmd('acl copy', array('from_media_id'=>$from_media_id,'to_media_ids'=>implode(',',$to_media_ids)) );
	}

	public function TreeAdd( string $name, bool $visibility, bool $multifiling, bool $conjunct_mode=false ) {
		return $this->_cmd('tree add',array('name'=>$name,'visibility'=>$visibility,'multifiling'=>$multifiling,'conjunct_mode'=>$conjunct_mode));
	}
	public function TreeDelete( int $tree_id ) {
		return $this->_cmd('tree delete',array('tree_id'=>$tree_id));
	}
	public function TreeUpdate( int $tree_id, string $name, bool $visibility, bool $multifiling, bool $conjunct_mode=false ) {
		return $this->_cmd('tree update',array('tree_id'=>$tree_id,'name'=>$name,'visibility'=>$visibility,'multifiling'=>$multifiling,'conjunct_mode'=>$conjunct_mode));
	}
	public function TreeList() {
		$tmp = $this->_cmd('tree list');
		if( $tmp===false ) return false;
		$tmp1 = $this->_parse_dot_syntax($tmp);
		foreach( $tmp1 as $k=>&$v ) {
			$v['tree_id'] = $k;
		}
		//unset($v);
		ksort($tmp1);
		return $tmp1;
	}

	public function CategoryAdd( string $name, int $tree_id, int $parent_id, int $after_id, bool $sort_alphabetically_into_parent=false ) {
		$p = array('name'=>$name,'tree_id'=>$tree_id,'parent_id'=>$parent_id,'after_id'=>$after_id);
		// note: $after_id=0 -> sort as first node in parent, $after_id=-1 -> sort as last node in parent, every other $after_id must be a valid category id
		if( $sort_alphabetically_into_parent ) $p['insert_alphabetically_sorted'] = '1';
		return $this->_cmd('category add',$p);
	}
	public function CategoryDelete( int $cat_id ) {
		return $this->_cmd('category delete',array('cat_id'=>$cat_id));
	}
	public function CategoryUpdate( int $cat_id, string $name, bool $sort_alphabetically_into_parent=false ) {
		$p = array('cat_id'=>$cat_id,'name'=>$name);
		if( $sort_alphabetically_into_parent ) $p['insert_alphabetically_sorted'] = '1';
		return $this->_cmd('category update',$p);
	}
	public function CategoryMove( int $cat_id, int $parent_id, int $after_id, bool $sort_alphabetically_into_parent=false ) {
		$p = array('cat_id'=>$cat_id,'parent_id'=>$parent_id,'after_id'=>$after_id);
		// note: $after_id=0 -> sort as first node in parent, $after_id=-1 -> sort as last node in parent, every other $after_id must be a valid category id
		if( $sort_alphabetically_into_parent ) $p['insert_alphabetically_sorted'] = '1';
		return $this->_cmd('category move',$p);
	}
	public function CategorySort( int $cat_id, bool $recursive=false ) {
		return $this->_cmd('category sort',array('cat_id'=>$cat_id,'recursive'=>$recursive?'1':'0'));
	}

	/**
	 * @param int $tree_id
	 * @param bool $dont_filter
	 * @param bool|string $compact_result	true, false or 'skip-mod-crd'
	 *
	 * @return array|false
	 */
	public function CategoryList( int $tree_id, bool $dont_filter=false, $compact_result=false ) {
		$p = array('tree_id'=>$tree_id,'compact_format'=>'1');
		if( $dont_filter ) $p['dont_filter'] = '1';
		if( $compact_result==='skip-mod-crd' ) $par['skip_modified_and_created'] = '1';
		$tmp = $this->_cmd('category list',$p);
		if( $tmp===false ) return false;
		return self::_parse_dot_syntax_and_prepare_category_node_list($tmp,$compact_result);
	}

	/**
	 * @param int $tree_id
	 * @param int $cat_id
	 *
	 * @return array|false
	 */
	public function CategoryDescendantsList( int $tree_id, int $cat_id ) {
		$p = array('tree_id'=>$tree_id,'cat_id'=>$cat_id);
		$tmp = $this->_cmd('category descendants list',$p);
		if( $tmp===false ) return false;
		return x_explode(',',$tmp['descendants']);
	}

	/**
	 * @param int $cat_id
	 * @param bool|string $compact_result	true, false or 'skip-mod-crd'
	 *
	 * @return array|false
	 */
	public function CategoryParentsList( int $cat_id, $compact_result=false ) {
		$p = array('cat_id'=>$cat_id,'compact_format'=>'1');
		if( $compact_result==='skip-mod-crd' ) $par['skip_modified_and_created'] = '1';
		$tmp = $this->_cmd('category parents list',$p);
		if( $tmp===false ) return false;
		return self::_parse_dot_syntax_and_prepare_category_node_list($tmp,$compact_result);
	}

	public function CategoryCustomPreviewFromMedium( int $cat_id, $media_id ) {
		return $this->_cmd( 'category custom preview from medium', array( 'cat_id'=>$cat_id, 'media_id'=>$media_id ) );
	}
	public function CategoryCustomPreviewUpload( int $cat_id, string $filepath ) {
		$opts = array( 'cat_id'=>$cat_id );
		return $this->_upload_from_file_cmd( 'category custom preview upload', $opts, $filepath );
	}
	public function CategoryCustomPreviewRemove( int $cat_id ) {
		return $this->_cmd( 'category custom preview remove', array( 'cat_id'=>$cat_id ) );
	}
	public function CategoryCustomPreviewInfo( int $cat_id ) {
		return $this->_cmd( 'category custom preview info', array( 'cat_id'=>$cat_id ) );
	}
	public function CategoryCustomPreviewGet( array $options, bool $stream=true, string $filename=null ) {
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'category custom preview get', $options );
		return !$stream ? $tmp : $this->_stream_handle_response( $tmp,$filename,60);
	}

	public function LogGet( $offset, $count, string $tz, array $filter=null ) {
		$para = array('offset'=>$offset,'count'=>$count,'tz'=>$tz);
		if( $filter!==null ) {
			if( isset($filter['date']) && $filter['date']!=='' ) $para['flt_date'] = $filter['date'];
			if( isset($filter['date_from']) && $filter['date_from']!=='' ) $para['flt_date_from'] = $filter['date_from'];
			if( isset($filter['date_to']) && $filter['date_to']!=='' ) $para['flt_date_to'] = $filter['date_to'];
			if( isset($filter['entity_type']) && $filter['entity_type']!=='' ) $para['flt_entity_type'] = $filter['entity_type'];
			if( isset($filter['entity_id']) && $filter['entity_id']!=='' ) $para['flt_entity_id'] = $filter['entity_id'];
			if( isset($filter['action']) && $filter['action']!=='' ) $para['flt_action'] = $filter['action'];
			if( isset($filter['user_type']) && $filter['user_type']!=='' ) $para['flt_user_type'] = $filter['user_type'];
			if( isset($filter['user_id']) && $filter['user_id']!=='' ) $para['flt_user_id'] = $filter['user_id'];
			if( isset($filter['client_id']) && $filter['client_id']!=='' ) $para['flt_client_id'] = $filter['client_id'];
			if( isset($filter['client_ids']) ) $para['flt_client_ids'] = is_array($filter['client_ids']) ? implode(',',$filter['client_ids']) : $filter['client_ids'];
			if( isset($filter['fulltext_pattern']) && $filter['fulltext_pattern']!=='' ) $para['flt_fulltext_pattern'] = $filter['fulltext_pattern'];
			if( isset($filter['fulltext_match_partial']) && $filter['fulltext_match_partial'] ) $para['flt_fulltext_match_partial'] = '1';
		}
		$tmp = $this->_cmd('log get',$para);
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		$ktrans = array(	'lid'=>'id', 'ltm'=>'ltime',
							'cid'=>'client_id',
							'uid'=>'user_id', 'unm'=>'user_name',
							'isu'=>'impersonate_source_user_id', 'iss'=>'impersonate_source_session_id',
							'sid'=>'session_id',
							'act'=>'action', 'etp'=>'entity_type', 'ent'=>'entity',
							'det'=>'details' );
		$entries = [];
		foreach( $tmp as $e ) {
			$o = [];
			foreach( $ktrans as $k1=>$k2 ) $o[$k2] = trim($e[$k1]);
			$entries[] = $o;
		}
		return $entries;
	}

	public function LogClientsList() {
		$tmp = $this->_cmd( 'log clients list' );
		if( $tmp===false ) return false;
		return $this->_parse_numeric_result( $tmp );
	}

	public function LogStats( string $tz ) {
		$para = array('tz'=>$tz);
		$tmp = $this->_cmd('log stats',$para);
		if( $tmp===false ) return false;
		$r = array(
			'h' => $this->_extract_dot_list('h',$tmp),
			'cmin' => $this->_extract_dot_list('cmin',$tmp),
			'cmax' => $this->_extract_dot_list('cmax',$tmp),
		);
		return $r;
	}

	public function LogCustomAdd( int $action, array $para ) {
		$para['action'] = $action;
		return $this->_cmd('log custom add',$para);
	}



	public function ExportChannelAdd( string $name, string $target_url, bool $recursive_dirs, int $recursive_dirs_tree_id=1, int $acl_action=0 ) {
		$para = array('name'=>$name,'target_url'=>$target_url,'recursive_dirs'=>$recursive_dirs,'recursive_dirs_tree_id'=>$recursive_dirs_tree_id,'acl_action'=>$acl_action);
		return $this->_cmd('export channel add',$para);
	}
	public function ExportChannelUpdate( int $channel_id, string $name, string $target_url, bool $recursive_dirs, int $recursive_dirs_tree_id=1, int $acl_action=0 ) {
		$para = array('channel_id'=>$channel_id,'name'=>$name,'target_url'=>$target_url,'recursive_dirs'=>$recursive_dirs,'recursive_dirs_tree_id'=>$recursive_dirs_tree_id,'acl_action'=>$acl_action);
		return $this->_cmd('export channel update',$para);
	}
	public function ExportChannelDelete( int $channel_id ) {
		return $this->_cmd('export channel delete',array('channel_id'=>$channel_id));
	}

	public function ExportQueueAddAllMedia( int $channel_id, string $filename_schema ) {
		return $this->_cmd('export queue add all media',array('channel_id'=>$channel_id,'filename_schema'=>$filename_schema));
	}
	public function ExportQueueInfo() {
		return $this->_cmd('export queue info');
	}
	public function ExportQueueCancel() {
		return $this->_cmd('export queue cancel');
	}

	public function ExportLogGet( int $count, int $channel_id=null, $geq_ltime=null, $leq_ltime=null, $since_log_id=null, int $flt_success=null ) {
		$para = [];
		if( $count!==null ) $para['count'] = $count;
		if( $channel_id!==null ) $para['channel_id'] = $channel_id;
		if( $geq_ltime!==null ) $para['geq_ltime'] = $geq_ltime;
		if( $leq_ltime!==null ) $para['leq_ltime'] = $leq_ltime;
		if( $since_log_id!==null ) $para['since_log_id'] = $since_log_id;
		if( $flt_success!==null ) $para['flt_success'] = $flt_success;
		$tmp = $this->_cmd('export log get',$para);
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function ExportChannelsList() {
		$tmp = $this->_cmd('export channels list');
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function ExportQueueAddMedia( $channel_id, array $media_ids, array $formats, array $filenames ) {
		return $this->_cmd('export queue add media',array('channel_id'=>$channel_id,'media_ids'=>implode(',',$media_ids),'formats'=>implode(',',$formats),'filenames'=>implode(':',$filenames)));
	}





	public function WatermarkAdd( array $opts=[], string $filepath=null ) {
		if( $filepath!==null ) {
			$tmp = $this->_upload_from_file_cmd('watermark add',$opts,$filepath);
		} else {
			$tmp = $this->_cmd('watermark add',$opts);
		}
		if( $tmp===false || !isset($tmp['wm_id']) ) return false;
		return $tmp['wm_id'];
	}

	public function WatermarkUpdate( int $wm_id, array $opts=[], string $filepath=null ) {
		$opts['wm_id'] = $wm_id;
		if( $filepath!==null ) {
			return $this->_upload_from_file_cmd('watermark update',$opts,$filepath);
		}
		return $this->_cmd('watermark update',$opts);
	}

	public function WatermarkDelete( int $wm_id ) {
		return $this->_cmd('watermark delete',array('wm_id'=>$wm_id));
	}

	public function WatermarkGet( int $wm_id ) {
		return $this->_cmd('watermark get',array('wm_id'=>$wm_id));
	}

	public function WatermarkPreviewGet( int $wm_id, array $options, bool $stream=true, string $filename=null ) {
		$options['wm_id'] = $wm_id;
		if( $stream ) $this->_stream_parse_request($options);
		$tmp = $this->_cmd( 'watermark preview get', $options );
		return !$stream ? $tmp : $this->_stream_handle_response($tmp,$filename,60);
	}

	public function WatermarksList() {
		$tmp = $this->_cmd('watermarks list');
		if( $tmp===false ) return false;
		return $this->_extract_dot_list('name',$tmp);
	}


	public function CategoryWatermarkGet( int $cat_id ) {
		return $this->_cmd('category watermark get',array('cat_id'=>$cat_id));
	}

	public function CategoryWatermarkSet( int $cat_id, int $wm_id, string $wm_rule ) {
		return $this->_cmd('category watermark set',array('cat_id'=>$cat_id,'wm_id'=>$wm_id,'wm_rule'=>$wm_rule));
	}



	public function BulkPermissionsMediaInfo( array $media_ids, array $actions, int $group_id=0, int $user_id=0 ) {
		$para = array( 'media_ids'=>implode(',',$media_ids), 'actions'=>implode(',',$actions) );
		if( $group_id ) $para['group_id'] = $group_id;
		if( $user_id ) $para['user_id'] = $user_id;
		$tmp = $this->_cmd('bulk permissions media info',$para);
		if( $tmp===false ) return false;
		$result = [];
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,4); // key parts
			if( !isset($kp[3]) ) continue;
			$action = $kp[1];
			$skey = $kp[2];
			$media_id = $kp[3];
			if( $skey==='apply_to' ) {
				$v = explode('.',$v,2);
			} else if( $skey==='allow_deny' ) {
				$v = $v==='1';
			}
			if( $kp[0]==='i' ) {
				// inherited (=permission by acl on category or some parent category of media item)
				$result[$action][$media_id]['inherited'][$skey] = $v;
			} else if( $kp[0]==='e' ) {
				// explicit (=permission by acl on media item)
				$result[$action][$media_id]['explicit'][$skey] = $v;
			}
		}
		return $result;
	}


	public function BulkPermissionsMediaApply( array $rules, int $action, int $group_id=0, int $user_id=0 ) {
		$para = array( 'action'=>$action );
		if( $group_id ) $para['group_id'] = $group_id;
		if( $user_id ) $para['user_id'] = $user_id;
		foreach( $rules as $media_id=>$rule ) $para['r.'.$media_id] = $rule;
		return $this->_cmd('bulk permissions media apply',$para);
	}


	//--------------------------------------------------------------------------
	// ADMIN/MAINTENANCE COMMANDS
	//--------------------------------------------------------------------------

	public function MaintenancePostImport( array $media_ids=null, bool $post_delayed ) {
		$para = array( 'post_delayed'=>$post_delayed?'1':'0' );
		if( $media_ids!==null ) $para['media_ids'] = implode(',',$media_ids);
		return $this->_cmd('maintenance post import',$para);
	}

	public function MaintenanceReImport( array $media_ids=null ) {
		$para = [];
		if( $media_ids!==null ) $para['media_ids'] = implode(',',$media_ids);
		return $this->_cmd('maintenance re import',$para);
	}

	public function MaintenanceMediaUpdate( array $media_ids=null, bool $post_delayed ) {
		$para = array( 'post_delayed'=>$post_delayed?'1':'0' );
		if( $media_ids!==null ) $para['media_ids'] = implode(',',$media_ids);
		return $this->_cmd('maintenance media update',$para);
	}



	public function MaintenanceJobQueuesInfo() {
		return $this->_cmd('maintenance job queues info');
	}

	public function MaintenanceAuthElevatePrivileges() {
		return $this->_cmd('maintenance auth elevate privileges');
	}


	public function MaintenanceStorageInfo( bool $dump_deleted_media_assets=true, bool $dump_restore_parameters=true, bool $dump_paths=true ) {
		$para = [];
		$para['dump_deleted_media_assets'] = $dump_deleted_media_assets ? '1' : '0';
		$para['dump_restore_parameters'] = $dump_restore_parameters ? '1' : '0';
		$para['dump_paths'] = $dump_paths ? '1' : '0';
		$tmp = $this->_cmd('maintenance storage info',$para);
		if( $tmp===false ) return false;
		if( $dump_restore_parameters ) {
			unset($tmp['visit_media_count']);
			unset($tmp['visit_deleted_count']);
			return $this->_parse_dot_syntax($tmp);
		}
		return $this->_parse_dot_syntax($tmp);
	}

	public function MaintenanceStorageRestore( $media_id, int $timestamp=0 ) {
		$tmp = $this->_cmd('maintenance storage restore',['media_id'=>$media_id,'timestamp'=>$timestamp]);
		if( $tmp===false ) return false;
		return $tmp;
	}

	public function MaintenanceStorageDelete( $media_id, int $timestamp=0 ) {
		$tmp = $this->_cmd('maintenance storage delete',['media_id'=>$media_id,'timestamp'=>$timestamp]);
		if( $tmp===false ) return false;
		return $tmp;
	}


	//--------------------------------------------------------------------------
	// OBJECT COMMANDS
	//--------------------------------------------------------------------------

	public function ObjectGet( string $object_type, int $object_id ) {
		$tmp = $this->_cmd( 'object get', array('type'=>$object_type,'object_id'=>$object_id) );
		if( $tmp===false ) return false;
		if( isset($tmp['attributes']) ) {
			$tmp['attributes'] = json_decode($tmp['attributes'],true);
			$tmp = array_merge($tmp, $tmp['attributes']);
		}
		$tmp['object_id'] = $object_id;
		$tmp['list_id'] = $object_id; // for compatibility with mj<=4.0
		return $tmp;
	}

	public function ObjectList( string $object_type, bool $json_compatible=false, $listfilter='', string $relation_type='shared' ) {
		$tmp = $this->_cmd( 'object list', array('type'=>$object_type,'relation'=>$relation_type) );
		if( $tmp===false ) return false;
		$user_id = $this->GetConnectedUserId();
		if( !is_array($listfilter) ) $listfilter = array($listfilter);
		$output = [];
		foreach( $this->_parse_dot_syntax($tmp) as $object_id=>$object ) {
			$object['object_id'] = $object_id;
			$object['list_id'] = $object_id; // for compatibility with mj<=4.0
			if( isset($object['attributes']) ) {
				$object['attributes'] = json_decode($object['attributes'],true);
				$object = array_merge($object, $object['attributes']);
			}
			if( in_array('own',$listfilter) && $object['owner_id']==$user_id ) {
				$output[$object_id] = $object;
			} else if( in_array('relation',$listfilter) && $object['owner_id']!=$user_id && !$object['is_public'] ) {
				$output[$object_id] = $object;
			} else if( in_array('public',$listfilter) && $object['is_public'] ) {
				$output[$object_id] = $object;
			} else if( in_array('',$listfilter) ) {
				$output[$object_id] = $object;
			}
		}
		uasort($output,function($a,$b){
			return strcoll($a['sort_key'],$b['sort_key']);
		});
		return $json_compatible ? array_values($output) : $output;
	}

	public function ObjectListByLogin( string $object_type, string $login, bool $json_compatible=false, string $style='combined', string $relation_type='shared' ) {
		$tmp = $this->_cmd( 'object list', array( 'login'=>$login, 'type'=>$object_type, 'relation'=>$relation_type, 'style'=>$style ) );
		if( $tmp===false ) return false;
		$output = [];
		foreach( $this->_parse_dot_syntax($tmp) as $object_id=>$object ) {
			$object['object_id'] = $object_id;
			$object['list_id'] = $object_id; // for compatibility with mj<=4.0
			if( isset($object['attributes']) ) {
				$object['attributes'] = json_decode($object['attributes'],true);
				$object = array_merge($object,$object['attributes']);
			}
			$output[$object_id] = $object;
		}
		uasort($output,function($a,$b){
			return strcoll($a['sort_key'],$b['sort_key']);
		});
		return $json_compatible ? array_values($output) : $output;
	}

	public function ObjectCreate( string $object_type, $attributes, ?string $sort_key=null, bool $is_public=false ) {
		$p = array( 'type'=>$object_type );
		if( is_array($attributes) ) $p['attributes'] = mj_json_encode($attributes,true);
		else if( $attributes!==null ) $p['attributes'] = $attributes;
		if( $sort_key!==null ) $p['sort_key'] = $sort_key;
		$p['is_public'] = $is_public?'1':'';
		return $this->_cmd( 'object create', $p );
	}

	public function ObjectUpdate( string $object_type, int $object_id, $attributes, bool $merge_attributes, ?string $sort_key=null, ?bool $is_public=null ) {
		$p = array( 'type'=>$object_type, 'object_id'=>$object_id );
		if( is_array($attributes) ) $p['attributes'] = mj_json_encode($attributes,true);
		else if( $attributes!==null ) $p['attributes'] = $attributes;
		if( $merge_attributes ) $p['merge_attributes'] = '1';
		if( $sort_key!==null ) $p['sort_key'] = $sort_key;
		if( $is_public!==null ) $p['is_public'] = $is_public?'1':'';
		return $this->_cmd( 'object update', $p );
	}

	public function ObjectDelete( string $object_type, int $object_id ) {
		return $this->_cmd( 'object delete', array( 'type'=>$object_type, 'object_id'=>$object_id ));
	}


	public function ObjectItemsInsert( string $object_type, int $object_id, array $items, $pos=-1 ) {
		$item_list = [];
		foreach( $items as $item ) $item_list[] = mj_json_encode($item);
		return $this->_cmd( 'object items insert', array('type'=>$object_type,'object_id'=>$object_id,'items'=>implode("\n",$item_list),'pos'=>$pos) );
	}

	public function ObjectItemsRemove( string $object_type, int $object_id, array $items, string $item_key ) {
		return $this->_cmd( 'object items remove', array('type'=>$object_type,'object_id'=>$object_id,'item_key'=>$item_key,'items'=>implode("\n", $items)));
	}

	/**
	 * compatibility alias to ObjectItemsList
	 */
	public function ObjectListGet( string $object_type, int $object_id, string $item_key, $offset, $count, $meta_ids, bool $filter_deleted=false, bool $with_categories=false, bool $with_visibility_status=false ) {
		return $this->ObjectItemsList($object_type,$object_id,$item_key,$offset,$count,$meta_ids,$filter_deleted,$with_categories,$with_visibility_status);
	}

	public function ObjectItemsList( string $object_type, int $object_id, string $item_key, $offset, $count, $meta_ids, bool $filter_deleted=false, bool $with_categories=false, bool $with_visibility_status=false ) {
		$parameters = array( 'type'=>$object_type, 'item_key'=>$item_key, 'object_id'=>$object_id, 'offset'=>$offset, 'count'=>$count );
		if( $with_categories ) $parameters['with_categories'] = '1';
		if( $with_visibility_status ) $par['with_visibility_status'] = '1';
		if( $filter_deleted ) {
			if( is_array($meta_ids) ) $meta_ids[] = -1;
			else $meta_ids = array(-1);
		}
		if( is_array($meta_ids) && isset($meta_ids[0]) ) $parameters['meta_ids'] = implode(',',array_keys(array_flip($meta_ids)));
		$tmp = $this->_cmd( 'object items list', $parameters );
		if( $tmp===false ) return false;
		// index media-ids by position in global list
		$mids = [];
		if( (int)$tmp['result_count'] > 0 ) {
			$idx = $offset;
			foreach( explode("\n",$tmp['result']) as $item ) {
				$decoded_item = json_decode($item,true) ?? [];
				$mids[$idx++] = $decoded_item[$item_key];
			}
		}
		$meta = [];
		$categories = [];
		if( $meta_ids!==null && $with_categories ) {
			// meta-data & categories
			foreach( $tmp as $k=>$v ) {
				if( $k[1]==='.' ) {
					if( $k[0]==='m' ) {
						$x = explode('.',$k,4);
						$meta[$x[1]][$x[2]][$x[3]] = $v;
					} else if( $k[0]==='c' ) {
						$x = explode('.',$k,3);
						$categories[$x[1]][$x[2]] = strpos($v,',')===false ? $v : x_explode(',',$v);
					}
				}
			}
		} else if( $meta_ids!==null ) {
			// meta-data
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='m' && $k[1]==='.' ) {
					$x = explode('.',$k,4);
					$meta[$x[1]][$x[2]][$x[3]] = $v;
				}
			}
		} else if( $with_categories ) {
			// categories
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='c' && $k[1]==='.' ) {
					$x = explode('.',$k,3);
					$categories[$x[1]][$x[2]] = x_explode(',',$v);
				}
			}
		}
		$visibility_status = [];
		if( $with_visibility_status ) {
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='v' && $k[1]==='s' && $k[2]==='.' ) {
					// $x = explode('.',$k,2);
					// $visibility_status[$x[1]] = $v;
					$x1 = substr($k,3);
					$visibility_status[$x1] = $v;
				}
			}
		}
		if( $filter_deleted ) {
			$filtered_mids = [];
			foreach( $mids as $mid ) if( isset($meta[$mid]) ) $filtered_mids[] = $mid;
			$mids = $filtered_mids;
		}
		return array(	'result_count' => $tmp['result_count'],
						$item_key.'s' => $mids,
						'meta' => $meta,
						'categories' => $categories,
						'visibility_status' => $visibility_status );
	}


	public function ObjectRelationsList( string $object_type, int $object_id, string $relation_type ) {
		$p = array('type'=>$object_type, 'object_id'=>$object_id, 'relation'=>$relation_type);
		$tmp = $this->_cmd( 'object relations list', $p);
		if( $tmp===false ) return false;
		foreach( $tmp as $user_id=>&$options ) {
			// $tmp[$user_id] = x_explode(',',$options);
			$options = x_explode(',',$options);
		}
		unset($options);
		return $tmp;
	}

	public function ObjectRelationGet( int $object_id, string $relation_type, int $user_id=null ) {
		$p = array('object_id'=>$object_id, 'relation'=>$relation_type);
		if( $user_id!==null ) $p['user_id'] = $user_id;
		$tmp = $this->_cmd( 'object relation get', $p );
		if( $tmp===false ) return false;
		if( isset($tmp['options']) ) $tmp['options'] = x_explode(',',$tmp['options']);
		return $tmp;
	}

	public function ObjectRelationExists( int $object_id, string $relation_type, int $user_id=null, string $option_name=null ) {
		$tmp = $this->ObjectRelationGet($object_id,$relation_type,$user_id);
		if( $tmp===false ) return false;
		if( ! $tmp['exists'] ) return false;
		if( $option_name===null ) return true;
		return in_array($option_name,$tmp['options']);
	}

	public function ObjectRelationsDelete( int $object_id, string $relation_type, int $user_id=null ) {
		$p = array('object_id'=>$object_id, 'relation'=>$relation_type);
		if( $user_id!==null ) $p['user_id'] = $user_id;
		return $this->_cmd( 'object relations delete', $p);
	}
	public function ObjectRelationSet( int $object_id, string $relation_type, int $user_id, array $options ) {
		$p = array( 'object_id'=>$object_id, 'relation'=>$relation_type, 'user_id'=>$user_id, 'options'=>implode(',',$options) );
		return $this->_cmd( 'object relation set', $p);
	}
	public function ObjectRelationsSet( int $object_id, string $relation_type, array $relation_items ) {
		$p = array('object_id'=>$object_id, 'relation'=>$relation_type);
		foreach( $relation_items as $user_id => $options ) {
			$p['user_id'] = $user_id;
			$p['options'] = implode(',',$options);
			$this->_cmd( 'object relation set', $p);
		}
	}

	public function _ObjectLightboxSend_Impl( bool $second_variant, int $rcpt_user_id, ?int $list_id, int $expires_offset=0, ?string $new_list_title=null, ?array $media_ids=null, ?array $acl_actions=null ) {
		$p = array('user_id'=>$rcpt_user_id);
		if( $list_id!==null ) $p['list_id'] = $list_id;
		if( $expires_offset!==0 ) $p['expires'] = $expires_offset;

		if( $acl_actions!==null ) {
			if( $second_variant ) {
				foreach( $acl_actions as $media_id=>$actions ) $p['acl_action.'.$media_id] = implode(',',$actions);
			} else {
				$p['acl_actions'] = implode(',',$acl_actions);
			}
		}

		if( $media_ids!==null ) $p['media_ids'] = implode(',',$media_ids);

		if( $new_list_title!==null ) {
			$attributes = array( 'title'=>$new_list_title, 'note'=>'' );
			$p['attributes'] = mj_json_encode($attributes,true);
		}
		//$p['sort_key'] = $new_list_title;
		return $this->_cmd( $second_variant ? 'object lightbox send 2' : 'object lightbox send', $p );
	}


	public function ObjectLightboxSend2( int $rcpt_user_id, ?int $list_id, int $expires_offset=0, ?string $new_list_title=null, ?array $media_ids=null, ?array $acl_actions=null ) {
		return $this->_ObjectLightboxSend_Impl(true,$rcpt_user_id,$list_id,$expires_offset,$new_list_title,$media_ids,$acl_actions);
	}

	public function ObjectLightboxSend( int $rcpt_user_id, ?int $list_id, ?string $new_list_title, ?array $media_ids, ?array $acl_actions ) {
		return $this->_ObjectLightboxSend_Impl(false,$rcpt_user_id,$list_id,0,$new_list_title,$media_ids,$acl_actions);
	}


	public function ObjectLightboxSendGuest2( string $email, string $first_name, string $last_name, string $pass, int $expires_offset, string $new_list_title, array $media_ids, ?array $acl_actions ) {
		$p = array('email'=>$email,'first_name'=>$first_name,'last_name'=>$last_name,'pass'=>$pass);

		if( $expires_offset!==0 ) $p['expires'] = $expires_offset;

		if( $acl_actions!==null ) {
			foreach( $acl_actions as $media_id=>$actions ) $p['acl_action.'.$media_id] = implode(',',$actions);
		}

		// TODO: remove duplicate media_ids & item_list !!!
		$p['media_ids'] = implode(',',$media_ids);
		$item_list = [];
		foreach( $media_ids as $media_id ) $item_list[] = mj_json_encode(array('media_id'=>$media_id),true);
		$p['item_list'] = implode("\n",$item_list);

		$attributes = array( 'title'=>$new_list_title, 'note'=>'' );
		$p['attributes'] = mj_json_encode($attributes,true);

		$p['sort_key'] = $new_list_title;
		return $this->_cmd( 'object lightbox send guest 2', $p );
	}



	/**** start compatibility functions ****/
	public function ObjectLightboxListGet( int $object_id, $offset, $count, $meta_ids, bool $filter_deleted=false, bool $with_categories=false, bool $with_visibility_status=false ) {
		return $this->ObjectItemsList('lightbox',$object_id,'media_id',$offset,$count,$meta_ids,$filter_deleted,$with_categories,$with_visibility_status);
	}
	public function ObjectLightboxList( bool $json_compatible=false ) {
		return $this->ObjectList('lightbox',$json_compatible);
	}
	/**** end compatibility functions ****/










	//--------------------------------------------------------------------------
	// INTERNALS
	//--------------------------------------------------------------------------

	/**
	 * @param string $buffer
	 *
	 * @return int|null
	 */
	private function _write( string $buffer ) : ?int {
		$len = strlen($buffer);
		if( ($written=@fwrite($this->fp,$buffer,$len))===false ) return null;
		while( $written < $len ) {
			if( ($w=@fwrite($this->fp,substr($buffer,$written),$len-$written))===false || $w===0 ) return null;
			$written += $w;
		}
		return $written;
	}

	/**
	 * Send command and parse result, sets error states
	 *
	 * @param string $cmd
	 * @param array|null $para
	 * @param resource|null $binary_fp
	 *
	 * @return array|false
	 */
	public function _cmd( string $cmd, ?array $para=null, $binary_fp=null ) {
		if( $this->stream_state!=='idle' ) return $this->_cmd_error('pro/3');

		// build command
		$cmdstring = $cmd."\n__xr=2\n";
		if( $para!==null ) {
			foreach( $para as $k=>$p ) $cmdstring .= $k.'='.addcslashes((string)$p,"\n\\")."\n";
		}

		// send command
		//$this->_protocol_log('SEND',$cmdstring);
		if( $this->_write($cmdstring."\n")===null ) return $this->_cmd_error('pro/2');

		if( $binary_fp!==null ) {
			// send input data stream
			//$this->_protocol_log('SEND','<binary data>');
			$chunk_size = 16384;
			if( $para!==null && isset($para['payload']) && ($payload_size=$para['payload'])>0 ) {
				if( $payload_size > 200*1024*1024 ) $chunk_size *= 8;
				else if( $payload_size > 100*1024*1024 ) $chunk_size *= 4;
				else if( $payload_size > 50*1024*1024 ) $chunk_size *= 2;
			}
			while( !feof($binary_fp) && ($buf=fread($binary_fp,$chunk_size))!=='' ) {
				if( $buf===false ) return $this->_cmd_error('pro/10');
				if( $this->_write($buf)===null ) return $this->_cmd_error('pro/9');
			}
		}

		// get result status (max result status: "json o 18446744073709551616\n" = 27+1+1)
		if( ($line=stream_get_line($this->fp,31,"\n"))===false ) return $this->_cmd_error('pro/4'); // communication error

		// NOTE: empty string from stream_get_line() => server dropped connection before writing result:
		// if( $line==='' ) return $this->_cmd_error('pro/4'); // communication error

		$result = [];
		if( strncmp($line,'json ',5)===0 ) {
			// mj2.4++ "JSON" response format ("json o <response_size>\n" or "json e <response_size>\n")
			if( ($size=(int)substr($line,7))>0 ) {
				$raw = stream_get_contents($this->fp,$size);
				$tmp_result = json_decode($raw,true,2,JSON_BIGINT_AS_STRING);
				if( !is_array($tmp_result) ) return $this->_error('manja/2','unknown error from manja server');
				$result = $tmp_result;
			}
			if( $line[5]==='o' ) {

				// valid result:
				return $result;
			}
			// error
			if( isset($result['info']) ) return $this->_error('manja/1',$result['info']); // with details ...
			// without details ...
			return $this->_error('manja/2','unknown error from manja server');
		} else if( $line==='ok' ) {
			// standard response format ("ok\n", followed by "key=value\n" lines, terminated by "\n\n" line)
			// valid result
			for( $i=0; $i<2147000000; ++$i ) { // omit infinite loops
				if( ($line=fgets($this->fp))===false ) return $this->_cmd_error('pro/4'); // communication error
				if( !isset($line[0]) ) return $this->_cmd_error('pro/4'); // communication error
				//$this->_protocol_log('RCV2',$line);
				if( $line[0]==="\n" ) break; // empty line = end-of-result
				$x = explode('=',$line,2);
				$result[$x[0]] = stripcslashes(substr($x[1],0,-1));
			}

			// valid result:
			return $result;
		}

		// standard error reponse format ("error\n", followed by "key=value\n" lines, terminated by "\n\n" line)
		$error_code = 'manja/2';
		$error_string = 'unknown error from manja server';
		for( $i=0; $i<2147000000; ++$i ) { // omit infinite loops
			if( ($line=fgets($this->fp))===false ) return $this->_cmd_error('pro/4'); // communication error
			if( !isset($line[0]) ) return $this->_cmd_error('pro/4'); // communication error
			if( $line[0]==="\n" ) break; // empty line = end-of-result
			if( strncmp($line,'info=',5)===0 ) {
				// with details ...
				$error_code = 'manja/1';
				$error_string = stripcslashes( @substr($line,5,-1) );
			}
		}
		return $this->_error($error_code,$error_string);
	}


	/**
	 * Send command with payload from stream. Parses result, sets error states.
	 *
	 * @param string $cmd
	 * @param array $para
	 * @param resource|null $stream			input stream resource or null
	 * @param int $size						input stream size or -1 when size is unknown
	 *
	 * @return array|false
	 */
	public function _upload_from_stream_cmd( string $cmd, array $para, $stream, int $size ) {
		if( $size >= 0 ) {
			// filesize is known -> upload directly from stream
			$para['payload'] = $size;
			if( $stream===null && $size!==0 ) return $this->_error('fs/4','invalid input (size>0 but no stream provided)');
			return $this->_cmd($cmd,$para,$stream);
		}
		// unknown filesize => but filesize is required => write stream contents to tempfile first
		if( $this->cfg_server_stream_timeout < 3600 ) stream_set_timeout($this->fp,3600); // set large timeout on socket.. (but notice: server-side may also have a timeout..)
		if( $this->_cmd('noop')===false ) return false; // .. to avoid timeout
		if( ($tmp_fp=tmpfile())===false ) return $this->_error('fs/3','failed to create temporary file');
		// read from stream and write to temp file, while avoiding timeouts on manja server connection..
		$size = 0;
		while( !feof($stream) ) {
			if( $this->_cmd('noop')===false ) return false; // .. to avoid timeout
			if( ($d=fread($stream,2*16384))===false ) {
				$m = stream_get_meta_data($stream);
				return $this->_error('http/1','failed to read input stream'.($m['timed_out']?' (timeout)':''));
			}
			if( !isset($d[0]) ) break; // reached EOF
			$l = strlen($d);
			if( fwrite($tmp_fp,$d,$l)===false ) {
				@fclose($tmp_fp);
				return $this->_error('fs/2','failed writing to temporary file');
			}
			$size += $l;
		}
		$para['payload'] = $size;
		fseek($tmp_fp,0);
		$tmp = $this->_cmd($cmd,$para,$tmp_fp);
		fclose($tmp_fp);
		if( $this->cfg_server_stream_timeout < 3600 ) stream_set_timeout($this->fp,$this->cfg_server_stream_timeout); // restore timeout on socket
		return $tmp;
	}


	/**
	 * Send command with payload from file path.
	 *
	 * @param string $cmd
	 * @param array $para
	 * @param string $filepath
	 *
	 * @return array|false
	 */
	public function _upload_from_file_cmd( string $cmd, array $para, string $filepath ) {
		$para['payload'] = filesize($filepath);
		if( ($binary_fp=fopen($filepath,'rb'))===false ) return $this->_error('fs/1','could not read input file');
		$tmp = $this->_cmd($cmd,$para,$binary_fp);
		fclose($binary_fp);
		return $tmp;
	}



	/**
	 * Set error state
	 *
	 * @param string $error_code
	 *
	 * @return bool		always false
	 */
	private function _cmd_error( string $error_code ) : bool {
		$error_string = '';
		switch( $error_code ) {
		case 'pro/2':  $error_string = 'connection interrupted while writing'; break;
		case 'pro/3':  $error_string = 'not connected'; break;
		case 'pro/4':  $error_string = 'connection interrupted while reading'; break;
		case 'pro/9':  $error_string = 'connection interrupted while writing binary data'; break;
		case 'pro/10': $error_string = 'connection interrupted while reading binary data input stream'; break;
		}
		if( $this->fp!==null && $this->fp!==false ) {
			$m = stream_get_meta_data($this->fp);
			if( $m['timed_out'] ) $error_string .= ' (timeout)';
		}
		return $this->_error($error_code,$error_string,false,true);
	}


	/**
	 *
	 * @param array $options
	 */
	private function _stream_parse_request( array &$options ) {
		// for best streaming experience - i.e. smooth inter-op on client-facing side of things (including the internet with all kinds of proxies and caches),
		// the manja server should terminate its connection after it sent its payload stream. for variable sized payloads, there is no mechanism for the client
		// to determine end of a payload stream. though for fixed size payloads its possible:
		//   TODO:  could be improved (e.g. keep connections alive on fixed size payloads, close only on variable sized payloads).
		// HOWEVER: "close after payload" is perfect fit for classic "connection-less" style of HTTP/1.0 & HTTP/1.1
		//          such change may be sensible for HTTP/2 ...

		// instruct server to close connection after send:
		$options['exit'] = '1';

		// parse http conditionals ..

		// If-Modified-Since: HTTP-date
		// https://tools.ietf.org/html/rfc2616#section-14.25
		if( ($if_modified_since=mjHttpUtil::GetIfModifiedSinceHeaderValue())!==null && $if_modified_since>0 ) $options['if_modified_since'] = $if_modified_since;

		// If-None-Match: "*" | 1#entity-tag
		// https://tools.ietf.org/html/rfc2616#section-14.26
		if( ($if_none_match=mjHttpUtil::GetIfNoneMatchHeaderTags())!==null ) $options['if_none_match'] = $if_none_match===true ? '*' : implode(',',$if_none_match);

		// parse http byte ranges ..
		// translate FIRST range into manja protocol payload_range_start+_end parameters
		// (NOTE: manja protocol )
		if( isset($_SERVER['HTTP_RANGE']) ) {
			if( !preg_match( '/^bytes=\d*-\d*(,\d*-\d*)*$/', $_SERVER['HTTP_RANGE'] ) ) {
				mjHttpUtil::SendResponseHeader(416);
				header( 'Content-Range: bytes */*' ); /*..*/
				exit;
			}

			// also parse conditional If-Range: entity-tag | HTTP-date
			// https://tools.ietf.org/html/rfc2616#section-14.27
			if( ($if_range=mjHttpUtil::GetIfRangeHeaderValue())!==null ) $options['if_range'] = $if_range;

			foreach( explode(',',substr($_SERVER['HTTP_RANGE'],6)) as $range ) {
				$parts = explode('-',$range,2);
				if( !isset($parts[0][0]) ) {
					// e.g.: "Range: -99" = range specifies the LAST 99 bytes from file.
					$start = -intval($parts[1],10);
					$end = 0;
				} else {
					$start = intval($parts[0],10);
					$end = isset($parts[1][0]) ? intval($parts[1],10) : -1;
				}
				if( $start>$end && $end!==-1 ) {
					mjHttpUtil::SendResponseHeader(416);
					header( 'Content-Range: bytes */*' ); /*..*/
					exit;
				}
				$options['payload_range_start'] = $start;
				$options['payload_range_end'] = $end;
				return;
			}
		}
	}


	/**
	 * Get payload as string.
	 *
	 * @param array $result
	 * @param bool $reconnect
	 *
	 * @return string|false
	 */
	public function get_payload( array $result, bool $reconnect=false ) {
		$size = isset($result['payload']) ? (int)$result['payload'] : -1;
		$r = '';
		if( $size!==-1 ) {
			// read from streams with known size ...
			$r = stream_get_contents($this->fp,$size);
			// connection remains valid!
		} else {
			// stream media with unknown size - this allows a safe connection abort
			// save & invalidate current connection..
			$old_fp = $this->fp;
			$this->fp = null;
			$this->stream_state = 'none';
			// read payload..
			$prv_iua = ignore_user_abort(true);
			while( !connection_aborted() ) {
				//if( feof($old_fp) ) break; // success, done
				if( ($d=fread($old_fp,16436))===false ) return $this->_cmd_error('pro/4');
				if( !isset($d[0]) ) break; // reached EOF
				$r .= $d;
			}
			fclose($old_fp);
			ignore_user_abort(!!$prv_iua);
			// re-connect, when required
			if( $reconnect ) $this->_reconnect();
		}
		return $r;
	}

	/**
	 * Get payload as stream.
	 *
	 * @param array $result
	 * @param bool $reconnect
	 *
	 * @return resource|null
	 */
	public function get_stream( array $result, bool $reconnect=false ) {
		// save & invalidate current connection..
		$old_fp = $this->fp;
		$this->fp = null;
		$this->stream_state = 'none';
		if( $reconnect ) $this->_reconnect();
		return $old_fp;
	}

	private function _reconnect() {
		if( $this->fp===null ) {
			$this->Connect();
			if( $this->connected_ssl_active ) $this->SSL($this->connected_ssl_ctx_opts);
			if( $this->connected_session_id===null ) {
				$r = $this->connected_user_relogin_info;
				assert( is_array($r) );
				$this->Login($r['username'],$r['password'],$r['remote_host'],$r['remote_port'],$r['login_token']);
			} else {
				$this->SessionResume($this->connected_session_id,true,true);
			}
		}
	}

	/**
	 * Skip payload and return number of bytes skipped
	 *
	 * @param array $result
	 *
	 * @return int|false
	 */
	public function skip_payload( array $result ) {
		$size = isset($result['payload']) ? (int)$result['payload'] : -1;
		$nskipped = 0;
		if( $size!==-1 ) {
			// slow path for persistent sockets & streams with known size
			$x = $size < 16436 ? $size : 16436;
			while( $x>0 && ($d=fread($this->fp,$x))!==false ) {
				$xs = strlen($d);
				$size -= $xs;
				$nskipped += $xs;
				$x = $size < 16436 ? $size : 16436;
			}
		} else {
			// stream media with unknown size - this allows a safe connection abort
			$prv_iua = ignore_user_abort( true );
			while( !connection_aborted() ) {
				if( feof($this->fp) ) break; // success, done
				if( ($d=fread($this->fp,16436))===false ) return $this->_cmd_error('pro/4');
				if( !isset($d[0]) ) break; // reached EOF
				$nskipped += strlen($d);
			}
			fclose( $this->fp );
			$this->fp = null;
			$this->stream_state = 'none';
			ignore_user_abort(!!$prv_iua);
		}
		return $nskipped;
	}

	/**
	 * Stream payload result of query at manja server back to requesting client
	 *
	 * @param array|false $result		response from request on manja server
	 * @param string|null $filename		optional filename of stream (if set a content-disposition: attachment header will be sent)
	 * @param int|null $def_max_age		optional max-age time in seconds for cache-control and expires headers
	 *
	 * @return array|false
	 */
	private function _stream_handle_response( $result, ?string $filename=null, int $def_max_age=null ) {
		if( $result===false ) return false;

		$has_payload = isset($result['payload']);
		$payload_omitted_by_conditional = !$has_payload && isset($result['payload_omitted']) && $result['payload_omitted']==='1';
		if( !$has_payload && !$payload_omitted_by_conditional ) {
			//???//throw new mjError('logic error in server communication protocol: client should earlier have been detected, that the response is done, and not eventually followed by stream...');
			//???
			return $result;
		}

		$payload_volatile = isset($result['payload_volatile']) && $result['payload_volatile']==='1';
		$payload_expiry = isset($result['payload_expiry']) ? (int)$result['payload_expiry'] : 0;
		if( $def_max_age===null ) $def_max_age = $payload_volatile ? 60*60 : 1*86400;	// volatile -> 60min, nonvolatile -> 1d
		header_remove( 'Set-Cookie' ); // avoid sending cookies with files
		mjHttpUtil::SendCacheControlHeaders(
			'private',
			$def_max_age,
			isset($result['mtime']) && isset($result['mtime'][0]) ? intval($result['mtime'],10) : null,
			$payload_expiry>0 ? $payload_expiry : null,
			isset($result['cache_tag']) && isset($result['cache_tag'][0]) ? $result['cache_tag'] : null );

		if( $payload_omitted_by_conditional ) {
			// no payload - because of conditional cache request
			mjHttpUtil::SendResponseHeader(304);
			$result['_payload_streamed'] = true;
			return $result;
		}

		if( !$has_payload ) {
			// no payload - other reasons
			return $result;
		}

		if( isset($result['ctype']) && isset($result['ctype'][0]) ) {
			header( 'Content-Type: '.$result['ctype'] );
		}
		if( $filename!==null ) mjHttpUtil::SendFilenameHeaders($filename);
		if( isset($result['content_duration']) ) {
			// add content duration headers (for audio/video pseudo streaming)
			header( 'X-Content-Duration: '.$result['content_duration'] );
			header( 'Content-Duration: '.$result['content_duration'] );
		}
		$payload = isset($result['payload']) ? (int)$result['payload'] : -1;
		if( isset($result['payload_range_start']) ) {
			// byte range delivery
			$range_start = (int)$result['payload_range_start'];
			$range_end   = (int)$result['payload_range_end'];
			$range_size  = $range_end - $range_start + 1;
			if( $payload!==-1 && $range_start >= $payload ) {
				// manja clipped resulting range: so, we explicitly handle any remaining "range not satisfiable" cases here
				header( 'Accept-Ranges: '.($payload_volatile?'none':'bytes'), true, 416 );
				header( 'Content-Range: bytes *'.'/'.$payload );
				header( 'Content-Length: 0' );
				$result['_payload_streamed'] = true;
				return $result;
			}
			if( $payload!==-1 && $range_start===0 && $range_size===$payload ) {
				// requested partial, but result is the whole content
				header( 'Accept-Ranges: '.($payload_volatile?'none':'bytes'), true, 200 );
				header( 'Content-Length: '.$payload );
			} else {
				header( 'Accept-Ranges: '.($payload_volatile?'none':'bytes'), true, 206 );
				header( 'Content-Range: bytes '.$range_start.'-'.$range_end.'/'.($payload===-1?'*':$payload) );
				header( 'Content-Length: ' . $range_size );
				$payload = $range_size;
			}
		} else if( $payload!==-1 ) {
			// send whole stream: fixed size
			header( 'Accept-Ranges: '.($payload_volatile?'none':'bytes') );
			header( 'Content-Length: '.$payload );
		} else {
			// send whole stream: variable size, also disable further range requests
			header( 'Accept-Ranges: none' );
		}
		if( $payload===-1 ) {
			// stream media with unknown size - send in chunks, enable safe connection abort
			$prv_iua = (bool)ignore_user_abort(true);
			$bytes_seen = 0;
			while( !connection_aborted() ) {
				if( feof($this->fp) ) break; // success, done
				if( ($d=fread($this->fp,16436))===false ) {
					return $this->_cmd_error('pro/4'); // read error
				}
				if( !isset($d[0]) ) {
					break; // end of stream
				}
				$bytes_seen += strlen($d);
				echo $d;
				flush();
			}
			ignore_user_abort($prv_iua);
		} else {
			// fast path for all other cases
			fpassthru( $this->fp );
		}
		fclose( $this->fp );
		$this->fp = null;
		$this->stream_state = 'none';
		$result['_payload_streamed'] = true;
		return $result;
	}


	/**
	 * Error trap - function may display error message and die or just return - depending on throw_or_die_on_error setting
	 * - returns false (for simplified chaining, e.g. "return $this->_error()")
	 *
	 * @param string $code
	 * @param string $string
	 * @param bool $disconnect
	 * @param bool $set_stream_state
	 *
	 * @return bool		returns always false
	 */
	private function _error( string $code, string $string, bool $disconnect=false, bool $set_stream_state=false ) : bool {
		if( $set_stream_state ) $this->stream_state = 'error';
		$this->error_code = $code;
		$this->error_string = $string;
		if( $disconnect ) $this->Disconnect();

		//$this->_protocol_log('ERRR',$this->error_code.': '.$this->error_string);
		if( $this->error_callback!==null ) {
			if( is_array($this->error_callback) ) {
				$obj =& $this->error_callback[0];
				$fn = $this->error_callback[1];
				if( !method_exists($obj,$fn) ) {
					echo $this->error_code.': '.$this->error_string;
					$this->Disconnect();
					echo 'unknown error handling method '.get_class($obj).'::'.$fn.'()';
					exit;
				}
				$obj->$fn($this->throw_or_die_on_error,$this->error_code,$this->error_string);
			} else {
				$fn = $this->error_callback;
				$fn($this->throw_or_die_on_error,$this->error_code,$this->error_string);
			}
		}
		if( $this->throw_or_die_on_error ) {
			if( $this->error_callback===null ) echo $this->error_code.': '.$this->error_string;
			$this->Disconnect();
			exit;
		}
		return false;
	}


	//--------------------------------------------------------------------------
	// PRIVATE UTILITIES
	//--------------------------------------------------------------------------

	/***
	private function _protocol_log( string $type, string $str ) {
		$fn = __DIR__.'/../../../var/log/mj_protocol.log';
		switch( $type ) {
		case 'RCV2':
			// dont log
			//break;
		case 'SEND':
		case 'RCV1':
		case 'ERRR':
		default:
			file_put_contents($fn,date('Y-m-d H:i:s').': '.$type.' '.rtrim($str)."\n",FILE_APPEND|LOCK_EX);
		}
	}
	***/

	private function _parse_dot_syntax( array $sresult ) : array {
		$r = [];
		foreach( $sresult as $k=>$v ) {
			// "key.id=value" => r[id][key] = value
			$t = explode('.',$k,2);
			if( isset($t[1]) ) $r[$t[1]][$t[0]] = $v;
		}
		return $r;
	}


	/**
	 * get array, where every contiguous data-member is marked with an index eg data1[$index], data2[$index]
	 *
	 * @return array
	 */
	private function _parse_index_syntax( array $sresult ) : array {
		$r = [];
		foreach( $sresult as $key => $value ) {
			$t = explode('[',$key,2);
			if( isset($t[1]) ) {
				$index = substr($t[1], 0, -1);
				if( !isset($r[$index]) ) $r[$index] = [];
				$r[$index][$t[0]] = $value;
			}
		}
		return $r;
	}


	private function _parse_dot_syntax2( array $sresult, array $int_keys ) : array {
		//ManjaAppContext::LogMsg(Logger::INFO,'pds2',$sresult);
		$r = [];
		$id = null;
		$ref = null;
		foreach( $sresult as $k=>$v ) {
			// "key.id=value" => r[id][key] = value
			$t = explode('.',$k,2);
			if( !isset($t[1]) ) continue;
			if( $t[1]!==$id ) {
				$id = $t[1];
				$r[$id] = [];
				$ref = &$r[$id];
			}
			$ref[$t[0]] = isset($int_keys[$t[0]]) ? (int)$v : $v;
		}
		return $r;
	}

	private function _extract_dot_list( string $pfx, array $sresult ) : array {
		$r = [];
		foreach( $sresult as $k=>$v ) {
			// "<pfx>.id=value" => r[id] = value
			$t = explode('.',$k,2);
			if( isset($t[1]) && $t[0]===$pfx ) $r[$t[1]] = $v;
		}
		return $r;
	}

	private function _parse_numeric_result( array $sresult ) : array {
		$r = [];
		foreach( $sresult as $k=>$v ) $r[(int)$k]=$v;
		ksort($r,SORT_NUMERIC);
		return $r;
	}

	// $m = array( meta_id => array(v,v,v,..), ... )
	private function _sort_metadata_values1( array &$mm ) {
		foreach( $mm as &$values ) ksort($values,SORT_NUMERIC);
		//unset($values);
	}

	// $m = array( media_id => array( meta_id => array(v,v,v,..), ... ), ... )
	private function _sort_metadata_values( array &$m ) {
		foreach( $m as &$mm ) {
			foreach( $mm as &$values ) ksort($values,SORT_NUMERIC);
			unset($values);
		}
		//unset($mm);
	}

	/**
	 * @param array $sresult
	 * @param bool|string $compact_result	true, false or 'skip-mod-crd'
	 *
	 * @return array
	 */
	private static function _parse_dot_syntax_and_prepare_category_node_list( array $sresult, $compact_result ) : array {
		$r = [];
		$p_n0 = 0;
		$p_n1 = 0;
		$p_n2p1 = 1;
		$p_n3p1 = 1;
		$n = null;

		if( $compact_result==='skip-mod-crd' ) {

			foreach( $sresult as $k=>$v ) {

				// "key.id=value" => r[id][key] = value
				$t = explode('.',$k,2);
				if( !isset($t[1]) ) continue;
				/*	node = array(	0 => cat_id,
									1 => parent,
									2 => left,
									3 => right - left,
									4 => name
				) */
				if( $t[0]==='plrn' ) {
					$v = explode(',',$v,4);
					if( ($cat_id=(int)$t[1])!==$p_n0 ) {
						$n = isset($r[$cat_id]) ? $r[$cat_id] : [$cat_id,0,0,0,''];
					} // else: keep $n[] from last iteration, and supplement ..
					$n[1] = (int)$v[0];
					$n[2] = (int)$v[1];
					$n[3] = (int)$v[2];
					$n[4] = $v[3];
				} else {
					continue; // skip other keys and code
				}

				if( $cat_id!==$p_n0 ) {
					// delta coding for "parent":
					//  parent===parent of previous node    =>  0
					//  parent===id of previous node        => -1
					$c_n1 = $n[1];
					if( $c_n1!==1 && $c_n1===$p_n1 ) $n[1] = 0;
					else if( $c_n1===$p_n0 ) $n[1] = -1;
					$p_n0 = $cat_id;
					$p_n1 = $c_n1;
					// delta coding for "left":
					//  left===previous nodes left  + 1     => -1
					//  left===previous nodes right + 1		=>  0
					$c_n2 = $n[2];
					if( $c_n2===$p_n2p1 ) $n[2] = -1;
					else if( $c_n2===$p_n3p1 ) $n[2] = 0;
					$p_n2p1 = $c_n2+1;
					// delta coding for "right":
					//  right = right - left
					$c_n3 = $n[3];
					$n[3] = $c_n3 - $c_n2;
					$p_n3p1 = $c_n3+1;
				}

				$r[$cat_id] = $n;
			}

		} else if( $compact_result ) {

			foreach( $sresult as $k=>$v ) {

				// "key.id=value" => r[id][key] = value
				$t = explode('.',$k,2);
				if( !isset($t[1]) ) continue;
				/*	node = array(	0 => cat_id,
									1 => parent,
									2 => left,
									3 => right - left,
									4 => name,
									5 => crd_dt,		(optional)
									6 => crd_by,		(optional)
									7 => mod_dt,		(optional)
									8 => mod_by			(optional)
				) */
				if( ($cat_id=(int)$t[1])!==$p_n0 ) {
					$n = isset($r[$cat_id]) ? $r[$cat_id] : [$cat_id,0,0,0,'','',''];
				} // else: keep $n[] from last iteration, and supplement ..

				switch( $t[0] ) {
				case 'plrn':									// = parent, left, right, name
					$v = explode(',',$v,4);
					$n[1] = (int)$v[0];
					$n[2] = (int)$v[1];
					$n[3] = (int)$v[2];
					$n[4] = $v[3];
					break;
				case 'crmd':									// = crd_dt, mod_dt, crd_by
					$v = explode(',',$v,3);
					$v0 = substr($v[0],0,19);					// cut-off milliseconds ... YYYY-MM-DD HH:mm:ss
					$v1 = substr($v[1],0,19);					// cut-off milliseconds ... YYYY-MM-DD HH:mm:ss
					$n[5] = $v0;
					$n[6] = $v[2];
					if( $v0!==$v1 ) $n[7] = $v1;
					break;
				case 'crd':										// = crd_dt, crd_by
					$v = explode(',',$v,2);
					$n[5] = substr($v[0],0,19);					// cut-off milliseconds ... YYYY-MM-DD HH:mm:ss
					$n[6] = $v[1];
					break;
				case 'mod':										// = mod_dt, mod_by
					$v = explode(',',$v,2);
					$v0 = substr($v[0],0,19);					// cut-off milliseconds ... YYYY-MM-DD HH:mm:ss
					if( $n[5]===0 || $n[5]!==$v0 ) $n[7] = $v0;
					if( $n[6]===0 || $n[6]!==$v[1] ) {
						if( !isset($n[7]) ) $n[7] = 0;			// fill gap
						$n[8] = $v[1];
					}
					break;
				}

				if( $cat_id!==$p_n0 ) {
					// delta coding for "parent":
					//  parent===parent of previous node    =>  0
					//  parent===id of previous node        => -1
					$c_n1 = $n[1];
					if( $c_n1!==1 && $c_n1===$p_n1 ) $n[1] = 0;
					else if( $c_n1===$p_n0 ) $n[1] = -1;
					$p_n0 = $cat_id;
					$p_n1 = $c_n1;
					// delta coding for "left":
					//  left===previous nodes left  + 1     => -1
					//  left===previous nodes right + 1		=>  0
					$c_n2 = $n[2];
					if( $c_n2===$p_n2p1 ) $n[2] = -1;
					else if( $c_n2===$p_n3p1 ) $n[2] = 0;
					$p_n2p1 = $c_n2+1;
					// delta coding for "right":
					//  right = right - left
					$c_n3 = $n[3];
					$n[3] = $c_n3 - $c_n2;
					$p_n3p1 = $c_n3+1;
				}

				$r[$cat_id] = $n;
			}

		} else { // !$compact_result

			foreach( $sresult as $k=>$v ) {
				// "key.id=value" => r[id][key] = value
				$t = explode('.',$k,2);
				if( !isset($t[1]) ) continue;
				$cat_id = $t[1];

				switch( $t[0] ) {
				case 'plrn':
					$r[$cat_id]['cat_id'] = $cat_id;
					$v = explode(',',$v,4);
					$r[$cat_id]['parent'] = (int)$v[0];
					$r[$cat_id]['left']   = (int)$v[1];
					$r[$cat_id]['right']  = (int)$v[2];
					$r[$cat_id]['name']   =      $v[3];
					break;
				case 'crmd':
					$v = explode(',',$v,3);
					$r[$cat_id]['crd_dt'] = $v[0];
					$r[$cat_id]['mod_dt'] = $v[1];
					$r[$cat_id]['crd_by'] = $v[2];
					$r[$cat_id]['mod_by'] = $v[2];
					break;
				case 'mod':
					$v = explode(',',$v,2);
					$r[$cat_id]['mod_dt'] = $v[0];
					$r[$cat_id]['mod_by'] = $v[1];
					break;
				case 'crd':
					$v = explode(',',$v,2);
					$r[$cat_id]['crd_dt'] = $v[0];
					$r[$cat_id]['crd_by'] = $v[1];
					break;
				case 'tree':
					$r[$cat_id]['tree'] = (int)$v;
					break;
				}
			}

		}
		return $r;
	}

	//--------------------------------------------------------------------------
	// PUBLIC UTILITIES
	//--------------------------------------------------------------------------

	private $plugins_by_ctype = null;

	static public function GetMediaTypeFromSuffixConstants( string $suffix ) : ?string {
		switch( $suffix ) {
			// known formats - these are hardcoded for fast thumbnail/preview delivery!
			case 'png':		return 'image/png';
			case 'jpg':		return 'image/jpeg';
			case 'tif':
			case 'tiff':	return 'image/tiff';

			case 'svg':		return 'image/svg+xml';
			case 'cdr':		return 'application/cdr';

			case 'pdf':		return 'application/pdf';
			case 'xod':		return 'application/vnd.ms-xpsdocument';

			case 'm4a':		return 'audio/mp4';
			case 'mp3':		return 'audio/mpeg';
			case 'oga':		return 'audio/ogg';
			case 'm2a':		return 'audio/MPA';
			case 'wav':		return 'audio/wav';

			case 'webm':	return 'video/webm';
			case 'm4v':
			case 'mp4':		return 'video/mp4';
			case 'mov':		return 'video/quicktime';
			case '3gp':
			case '3gpp':	return 'video/3gpp';
			case '3g2':
			case '3gp2':
			case '3gpp2':	return 'video/3gpp2';
			case 'ogv':		return 'video/ogg';
			case 'mpg':		return 'video/MP2P';
			case 'ts':		return 'video/MP2T';
			case 'm2v':		return 'video/MPV';
			case 'mpeg':	return 'video/MP1S';

			case 'mkv':		return 'video/x-matroska';
			case 'mka':		return 'audio/x-matroska';

			case 'webm':	return 'video/webm';
			//case 'webma':	return 'audio/webm';

			case 'flv':		return 'video/x-flv';
			case 'avi':		return 'video/avi';

			case 'asf':		return 'video/x-ms-asf';
			case 'wmv':		return 'video/x-ms-wmv';
			case 'wma':		return 'audio/x-ms-wma';

			case 'ogg':		return 'application/ogg';
			default: return null;
		}
	}

	public function GetMediaTypeFromSuffix( string $suffix ) : ?string {
		$type = \ManjaServer::GetMediaTypeFromSuffixConstants($suffix);
		if( $type!==null ) return $type;
		// other formats.. get plugin capabilities and content-type
		$info = $this->MediaPluginList($suffix);
		return $info!==false && isset($info[$suffix]) ? $info[$suffix]['ctype'] : null;
	}

	public function GetSuffixFromMediaType( string $ctype ) : ?string {
		if( ($p=strpos($ctype,';'))!==false ) $ctype = rtrim(substr($ctype,0,$p)); // cut-off possible mime parameters
		switch( $ctype ) {
		// known formats - these are hardcoded for fast thumbnail/preview delivery!
		case 'image/png':						return 'png';
		case 'image/jpeg':						return 'jpg';
		case 'image/tiff':						return 'tiff';

		case 'image/svg+xml':					return 'svg';
		case 'application/cdr':					return 'cdr';

		case 'application/pdf':					return 'pdf';
		case 'application/vnd.ms-xpsdocument':	return 'xod';

		case 'audio/mp4':						return 'm4a';
		case 'audio/mpeg':						return 'mp3';
		case 'audio/ogg':						return 'oga';
		//case 'audio/3gpp':						return '3gp';
		case 'audio/MPA':						return 'm2a';
		case 'audio/wav':						return 'wav';

		case 'video/mp4':						return 'm4v';
		case 'video/quicktime':					return 'mov';
		case 'video/3gpp':						return '3gp';
		case 'video/3gpp2':						return '3g2';
		case 'video/ogg':						return 'ogv';
		case 'video/MP2P':						return 'mpg';
		case 'video/MP2T':						return 'ts';
		case 'video/MPV':						return 'm2v';
		case 'video/MP1S':						return 'mpeg';

		case 'video/webm':						return 'webm';
		//case 'audio/webm':					return 'webma';

		case 'video/x-matroska':				return 'mkv';
		case 'audio/x-matroska':				return 'mka';

		case 'video/x-flv':						return 'flv';
		case 'video/avi':						return 'avi';

		case 'video/x-ms-asf':					return 'asf';
		case 'video/x-ms-wmv':					return 'wmv';
		case 'audio/x-ms-wma':					return 'wma';

		case 'application/ogg':					return 'ogg';

		default:
			// unknown formats.. get plugin capabilities and content-type
			if( $this->plugins_by_ctype===null && ($plugins=$this->MediaPluginList())!==false ) {
				$this->plugins_by_ctype = [];
				foreach( $plugins as $sfx=>$plugin ) {
					$plugin['sfx'] = $sfx;
					$this->plugins_by_ctype[$plugin['ctype']] = $plugin;
				}
			}
			return ( $this->plugins_by_ctype!==null && isset($this->plugins_by_ctype[$ctype]) ) ? $this->plugins_by_ctype[$ctype]['sfx'] : null;
		}
	}

	public static function GetAllMediaClasses() : array {
		return [
			ManjaServerDefs::MC_IMAGE,
			ManjaServerDefs::MC_VIDEO,
			ManjaServerDefs::MC_AUDIO,
			ManjaServerDefs::MC_TEXT,
			ManjaServerDefs::MC_CONTAINER,
			ManjaServerDefs::MC_OTHER,
			ManjaServerDefs::MC_UNKNOWN
		];
	}

	public static function GetMediaClassIdFromName( string $mc_name ) : int {
		switch( strtolower(trim($mc_name)) ) {
		case 'image':		return ManjaServerDefs::MC_IMAGE;
		case 'video':		return ManjaServerDefs::MC_VIDEO;
		case 'audio':		return ManjaServerDefs::MC_AUDIO;
		case 'text':		return ManjaServerDefs::MC_TEXT;
		case 'container':	return ManjaServerDefs::MC_CONTAINER;
		case 'other':		return ManjaServerDefs::MC_OTHER;
		case 'unknown':
		default:			return ManjaServerDefs::MC_UNKNOWN;
		}
	}

	public static function GetMediaClassNameFromId( int $mc_id ) : string {
		switch( $mc_id ) {
		case ManjaServerDefs::MC_IMAGE:		return 'image';
		case ManjaServerDefs::MC_VIDEO:		return 'video';
		case ManjaServerDefs::MC_AUDIO:		return 'audio';
		case ManjaServerDefs::MC_TEXT:		return 'text';
		case ManjaServerDefs::MC_CONTAINER:	return 'container';
		case ManjaServerDefs::MC_OTHER:		return 'other';
		case ManjaServerDefs::MC_UNKNOWN:
		default:							return 'unknown';
		}
	}

	public static function GetMetaTypeName( int $type ) : string {
		switch( $type ) {
		case ManjaServerDefs::MT_STRING:	return 'string';
		case ManjaServerDefs::MT_TEXT:		return 'text';
		case ManjaServerDefs::MT_INT:		return 'int';
		case ManjaServerDefs::MT_REAL:		return 'real';
		case ManjaServerDefs::MT_BINARY:	return 'binary';
		case ManjaServerDefs::MT_DATE:		return 'date';
		case ManjaServerDefs::MT_TIME:		return 'time';
		case ManjaServerDefs::MT_DATETIME:	return 'datetime';
		case ManjaServerDefs::MT_REF_MEDIA:	return 'ref_media';
		case ManjaServerDefs::MT_OBJECT:	return 'object';
		default:							return 'unknown';
		}
	}


	public function GetFilenamesMetaIds( array $download_formats ) : array {
		$filename_patterns = '';
		foreach( $download_formats as $df ) $filename_patterns .= ' ' . ( isset($df['filename']) ? $df['filename'] : '' );
		// find required meta-ids
		$matches = [];
		preg_match_all( '/%([-s0-9]+)\\.?([^%|]*)\\|?([-s0-9]*)\\.?([^%]*)%/', $filename_patterns, $matches );
		$meta_ids = [];
		// add primary meta_ids from "%i%" syntax
		foreach( $matches[1] as $x ) {
			if( isset($x[0]) && $x!=='s' ) $meta_ids[] = $x;
		}
		// add fallback meta_ids, from "%i|j%" syntax
		foreach( $matches[3] as $x ) {
			if( isset($x[0]) && $x!=='s' ) $meta_ids[] = $x;
		}
		$meta_ids[] = -7; // source filename
		$meta_ids[] = -2; // content type
		$meta_ids[] = -1; // media class
		$meta_ids[] =  1; // filename
		$meta_ids[] =  6; // filename suffix
		return array_keys(array_flip($meta_ids));//array_values(array_unique($meta_ids));
	}

	public function PreviewFormatGetFirstOfXGroup( string $pf_xgroup, int $filter_media_class=null ) {
		$list = $this->PreviewFormatList(true,$filter_media_class);
		if( $list===false ) return false;
		foreach( $list as $pf ) {
			if( isset($pf['options']) && isset($pf['options']['xgroup']) && $pf['options']['xgroup']===$pf_xgroup ) {
				return $pf;
			}
		}
		return false;
	}

	public function GetFilenames( array $media_ids, array $download_formats, array $meta_ids, array $meta_data ) : array {
		$return = []; // array( media_id => array( format => fn, format => fn, ... ), ... )
		$mt2sfx_cache = [];
		foreach( $media_ids as $media_id ) {
			$mmd = isset($meta_data[$media_id]) ? $meta_data[$media_id] : [];
			$mmc = isset($mmd[-1]) ? (int)$mmd[-1][0] : ManjaServerDefs::MC_UNKNOWN;
			foreach( $download_formats as $dlf_id => $dlf ) {
				if( $mmc!==(int)$dlf['media_class'] ) continue;
				// parse filename pattern
				$fnp = isset($dlf['filename']) ? trim($dlf['filename']) : '';
				$fn = '';
				if( $fnp === '' ) {
					// original filename
					$fn = trim( $mmd[1][0] );
				} else {
					// replace data
					$fmt = $dlf['format'];
					$sfx = '';
					// $patterns = array( '%s%' );
					if( $fmt==='*' ) {
						$sfx = isset($mmd['6'][0]) ? $mmd['6'][0] : '';
						if( $sfx==='' && isset($mmd['-7'][0]) ) {
							$tmp = mj_str_last_part('.',$mmd['-7'][0]);
							if( $tmp!==null ) $sfx = $tmp;
						}
					} else {
						if( $fmt==='+' && isset($dlf['options']) && isset($dlf['options']['pf_xgroup']) ) {
							$pf_xgroup = $dlf['options']['pf_xgroup'];
							$pf = $this->PreviewFormatGetFirstOfXGroup($pf_xgroup,$mmc);
							if( $pf!==false ) {
								$sfx = isset($pf['options']) && isset($pf['options']['mux_file_sfx']) ? $pf['options']['mux_file_sfx'] : '';
								if( $sfx==='' ) {
									$fmt = isset($pf['options']) && isset($pf['options']['mux_mime_type']) ? $pf['options']['mux_mime_type'] : '';
								}
							}
						}
						if( $sfx==='' ) {
							if( !isset($mt2sfx_cache[$fmt]) ) $mt2sfx_cache[$fmt] = $this->GetSuffixFromMediaType($fmt);
							$sfx = $mt2sfx_cache[$fmt];
						}
					}

					$lookup_meta_data_value = function( $meta_id ) use(&$mmd,&$sfx) {
						if( $meta_id==='s' ) {
							return $sfx;
						}
						$tmp = isset($mmd[$meta_id]) && !empty($mmd[$meta_id]) ? array_values($mmd[$meta_id]) : array('');
						$val = str_replace(["\n","\r"],['',''],trim($tmp[0]));
						return $val;
					};
					$transform1_meta_data_value = function( $val, $xform ) {
						if( $val===null ) $val = '';
						switch( $xform ) {
						case 'basename':
							return mj_str_all_but_last_part('.',$val);
						case 'suffix':
							$val = mj_str_last_part('.',$val);
							return $val===null ? '' : $val;
						case 'upper':					return mb_strtoupper($val);
						case 'lower':					return mb_strtolower($val);
						case 'ucfirst':					return mb_ucfirst($val);
						case 'safealphanumeric':		return preg_replace('/[^[:alnum:]]/u','_',$val);
						case 'safefilename':			return mj_make_filename($val);
						case 'underscores2spaces':		return str_replace('_',' ',$val);
						case 'trim':					return trim($val);
						case 'whitespace2underscores':	return preg_replace('/\s+/','_',$val);
						case 'collapseunderscores':		return preg_replace('/_+/','_',$val);
						case 'collapsewhitespace':		return preg_replace('/\s+/',' ',$val);
						case 'lowerCamelCase':			return to_camel_case($val,false);
						case 'UpperCamelCase':			return to_camel_case($val,true);
						case 'pluralize':				return mj_pluralize($val);
						case 'singularize':				return mj_singularize($val);
						case 'date':					return substr($val,0,10);	// YYYY-mm-dd
						}
						// unknown xform
						return $val;
					};
					$transform_meta_data_value = function( $val, $xforms ) use(&$transform1_meta_data_value) {
						$xforms = x_explode('.',$xforms);
						foreach( $xforms as $xform ) $val = $transform1_meta_data_value($val,$xform);
						return $val;
					};
					$fn = preg_replace_callback( '/%([-s0-9]+)\\.?([^%|]*)\\|?([-s0-9]*)\\.?([^%]*)%/', function( $match ) use(&$lookup_meta_data_value,&$transform_meta_data_value) {
						// lookup meta data
						$val = $lookup_meta_data_value($match[1]);
						if( isset($match[2][0]) ) $val = $transform_meta_data_value($val,$match[2]);
						if( $val===null || $val==='' ) {
							// lookup meta data of alternative field
							if( isset($match[3][0]) ) {
								$val = $lookup_meta_data_value($match[3]);
								if( isset($match[4][0]) ) $val = $transform_meta_data_value($val,$match[4]);
							}
						}
						return $val;
					}, $fnp );
				}
				$return[$media_id][$dlf_id] = str_replace( ['/','\\',':'], ['_','_','_'], $fn );
			}
		}
		return $return;
	}

	public function GetFilename( $media_id, int $dlf_id ) : ?string {
		// get download format info
		if( $dlf_id==0 ) return null;
		if( ($df=$this->DownloadFormatGet($dlf_id,true))===false ) return null;
		// get required meta_ids and meta_data
		$meta_ids = $this->GetFilenamesMetaIds( [$df] );
		if( ($meta_data=$this->MediaMetaList([$media_id],$meta_ids))===false ) return null;
		// generate filename
		$filenames = $this->GetFilenames( [$media_id], [$dlf_id=>$df], $meta_ids, $meta_data );
		return isset($filenames[$media_id]) && isset($filenames[$media_id][$dlf_id]) ? $filenames[$media_id][$dlf_id] : '';
	}


	/**
	 * @param int $user_id
	 *
	 * @return string|false
	 */
	public function CreatePasswordResetRequest( int $user_id ) {
		$token = md5($user_id.'::'.mt_rand());
		if( ($tmp=$this->UserdataSet($user_id,array('reset-password-token'=>$token,'reset-password-token-time'=>time())))===false ) return false;
		return $token;
	}

	/**
	 * @param int $user_id
	 * @param string $token
	 * @param int $lifetime
	 *
	 * @return bool
	 */
	public function CheckPasswordResetRequest( int $user_id, string $token, int $lifetime ) : bool {
		$ud = $this->UserdataGet($user_id,array('reset-password-token','reset-password-token-time'));
		if( $ud!==false && is_array($ud) && isset($ud['reset-password-token']) ) {
			$stored_token = $ud['reset-password-token'];
			$stored_token_time = isset($ud['reset-password-token-time']) ? intval($ud['reset-password-token-time'],10) : 0;
			$now = time();
			if( $stored_token===$token && trim($stored_token)!=='' && $now>$stored_token_time && $now<$stored_token_time+$lifetime ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public function ClearPasswordResetRequest( int $user_id ) : bool {
		if( ($tmp=$this->UserdataSet($user_id,array('reset-password-token'=>'','reset-password-token-time'=>'')))===false ) return false;
		return true;
	}


	public function SanitizeUserdata( array &$userdata ) {
		unset($userdata['reset-password-token']);
		unset($userdata['reset-password-token-time']);
		foreach( $userdata as $k=>$v ) {
			if( strncmp($k,'sp2ud-',6)===0 ) unset($userdata[$k]); // any "sp2ud-" data (=SessionParameters2UserData..)
		}
	}

	public function SanitizeUserlist( array &$userlist ) {
		foreach( $userlist as &$v ) {
			if( isset($v['userdata']) ) $this->SanitizeUserdata($v['userdata']);
		}
		//unset($v);
	}

}

