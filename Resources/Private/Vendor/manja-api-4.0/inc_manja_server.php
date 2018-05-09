<?php
/*------------------------------------------------------------------------------
 (c)(r) 2008-2018 IT-Service Robert Frunzke
--------------------------------------------------------------------------------
Manja Server Communication Object
- Manja 3.7
------------------------------------------------------------------------------*/

// media classes
define('MC_UNKNOWN',0);
define('MC_IMAGE',1);
define('MC_VIDEO',2);
define('MC_AUDIO',3);
define('MC_TEXT',4);
//define('MC_COMBINED',5); // unused
define('MC_CONTAINER',6);
define('MC_OTHER',7);

// acl item actions
define('ACL_ITEM_FIND',1);
define('ACL_ITEM_VIEW_LOWRES',2);		// also means: previews with watermark only (if watermarks enabled)
define('ACL_ITEM_VIEW_HIGHRES',3);		// also means: previews without watermark
define('ACL_ITEM_DOWNLOAD',4);
define('ACL_ITEM_UPLOAD',5);
define('ACL_ITEM_EDIT',6);
define('ACL_ITEM_EDIT_STRUCTURE',7);
define('ACL_ITEM_DELETE',8);

// Collaboration & Annotations Add On
define('ACL_ITEM_ANNOTS_VIEW',51);
define('ACL_ITEM_ANNOTS_EDIT',52);
define('ACL_ITEM_ANNOTS_ADMIN',53);

define('ACL_ITEM_CUSTOM_BASE',1000);

// custom action ranges
define('ACL_ITEM_CUSTOM_DLF_FIRST',     1); // actual acl_action = ACL_ITEM_CUSTOM_BASE + ACL_ITEM_CUSTOM_DLF_FIRST
define('ACL_ITEM_CUSTOM_DLF_LAST',     20);
define('ACL_ITEM_CUSTOM_EXPCH_FIRST', 100);
define('ACL_ITEM_CUSTOM_EXPCH_LAST',  120);


// meta data types
define('MT_STRING',0);		// strict single line text
define('MT_TEXT',1);		// common text, which may contain line breaks
define('MT_INT',2);			// integer numbers
define('MT_REAL',3);		// real numbers
define('MT_BINARY',4);		// binary data
define('MT_DATE',5);		// date, format YYYY-MM-DD
define('MT_TIME',6);		// time, format HH:MM:SS (24 hours)
define('MT_DATETIME',7);	// datetime, format YYYY-MM-DD HH:MM:SS
define('MT_REF_MEDIA',8);	// reference to media_id (based on MT_INT)

// media status values
define('MS_EMPTY',0);					// media created, but still empty
define('MS_DELETED',1);					// media was deleted - not available anymore
define('MS_UPLOAD_PROGRESS',2);			// media data is uploading
define('MS_UPLOAD_SUSPENDED',3);		// upload was suspended
define('MS_UPLOAD_BROKEN',4);			// upload was broken - media is not available or empty
define('MS_UPLOAD_SUCCEEDED',5);		// upload succeeded, no meta-data yet
define('MS_POSTPROCESSING',6);			// media data is being postprocessed (e.g. thumbnail images will be generated)
define('MS_POSTPROCESSING_FAILED',7);	// post-processing failed, media will not be available
define('MS_AVAILABLE',8);				// media is completely imported, postprocessing is finished

// media meta status values
define('MMS_NONE',0);				// meta-data not processed yet
define('MMS_META_PARSING',1);		// reading and writing meta data
define('MMS_META_PARSE_DONE',2);	// reading and writing meta-data is finished (either successful or with failure)
define('MMS_META_ANALYSING',3);		// analysing meta-data (splitting words, building index)
define('MMS_AVAILABLE',4);			// meta-data is written, analysed, indexed and finally available for distribution

// media plugin capabilities
define('MPC_READ_BITMAP',1);
define('MPC_WRITE_BITMAP',2);
define('MPC_READ_MULTI_PAGE_BITMAP',4);
define('MPC_READ_BITMAP_IS_COMPLEX',8);
define('MPC_READ_BITMAP_CROP_SUPPORTED',16);
define('MPC_READ_VIDEO',32);
define('MPC_WRITE_VIDEO',64);
define('MPC_READ_AUDIO',128);
define('MPC_WRITE_AUDIO',256);
define('MPC_READ_TEXT',512);
define('MPC_WRITE_TEXT',1024);
define('MPC_POST_PARSE_METADATA',2048);
define('MPC_WRITE_BITMAP_ALPHA',4096);
define('MPC_PROVIDE_COMPARE_CONTENT',8192);
define('MPC_WRITE_STREAM_CONVERTED_RAW_DATA',16384);
define('MPC_WRITE_TRUE_STREAM_CONVERTED_RAW_DATA',32768);

// codec plugin capabilities
define('CPC_READ_VIDEO',32);
define('CPC_WRITE_VIDEO',64);
define('CPC_READ_AUDIO',128);
define('CPC_WRITE_AUDIO',256);
define('CPC_READ_TEXT',512);
define('CPC_WRITE_TEXT',1024);

// meta plugin capabilities
define('MDPC_READ',1);
define('MDPC_WRITE',2);
define('MDPC_WRITE_EMBED',4);


/**
 * like explode (without limit), but returns empty array when string is empty (not an array with one element as explode does)
 * 
 * @param string $delimiter
 * @param string $string
 */
function x_explode( $delimiter, $string ) {
	return isset($string[0]) ? explode($delimiter,$string) : array();
}

function x_explode2floats( $delimiter, $string ) {
	$r = x_explode($delimiter,$string);
	foreach( $r as &$s ) $s = floatval($s);
	return $r;
}

function _mj_cmp_lists( $a, $b ) {
	return strcoll($a['title'],$b['title']);
}
function _mj_cmp_lists2( $a, $b ) {
	return strcoll($a['sort_key'],$b['sort_key']);
}
function _mj_cmp_tree_nodes( $a, $b ) {
	return $a['left']===$b['left'] ? 0 : ( $a['left']<$b['left'] ? -1 : +1 );
}
function _mj_cmp_color_profiles( $a, $b ) {
	return strcoll($a['name'],$b['name']);
}
function _mj_cmp_acl_items( $a, $b ) {
	return $a['order']===$b['order'] ? 0 : ( $a['order']<$b['order'] ? -1 : +1 );
}
function _mj_cmp_items_by_sort_key( $a, $b ) {
	return $a['sort']===$b['sort'] ? 0 : ( $a['sort']<$b['sort'] ? -1 : +1 );
}



/**
 * Manja Server Communication Object
 * @author rob
 */
class ManjaServer {

	private $host, $port;
	private $error_code = 0;
	private $error_string = '';
	private $ctx = null;
	private $fp = null;
	private $stream_state = 'none'; // none, idle, error
	private $die_on_error = false; // set to true to let the server class die on error (and display detailed error messages) - saves lots of error checking
	private $connect_recursion_counter = 0;

	private $cfg_server_connect_timeout = 20;
	private $cfg_server_stream_timeout = 3600;
	private $cfg_client_id;

	private $error_callback = null;

	private $server_version = null;				// string "minor.major", e.g. "2.3"
	private $extended_response_format = false;	// available in server version 2.4
	private $ssl_supported = false;
	private $ssl_required = false;
	private $server_features = array();			// array( feat=>feat, ... ), e.g. ssl, i18n, versioning, automation

	private $connected_username = null;
	private $connected_user_password = null;
	private $connected_session_id = null;
	private $connected_user_id = null;
	private $connected_ssl_active = false;
	private $connected_ssl_ctx_opts = null;

	public function __construct( $client_id, $host, $port ) {
		// set connection data
		$this->cfg_client_id = $client_id;
		$this->host = $host;
		$this->port = (int)$port;
		if( $this->port == 0 ) $this->port = 12345;
	}

	public function __destruct() {
		$this->Disconnect();
	}

	// enable/disable die_on_error functionality
	public function SetDieOnError( $enabled ) {
		$this->die_on_error = $enabled;
	}

	public function GetDieOnError() {
		return $this->die_on_error;
	}

	public function SetErrorCallback( $callback ) {
		$this->error_callback = $callback;
	}

	public function GetErrorCode() {
		return $this->error_code;
	}

	public function GetErrorString() {
		return $this->error_string;
	}

	public function GetHost() {
		return $this->host;
	}
	public function GetPort() {
		return $this->port;
	}

	public function GetConnectedUsername() {
		return $this->connected_username;
	}
	public function GetConnectedSessionId() {
		return $this->connected_session_id;
	}
	public function GetConnectedUserId() {
		return $this->connected_user_id;
	}
	
	public function ConfigureTimeouts( $server_connect_timeout, $server_stream_timeout ) {
		$this->cfg_server_connect_timeout = $server_connect_timeout;
		$this->cfg_server_stream_timeout = $server_stream_timeout;
	}

	// connect to server, return true on success, false on error, sets error states on error
	public function Connect() {
		if( $this->host==='localhost' || $this->host==='127.0.0.1' ) {
			$host = '127.0.0.1';
		} else {
			// fsockopen does not return a valid code for dns resolution errors, so check for dns errors first:
			$hosts = @gethostbynamel( $this->host );
			if( $hosts === false ) {
				$this->error_code = 'dns/1';
				$this->error_string = 'unknown host';
				return $this->_error();
			}
			$host = count($hosts) ? $hosts[0] : $this->host;
		}
		// then connect
		$errno = 0; $errstr = '';
		$this->ctx = stream_context_create();
		//$this->fp = @fsockopen( $host, $this->port, $errno, $errstr, $this->cfg_server_connect_timeout );
		$this->fp = @stream_socket_client( 'tcp://'.$host.':'.$this->port, $errno, $errstr, $this->cfg_server_connect_timeout, STREAM_CLIENT_CONNECT, $this->ctx );
		if( $this->fp === false ) {
			$this->error_code = 'sys/'.$errno;
			$this->error_string = $errstr;
			return $this->_error();
		}
		// set read/write timeout
		stream_set_timeout( $this->fp, (int)$this->cfg_server_stream_timeout );
		// stream is up and waiting for commands
		$this->stream_state = 'idle';
		// check handshake - "manja <version> <plain> <ssl>"
		if( ($line=fgets($this->fp,255))===false ) {
			$this->stream_state = 'error';
			$this->error_code = 'pro/1';
			$this->error_string = 'not a manja server or server busy (1)';
			$this->Disconnect();
			return $this->_error();
		}
		$x = explode(' ',rtrim($line));
		if( array_shift($x)!=='manja' ) {
			$this->stream_state = 'error';
			$this->error_code = 'pro/1';
			$this->error_string = 'not a manja server or server busy (2)';
			$this->Disconnect();
			return $this->_error();
		}
		$this->server_version = array_shift($x);
		$this->extended_response_format = $this->server_version > 2.3;
		// get & extract feature list
		$this->server_features = array_combine($x,$x);
		// set ssl_supported & ssl_required flags
		if( isset($this->server_features['ssl']) ) {
			$this->ssl_supported = true;
			if( !isset($this->server_features['plain']) ) $this->ssl_required = true;
		}
		// alright
		return true;
	}

	public function SSL( $ssl_ctx_opts=null ) {
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
		if( $ssl_ctx_opts===null ) $ssl_ctx_opts = array();
		// NOTE: default for verify_peer changed in PHP 5.6.x - we revert that change:
		// (see http://php.net/manual/de/migration56.openssl.php)
		if( !isset($ssl_ctx_opts['verify_peer']) ) $ssl_ctx_opts['verify_peer'] = false;
		if( count($ssl_ctx_opts)>0 ) stream_context_set_option($this->ctx,array('ssl'=>$ssl_ctx_opts));
		$crypto_types = STREAM_CRYPTO_METHOD_TLS_CLIENT;
		// in PHP >= 5.6.7 STREAM_CRYPTO_METHOD_TLS_CLIENT was redefined and excludes TLSv1.1 and TLS v1.2 - revert that change:
		if( defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') ) $crypto_types = $crypto_types|STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
		if( !stream_socket_enable_crypto($this->fp,true,$crypto_types) ) {
			$this->stream_state = 'error';
			$this->error_code = 'ssl/1';
			$this->error_string = 'failed to initiate ssl mode';
			$this->Disconnect();
			return $this->_error();
		}
		$this->connected_ssl_active = true;
		$this->connected_ssl_ctx_opts = $ssl_ctx_opts;
		return true;
	}

	public function Login( $username, $password, $remote_host=null, $remote_port=null ) {
		// login
		$par = array('user'=>$username,'pass'=>$password,'client_id'=>$this->cfg_client_id);
		if( $remote_host!==null ) $par['remote_host'] = $remote_host;
		if( $remote_port!==null ) $par['remote_port'] = $remote_port;
		if( ($result=$this->_cmd('login',$par)) === false ) {
			$this->error_code = 'auth/2';
			$this->error_string = 'login failed';
			$this->_error();
			$this->Disconnect();
			return 0;
		}
		$this->connected_username = $username;
		$this->connected_user_password = $password;
		$this->connected_user_id = (int)$result['user_id'];
		return $this->connected_user_id;
	}

	// this invalidates the current login and/or session
	public function Init() {
		$this->connected_username = null;
		$this->connected_user_password = null;
		$this->connected_session_id = null;
		$this->connected_user_id = null;
		return $this->_cmd( 'init' );
	}

	// no op command (only used to avoid timeouts)
	public function NoOp() {
		return $this->_cmd( 'noop' );
	}

	// disconnect from server
	public function Disconnect() {
		if( $this->fp === null ) return;
		// let server close the connection whenever possible
		if( $this->stream_state === 'idle' ) {
			// but do not wait forever on the close
			@stream_set_timeout( $this->fp, 3 );
			@fwrite($this->fp,"exit\n\n");
			$this->stream_state = 'none';
		}
		// and add an optional close on client side (close streams in error-states too, error-state may be result of a communication error with still valid connection)
		@fclose($this->fp);
		$this->fp = null;
	}

	public function GetFeatures() {
		return $this->server_features;
	}

	public function HasFeature( $feature ) {
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
		return $tmp;
	}

	public function SessionResume( $sid, $get_data=false, $get_roles=false, $do_not_touch=false, $remote_host=null, $remote_port=null ) {
		$par = array('session_id'=>$sid,'client_id'=>$this->cfg_client_id);
		if( $get_data ) $par['get_data'] = '1';
		if( $get_roles ) $par['get_roles'] = '1';
		if( $do_not_touch ) $par['do_not_touch'] = '1';
		if( $remote_host!==null ) $par['remote_host'] = $remote_host;
		if( $remote_port!==null ) $par['remote_port'] = $remote_port;
		$tmp = $this->_cmd('session resume',$par);
		if( $tmp===false ) return false;
		$this->connected_username = $tmp['login'];
		$this->connected_user_id = (int)$tmp['user_id'];
		$this->connected_session_id = $sid;
		if( $get_data ) {
			$data = array();
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='d' && isset($k[1]) && $k[1]==='.' ) $data[substr($k,2)] = $v;
			}
			$tmp['session_data'] = $data;
		}
		if( $get_roles && isset($tmp['roles']) ) {
			// build key=value list where key and value are the role (makes some things easier..)
			$r = x_explode(',',$tmp['roles']);
			$tmp['roles'] = isset($r[0]) ? array_combine($r,$r) : array();
		}
		return $tmp;
	}

	public function SessionSet( $values ) {
		return $this->_cmd('session set',$values);
	}
	public function SessionGet( $keys=null ) {
		if( $keys === null ) return $this->_cmd('session get');
		else return $this->_cmd('session get',array('parameters'=>implode(',',$keys)));
	}
	public function SessionExit() {
		$tmp = $this->_cmd('session exit');
		if( $tmp===false ) return false;
		$this->connected_session_id = null;
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
			return isset($r[0]) ? array_combine($r,$r) : array();
		}
		return false;
	}

	public function UtilNowGet( $utc=true, $interval1=null, $interval2=null ) {
		$par = array( 'utc' => $utc?'1':'0' );
		if( $interval1!==null ) $par['interval1'] = $interval1;
		if( $interval2!==null ) $par['interval2'] = $interval2;
		$tmp = $this->_cmd('util now get',$par);
		if( is_array($tmp) && isset($tmp['result']) ) return $tmp['result'];
		return false;
	}

	public function UtilDatetimeIntervalCompute( $dt, $interval1=null, $interval2=null ) {
		$par = array( 'dt' => $dt );
		if( $interval1!==null ) $par['interval1'] = $interval1;
		if( $interval2!==null ) $par['interval2'] = $interval2;
		$tmp = $this->_cmd('util datetime interval compute',$par);
		if( is_array($tmp) && isset($tmp['result']) ) return $tmp['result'];
		return false;
	}


	// TODO: refactoring of IndexSearch & IndexSearch2

	public function IndexSearch( $fulltext, $fulltext_match_partial=false, $result_list_id=0, $work_list_id=0, $meta_ids=null, $dominant_color=null, $dominant_color_limit=null, $filters=null, $similar_to_media_ids=null, $in_category_titles=false ) {
		$par = array( 'fulltext'=>$fulltext, 'fulltext_match_partial'=>$fulltext_match_partial?'1':'0', 'fulltext_match_category_titles'=>$in_category_titles?'1':'0', 'result_list_id'=>$result_list_id, 'work_list_id'=>$work_list_id );
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

	public function IndexSearch2( $fulltext, $fulltext_match_partial=false, $result_list_id, $work_list_id, $meta_ids, $dominant_color, $dominant_color_limit, $filters, $similar_to_media_ids, $in_categories, $in_trees, $not_in_trees, $conjunct_mode_trees=array(), $in_category_titles=false ) {
		$par = array( 'fulltext'=>$fulltext, 'fulltext_match_partial'=>$fulltext_match_partial?'1':'0', 'fulltext_match_category_titles'=>$in_category_titles?'1':'0', 'result_list_id'=>$result_list_id, 'work_list_id'=>$work_list_id,
					  'filter_categories'=>'1',
					  'in_categories'=>implode(',',$in_categories), 'in_trees'=>implode(',',$in_trees), 'not_in_trees'=>implode(',',$not_in_trees), 'conjunct_mode_trees'=>implode(',',$conjunct_mode_trees) );
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

	public function IndexListFilter( $result_list_id, $work_list_id, $in_categories, $in_trees, $not_in_trees, $conjunct_mode_trees=array() ) {
		$par = array( 'result_list_id'=>$result_list_id, 'work_list_id'=>$work_list_id, 'in_categories'=>implode(',',$in_categories), 'in_trees'=>implode(',',$in_trees), 'not_in_trees'=>implode(',',$not_in_trees), 'conjunct_mode_trees'=>implode(',',$conjunct_mode_trees) );
		return $this->_cmd( 'index list filter', $par );
	}

	public function IndexListCounts( $list_id ) {
		$tmp = $this->_cmd( 'index list counts', array('list_id'=>$list_id) );
		if( $tmp===false ) return false;
		$assigned_to_categories = array();
		$unassigned_to_trees = array();
		foreach( $tmp as $k=>$v ) {
			if( strncmp($k,'assigned.',9)===0 ) {
				$assigned_to_categories[substr($k,9)] = x_explode(',',$v);
			} else if( strncmp($k,'unassigned.',11)===0 ) {
				$unassigned_to_trees[substr($k,11)] = x_explode(',',$v);
			}
		}
		return array( 'assigned'=>$assigned_to_categories, 'unassigned'=>$unassigned_to_trees );
	}

	public function IndexListGet( $list_id, $offset, $count, $meta_ids=null, $filter_deleted=false, $with_categories=false, $with_relevance=false ) {
		$par = array( 'list_id'=>$list_id, 'offset'=>$offset, 'count'=>$count );
		if( $filter_deleted ) {
			if( is_array($meta_ids) ) $meta_ids[] = -1;
			else $meta_ids = array(-1);
		}
		if( is_array($meta_ids) && isset($meta_ids[0]) ) $par['meta_ids'] = implode(',',array_keys(array_flip($meta_ids)));
		if( $with_categories ) $par['with_categories'] = '1';
		if( $with_relevance ) $par['with_relevance'] = '1';
		$tmp = $this->_cmd( 'index list get', $par );
		if( $tmp===false ) return false;
		// index media-ids by position in global list
		$mids = array();
		$relevance = array();
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
		$meta = array();
		$categories = array();
		if( $meta_ids !== null && $with_categories ) {
			// meta-data & categories
			foreach( $tmp as $k=>$v ) {
				//if( $k[1]==='.' ) {
					if( $k[0]==='m' ) {
						$x = explode('.',$k,4);
						$meta[$x[1]][$x[2]][$x[3]] = $v;
					} else if( $k[0]==='c' ) {
						$x = explode('.',$k,3);
						$categories[$x[1]][$x[2]] = strpos($v,',')===false ? $v : x_explode(',',$v);
					}
				//}
			}
			//not-required-anymore://$this->_sort_metadata_values($meta);
		} else if( $meta_ids !== null ) {
			// meta-data
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='m' ) {//&& $k[1]==='.' ) {
					$x = explode('.',$k,4);
					$meta[$x[1]][$x[2]][$x[3]] = $v;
				}
			}
			//not-required-anymore://$this->_sort_metadata_values($meta);
		} else if( $with_categories ) {
			// categories
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='c' ) {//&& $k[1]==='.' ) {
					$x = explode('.',$k,3);
					$categories[$x[1]][$x[2]] = x_explode(',',$v);
				}
			}
		}
		if( $filter_deleted ) {
			$filtered_mids = array();
			foreach( $mids as $mid ) if( isset($meta[$mid]) ) $filtered_mids[] = $mid;
			$mids = $filtered_mids;
		}
		return array(	'result_count' => $rc,
						'media_ids' => $mids,
						'relevance' => $relevance,
						'meta' => $meta,
						'categories' => $categories );
	}

	public function IndexListSort( $list_id, $criteria ) {
		$par = array( 'list_id'=>$list_id );
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

	private function JSONCompatibleOrderedIndexListList( $lists ) {
		$r = array();
		foreach( $lists as $list_id=>$list ) {
			$list['list_id'] = $list_id;
			$r[] = $list;
		}
		return $r;
	}

	/*** DEPRECATED:
	public function IndexListList( $user_id=null, $json_compatible=false, $session_id=null ) {
		$par = array();
		if( $user_id!==null ) $par['user_id'] = $user_id;
		if( $session_id!==null ) $par['session_id'] = $session_id;
		$tmp = $this->_cmd( 'index list list', $par );
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax( $tmp );
		uasort( $tmp, '_mj_cmp_lists' );
		return $json_compatible ? $this->JSONCompatibleOrderedIndexListList($tmp) : $tmp;
	}
	public function IndexListListByLogin( $login, $json_compatible=false ) {
		$tmp = $this->_cmd( 'index list list', array('login'=>$login) );
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax( $tmp );
		uasort( $tmp, '_mj_cmp_lists' );        
		return $json_compatible ? $this->JSONCompatibleOrderedIndexListList($tmp) : $tmp;
	}
	***/
	/*** DEPRECATED:
	public function IndexListCreate( $title, $session_id=null ) {
		$par = array('title'=>$title);
		if( $session_id!==null ) $par['session_id'] = $session_id;
		return $this->_cmd( 'index list create', $par );
	}
	***/
	public function IndexListCreate( $session_id=null ) {
		$par = array();
		if( $session_id!==null ) $par['session_id'] = $session_id;
		return $this->_cmd( 'index list create', $par );
	}
	/*** DEPRECATED:
	public function IndexListMediaAdd( $list_id, $media_ids, $pos=0 ) {
		return $this->_cmd( 'index list media add', array('list_id'=>$list_id,'media_ids'=>implode(',',$media_ids),'pos'=>$pos) );
	}
	public function IndexListMediaRemove( $list_id, $media_ids ) {
		return $this->_cmd( 'index list media remove', array('list_id'=>$list_id,'media_ids'=>implode(',',$media_ids)) );
	}
	public function IndexListDelete( $list_id ) {
		return $this->_cmd( 'index list delete', array('list_id'=>$list_id) );
	}
	***/
	/*** DEPRECATED:
	public function IndexListRename( $list_id, $title ) {
		return $this->_cmd( 'index list rename', array('list_id'=>$list_id,'title'=>$title) );
	}
    public function IndexListNote( $list_id, $note ) {
        return $this->_cmd( 'index list note', array('list_id'=>$list_id, 'note'=>$note) );
    }
    ***/

    /*** DEPRECATED:
	public function IndexListSend( $rcpt_user_id, $list_id, $new_list_title, $media_ids, $acl_actions ) {
		return $this->_cmd( 'index list send', array('user_id'=>$rcpt_user_id,'list_id'=>$list_id,'title'=>$new_list_title,'media_ids'=>implode(',',$media_ids),'acl_actions'=>implode(',',$acl_actions)) );
	}
	public function IndexListSendGuest( $email, $first_name, $last_name, $pass, $expires, $lb_title, $media_ids, $acl_actions ) {
		return $this->_cmd( 'index list send guest', array('email'=>$email,'first_name'=>$first_name,'last_name'=>$last_name,'pass'=>$pass,'expires'=>$expires,'title'=>$lb_title,'media_ids'=>implode(',',$media_ids),'acl_actions'=>implode(',',$acl_actions)) );
	}
	public function IndexListSend2( $rcpt_user_id, $list_id, $expires, $new_list_title, $media_ids, $acl_actions ) {
		$p = array('user_id'=>$rcpt_user_id,'list_id'=>$list_id,'expires'=>$expires,'title'=>$new_list_title,'media_ids'=>implode(',',$media_ids));
		foreach( $acl_actions as $media_id=>$actions ) {
			$p['acl_action.'.$media_id] = implode(',',$actions);
		}
		return $this->_cmd( 'index list send 2', $p );
	}
	public function IndexListSendGuest2( $email, $first_name, $last_name, $pass, $expires, $lb_title, $media_ids, $acl_actions ) {
		$p = array('email'=>$email,'first_name'=>$first_name,'last_name'=>$last_name,'pass'=>$pass,'expires'=>$expires,'title'=>$lb_title,'media_ids'=>implode(',',$media_ids));
		foreach( $acl_actions as $media_id=>$actions ) {
			$p['acl_action.'.$media_id] = implode(',',$actions);
		}
		return $this->_cmd( 'index list send guest 2', $p );
	}
	***/

	public function IndexMetaSuggestionsGet( $meta_id, $cur_count, $freq_count, $char_limit ) {
		$tmp = $this->_cmd( 'index meta suggestions get', array( 'meta_id'=>$meta_id, 'cur_count'=>$cur_count, 'freq_count'=>$freq_count, 'char_limit'=>$char_limit ) );
		if( $tmp===false ) return false;
		return $this->_parse_numeric_result( $tmp );
	}

	public function IndexSearchSuggestionsGet( $meta_ids, $count, $char_limit, $pattern, $in_category_titles=false ) {
		if( is_array($meta_ids) ) $meta_ids = implode(',',$meta_ids);
		$par = array( 'meta_ids'=>$meta_ids, 'fulltext_match_category_titles'=>$in_category_titles?'1':'0', 'count'=>$count, 'char_limit'=>$char_limit, 'pattern'=>$pattern );
		$tmp = $this->_cmd( 'index search suggestions get', $par );
		if( $tmp===false ) return false;
		return $this->_parse_numeric_result( $tmp );
	}

	public function IndexMetaValuesGet( $meta_id ) {
		$tmp = $this->_cmd( 'index meta values get', array('meta_id'=>$meta_id) );
		if( $tmp===false ) return false;
		return $this->_parse_numeric_result( $tmp );
	}

	public function MediumGet( $media_id, $stream=true, $filename=null, $options=array() ) {
		$options['media_id'] = $media_id;
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'medium get', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options );
		return $tmp;
	}

	public function MediumDownload( $media_id, $dlf_id, $stream=true, $filename=null, $options=array() ) {
		$options['media_id'] = $media_id;
		$options['dlf_id'] = $dlf_id;
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		if( $filename!==null ) $options['filename'] = $filename;
		$tmp = $this->_cmd( 'medium download', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options );
		return $tmp;
	}

	public function MediumDownloadSize( $media_id, $dlf_id ) {
		$options = array('media_id'=>$media_id,'dlf_id'=>$dlf_id);
		$tmp = $this->_cmd( 'medium download size', $options );
		if( $tmp===false ) return false;
		return $tmp;
	}

	public function MediumPreview( $options, $stream=true, $filename=null ) {
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'medium preview', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options );
		return $tmp;
	}

	public function MediumDimensions( $media_id, $page, $box=null ) {
		$opts = array('media_id'=>$media_id,'page'=>$page);
		if( $box!==null ) $opts['box'] = $box;
		return $this->_cmd( 'medium dimensions', $opts );
	}

	public function MediumUpload( $file, $media_id=null, $cat_id_tree_1=1, $filename='', $new_media_id=null ) {
		$filename = Normalizer::normalize($filename);
		$opts = array( 'filename'=>$filename );
		if( $media_id===null ) {
			$opts['cat_id'] = is_array($cat_id_tree_1) ? implode(',',$cat_id_tree_1) : (string)$cat_id_tree_1;
			if( $new_media_id!==null ) $opts['new_media_id'] = $new_media_id;
		} else {
			$opts['media_id'] = $media_id;
		}
		$opts['payload'] = filesize($file);
		$fp = fopen($file,'rb');
		if( $fp === false ) {
			$this->error_code = 'fs/1';
			$this->error_string = 'could not read input file';
			return $this->_error();
		}
		$tmp = $this->_cmd( 'medium upload', $opts, $fp );
		fclose($fp);
		return $tmp;
	}

	public function MediumUploadFromStream( $stream, $size=-1, $media_id=null, $cat_id_tree_1=1, $filename='', $new_media_id=null ) {
		$filename = Normalizer::normalize($filename);
		$opts = array( 'filename'=>$filename );
		if( $media_id===null ) {
			$opts['cat_id'] = $cat_id_tree_1;
			if( $new_media_id!==null ) $opts['new_media_id'] = $new_media_id;
		} else {
			$opts['media_id'] = $media_id;
		}
		if( $size >= 0 ) {
			// filesize is known, so upload directly from stream
			$opts['payload'] = $size;
			$fp = $stream;
		} else {
			// we need the filesize, so write contents to tempfile first
			stream_set_timeout( $this->fp, 3600 ); // set large timeout on socket.. (but: server-side may also have a timeout..)
			if( $this->_cmd('noop')===false ) return false; // .. to avoid timeout
			$fp = tmpfile();
			if( $fp === false ) {
				$this->error_code = 'fs/3';
				$this->error_string = 'failed to create temporary file';
				return $this->_error();
			}
			// read from client and write to temp file, while avoiding timeouts on manja server connection..
			$size = 0;
			while( !feof($stream) ) {
				if( $this->_cmd('noop')===false ) return false; // .. to avoid timeout
				$d = fread($stream,2*16384);//4*16436);
				if( $d===false ) {
					$this->error_code = 'http/1';
					$this->error_string = 'failed to read input stream';
					$m = stream_get_meta_data($stream);
					if( $m['timed_out'] ) {
						$this->error_string .= ' (timeout)';
					}
					return $this->_error();
				} else {
					if( ($l=strlen($d))==0 ) break; // reached EOF
					if( fwrite($fp,$d)===false ) {
						$this->error_code = 'fs/2';
						$this->error_string = 'failed writing to temporary file';
						return $this->_error();
					}
					$size += $l;
				}
			}
			$opts['payload'] = $size;
			fseek($fp,0);
		}
		$tmp = $this->_cmd( 'medium upload', $opts, $fp );
		if( $stream!==$fp ) {
			fclose($fp);
			stream_set_timeout( $this->fp, (int)$this->cfg_server_stream_timeout );
		}
		return $tmp;
	}


	public function MediumXmpGet( $media_id, $stream=true, $filename=null, $options=array() ) {
		$options['media_id'] = $media_id;
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'medium xmp get', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options );
		return $tmp;
	}

	public function MediumXmpUpload( $media_id, $file, $persist_as_sidecar_file, $do_not_create_version=false ) {
		$opts = array( 'media_id'=>$media_id, 'persist_as_sidecar_file'=>$persist_as_sidecar_file?'1':'0', 'do_not_create_version'=>$do_not_create_version?'1':'0' );
		$opts['payload'] = filesize($file);
		$fp = fopen($file,'rb');
		if( $fp === false ) {
			$this->error_code = 'fs/1';
			$this->error_string = 'could not input read file';
			return $this->_error();
		}
		$tmp = $this->_cmd( 'medium xmp upload', $opts, $fp );
		fclose($fp);
		return $tmp;
	}


	public function MediumCustomPreviewUpload( $media_id, $file, $cp_id=0, $filename='' ) {
		$filename = Normalizer::normalize($filename);
		$opts = array( 'media_id'=>$media_id, 'cp_id'=>$cp_id, 'filename'=>$filename );
		$opts['payload'] = filesize($file);
		$fp = fopen($file,'rb');
		if( $fp === false ) {
			$this->error_code = 'fs/1';
			$this->error_string = 'could not input read file';
			return $this->_error();
		}
		$tmp = $this->_cmd( 'medium custom preview upload', $opts, $fp );
		fclose($fp);
		return $tmp;
	}

	public function MediumCustomPreviewRemove( $media_id, $cp_id=0 ) {
		return $this->_cmd( 'medium custom preview remove', array( 'media_id'=>$media_id, 'cp_id'=>$cp_id ) );
	}

	public function MediumCustomPreviewGet( $options, $stream=true, $filename=null ) {
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'medium custom preview get', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options, 60 );
		return $tmp;
	}


	public function MediumXFDFInfo( $media_id, $version=0, $with_xfdf=false, $with_actual_annots_count=false ) {
		$opts = array( 'media_id'=>$media_id );
		if( $version>0 ) $opts['version'] = $version;
		if( $with_xfdf ) $opts['with_xfdf'] = '1';
		if( $with_actual_annots_count ) $opts['with_actual_annots_count'] = '1';
		$tmp = $this->_cmd('medium xfdf info',$opts);
		if( $tmp===false ) return false;
		return $tmp;
	}

	public function MediumXFDFGet( $media_id, $version=0, $if_modified_since=null, $if_none_match=null ) {
		$opts = array( 'media_id'=>$media_id );
		if( $version>0 ) $opts['version'] = $version;
		if( $if_modified_since!==null && $if_modified_since>0 ) $opts['if_modified_since'] = $if_modified_since;
		if( $if_none_match!==null ) {
			if( $if_none_match===true ) $opts['if_none_match'] = '*';
			else $opts['if_none_match'] = implode(',',$if_none_match);
		}
		$tmp = $this->_cmd('medium xfdf get',$opts);
		if( $tmp===false ) return false;
		return $tmp;
	}

	public function MediumXFDFPut( $media_id, $xfdf, $update_embedded=false ) {
		$opts = array( 'media_id'=>$media_id, 'xfdf'=>$xfdf );
		if( $update_embedded ) $opts['update_embedded'] = '1';
		$tmp = $this->_cmd('medium xfdf put',$opts);
		if( $tmp===false ) return false;
		return $tmp;
	}

	public function MediumXFDFMerge( $media_id, $action, $annotation_id, $parent_author_id, $xfdf, $update_embedded=false ) {
		$opts = array( 'media_id'=>$media_id, 'action'=>$action, 'annotation_id'=>$annotation_id, 'parent_author_id'=>$parent_author_id, 'xfdf'=>$xfdf );
		if( $update_embedded ) $opts['update_embedded'] = '1';
		$tmp = $this->_cmd('medium xfdf merge',$opts);
		if( $tmp===false ) return false;
		return $tmp;
	}

	public function MediumClone( $from_media_id, $to_media_id=null ) {//, $delete_from=false ) {
		$opts = array( 'from_media_id'=>$from_media_id );
		if( $to_media_id!==null ) $opts['to_media_id'] = $to_media_id;
		//if( $delete_from ) $opts['delete_from'] = '1';
		$tmp = $this->_cmd('medium clone',$opts);
		if( $tmp===false ) return false;
		return $tmp;
	}

	public function MediumStructureInfo( $media_id, $page=0, $box=null, $options=array() ) {
		$opts = $options;
		$opts['media_id'] = $media_id;
		if( $page>0 ) $opts['page'] = $page;
		if( $box!==null ) $opts['box'] = $box;
		$tmp = $this->_cmd( 'medium structure info', $opts );
		if( $tmp===false ) return false;
		$result = array();
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,5); // key parts
			if( $kp[0]==='p' ) {
				$pageno = $kp[1];
				if( !isset($result[$pageno]) ) $result[$pageno] = array( 'page'=>$pageno, 'media_box'=>'', 'ref_box'=>'', 'elements'=>array() );
				switch( $kp[2] ) {
				case 'ref_box':
					$result[$pageno]['ref_box'] = x_explode2floats(' ',$v);
					break;
				case 'media_box':
					$result[$pageno]['media_box'] = x_explode2floats(' ',$v);
					break;
				case $kp[2]==='e':
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

	public function MediaStructureCompare( $media_id_a, $media_id_b, $page_a, $page_b, $box_a, $box_b, $options=array() ) {
		//$opts = array_merge( $options, array('media_id_a'=>$media_id_a,'media_id_b'=>$media_id_b,'page_a'=>$page_a,'page_b'=>$page_b,'box_a'=>$box_a,'box_b'=>$box_b) );
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
		$page_diffs = array();
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,5); // key parts
			if( $kp[0]==='pd' ) { // pd.
				$pd_key = $kp[1]; // pd.<page_a>:<page_b>.
				if( !isset($page_diffs[$pd_key]) ) $page_diffs[$pd_key] = array('elem_diffs'=>array());
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
					if( $kp[3]==='media_box' || $kp[3]==='ref_box' ) $page_diffs[$pd_key]['info_a'][$kp[3]] = x_explode2floats(' ',$v);
					else $page_diffs[$pd_key]['info_a'][$kp[3]] = $v;
					break;
				case 'b':
					if( $kp[3]==='media_box' || $kp[3]==='ref_box' ) $page_diffs[$pd_key]['info_b'][$kp[3]] = x_explode2floats(' ',$v);
					else $page_diffs[$pd_key]['info_b'][$kp[3]] = $v;
					break;
				case 'ed':
					$ed_idx = (int)$kp[3];
					if( $kp[4]==='bbox_a' || $kp[4]==='bbox_b' ) $page_diffs[$pd_key]['elem_diffs'][$ed_idx][$kp[4]] = x_explode2floats(' ',$v);
					else $page_diffs[$pd_key]['elem_diffs'][$ed_idx][$kp[4]] = $v;
					break;
				}
			}
		}
		$result['page_diffs'] = $page_diffs;
		return $result;
	}

	public function MediaInfo( $media_ids, $meta_ids=null ) {
		if( !is_array($media_ids) ) $media_ids = array($media_ids);
		$p = array( 'media_ids'=>implode(',',$media_ids) );
		if( is_array($meta_ids) && count($meta_ids) ) $p['meta_ids'] = implode(',',$meta_ids);
		else if( $meta_ids!==null ) $p['meta_ids'] = $meta_ids;
		$tmp = $this->_cmd( 'media info', $p );
		if( $tmp===false ) return false;
		$m = array();
		$c = array();
		$vn = array();
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,4); // key parts
			if( $k[0]==='m' ) $m[$kp[1]][$kp[2]][$kp[3]] = $v;
			else if( $k[0]==='c' ) $c[$kp[1]][$kp[2]] = x_explode(',',$v);
			else if( $k[0]==='v' ) $vn[$kp[1]] = $v;
		}
		$this->_sort_metadata_values($m);
		return array('meta'=>$m,'categories'=>$c,'versions'=>$vn);
	}

	public function MediaMetaList( $media_ids, $meta_ids=null ) {
		if( !is_array($media_ids) ) $media_ids = array($media_ids);
		$p = array( 'media_ids'=>implode(',',$media_ids) );
		if( is_array($meta_ids) && count($meta_ids) ) $p['meta_ids'] = implode(',',$meta_ids);
		else if( $meta_ids!==null ) $p['meta_ids'] = $meta_ids;
		//throw new Exception('media meta list (media_ids='.implode(',',$media_ids).'; meta_ids='.implode(',',$meta_ids).')');
		$tmp = $this->_cmd( 'media meta list', $p );
		if( $tmp===false ) return false;
		$m = array();
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,3); // key parts
			$m[$kp[0]][$kp[1]][$kp[2]] = $v;
		}
		$this->_sort_metadata_values($m);
		return $m;
	}

	public function MediaCategoriesList( $media_ids ) {
		if( !is_array($media_ids) ) $media_ids = array($media_ids);
		$tmp = $this->_cmd( 'media categories list', array('media_ids'=>implode(',',$media_ids)) );
		if( $tmp===false ) return false;
		$c = array();
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,2); // key parts
			$c[$kp[0]][$kp[1]] = x_explode(',',$v);
		}
		return $c;
	}

	public function MediaUpdate( $media_ids, $meta, $categories, $do_not_create_version=false ) {
		if( !is_array($media_ids) ) $media_ids = array($media_ids);
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

	public function MediaMetaAdd( $media_ids, $meta, $filter_duplicates=false ) {
		if( !is_array($media_ids) ) $media_ids = array($media_ids);
		$p = array( 'media_ids'=>implode(',',$media_ids) );
		foreach( $meta as $meta_id => $info ) {
			foreach( $info as $idx => $data ) {
				$p['meta.'.$meta_id.'.'.$idx] = $data;
			}
		}
		if( $filter_duplicates ) $p['filter_duplicates'] = '1';
		return $this->_cmd( 'media meta add', $p );
	}

	public function MediaCategoriesAdd( $media_ids, $categories ) {
		if( !is_array($media_ids) ) $media_ids = array($media_ids);
		$p = array( 'media_ids'=>implode(',',$media_ids) );
		foreach( $categories as $tree_id => $cat_ids ) {
			$p['cat.'.$tree_id] = is_array($cat_ids) ? implode(',',$cat_ids) : (string)$cat_ids;
		}
		return $this->_cmd( 'media categories add', $p );
	}

	public function MediaStatus( $media_ids ) {
		if( !is_array($media_ids) ) $media_ids = array($media_ids);
		$tmp = $this->_cmd( 'media status', array('media_ids'=>implode(',',$media_ids)) );
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function MediaDelete( $media_ids, $rebuild_temp_file_references=false, $defered=false ) {
		if( !is_array($media_ids) ) $media_ids = array($media_ids);
		$p = array('media_ids'=>implode(',',$media_ids));
		if( $rebuild_temp_file_references ) $p['rebuild_temp_file_references'] = '1';
		if( $defered ) $p['defered'] = '1';
		$tmp = $this->_cmd( 'media delete', $p );
		if( $tmp===false ) return false;
		$tmp['media_ids'] = isset($tmp['media_ids']) ? x_explode(',',$tmp['media_ids']) : array();
		return $tmp;
	}

	public function MediaDownloadsList( $media_ids, $flt_user_id=0 ) {
		if( !is_array($media_ids) ) $media_ids = array($media_ids);
		$p = array('media_ids'=>implode(',',$media_ids));
		$p['flt_user_id'] = $flt_user_id;
		$tmp = $this->_cmd( 'media downloads list', $p );
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function MediaPluginList( $suffix=null ) {
		$par = $suffix===null ? array() : array('suffix'=>$suffix);
		$tmp = $this->_cmd('media plugin list',$par);
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function MetaPluginList( $name=null ) {
		$par = $name===null ? array() : array('name'=>$name);
		$tmp = $this->_cmd('meta plugin list',$par);
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function CodecPluginList( $name=null ) {
		$par = $name===null ? array() : array('name'=>$name);
		$tmp = $this->_cmd('codec plugin list',$par);
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function MetaGroupsList( $add_id_key=false, $cvt_values_to_int=false ) {
		$tmp = $this->_cmd('meta groups list');
		if( $tmp===false ) return false;
		$tmp1 = $this->_parse_dot_syntax($tmp);
		uasort($tmp1,'_mj_cmp_items_by_sort_key');
		if( $add_id_key ) {
			foreach( $tmp1 as $id=>&$info ) $info['id'] = $id;
			unset($info);
		}
		if( $cvt_values_to_int ) {
			foreach( $tmp1 as $id=>&$info ) {
				$info['id'] = (int)$info['id'];
				$info['auto'] = (int)$info['auto'];
				$info['user'] = (int)$info['user'];
				$info['sort'] = (int)$info['sort'];
				//$tmp1[$id] = $info;
			}
			//unset($info);
		}
		return $tmp1;
	}
	public function MetaGroupMove( $group_id, $after_id ) {
		return $this->_cmd('meta group move',array('group_id'=>$group_id,'after_id'=>$after_id));
	}
	
	public function MetaDefsList( $meta_ids=null, $group_id=null, $with_options=false, $cvt_values_to_int=false ) {
		$p = array('options'=>$with_options?'1':'');
		if( $meta_ids !== null ) $p['meta_ids'] = implode(',',$meta_ids);
		if( $group_id !== null ) $p['group_id'] = $group_id;
		$tmp = $this->_cmd('meta defs list',$p);
		if( $tmp===false ) return false;
		$r = array();
		if( $with_options ) {
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,3);
				if( isset($ka[1]) ) {
					$meta_id = $ka[1];
					if( $ka[0]==='o' ) $r[$meta_id]['options'][$ka[2]] = $v;		// o.<meta_id>.<opt_name>
					else $r[$meta_id][$ka[0]] = $v;									// xx.<meta_id>
				}
			}
			foreach( $r as $k=>&$v ) {
				// add an 'id' key
				$v['id'] = $k;
				// add empty options array, if it does not exist yet
				if( !isset($v['options']) ) $v['options'] = array();
			}
			unset($v);
		} else {
			$r = $this->_parse_dot_syntax($tmp);
		}
		if( $cvt_values_to_int ) {
			foreach( $r as $k=>&$v ) {
				$v['id'] = (int)$v['id'];
				$v['group'] = (int)$v['group'];
				$v['type'] = (int)$v['type'];
				$v['list'] = (int)$v['list'];
				$v['relevance'] = (int)$v['relevance'];
				$v['auto'] = (int)$v['auto'];
				$v['user'] = (int)$v['user'];
				$v['intr'] = (int)$v['intr'];
				$v['sort'] = (int)$v['sort'];
				$v['usre'] = (int)$v['usre'];
				$v['rdup'] = (int)$v['rdup'];
				//$r[$k] = $v;
			}
			//unset($v);
		}
		return $r;
	}
	public function MetaDefMove( $meta_id, $after_id ) {
		return $this->_cmd('meta def move',array('meta_id'=>$meta_id,'after_id'=>$after_id));
	}
	public function MetaDefOptionsSet( $meta_id, $options ) {
		$p = array('meta_id'=>$meta_id);
		foreach( $options as $k=>$v ) $p['o.'.$k] = $v;
		return $this->_cmd('meta def options set',$p);
	}

	public function MetaModifiedGet() {
		return $this->_cmd('meta modified get');
	}

	public function MetaSearch( $meta_id, $value, $op='e' ) {
		$p = array( 'meta_id'=>$meta_id, 'value'=>$value, 'op'=>$op );
		$tmp = $this->_cmd('meta search',$p);
		if( $tmp===false ) return false;
		if( !isset($tmp['media_ids']) || !isset($tmp['media_ids'][0]) ) return array();
		return x_explode(',',$tmp['media_ids']);
	}

	public function UserdataGet( $user_id=0, $parameters=null ) {
		$p = array();
		if( $user_id ) $p['user_id'] = $user_id;
		if( $parameters===null ) {
			// get all keys
			return $this->_cmd('userdata get',$p);
		} else if( is_array($parameters) ) {
			// fixed list of keys
			$p['parameters'] = implode(',',$parameters);
			return $this->_cmd('userdata get',$p);
		} else {
			// get single key only
			$p['parameters'] = $parameters;
			$tmp = $this->_cmd('userdata get',$p);
			if( $tmp===false ) return false;
			return isset($tmp[$parameters]) ? $tmp[$parameters] : ''; 
		}
	}

	public function UserdataGetLike( $user_id=0, $pattern='%' ) {
		$p = array();
		if( $user_id ) $p['user_id'] = $user_id;
		$p['parameters_like'] = $pattern;
		return $this->_cmd('userdata get',$p);
	}

	public function UserdataSet( $user_id=0, $key_value_pairs=array() ) {
		$p = $key_value_pairs;
		if( $user_id ) $p['user_id'] = $user_id;
		return $this->_cmd('userdata set',$p);
	}

	public function UserdataGetByLogin( $login, $parameters=null ) {
		$p = array();
		$p['login'] = $login;
		if( $parameters===null ) {
			// get all keys
			return $this->_cmd('userdata get',$p);
		} else if( is_array($parameters) ) {
			// fixed list of keys
			$p['parameters'] = implode(',',$parameters);
			return $this->_cmd('userdata get',$p);
		} else {
			// get single key only
			$p['parameters'] = $parameters;
			$tmp = $this->_cmd('userdata get',$p);
			if( $tmp===false ) return false;
			return isset($tmp[$parameters]) ? $tmp[$parameters] : '';
		}
	}

	public function UserActiveCount( $user_id=0 ) {
		$tmp = $this->_cmd('user active count',array('user_id'=>$user_id));
		if( $tmp===false ) return false;
		return $tmp['count'];
	}

	
	public function ClientdataGet( $parameters=null, $client_id=null, $lock_keys=false ) {
		if( $client_id===null ) $client_id = $this->cfg_client_id;
		$p = array( 'client_id' => $client_id );
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
	public function ClientdataSet( $key_value_pairs=array(), $client_id=null, $lock_keys=false ) {
		if( $client_id===null ) $client_id = $this->cfg_client_id;
		$p = $key_value_pairs;
		$p['client_id'] = $client_id;
		if( $lock_keys ) $p['lock_keys'] = '1';
		return $this->_cmd('clientdata set',$p);
	}
	public function ClientdataRemove( $parameters, $client_id=null ) {
		if( $client_id===null ) $client_id = $this->cfg_client_id;
		return $this->_cmd('clientdata remove',array('client_id'=>$client_id,'parameters'=>implode(',',$parameters)));
	}


	public function RightsMediaGet( $media_ids, $action, $bool_result=false ) {
			$tmp = $this->_cmd('rights media get',array('media_ids'=>implode(',',$media_ids),'action'=>$action));
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		if( $bool_result ) {
			foreach( $tmp as $mid=>&$inf ) $inf = $inf['allowed']==='1';
		}
		return $tmp;
	}
	public function RightsMediumGet( $media_id, $action ) {
			$tmp = $this->_cmd('rights media get',array('media_ids'=>$media_id,'action'=>$action));
		if( $tmp===false ) return false;
		$k = 'allowed.'.$media_id;
		return isset($tmp[$k]) && $tmp[$k]==='1';
	}
	public function UserRightsMediaGet( $user_id, $media_ids, $action, $bool_result=false ) {
		$tmp = $this->_cmd('user rights media get',array('user_id'=>$user_id,'media_ids'=>implode(',',$media_ids),'action'=>$action));
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		if( $bool_result ) {
			foreach( $tmp as $mid=>&$inf ) $inf = $inf['allowed']==='1';
		}
		return $tmp;
	}
	public function GroupRightsMediaGet( $group_id, $media_ids, $action, $bool_result=false ) {
		$tmp = $this->_cmd('group rights media get',array('group_id'=>$group_id,'media_ids'=>implode(',',$media_ids),'action'=>$action));
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		if( $bool_result ) {
			foreach( $tmp as $mid=>&$inf ) $inf = $inf['allowed']==='1';
		}
		return $tmp;
	}

	public function RightsCategoriesGet( $cat_ids, $action, $bool_result=false ) {
		$tmp = $this->_cmd('rights categories get',array('cat_ids'=>implode(',',$cat_ids),'action'=>$action));
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		if( $bool_result ) {
			foreach( $tmp as $cid=>&$inf ) $inf = $inf['allowed']==='1';
		}
		return $tmp;
	}
	public function RightsListGet( $list_id, $offset, $count, $action ) {
		$tmp = $this->_cmd('rights list get',array('list_id'=>$list_id,'offset'=>$offset,'count'=>$count,'action'=>$action));
		if( $tmp===false ) return false;
		if( isset($tmp['allowed']) ) $tmp['allowed'] = x_explode(',',$tmp['allowed']);
		return $tmp;
	}
	public function RightsListGet2( $list_id, $offset, $count, $actions ) {
		$tmp = $this->_cmd('rights list get 2',array('list_id'=>$list_id,'offset'=>$offset,'count'=>$count,'actions'=>implode(',',$actions)));
		if( $tmp===false ) return false;
		$r = array();
		foreach( $tmp as $k=>$v ) {
			$k = explode('.',$k,2);
			if( $k[0]==='allowed' ) $r[$k[1]] = x_explode(',',$v);
		}
		return $r;
	}
	public function RightsTreeGet( $action ) {
		$tmp = $this->_cmd('rights tree get',array('action'=>$action));
		if( $tmp===false ) return false;
		$tmp['categories'] = x_explode(',',$tmp['categories']);
		return $tmp;
	}


	public function ArchiveCreate( $media_items, $lifetime, $intent='' ) {
		$media_ids = array();
		$dlf_ids = array();
		$filenames = array();
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
	public function ArchiveReady( $archive_id ) {
		return $this->_cmd('archive ready',array('archive_id'=>$archive_id));
	}
	public function ArchiveGet( $archive_id, $stream=true, $filename=null ) {
		$options = array('archive_id'=>$archive_id);
		if( $stream ) {
			$options['exit'] = '1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'archive get', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options );
		return $tmp;
	}

	public function DownloadFormatList( $with_options, $filter_media_class=null ) {
		$tmp = $this->_cmd('download format list',array('options'=>$with_options?'1':''));
		if( $tmp===false ) return false;
		$r = array();
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
			if( $with_options && !isset($v['options']) ) $v['options'] = array();
		}
		unset($v);
		if( $filter_media_class!==null ) {
			$r2 = array();
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
		return x_explode(',',$tmp['actions']);
	}
	public function DownloadFormatGet( $dlf_id, $with_options ) {
		$tmp = $this->_cmd('download format get',array('dlf_id'=>$dlf_id,'options'=>$with_options?'1':''));
		if( $tmp===false ) return false;
		$r = array();
		foreach( $tmp as $k=>$v ) {
			$ka = explode('.',$k,2);
			if( isset($ka[1]) ) $r['options'][$ka[1]] = $v;
			else $r[$k] = $v;
		}
		return $r;
	}
	public function DownloadFormatAdd( $dlf ) {
		$para = array();
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
	public function DownloadFormatUpdate( $dlf_id, $dlf ) {
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
	public function DownloadFormatDelete( $dlf_id ) {
		return $this->_cmd('download format delete',array('dlf_id'=>$dlf_id));
	}


	public function ColorProfileList() {
		$tmp = $this->_cmd('color profile list');
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax($tmp);
		uasort($tmp,'_mj_cmp_color_profiles');
		return $tmp;
	}
	public function ColorIntentList() {
		$tmp = $this->_cmd('color intent list');
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}



	public function PreviewFormatList( $with_options, $filter_media_class=null ) {
		$tmp = $this->_cmd('preview format list',array('options'=>$with_options?'1':''));
		if( $tmp===false ) return false;
		$r = array();
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
			if( $with_options && !isset($v['options']) ) $v['options'] = array();
		}
		unset($v);
		if( $filter_media_class!==null ) {
			$r2 = array();
			foreach( $r as $k=>&$v ) {
				if( $v['media_class']==$filter_media_class ) $r2[$k] = $v;
			}
			//unset($v);
			return $r2;
		}
		return $r;
	}
	public function PreviewFormatGet( $pf_id, $with_options ) {
		$tmp = $this->_cmd('preview format get',array('pf_id'=>$pf_id,'options'=>$with_options?'1':''));
		if( $tmp===false ) return false;
		$r = array();
		foreach( $tmp as $k=>$v ) {
			$ka = explode('.',$k,2);
			if( isset($ka[1]) ) $r['options'][$ka[1]] = $v;
			else $r[$k] = $v;
		}
		return $r;
	}

	public function PreviewFormatAdd( $pf ) {
		$para = array();
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

	public function PreviewFormatUpdate( $pf_id, $pf ) {
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

	public function PreviewFormatDelete( $pf_id ) {
		return $this->_cmd('preview format delete',array('pf_id'=>$pf_id));
	}


	public function MediumPreviewFormatList( $media_id, $with_options=false, $filter_media_class=null ) {
		$par = array('media_id'=>$media_id,'options'=>$with_options?'1':'');
		if( $filter_media_class!==null ) $par['flt_media_class'] = $filter_media_class;
		$tmp = $this->_cmd('medium preview format list',$par);
		if( $tmp===false ) return false;
		$ktrans = array(	'mc'=>'media_class', 'so'=>'sort', 'fs'=>'file_size', 'fo'=>'format', 'na'=>'name',
							'vw'=>'vid_width', 'vh'=>'vid_height', 'vd'=>'vid_duration', 'vf'=>'vid_framerate',
							'pw'=>'pic_width', 'ph'=>'pic_height', 'pt'=>'pic_time',
							'ad'=>'aud_duration', 'ac'=>'aud_channels', 'as'=>'aud_samplerate',
					);
		$r = array();
		if( $with_options ) {
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,3);
				if( isset($ka[1]) ) {
					$ka0 = $ka[0];
					if( $ka0==='o' ) $r[$ka[1]]['options'][$ka[2]] = $v;
					else $r[$ka[1]][isset($ktrans[$ka0])?$ktrans[$ka0]:$ka0] = $v;
				}
			}
			// add empty options array, if it does not exist yet
			foreach( $r as $k=>&$v ) {
				if( !isset($v['options']) ) $v['options'] = array();
			}
			unset($v);
		} else {
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,2);
				if( isset($ka[1]) ) {
					$ka0 = $ka[0];
					$r[$ka[1]][isset($ktrans[$ka0])?$ktrans[$ka0]:$ka0] = $v;
				}
			}
		}
		if( $this->server_version < 2.4 && $filter_media_class!==null ) {
			// compatibility version for mj < 2.4
			$r2 = array();
			foreach( $r as $k=>$v ) {
				if( $v['media_class']==$filter_media_class ) $r2[$k] = $v;
			}
			return $r2;
		}
		return $r;
	}

	public function MediaPreviewFormatList( $media_ids, $with_options=false, $filter_media_class=null ) {
		if( $this->server_version < 2.4 ) {
			// compatibility version for mj < 2.4
			$r = array();
			foreach( $media_ids as $mid ) {
				$tmp = $this->MediumPreviewFormatList($mid,$with_options,$filter_media_class);
				if( $tmp===false ) return false;
				$r[$mid] = $tmp;
			}
			return $r;
		}
		// mj 2.4
		$par = array('media_ids'=>implode(',',$media_ids),'options'=>$with_options?'1':'');
		if( $filter_media_class!==null ) $par['flt_media_class'] = $filter_media_class;
		$tmp = $this->_cmd('media preview format list',$par);
		if( $tmp===false ) return false;
		$ktrans = array(	'mc'=>'media_class', 'so'=>'sort', 'fs'=>'file_size', 'fo'=>'format', 'na'=>'name',
							'vw'=>'vid_width', 'vh'=>'vid_height', 'vd'=>'vid_duration', 'vf'=>'vid_framerate',
							'pw'=>'pic_width', 'ph'=>'pic_height', 'pt'=>'pic_time',
							'ad'=>'aud_duration', 'ac'=>'aud_channels', 'as'=>'aud_samplerate',
					);
		$r = array();
		if( $with_options ) {
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,4);
				if( isset($ka[1]) ) {
					$ka0 = $ka[0];
					if( $ka0==='o' ) $r[$ka[2]][$ka[1]]['options'][$ka[3]] = $v;
					else $r[$ka[2]][$ka[1]][isset($ktrans[$ka0])?$ktrans[$ka0]:$ka0] = $v;
				}
			}
			// add empty options array, if it does not exist yet
			foreach( $r as $m=>&$fs ) {
				foreach( $fs as $k=>&$v ) {
					if( !isset($v['options']) ) $v['options'] = array();
				}
				unset($v);
			}
			unset($fs);
		} else {
			foreach( $tmp as $k=>$v ) {
				$ka = explode('.',$k,3);
				if( isset($ka[1]) ) {
					$ka0 = $ka[0];
					$r[$ka[2]][$ka[1]][isset($ktrans[$ka0])?$ktrans[$ka0]:$ka0] = $v;
				}
			}
		}
		if( $this->server_version < 2.4 && $filter_media_class!==null ) {
			// compatibility version for mj < 2.4
			$r2 = array();
			foreach( $r as $m=>$fs ) {
				foreach( $fs as $k=>$v ) {
					if( $v['media_class']==$filter_media_class ) $r2[$m][$k] = $v;
				}
			}
			return $r2;
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

	public function FolderCategoryList( $cat_id, $compact_result=false ) {
		$tmp = $this->_cmd('folder category list',array('cat_id'=>$cat_id,'compact_format'=>'1'));
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax_and_prepare_category_node_list($tmp,$compact_result);
	}

	public function FolderGet( $cat_id, $compact_result=false ) {
		$tmp = $this->_cmd('folder get',array('cat_id'=>$cat_id,'compact_format'=>'1'));
		if( $tmp===false ) return false;
		$tmp1 = $this->_parse_dot_syntax_and_prepare_category_node_list($tmp,$compact_result);
		return isset($tmp1[$cat_id]) ? $tmp1[$cat_id] : false;
	}

	public function FolderMediaList( $cat_id, $offset, $count, $meta_ids=null ) {
		$par = array( 'cat_id'=>$cat_id, 'offset'=>$offset, 'count'=>$count );
		if( is_array($meta_ids) && count($meta_ids) ) $par['meta_ids'] = implode(',',$meta_ids);
		$tmp = $this->_cmd( 'folder media list', $par );
		if( $tmp===false ) return false;
		// index media-ids by position in global list
		$mids = array();
		if( (int)$tmp['result_count'] > 0 ) {
			$idx = $offset;
			foreach( explode(',',$tmp['result']) as $id ) $mids[$idx++] = $id;
		}
		// meta-data
		$m = array();
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

	public function FolderMediaGet( $cat_id, $filename, $meta_ids=null ) {
		$par = array( 'cat_id'=>$cat_id, 'filename'=>$filename );
		if( is_array($meta_ids) && count($meta_ids) ) $par['meta_ids'] = implode(',',$meta_ids);
		$tmp = $this->_cmd( 'folder media get', $par );
		if( $tmp===false ) return false;
		$mids = array();
		if( (int)$tmp['result_count'] > 0 ) $mids = explode(',',$tmp['result']);
		// meta-data
		$m = array();
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

	public function ExportChannelsList() {
		$tmp = $this->_cmd('export channels list');
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax($tmp);
	}

	public function ExportQueueAddMedia( $channel_id, $media_ids, $formats, $filenames ) {
		if( is_array($media_ids) ) $media_ids = implode(',',$media_ids);
		if( is_array($formats) ) $formats = implode(',',$formats);
		if( is_array($filenames) ) $filenames = implode(':',$filenames);
		return $this->_cmd('export queue add media',array('channel_id'=>$channel_id,'media_ids'=>$media_ids,'formats'=>$formats,'filenames'=>$filenames));
	}



	//--------------------------------------------------------------------------
	// VERSIONING COMMANDS
	//--------------------------------------------------------------------------

	public function VersionMediumListVersions( $media_id ) {
		$tmp = $this->_cmd('version medium list versions',array('media_id'=>$media_id));
		if( $tmp===false ) return false;
		if( isset($tmp['versions']) ) $tmp['versions'] = x_explode(',',$tmp['versions']);
		return $tmp;
	}

	public function VersionMediumGet( $media_id, $version, $stream=true, $filename=null, $options=array() ) {
		$options['media_id'] = $media_id;
		$options['version'] = $version;
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'version medium get', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options );
		return $tmp;
	}

	public function VersionMediumXmpGet( $media_id, $version, $stream=true, $filename=null, $options=array() ) {
		$options['media_id'] = $media_id;
		$options['version'] = $version;
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'version medium xmp get', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options );
		return $tmp;
	}

	public function VersionMediumInfo( $media_id, $version, $meta_ids=null ) {
		$p = array('media_id'=>$media_id,'version'=>$version);
		if( is_array($meta_ids) && count($meta_ids) ) $p['meta_ids'] = implode(',',$meta_ids);
		else if( $meta_ids!==null ) $p['meta_ids'] = $meta_ids;
		$tmp = $this->_cmd('version medium info',$p);
		if( $tmp===false ) return false;
		$m = array();
		$c = array();
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,3); // key parts
			if( $k[0]==='m' ) $m[$kp[1]][$kp[2]] = $v;
			else if( $k[0]==='c' ) $c[$kp[1]] = x_explode(',',$v);
		}
		$this->_sort_metadata_values1($m);
		return array('meta'=>$m,'categories'=>$c,'is_latest_version'=>isset($tmp['is_latest_version'])&&$tmp['is_latest_version']==='1');
	}

	public function VersionMediumPreview( $options, $stream=true, $filename=null ) {
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'version medium preview', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options );
		return $tmp;
	}

	public function VersionMediumCustomPreviewGet( $options, $stream=true, $filename=null ) {
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'version medium custom preview get', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options, 60 );
		return $tmp;
	}

	public function VersionMediumPromoteVersion( $media_id, $version, $comment=null ) {
		$para = array( 'media_id'=>$media_id, 'version'=>$version );
		if( $comment!==null ) $para['version_comment'] = $comment;
		$tmp = $this->_cmd( 'version medium promote version', $para );
		return $tmp===false ? false : true;
	}

	public function VersionMediumDimensions( $media_id, $version, $page, $box=null ) {
		$opts = array('media_id'=>$media_id,'version'=>$version,'page'=>$page);
		if( $box!==null ) $opts['box'] = $box;
		return $this->_cmd( 'version medium dimensions', $opts );
	}
	
	
	public function VersionMediaStructureCompare( $media_id_a, $media_id_b, $version_a, $version_b, $page_a, $page_b, $box_a, $box_b, $options=array() ) {
		//$opts = array_merge( $options, array('media_id_a'=>$media_id_a,'media_id_b'=>$media_id_b,'version_a'=>$version_a,'version_b'=>$version_b,'page_a'=>$page_a,'page_b'=>$page_b,'box_a'=>$box_a,'box_b'=>$box_b) );
		$opts = array('media_id_a'=>$media_id_a,'media_id_b'=>$media_id_b,'version_a'=>$version_a,'version_b'=>$version_b,'page_a'=>$page_a,'page_b'=>$page_b,'box_a'=>$box_a,'box_b'=>$box_b) + $options;
		$tmp = $this->_cmd( 'version media structure compare', $opts );
		if( $tmp===false ) return false;
		// result structure: exactly as in MediaStructureCompare() !
		$result = array(
				'npages_different'	=> $tmp['dd.npages_different']==='1',
				'document_a'		=> array( 'npages'=>$tmp['dd.a.npages'] ),
				'document_b'		=> array( 'npages'=>$tmp['dd.b.npages'] ),
		);
		$page_diffs = array();
		foreach( $tmp as $k=>$v ) {
			$kp = explode('.',$k,5); // key parts
			if( $kp[0]==='pd' ) { // pd.
				$pd_key = $kp[1]; // pd.<page_a>:<page_b>.
				if( !isset($page_diffs[$pd_key]) ) $page_diffs[$pd_key] = array('elem_diffs'=>array());
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
					if( $kp[3]==='media_box' || $kp[3]==='ref_box' ) $page_diffs[$pd_key]['info_a'][$kp[3]] = x_explode2floats(' ',$v);
					else $page_diffs[$pd_key]['info_a'][$kp[3]] = $v;
					break;
				case 'b':
					if( $kp[3]==='media_box' || $kp[3]==='ref_box' ) $page_diffs[$pd_key]['info_b'][$kp[3]] = x_explode2floats(' ',$v);
					else $page_diffs[$pd_key]['info_b'][$kp[3]] = $v;
					break;
				case 'ed':
					$ed_idx = (int)$kp[3];
					if( $kp[4]==='bbox_a' || $kp[4]==='bbox_b' ) $page_diffs[$pd_key]['elem_diffs'][$ed_idx][$kp[4]] = x_explode2floats(' ',$v);
					else $page_diffs[$pd_key]['elem_diffs'][$ed_idx][$kp[4]] = $v;
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
		return $this->_cmd( 'lock release', array('media_id'=>$media_id) ) === false ? false : true;
	}

	public function LockGet( $media_id ) {
		return $this->_cmd( 'lock get', array('media_id'=>$media_id) );
	}

	public function LockReleaseAll( $media_id ) {
		return $this->_cmd( 'lock release all' ) === false ? false : true;
	}

	//--------------------------------------------------------------------------
	// ADMIN COMMANDS
	//--------------------------------------------------------------------------

	public function GroupList() {
		$tmp = $this->_cmd('group list');
		if( $tmp===false ) return false;
		$groups = $this->_parse_dot_syntax($tmp);
		foreach( $groups as $k=>&$v ) $v['roles'] = x_explode(',',$v['roles']);
		//unset($v);
		ksort($groups);
		return $groups;
	}
	public function GroupAdd( $name, $roles ) {
		$roles = implode(',',$roles);
		return $this->_cmd('group add',array('name'=>$name,'roles'=>$roles));
	}
	public function GroupDelete( $group_id ) {
		return $this->_cmd('group delete',array('group_id'=>$group_id));
	}
	public function GroupUpdate( $group_id, $name, $roles ) {
		$roles = implode(',',$roles);
		return $this->_cmd('group update',array('group_id'=>$group_id,'name'=>$name,'roles'=>$roles));
	}


	public function UserList( $userdata=null, $with_roles=false ) {
		$opts = array();
		if( $userdata!==null && $userdata!==false ) $opts['userdata'] = $userdata===true ? '1' : implode(',',$userdata);
		if( $with_roles===true ) $opts['with_roles'] = '1';
		$tmp = $this->_cmd('user list',$opts);
		if( $tmp===false ) return false;
		$users = $this->_parse_dot_syntax($tmp);
		foreach( $users as $k=>&$v ) {
			$v['active'] = $v['active']==='1';
			$v['groups'] = x_explode(',',$v['groups']);
		}
		//unset($v);
		if( $with_roles===true ) {
			foreach( $users as $k=>&$v ) $v['roles'] = x_explode(',',$v['roles']);
			//unset($v);
		}
		if( $userdata!==null ) {
			// login, last_login, groups, expires, active, userdata=>array(..)
			foreach( $users as $k=>&$v ) {
				$u = array();
				$ud = array();
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
		return $users;
	}
	public function UserSearch( $fulltext, $match_partial=false, $userdata=null, $with_roles=false ) {
		$opts = array('fulltext'=>$fulltext);
		if( $match_partial===true ) $opts['match_partial'] = '1';
		if( $userdata!==null && $userdata!==false ) $opts['userdata'] = $userdata===true ? '1' : implode(',',$userdata);
		if( $with_roles===true ) $opts['with_roles'] = '1';
		$tmp = $this->_cmd('user search',$opts);
		if( $tmp===false ) return false;
		$users = $this->_parse_dot_syntax($tmp);
		foreach( $users as $k=>&$v ) {
			$v['active'] = $v['active']==='1';
			$v['groups'] = x_explode(',',$v['groups']);
		}
		//unset($v);
		if( $with_roles===true ) {
			foreach( $users as $k=>&$v ) $v['roles'] = x_explode(',',$v['roles']);
			//unset($v);
		}
		if( $userdata!==null ) {
			// login, last_login, groups, expires, active, userdata=>array(..)
			foreach( $users as $k=>&$v ) {
				$u = array();
				$ud = array();
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
		return $users;
	}
	public function UserGet( $user_id ) {
		$user = $this->_cmd('user get',array('user_id'=>$user_id));
		if( $user === false ) return false;
		$user['active'] = $user['active']==='1';
		$user['groups'] = x_explode(',',$user['groups']);
		return $user;
	}
	public function UserGetByLogin( $login ) {
		$user = $this->_cmd('user get',array('login'=>$login));
		if( $user === false ) return false;
		$user['active'] = $user['active']==='1';
		$user['groups'] = x_explode(',',$user['groups']);
		return $user;
	}
	public function UserExists( $user_id ) {
		return $this->_cmd('user exists',array('user_id'=>$user_id));
	}
	public function UserExistsByLogin( $login ) {
		return $this->_cmd('user exists',array('login'=>$login));
	}
	public function UserExistsByUserdataValue( $userdata_name, $userdata_value ) {
		return $this->_cmd('user exists',array('userdata_name'=>$userdata_name,'userdata_value'=>$userdata_value));
	}
	public function UserAdd( $login, $pass, $expires, $groups, $pass_is_md5=false, $active=true ) {
		$opts = array('login'=>$login,'pass'=>$pass,'expires'=>$expires,'active'=>$active?'1':'0','groups'=>implode(',',$groups));
		if( $pass_is_md5 ) $opts['pass_is_md5'] = '1';
		return $this->_cmd('user add',$opts);
	}
	public function UserDelete( $user_id ) {
		return $this->_cmd('user delete',array('user_id'=>$user_id));
	}
	public function UserUpdate( $user_id, $login, $expires, $groups, $active ) {
		return $this->_cmd('user update',array('user_id'=>$user_id,'login'=>$login,'expires'=>$expires,'active'=>$active?'1':'0','groups'=>implode(',',$groups)));
	}
	public function UserPassword( $user_id, $pass ) {
		return $this->_cmd('user password',array('user_id'=>$user_id,'pass'=>$pass));
	}
	public function UserLoginCheck( $login, $pass ) {
		$tmp = $this->_cmd('user login check',array('user'=>$login,'pass'=>$pass));
		if( $tmp===false ) return false;
		return $tmp['user_id'];
	}
	public function UserSessionCreate( $user_id ) {
		return $this->_cmd('user session create',array('user_id'=>$user_id));
	}
	public function UserSessionCreateByLogin( $login ) {
		return $this->_cmd('user session create',array('login'=>$login));
	}

	public function ACLItemList( $acl_id=null, $cat_id=null, $media_id=null ) {
		$p = array();
		if( $acl_id!==null ) $p['acl_id'] = $acl_id;
		else if( $cat_id!==null ) $p['cat_id'] = $cat_id;
		else if( $media_id!==null ) $p['media_id'] = $media_id;
		$tmp = $this->_cmd('acl item list',$p);
		if( $tmp===false ) return false;
		$acl_id = $tmp['acl_id'];
		unset($tmp['acl_id']);
		$acl_items = $this->_parse_dot_syntax($tmp);
		foreach( $acl_items as $k=>&$v ) $v['order'] = (int)$v['order'];
		//unset($v);
		uasort($acl_items,'_mj_cmp_acl_items');
		return array('acl_id'=>$acl_id,'acl_items'=>$acl_items);
	}
	public function ACLItemAdd( $acl_id,$cat_id,$media_id, $apply_to_group_id,$apply_to_user_id, $allow_deny, $action ) {
		$p = array();
		if( $acl_id!==null ) $p['acl_id'] = $acl_id;
		else if( $cat_id!==null ) $p['cat_id'] = $cat_id;
		else if( $media_id!==null ) $p['media_id'] = $media_id;
		if( $apply_to_group_id ) $p['group_id'] = $apply_to_group_id;
		else if( $apply_to_user_id ) $p['user_id'] = $apply_to_user_id;
		$p['allow_deny'] = $allow_deny;
		$p['action'] = $action;
		return $this->_cmd('acl item add',$p);
	}
	public function ACLItemDelete( $acl_item_id ) {
		return $this->_cmd('acl item delete',array('acl_item_id'=>$acl_item_id));
	}
	public function ACLItemMove( $acl_item_id, $after_id, $target_acl_id ) {
		return $this->_cmd('acl item move',array('acl_item_id'=>$acl_item_id,'after_id'=>$after_id,'target_acl_id'=>$target_acl_id));
	}
	public function ACLDelete( $acl_id=null, $cat_id=null, $media_id=null ) {
		$p = array();
		if( $acl_id!==null ) $p['acl_id'] = $acl_id;
		else if( $cat_id!==null ) $p['cat_id'] = $cat_id;
		else if( $media_id!==null ) $p['media_id'] = $media_id;
		return $this->_cmd('acl delete',$p);
	}
	public function ACLCopy( $from_media_id, $to_media_ids ) {
		return $this->_cmd('acl copy', array('from_media_id'=>$from_media_id,'to_media_ids'=>implode(',',$to_media_ids)) );
	}

	public function TreeAdd( $name, $visibility, $multifiling, $conjunct_mode=false ) {
		return $this->_cmd('tree add',array('name'=>$name,'visibility'=>$visibility,'multifiling'=>$multifiling,'conjunct_mode'=>$conjunct_mode));
	}
	public function TreeDelete( $tree_id ) {
		return $this->_cmd('tree delete',array('tree_id'=>$tree_id));
	}
	public function TreeUpdate( $tree_id, $name, $visibility, $multifiling, $conjunct_mode=false ) {
		return $this->_cmd('tree update',array('tree_id'=>$tree_id,'name'=>$name,'visibility'=>$visibility,'multifiling'=>$multifiling,'conjunct_mode'=>$conjunct_mode));
	}
	public function TreeList() {
		$tmp = $this->_cmd('tree list');
		if( $tmp===false ) return false;
		$tmp1 = $this->_parse_dot_syntax($tmp);
		ksort($tmp1);
		return $tmp1;
	}

	public function CategoryAdd( $name, $tree_id, $parent_id, $after_id, $sort_alphabetically_into_parent=false ) {
		$p = array('name'=>$name,'tree_id'=>$tree_id,'parent_id'=>$parent_id,'after_id'=>$after_id);
		// note: $after_id=0 -> sort as first node in parent, $after_id=-1 -> sort as last node in parent, every other $after_id must be a valid category id 
		if( $sort_alphabetically_into_parent ) $p['insert_alphabetically_sorted'] = '1';
		return $this->_cmd('category add',$p);
	}
	public function CategoryDelete( $cat_id ) {
		return $this->_cmd('category delete',array('cat_id'=>$cat_id));
	}
	public function CategoryUpdate( $cat_id, $name, $sort_alphabetically_into_parent=false ) {
		$p = array('cat_id'=>$cat_id,'name'=>$name);
		if( $sort_alphabetically_into_parent ) $p['insert_alphabetically_sorted'] = '1';
		return $this->_cmd('category update',$p);
	}
	public function CategoryMove( $cat_id, $parent_id, $after_id, $sort_alphabetically_into_parent=false ) {
		$p = array('cat_id'=>$cat_id,'parent_id'=>$parent_id,'after_id'=>$after_id);
		// note: $after_id=0 -> sort as first node in parent, $after_id=-1 -> sort as last node in parent, every other $after_id must be a valid category id
		if( $sort_alphabetically_into_parent ) $p['insert_alphabetically_sorted'] = '1';
		return $this->_cmd('category move',$p);
	}
	public function CategorySort( $cat_id, $recursive=false ) {
		return $this->_cmd('category sort',array('cat_id'=>$cat_id,'recursive'=>$recursive?'1':'0'));
	}
	public function CategoryList( $tree_id, $dont_filter=false, $compact_result=false ) {
		$p = array('tree_id'=>$tree_id,'compact_format'=>'1');
		if( $dont_filter ) $p['dont_filter'] = '1';
		$tmp = $this->_cmd('category list',$p);
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax_and_prepare_category_node_list($tmp,$compact_result);
	}
	public function CategoryParentsList( $cat_id, $compact_result=false ) {
		$p = array('cat_id'=>$cat_id,'compact_format'=>'1');
		$tmp = $this->_cmd('category parents list',$p);
		if( $tmp===false ) return false;
		return $this->_parse_dot_syntax_and_prepare_category_node_list($tmp,$compact_result);
	}

	public function CategoryCustomPreviewFromMedium( $cat_id, $media_id ) {
		return $this->_cmd( 'category custom preview from medium', array( 'cat_id'=>$cat_id, 'media_id'=>$media_id ) );
	}
	public function CategoryCustomPreviewUpload( $cat_id, $file ) {
		$opts = array( 'cat_id'=>$cat_id );
		$opts['payload'] = filesize($file);
		$fp = fopen($file,'rb');
		if( $fp === false ) {
			$this->error_code = 'fs/1';
			$this->error_string = 'could not input read file';
			return $this->_error();
		}
		$tmp = $this->_cmd( 'category custom preview upload', $opts, $fp );
		fclose($fp);
		return $tmp;
	}
	public function CategoryCustomPreviewRemove( $cat_id ) {
		return $this->_cmd( 'category custom preview remove', array( 'cat_id'=>$cat_id ) );
	}
	public function CategoryCustomPreviewInfo( $cat_id ) {
		return $this->_cmd( 'category custom preview info', array( 'cat_id'=>$cat_id ) );
	}
	public function CategoryCustomPreviewGet( $options, $stream=true, $filename=null ) {
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'category custom preview get', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options, 60 );
		return $tmp;
	}

	public function LogGet( $offset, $count, $tz, $filter=null ) {
		$para = array('offset'=>$offset,'count'=>$count,'tz'=>$tz);
		if( $filter!==null ) {
			if( isset($filter['date']) && $filter['date']!=='' ) $para['flt_date'] = $filter['date'];
			if( isset($filter['date_from']) && $filter['date_from']!=='' ) $para['flt_date_from'] = $filter['date_from'];
			if( isset($filter['date_to']) && $filter['date_to']!=='' ) $para['flt_date_to'] = $filter['date_to'];
			if( isset($filter['entity_type']) && $filter['entity_type']!=='' ) $para['flt_entity_type'] = $filter['entity_type'];
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
		$ktrans = array( 'lid'=>'id', 'ltm'=>'ltime', 'uid'=>'user_id', 'unm'=>'user_name', 'sid'=>'session_id', 'act'=>'action', 'etp'=>'entity_type', 'ent'=>'entity', 'det'=>'details', 'cid'=>'client_id' );
		$entries = array();
		foreach( $tmp as $e ) {
			$o = array();
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

	public function LogStats( $tz ) {
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


	public function ExportChannelAdd( $name, $target_url, $recursive_dirs, $recursive_dirs_tree_id=1, $acl_action=0 ) {
		$para = array('name'=>$name,'target_url'=>$target_url,'recursive_dirs'=>$recursive_dirs,'recursive_dirs_tree_id'=>$recursive_dirs_tree_id,'acl_action'=>$acl_action);
		return $this->_cmd('export channel add',$para);
	}
	public function ExportChannelUpdate( $channel_id, $name, $target_url, $recursive_dirs, $recursive_dirs_tree_id=1, $acl_action=0 ) {
		$para = array('channel_id'=>$channel_id,'name'=>$name,'target_url'=>$target_url,'recursive_dirs'=>$recursive_dirs,'recursive_dirs_tree_id'=>$recursive_dirs_tree_id,'acl_action'=>$acl_action);
		return $this->_cmd('export channel update',$para);
	}
	public function ExportChannelDelete( $channel_id ) {
		return $this->_cmd('export channel delete',array('channel_id'=>$channel_id));
	}

	public function ExportQueueAddAllMedia( $channel_id, $filename_schema ) {
		return $this->_cmd('export queue add all media',array('channel_id'=>$channel_id,'filename_schema'=>$filename_schema));
	}
	public function ExportQueueInfo() {
		return $this->_cmd('export queue info');
	}
	public function ExportQueueCancel() {
		return $this->_cmd('export queue cancel');
	}

	public function ExportLogGet( $count=null, $channel_id=null, $geq_ltime=null, $leq_ltime=null, $since_log_id=null, $flt_success=null ) {
		$para = array();
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



	public function WatermarkAdd( $opts=array(), $file=null ) {
		$fp = null;
		if( $file!==null ) {
			$opts['payload'] = filesize($file);
			$fp = fopen($file,'rb');
			if( $fp === false ) {
				$this->error_code = 'fs/1';
				$this->error_string = 'could not read input file';
				return $this->_error();
			}
		}
		$tmp = $this->_cmd('watermark add',$opts,$fp);
		if( $tmp===false || !isset($tmp['wm_id']) ) return false;
		return $tmp['wm_id'];
	}

	public function WatermarkUpdate( $wm_id, $opts=array(), $file=null ) {
		$fp = null;
		if( $file!==null ) {
			$opts['payload'] = filesize($file);
			$fp = fopen($file,'rb');
			if( $fp === false ) {
				$this->error_code = 'fs/1';
				$this->error_string = 'could not read input file';
				return $this->_error();
			}
		}
		$opts['wm_id'] = $wm_id;
		return $this->_cmd('watermark update',$opts,$fp);
	}

	public function WatermarkDelete( $wm_id ) {
		return $this->_cmd('watermark delete',array('wm_id'=>$wm_id));
	}

	public function WatermarkGet( $wm_id ) {
		return $this->_cmd('watermark get',array('wm_id'=>$wm_id));
	}

	public function WatermarkPreviewGet( $wm_id, $options, $stream=true, $filename=null ) {
		$options['wm_id'] = $wm_id;
		if( $stream ) {
			$options['exit']='1';
			$this->_stream_parse_byte_ranges( $options );
		}
		$tmp = $this->_cmd( 'watermark preview get', $options );
		if( $tmp===false ) return false;
		if( $stream && isset($tmp['payload']) ) $this->_stream_payload( $tmp, $filename, $options, 60 );
		return $tmp;
	}

	public function WatermarksList() {
		$tmp = $this->_cmd('watermarks list');
		if( $tmp===false ) return false;
		return $this->_extract_dot_list('name',$tmp);
	}


	public function CategoryWatermarkGet( $cat_id ) {
		return $this->_cmd('category watermark get',array('cat_id'=>$cat_id));
	}

	public function CategoryWatermarkSet( $cat_id, $wm_id, $wm_rule ) {
		return $this->_cmd('category watermark set',array('cat_id'=>$cat_id,'wm_id'=>$wm_id,'wm_rule'=>$wm_rule));
	}



	public function BulkPermissionsMediaInfo( $media_ids, $actions, $group_id=0, $user_id=0 ) {
		$para = array( 'media_ids'=>implode(',',$media_ids), 'actions'=>implode(',',$actions) );
		if( $group_id ) $para['group_id'] = $group_id;
		if( $user_id ) $para['user_id'] = $user_id;
		$tmp = $this->_cmd('bulk permissions media info',$para);
		if( $tmp===false ) return false;
		$result = array();
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


	public function BulkPermissionsMediaApply( $rules, $action, $group_id=0, $user_id=0 ) {
		$para = array( 'action'=>$action );
		if( $group_id ) $para['group_id'] = $group_id;
		if( $user_id ) $para['user_id'] = $user_id;
		foreach( $rules as $media_id=>$rule ) $para['r.'.$media_id] = $rule;
		return $this->_cmd('bulk permissions media apply',$para);
	}


	//--------------------------------------------------------------------------
	// ADMIN/MAINTENANCE COMMANDS
	//--------------------------------------------------------------------------

	public function MaintenancePostImport( $media_ids=null, $post_delayed ) {
		$para = array( 'post_delayed'=>$post_delayed?'1':'0' );
		if( $media_ids!==null ) $para['media_ids'] = implode(',',$media_ids);
		return $this->_cmd('maintenance post import',$para);
	}

	public function MaintenanceReImport( $media_ids=null ) {
		$para = array();
		if( $media_ids!==null ) $para['media_ids'] = implode(',',$media_ids);
		return $this->_cmd('maintenance re import',$para);
	}


	//--------------------------------------------------------------------------
	// INTERNALS
	//--------------------------------------------------------------------------

	// send command and parse result, sets error states
	public function _cmd( $cmd, $para=null, $binary_fp=null ) {
		if( $this->stream_state!=='idle' ) return $this->_cmd_error('pro/3');
		// build command
		$cmdstring = $cmd."\n".($this->extended_response_format?'__xr=2'."\n":'');        
		if( $para!==null ) {
			foreach( $para as $k=>$p ) $cmdstring .= $k.'='.addcslashes($p,"\n\\")."\n";
		}
		// send command
		$this->_protocol_log('SEND',$cmdstring);
		if( fwrite($this->fp,$cmdstring."\n")===false ) return $this->_cmd_error('pro/2');
		if( $binary_fp!==null ) {
			$this->_protocol_log('SEND','<binary data>');
			while( !feof($binary_fp) && ($buf=fread($binary_fp,16384))!=='' ) {
				if( $buf===false ) return $this->_cmd_error('pro/10');
				if( @fwrite($this->fp,$buf)===false ) return $this->_cmd_error('pro/9');
			}
		}
		//usleep(1000);
		// get result status (max result status: "ok 4294967296" = 13+1+1 OR "json o 4294967296" = 17+1+1)
		if( ($line=fgets($this->fp,23))===false ) {
			// communication error
			return $this->_cmd_error('pro/4');
		}
		$this->_protocol_log('RCV1',$line);
		$xline = explode(' ',$line,3);
		if( $xline[0]==='json' ) {
			// mj2.4 json response format ("json o <response_size>\n" or "json e <response_size>")
			$size = (int)$xline[2];
			if( $size===0 ) {
				$result = array();
				$this->_protocol_log('RCV2','');
			} else {
				$raw = '';
				while( $size>0 && ($d=fread($this->fp,$size))!==false ) {
					$raw .= $d;
					$size -= strlen($d);
				}
				$result = json_decode($raw,true);
				$this->_protocol_log('RCV2',$raw);
			}
			if( $xline[1]==='o' ) {
				// valid result
				return $result;
			}
			// error result
			if( isset($result['info']) ) {
				$this->error_code = 'manja/1';
				$this->error_string = $result['info'];
			} else {
				$this->error_code = 'manja/2';
				$this->error_string = 'unknown error from manja server';
			}
			return $this->_error();
		} else if( $xline[0]==='ok' ) {
			if( isset($xline[1]) ) {
				// mj2.4 extended response format ("ok <response_size>\n")
				$size = (int)$xline[1];
				if( $size===1 ) {
					// empty result (contains single empty line)
					fgetc($this->fp);
					return array();
				}
				$raw = '';
				while( $size>0 && ($d=fread($this->fp,$size))!==false ) {
					$raw .= $d;
					$size -= strlen($d);
				}
				$this->_protocol_log('RCV2',$raw);
				$result = array();
				foreach( explode("\n",substr($raw,0,-2)) as $rl ) {
					$x = explode('=',$rl,2);
					$result[$x[0]] = stripcslashes($x[1]);
				}
				return $result;
			}
			// valid result
			$result = array();
			for( $i=0; $i<2147000000; ++$i ) { // omit infinite loops
				if( ($line=fgets($this->fp))===false ) return $this->_cmd_error('pro/4'); // communication error
				$this->_protocol_log('RCV2',$line);
				if( $line[0]==="\n" ) break; // empty line = end-of-result
				$x = explode('=',$line,2);
				$result[$x[0]] = stripcslashes(substr($x[1],0,-1));
			}
			return $result;
		} else {
			// error result
			$this->error_code = 'manja/2';
			$this->error_string = 'unknown error from manja server';
			for( $i=0; $i<2147000000; ++$i ) { // omit infinite loops
				if( ($line=fgets($this->fp))===false ) return $this->_cmd_error('pro/4'); // communication error
				else if( $line[0]==="\n" ) break; // empty line = end-of-result
				if( strncmp($line,'info=',5)===0 ) {
					// get error details...
					$this->error_code = 'manja/1';
					$this->error_string = stripcslashes( @substr($line,5,-1) );
				}
			}
			return $this->_error();
		}
	}

	private function _cmd_error( $code ) {
		$this->stream_state = 'error';
		$this->error_code = $code;
		switch( $code ) {
		case 'pro/2': $this->error_string = 'connection interrupted while writing'; break;
		case 'pro/3': $this->error_string = 'not connected'; break;
		case 'pro/4': $this->error_string = 'connection interrupted while reading'; break;
		case 'pro/9': $this->error_string = 'connection interrupted while writing binary data'; break;
		case 'pro/10': $this->error_string = 'connection interrupted while reading binary data input stream'; break;
		}
		if( $this->fp ) { // Check for null and false
			$m = stream_get_meta_data( $this->fp );
			if( $m['timed_out'] ) $this->error_string .= ' (timeout)';
		}
		return $this->_error();
	}

	private function _stream_parse_byte_ranges( &$options ) {
		if( isset($_SERVER['HTTP_RANGE']) ) {
			if( !preg_match( '/^bytes=\d*-\d*(,\d*-\d*)*$/', $_SERVER['HTTP_RANGE'] ) ) {
				http_send_response_header(416);
				header( 'Content-Range: bytes */*' ); /*..*/
				exit;
			}
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
					http_send_response_header(416);
					header( 'Content-Range: bytes */*' ); /*..*/
					exit;
				}
				$options['payload_range_start'] = $start;
				$options['payload_range_end'] = $end;
				return;
			}
		}
	}

	public function get_payload( $result, $reconnect=false ) {
		$size = isset($result['payload']) ? (int)$result['payload'] : -1;
		$r = '';
		if( $size!==-1 ) {
			// read from streams with known size ...
			while( $size>0 && ($d=fread($this->fp,$size))!==false ) {
				$r .= $d;
				$size -= strlen($d);
			}
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
				else if( strlen($d)===0 ) break; // reached EOF
				$r .= $d;
			}
			fclose($old_fp);
			ignore_user_abort($prv_iua);
			// re-connect, when required
			if( $reconnect ) {
				$this->Connect();
				if( $this->connected_ssl_active ) $this->SSL($this->connected_ssl_ctx_opts);
				if( $this->connected_session_id===null ) $this->Login($this->connected_username,$this->connected_user_password);
				else $this->SessionResume($this->connected_session_id,true,true);
			}
		}
		return $r;
	}

	public function get_stream( $result, $reconnect=false ) {
		// save & invalidate current connection..
		$old_fp = $this->fp;
		$this->fp = null;
		$this->stream_state = 'none';
		if( $reconnect ) {
			$this->Connect();
			if( $this->connected_ssl_active ) $this->SSL($this->connected_ssl_ctx_opts);
			if( $this->connected_session_id===null ) $this->Login($this->connected_username,$this->connected_user_password);
			else $this->SessionResume($this->connected_session_id,true,true);
		}
		return $old_fp;
	}

	public function skip_payload( $result ) {
		$size = isset($result['payload']) ? (int)$result['payload'] : -1;
		$nskipped = 0;
		if( $size!==-1 ) {
			// slow path for persistent sockets & streams with known size
			$x = $size < 16436 ? $size : 16436;
			while( $x>0 && ($d=fread($this->fp,$x)) !== false ) {
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
				$d = fread( $this->fp, 16436 );
				if( $d===false || strlen($d)===0 ) {
					return $this->_cmd_error('pro/4'); // read error
				} else {
					$xs = strlen($d);
					$nskipped += $xs;
				}
			}
			fclose( $this->fp );
			$this->fp = null;
			$this->stream_state = 'none';
			ignore_user_abort( $prv_iua );
		}
		return $nskipped;
	}

	private function _stream_payload( $result, $filename=null, $options=array(), $default_max_age_seconds=null ) {
		// stream payload to client, evaluate all hints from server, about the nature of payload, caching issues, etc,
		$size = isset($result['payload']) ? (int)$result['payload'] : -1;
		$payload_volatile = isset($result['payload_volatile']) && $result['payload_volatile']==='1';
		$payload_expiry = isset($result['payload_expiry']) ? (int)$result['payload_expiry'] : 0;
		if( $default_max_age_seconds===null ) $default_max_age_seconds = $payload_volatile ? 60*60 : 1*86400;	// volatile -> 60min, nonvolatile -> 1d
		header_remove( 'Set-Cookie' ); // avoid sending cookies with files
		$clmodt = isset($result['mtime']) && isset($result['mtime'][0]) ? $result['mtime'] : null;
		$cexpit = $payload_expiry>0 ? $payload_expiry : null;
		$cctag = isset($result['cache_tag']) && isset($result['cache_tag'][0]) ? $result['cache_tag'] : null;
		if( !function_exists('http_cache_control_headers') ) require_once dirname(__FILE__).'/inc_util.php';
		http_cache_control_headers('private',$default_max_age_seconds,$clmodt,$cexpit,$cctag);
		if( isset($result['ctype']) && isset($result['ctype'][0]) ) {
			// Content Type Header
			header( 'Content-Type: '.$result['ctype'] );
			//header( 'Content-Type: text/html' );
		}
		if( isset($result['payload_range_start']) ) {
			// byte range delivery 
			$range_start = (int)$result['payload_range_start'];
			$range_end   = (int)$result['payload_range_end'];
			if( $size!==-1 && $range_start >= $size ) {
				// manja clips resulting range
				// so, we explicitly handle "range not satisfiable" case here 
				http_send_response_header(416);
				if( $payload_volatile ) header( 'Accept-Ranges: none' );
				else header( 'Accept-Ranges: bytes' );
				header( 'Content-Range: bytes *'.'/'.$size );
				header( 'Content-Length: 0' );
				return;
			}
			$range_size  = $range_end - $range_start + 1;
			if( $size!==-1 && $range_start===0 && $range_size===$size ) {
				// requested partial, but result is the whole content
				if( $payload_volatile ) header( 'Accept-Ranges: none' );
				else header( 'Accept-Ranges: bytes' );
				header( 'Content-Length: ' . $size );
			} else {
				http_send_response_header(206);
				if( $payload_volatile ) header( 'Accept-Ranges: none' );
				else header( 'Accept-Ranges: bytes' );
				header( 'Content-Range: bytes '.$range_start.'-'.$range_end.'/'.($size===-1?'*':$size) );
				header( 'Content-Length: ' . $range_size );
				$size = $range_size;
			}
		} else if( $size!==-1 ) {
			// complete file delivery
			if( $payload_volatile ) header( 'Accept-Ranges: none' );
			else header( 'Accept-Ranges: bytes' );
			header( 'Content-Length: ' . $size );
		} else {
			// stream - content length unknown, no byte range requests accepted
			header( 'Accept-Ranges: none' );
		}
		if( isset($result['content_duration']) ) {
			// add content duration headers (for audio/video)
			header( 'X-Content-Duration: ' . $result['content_duration'] );
			header( 'Content-Duration: ' . $result['content_duration'] );
		}
		if( $filename!==null ) http_filename_headers($filename);
		if( $size===-1 ) {
			// stream media with unknown size - send in chunks, enable safe connection abort
			$prv_iua = ignore_user_abort( true );
			while( !connection_aborted() ) {
				if( feof($this->fp) ) break; // success, done
				$d = fread( $this->fp, 16436 );
				if( $d===false || strlen($d)===0 ) {
					return $this->_cmd_error('pro/4'); // read error
				} else {
					echo $d;
					flush();
				}
			}
			fclose( $this->fp );
			$this->fp = null;
			$this->stream_state = 'none';
			ignore_user_abort( $prv_iua );
		} else {
			// fast path for all other cases
			fpassthru( $this->fp );
			fclose( $this->fp );
			$this->fp = null;
			$this->stream_state = 'none';
		}
	}

	// error trap - function may display error message and die or just return - depending on die_on_error setting
	// - returns false (for simplified chaining, e.g. "return $this->_error()")
	private function _error() {
		$this->_protocol_log('ERRR',$this->error_code.': '.$this->error_string);
		if( $this->error_callback!==null ) {
			if( is_array($this->error_callback) ) {
				$obj =& $this->error_callback[0];
				$fn = $this->error_callback[1];
				if( !method_exists($obj,$fn) ) {
					echo $this->error_code . ': ' . $this->error_string;
					$this->Disconnect();
					echo 'unknown error handling method '.get_class($obj).'::'.$fn.'()';
					exit;
				}
				$obj->$fn( $this->die_on_error, $this->error_code, $this->error_string );
			} else {
				$fn = $this->error_callback;
				$fn( $this->die_on_error, $this->error_code, $this->error_string );
			}
		}
		if( $this->die_on_error ) {
			if( $this->error_callback===null ) echo $this->error_code . ': ' . $this->error_string;
			$this->Disconnect();
			exit;
		}
		return false;
	}

	//--------------------------------------------------------------------------
	// PRIVATE UTILITIES
	//--------------------------------------------------------------------------

	private function _protocol_log( $type, $str ) {
		/***
		$fn = dirname(__FILE__).'/../cache/private/mj_protocol.log';
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
		***/
	}

	private function _parse_dot_syntax( $sresult ) {
		$r = array();
		foreach( $sresult as $k=>$v ) {
			// "key.id=value" => r[id][key] = value
			$t = explode('.',$k,2);
			if( isset($t[1]) ) $r[$t[1]][$t[0]] = $v;
		}
		return $r;
	}

	private function _extract_dot_list( $pfx, $sresult ) {
		$r = array();
		foreach( $sresult as $k=>$v ) {
			// "<pfx>.id=value" => r[id] = value
			$t = explode('.',$k,2);
			if( isset($t[1]) && $t[0]===$pfx ) $r[$t[1]] = $v;
		}
		return $r;
	}

	private function _parse_numeric_result( $sresult ) {
		$r = array();
		foreach( $sresult as $k=>$v ) $r[(int)$k]=$v;
		ksort($r,SORT_NUMERIC);
		return $r;
	}

	// $m = array( meta_id => array(v,v,v,..), ... )
	private function _sort_metadata_values1( &$mm ) {
		foreach( $mm as $meta_id=>&$values ) ksort($values,SORT_NUMERIC);
		//unset($values);
	}
	// $m = array( media_id => array( meta_id => array(v,v,v,..), ... ), ... )
	private function _sort_metadata_values( &$m ) {
		foreach( $m as $media_id=>&$mm ) {
			foreach( $mm as $meta_id=>&$values ) ksort($values,SORT_NUMERIC);
			unset($values);
		}
		//unset($mm);
	}


	private function _parse_dot_syntax_and_prepare_category_node_list( $sresult, $compact_result ) {
		$r = array();
		if( $compact_result ) {
			$prev_n_0 = 0;
			$prev_n_1 = 0;
			$prev_n_2 = 0;
			$prev_n_3 = 0;
			foreach( $sresult as $k=>$v ) {
				// "key.id=value" => r[id][key] = value
				$t = explode('.',$k,2);
				if( isset($t[1]) ) {
					$prop_k = $t[0];
					$cat_id = (int)$t[1];
					// node = array( cat_id, parent, left, right-left, name, crd_dt, crd_by, mod_dt, mod_by [,depth [,other]] )
					$n = isset($r[$cat_id])
							? $r[$cat_id]
							: array($cat_id,0,0,0,'','','');//,0,0);//,0);
					switch( $prop_k ) {
					case 'plrn':
						$v = explode(',',$v,4);
						$n[1] = (int)$v[0];
						$n[2] = (int)$v[1];
						$n[3] = (int)$v[2];
						$n[4] = $v[3];
						break;
					case 'crmd':
						$v = explode(',',$v,3);
						// cut-off milliseconds ... YYYY-MM-DD HH:mm:ss
						$n[5] = substr($v[0],0,19);
						$n[6] = $v[2];
						$v[1] = substr($v[1],0,19);
						if( $n[5]!==$v[1] ) $n[7] = $v[1];
						//$n[8] = 0;
						break;
					case 'mod':
						$v = explode(',',$v,2);
						$v[0] = substr($v[0],0,19);
						if( $n[5]===0 || $n[5]!==$v[0] ) $n[7] = $v[0];
						if( $n[6]===0 || $n[6]!==$v[1] ) {
							if( !isset($n[7]) ) $n[7] = 0; // fill gap
							$n[8] = $v[1];
						}
						break;
					case 'crd':
						$v = explode(',',$v,2);
						$v[0] = substr($v[0],0,19);
						$n[5] = $v[0];
						$n[6] = $v[1];
						break;
					case 'parent':
						$n[1] = (int)$v;
						break;
					case 'left':
						$n[2] = (int)$v;
						break;
					case 'right':
						$n[3] = (int)$v;
						break;
					case 'name':
						$n[4] = $v;
						break;
					case 'crd_dt':
						$v = substr($v,0,19);
						$n[5] = $v;
						break;
					case 'crd_by':
						$n[6] = $v;
						break;
					case 'mod_dt':
						$v = substr($v,0,19);
						if( $n[5]===0 || $n[5]!==$v ) $n[7] = $v;
						break;
					case 'mod_by':
						if( $n[6]===0 || $n[6]!==$v ) {
							if( !isset($n[7]) ) $n[7] = 0; // fill gap
							$n[8] = $v;
						}
						break;
					default:
						// future properties ?
						/***
						if( isset($n[10]) ) {
							$n[10] = ','.$prop_k.'='.$v;
						} else {
							if( !isset($n[9]) ) $n[9] = 0;
							$n[10] .= $prop_k.'='.$v;
						}***/
						break;
					}

					$is_next_node = $cat_id!==$prev_n_0;
					if( $is_next_node ) {
						$cur_n_2 = $n[2];
						$cur_n_3 = $n[3];

						// delta coding for "parent":
						//  parent===parent of previous node    =>  0 
						//  parent===id of previous node        => -1
						if( $n[1]===$prev_n_1 && $n[1]!==1 ) {
							$n[1] = 0;
						} else if( $n[1]===$prev_n_0 ) {
							$n[1] = -1;
							$prev_n_1 = $prev_n_0;
						} else {
							$prev_n_1 = $n[1];
						}

						// delta coding for "right":
						//  right = right - left
						$n[3] = $cur_n_3 - $cur_n_2;

						// delta coding for "left":
						//  left===previous nodes right + 1		=>  0  
						//  left===previous nodes left  + 1     => -1  
						if( $cur_n_2===$prev_n_2+1 ) $n[2] = -1;
						else if( $cur_n_2===$prev_n_3+1 ) $n[2] = 0;

						$prev_n_0 = $cat_id;
						$prev_n_2 = $cur_n_2;
						$prev_n_3 = $cur_n_3;
					}

					$r[$cat_id] = $n;
				}
			}
		} else {
			foreach( $sresult as $k=>$v ) {
				// "key.id=value" => r[id][key] = value
				$t = explode('.',$k,2);
				if( isset($t[1]) ) {
					$prop_k = $t[0];
					$cat_id = $t[1];
					switch( $prop_k ) {
					case 'plrn':
						$v = explode(',',$v,4);
						$r[$cat_id]['parent'] = (int)$v[0];
						$r[$cat_id]['left'] = (int)$v[1];
						$r[$cat_id]['right'] = (int)$v[2];
						$r[$cat_id]['name'] = $v[3];
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
					case 'parent':
					case 'left':
					case 'right':
						$r[$cat_id][$prop_k] = (int)$v;
						break;
					default:
						$r[$cat_id][$prop_k] = $v;
						break;
					}
				}
			}
		}
		return $r;
	}

	/***
	private function _prepare_category_node_list( &$tmp ) {
		foreach( $tmp as $k=>&$t ) {
			$t['left'] = (int)$t['left'];
			$t['right'] = (int)$t['right'];
			$t['parent'] = (int)$t['parent'];
			if( !isset($t['crd_by']) ) $t['crd_by'] = '';
			if( !isset($t['crd_dt']) ) $t['crd_dt'] = '0000-00-00 00:00:00';
			if( !isset($t['mod_by']) ) $t['mod_by'] = '';
			if( !isset($t['mod_dt']) ) $t['mod_dt'] = '0000-00-00 00:00:00';
			//$tmp[$k] = $t;
		}
		//unset($t);
		reset($tmp);
		// note: results are already sorted by 'left' value
		//DEPRECATED://uasort( $tmp, '_mj_cmp_tree_nodes' );
	}
	***/

	//--------------------------------------------------------------------------
	// PUBLIC UTILITIES
	//--------------------------------------------------------------------------

	private $plugins_by_ctype = null;

	public function GetMediaTypeFromSuffix( $suffix ) {
		switch( $suffix ) {
		// known formats - these are hardcoded for fast thumbnail/preview delivery!
		case 'png':		return 'image/png';
		case 'jpg':		return 'image/jpeg';
		case 'tif':
		case 'tiff':	return 'image/tiff';
		case 'ogv':		return 'video/ogg';
		case 'oga':		return 'audio/ogg';
		case 'ogg':		return 'application/ogg';
		case 'm4v':
		case 'mp4':		return 'video/mp4';
		case 'm4a':		return 'audio/mp4';
		case 'flv':		return 'video/x-flv';
		case 'mp3':		return 'audio/mpeg';
		case 'pdf':		return 'application/pdf';
		case 'xod':		return 'application/vnd.ms-xpsdocument';
		default:
			// other formats.. get plugin capabilities and content-type
			$info = $this->MediaPluginList( $suffix );
			return $info!==false && isset($info[$suffix]) ? $info[$suffix]['ctype'] : null;
		}
	}

	public function GetSuffixFromMediaType( $ctype ) {
		switch( $ctype ) {
		// known formats - these are hardcoded for fast thumbnail/preview delivery!
		case 'image/png':						return 'png';
		case 'image/jpeg':						return 'jpg';
		case 'image/tiff':						return 'tiff';
		case 'video/ogg':						return 'ogv';
		case 'audio/ogg':						return 'oga';
		case 'application/ogg':					return 'ogg';
		case 'video/mp4':						return 'm4v';
		case 'audio/mp4':						return 'm4a';
		case 'video/x-flv':						return 'flv';
		case 'audio/mpeg':						return 'mp3';
		case 'application/pdf':					return 'pdf';
		case 'application/vnd.ms-xpsdocument':	return 'xod';
		default:
			// unknown formats.. get plugin capabilities and content-type
			if( $this->plugins_by_ctype===null ) {
				if( ($plugins=$this->MediaPluginList()) !== false ) {
					$this->plugins_by_ctype = array();
					foreach( $plugins as $sfx=>$plugin ) {
						$plugin['sfx'] = $sfx;
						$this->plugins_by_ctype[$plugin['ctype']] = $plugin;
					}
				}
			}
			return ( $this->plugins_by_ctype!==null && isset($this->plugins_by_ctype[$ctype]) ) ? $this->plugins_by_ctype[$ctype]['sfx'] : null;
		}
	}

	public static function GetAllMediaClasses() {
		return array(MC_IMAGE,MC_VIDEO,MC_AUDIO,MC_TEXT,MC_CONTAINER,MC_OTHER,MC_UNKNOWN);
	}

	public static function GetMediaClassIdFromName( $mc_name ) {
		switch( strtolower(trim($mc_name)) ) {
		case 'image':		return MC_IMAGE;
		case 'video':		return MC_VIDEO;
		case 'audio':		return MC_AUDIO;
		case 'text':		return MC_TEXT;
		case 'container':	return MC_CONTAINER;
		case 'other':		return MC_OTHER;
		case 'unknown':
		default:			return MC_UNKNOWN;
		}
	}

	public static function GetMediaClassNameFromId( $mc_id ) {
		switch( $mc_id ) {
		case MC_IMAGE:		return 'image';
		case MC_VIDEO:		return 'video';
		case MC_AUDIO:		return 'audio';
		case MC_TEXT:		return 'text';
		case MC_CONTAINER:	return 'container';
		case MC_OTHER:		return 'other';
		case MC_UNKNOWN:
		default:			return 'unknown';
		}
	}

	public function GetFilenamesMetaIds( $download_formats ) {
		$filename_patterns = '';
		foreach( $download_formats as $df ) $filename_patterns .= ' ' . ( isset($df['filename']) ? $df['filename'] : '' );
		// find required meta-ids
		$matches = array();
		preg_match_all( '/%([-s0-9]+)\\.?([^%|]*)\\|?([-s0-9]*)\\.?([^%]*)%/', $filename_patterns, $matches );
		$meta_ids = array();
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

	public function GetFilenames( $media_ids, $download_formats, $meta_ids, $meta_data ) {
		$return = array(); // array( media_id => array( format => fn, format => fn, ... ), ... )
		$mt2sfx_cache = array();
		foreach( $media_ids as $media_id ) {
			$mmd = isset($meta_data[$media_id]) ? $meta_data[$media_id] : array();
			$mmc = isset($mmd[-1]) ? (int)$mmd[-1][0] : MC_UNKNOWN;
			foreach( $download_formats as $dlf_id => $dlf ) {
				if( $mmc !== (int)$dlf['media_class'] ) continue;
				// parse filename pattern
				$fnp = isset($dlf['filename']) ? trim($dlf['filename']) : '';
				$fn = '';
				if( $fnp === '' ) {
					// original filename
					$fn = trim( $mmd[1][0] );
				} else {
					// replace data
					$fmt = $dlf['format'];
					$patterns = array( '%s%' );
					if( $fmt==='*' ) {
						$sfx = isset($mmd['6'][0]) ? $mmd['6'][0] : '';
						if( $sfx=='' && isset($mmd['-7'][0]) ) {
							$tmp = mj_split_on_last_occurence('.',$mmd['-7'][0]);
							if( isset($tmp[1]) ) $sfx = $tmp[1];
						}
					} else {
						if( $fmt==='+' && isset($dlf['options']) && isset($dlf['options']['pf_id']) ) {
							$pf_id = $dlf['options']['pf_id'];
							$pf = $this->PreviewFormatGet($pf_id,false);
							$fmt = $pf['format'];
						}
						if( !isset($mt2sfx_cache[$fmt]) ) $mt2sfx_cache[$fmt] = $this->GetSuffixFromMediaType($fmt);
						$sfx = $mt2sfx_cache[$fmt];
					}

					$lookup_meta_data_value = function( $meta_id ) use(&$mmd,&$sfx) {
						if( $meta_id==='s' ) return $sfx;
						$tmp = isset($mmd[$meta_id]) && count($mmd[$meta_id]) ? array_values($mmd[$meta_id]) : array('');
						return trim($tmp[0]);
					};
					$transform1_meta_data_value = function( $val, $xform ) {
						switch( $xform ) {
						case 'basename':
							$val = mj_split_on_last_occurence('.',$val);
							return $val[0];
						case 'suffix':
							$val = mj_split_on_last_occurence('.',$val);
							return isset($val[1]) ? $val[1] : '';
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
						foreach( $xforms as $xform ) {
							$val = $transform1_meta_data_value($val,$xform);
						}
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
				$return[$media_id][$dlf_id] = str_replace( array('/','\\',':'), array('_','_','_'), $fn );
			}
		}
		return $return;
	}

	public function GetFilename( $media_id, $dlf_id ) {
		// get download format info
		if( $dlf_id==0 ) return null;
		if( ($df=$this->DownloadFormatGet($dlf_id,true))===false ) return null;
		// get required meta_ids and meta_data
		$meta_ids = $this->GetFilenamesMetaIds( array($df) );
		if( ($meta_data=$this->MediaMetaList(array($media_id),$meta_ids))===false ) return null;
		// generate filename
		$filenames = $this->GetFilenames( array($media_id), array($dlf_id=>$df), $meta_ids, $meta_data );
		return isset($filenames[$media_id]) && isset($filenames[$media_id][$dlf_id]) ? $filenames[$media_id][$dlf_id] : '';
	}

	public function CreatePasswordResetRequest( $user_id ) {
		$token = md5($user_id.'::'.mt_rand());
		if( ($tmp=$this->UserdataSet($user_id,array('reset-password-token'=>$token,'reset-password-token-time'=>time())))===false ) return false;
		return $token;
	}

	public function CheckPasswordResetRequest( $user_id, $token, $lifetime ) {
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

	public function ClearPasswordResetRequest( $user_id ) {
		if( ($tmp=$this->UserdataSet($user_id,array('reset-password-token'=>'','reset-password-token-time'=>'')))===false ) return false;
		return true;
	}


	public function SanitizeUserdata( &$userdata ) {
		unset($userdata['reset-password-token']);
		unset($userdata['reset-password-token-time']);
		foreach( $userdata as $k=>$v ) {
			if( strncmp($k,'sp2ud-',6)===0 ) unset($userdata[$k]); // any "sp2ud-" data (=SessionParameters2UserData..)
		}
	}

	public function SanitizeUserlist( &$userlist ) {
		foreach( $userlist as $k=>&$v ) {
			if( isset($v['userdata']) ) $this->SanitizeUserdata($v['userdata']);
		}
		//unset($v);
	}


	/**** start compatibility functions ****/
	public function ObjectLightboxListGet( $object_id, $offset, $count, $meta_ids, $filter_deleted=false, $with_categories=false ) {
		return $this->ObjectListGet('lightbox',$object_id,'media_id',$offset,$count,$meta_ids,$filter_deleted,$with_categories);
	}
	public function ObjectLightboxList( $json_compatible=false ) {
		return $this->ObjectList('lightbox',$json_compatible);
	}
	/**** end compatibility functions ****/


    public function ObjectGet( $type, $object_id ) {
        $tmp = $this->_cmd('object get',array('type'=>$type,'object_id'=>$object_id));
        if( $tmp===false ) return false;
        if( isset($tmp['attributes']) ) $tmp['attributes'] = json_decode($tmp['attributes'],true);
        return $tmp;
    }   

	public function ObjectList( $type, $json_compatible=false, $listfilter='', $relation='shared' ) {        
		$tmp = $this->_cmd( "object list", array( 'type'=>$type, 'relation'=>$relation ) );                        
		if( $tmp===false ) return false;
        $user_id = $this->GetConnectedUserId();
		$tmp = $this->_parse_dot_syntax( $tmp );    
        if (!is_array($listfilter)) {
            $listfilter = array($listfilter);
        }
        $output = array();        
		foreach($tmp as $id=>$content) {
            if( isset($content["attributes"]) ) {
				$content["attributes"] = json_decode($content["attributes"], true);
				$tmp[$id] = array_merge($content, $content["attributes"]);
			}
            if (in_array('own', $listfilter) && $content["owner"] == $user_id) {
                $output[$id] = $tmp[$id];
            }
            else if (in_array('relation', $listfilter) && $content["owner"] != $user_id && $content["is_public"] == 0) {
                $output[$id] = $tmp[$id];
            }
            else if (in_array('public', $listfilter) && $content["is_public"] == 1) {
                $output[$id] = $tmp[$id];
            }
            else if (in_array('', $listfilter)) {
                $output[$id] = $tmp[$id];
            }            
		}
		uasort( $output, '_mj_cmp_lists2' );
		return $json_compatible ? $this->JSONCompatibleOrderedIndexListList($output) : $output;
	}

	public function ObjectListByLogin( $type, $login, $json_compatible=false, $style='combined', $relation='shared' ) {
		$tmp = $this->_cmd( 'object list', array( 'login'=>$login, 'type'=>$type, 'relation'=>$relation, 'style'=>$style ) );
		if( $tmp===false ) return false;
		$tmp = $this->_parse_dot_syntax( $tmp );
		foreach($tmp as $id=>$content) {
			if( isset($content["attributes"]) ) {
				$content["attributes"] = json_decode($content["attributes"], true);
				$tmp[$id] = $content+$content["attributes"];
			}
		}
		uasort( $tmp, '_mj_cmp_lists2' );
		return $json_compatible ? $this->JSONCompatibleOrderedIndexListList($tmp) : $tmp;
	}

    public function ObjectItemAdd($type, $object_id, $items, $pos=0) {
        $item_list = array();
        foreach($items as $item) $item_list[] = mj_json_encode($item);
		return $this->_cmd( 'object items add', array('object_id'=>$object_id,'type'=>$type,'items'=>implode("\n",$item_list),'pos'=>$pos) );
	}

    public function ObjectItemRemove($type, $object_id, $items, $item_key) {
        return $this->_cmd( 'object items remove', array('object_id'=>$object_id,'type'=>$type,'item_key'=>$item_key,'items'=>implode("\n", $items)));
    }

    public function ObjectListGet($type, $object_id, $item_key, $offset, $count, $meta_ids, $filter_deleted=false, $with_categories=false) {    
        $parameters = array('type'=>$type, 'item_key'=>$item_key, 'object_id'=>$object_id, 'offset'=>$offset, 'count'=>$count);
        if( $with_categories ) $parameters['with_categories'] = '1';
        if( $filter_deleted ) {
			if( is_array($meta_ids) ) $meta_ids[] = -1;
			else $meta_ids = array(-1);
		}
		if( is_array($meta_ids) && isset($meta_ids[0]) ) $parameters['meta_ids'] = implode(',',array_keys(array_flip($meta_ids)));
        $tmp = $this->_cmd( 'object items get', $parameters);
        if( $tmp===false ) return false;
		// index media-ids by position in global list                
        $item_list = array();        
		$mids = array();       
		if( (int)$tmp['result_count'] > 0 ) {
            $item_array = explode("\n", $tmp['result']);
			$idx = $offset;
			foreach( $item_array as $item ) {
                $decoded_item = json_decode($item, true);        
                $mids[$idx++] = $decoded_item[$item_key];       
            }
		}        
		//echo mj_json_encode($tmp);exit;
		$meta = array();
		$categories = array();
		if( $meta_ids !== null && $with_categories ) {
			// meta-data & categories
			foreach( $tmp as $k=>$v ) {
				switch( $k[0] ) {
				case 'm':
					if( $k[1]==='.' ) {
						$x = explode('.',$k,4);
						$meta[$x[1]][$x[2]][$x[3]] = $v;
					}
					break;
				case 'c':
					if( $k[1]==='.' ) {
						$x = explode('.',$k,3);
						$categories[$x[1]][$x[2]] = strpos($v,',')===false ? $v : x_explode(',',$v);
					}
					break;
				}
			}
			//not-required-anymore://$this->_sort_metadata_values($meta);
		} else if( $meta_ids !== null ) {
			// meta-data
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='m' && $k[1]==='.' ) {
					$x = explode('.',$k,4);
					$meta[$x[1]][$x[2]][$x[3]] = $v;
				}
			}
			//not-required-anymore://$this->_sort_metadata_values($meta);
		} else if( $with_categories ) {
			// categories
			foreach( $tmp as $k=>$v ) {
				if( $k[0]==='c' && $k[1]==='.' ) {
					$x = explode('.',$k,3);
					$categories[$x[1]][$x[2]] = x_explode(',',$v);
				}
			}
		}
		if( $filter_deleted ) {
			$filtered_mids = array();
			foreach( $mids as $mid ) if( isset($meta[$mid]) ) $filtered_mids[] = $mid;
			$mids = $filtered_mids;
		}
		return array(	'result_count' => $tmp['result_count'],
                        'result' => $item_list,
						$item_key.'s' => $mids,
						'meta' => $meta,
						'categories' => $categories );
    }

    public function ObjectCreate($type, $attributes, $sortkey, $public = false) {
		if( is_array($attributes) ) $attributes = mj_json_encode($attributes);
		return $this->_cmd( 'object create', array( 'attributes'=>$attributes, 'sort_key'=>$sortkey, 'type'=>$type, 'public'=>$public ) );
    }

    public function ObjectUpdate($object_id, $attributes, $sortkey, $type, $is_public = false) {
		if( is_array($attributes) ) $attributes = mj_json_encode($attributes);
    	return $this->_cmd( 'object update', array( 'object_id'=>$object_id, 'type'=>$type, 'sort_key'=>$sortkey, 'attributes'=>$attributes, 'is_public'=>$is_public) );
    }

    public function ObjectDelete($object_id, $type) {
        return $this->_cmd( 'object delete', array('object_id'=>$object_id, 'type'=>$type));
    }
	public function ObjectLightboxSend2( $rcpt_user_id, $list_id, $expires, $new_list_title, $media_ids, $acl_actions ) {        
		$p = array('user_id'=>$rcpt_user_id,'list_id'=>$list_id,'expires'=>$expires,'media_ids'=>implode(',',$media_ids));
		foreach( $acl_actions as $media_id=>$actions ) {
			$p['acl_action.'.$media_id] = implode(',',$actions);
		}
        $item_list = array();
        foreach($media_ids as $media_id) $item_list[] = mj_json_encode(array('media_id'=>$media_id));
        $p['item_list'] = implode("\n", $item_list);
        $p['attributes'] = mj_json_encode(array('title'=>$new_list_title, 'note'=>''));
        $p['sort_key'] = $new_list_title;
		return $this->_cmd( 'object lightbox send 2', $p );
	}
    public function ObjectLightboxSendGuest2( $email, $first_name, $last_name, $pass, $expires, $lb_title, $media_ids, $acl_actions ) {
		$p = array('email'=>$email,'first_name'=>$first_name,'last_name'=>$last_name,'pass'=>$pass,'expires'=>$expires,'media_ids'=>implode(',',$media_ids));
		foreach( $acl_actions as $media_id=>$actions ) {
			$p['acl_action.'.$media_id] = implode(',',$actions);
		}
        $item_list = array();
        foreach($media_ids as $media_id) $item_list[] = mj_json_encode(array('media_id'=>$media_id));
        $p['item_list'] = implode("\n", $item_list);
        $p['attributes'] = mj_json_encode(array('title'=>$lb_title,'note'=>''));
        $p['sort_key'] = $lb_title;
		return $this->_cmd( 'object lightbox send guest 2', $p );
	}
    public function ObjectLightboxSend( $rcpt_user_id, $list_id, $new_list_title, $media_ids, $acl_actions ) {
        $p = array('user_id'=>$rcpt_user_id,'list_id'=>$list_id,'media_ids'=>implode(',',$media_ids),'acl_actions'=>implode(',',$acl_actions));
        $item_list = array();
        foreach($media_ids as $media_id) $item_list[] = mj_json_encode(array('media_id'=>$media_id));
        $p['item_list'] = implode("\n", $item_list);
        $p['attributes'] = mj_json_encode(array('title'=>$new_list_title,'note'=>''));
        $p['sort_key'] = $new_list_title;
		return $this->_cmd( 'object lightbox send', $p );
	}
	/*** DEPRECATED: not used anymore
	public function ObjectLightboxSendGuest( $email, $first_name, $last_name, $pass, $expires, $lb_title, $media_ids, $acl_actions ) {
        $p = array('email'=>$email,'first_name'=>$first_name,'last_name'=>$last_name,'pass'=>$pass,'expires'=>$expires,'media_ids'=>implode(',',$media_ids),'acl_actions'=>implode(',',$acl_actions));
        $item_list = array();
        foreach($media_ids as $media_id) $item_list[] = mj_json_encode(array('media_id'=>$media_id));
        $p['item_list'] = implode("\n", $item_list);
        $p['attributes'] = mj_json_encode(array('title'=>$lb_title,'note'=>''));
        $p['sort_key'] = $lb_title;
		return $this->_cmd( 'object lightbox send guest', $p );
	}    
	***/
    public function ObjectRelationGet($id, $type, $relation) {
        $p = array('object_id'=>$id, 'relation'=>$relation, 'type'=>$type);
        return $this->_cmd( 'object relation get', $p);
    }
    public function ObjectRelationCheck($id, $relation) {
        return $this->_cmd( 'object relation check', array('object_id'=>$id, 'relation'=>$relation));
    }
    public function ObjectRelationOptionCheck($id, $relation, $option) {
        return $this->_cmd( 'object relation option check', array('object_id'=>$id, 'relation'=>$relation, 'option'=>$option));
    }
    public function ObjectRelationDelete($id, $relation, $user_id=false) {
        $p = array('object_id'=>$id, 'relation'=>$relation);
        if ($user_id !== false ) {
            $p['user_id'] = $user_id;
        }
        return $this->_cmd( 'object relation delete', $p);
    }
    public function ObjectRelationInsert($id, $relation, $list) {
        $p = array('object_id'=>$id, 'relation'=>$relation);
        foreach($list as $id => $options) {
            $p['user_id'] = $id;
            $p['options'] = implode(",", $options);
            $this->_cmd( 'object relation insert', $p);
        }
    }

}
