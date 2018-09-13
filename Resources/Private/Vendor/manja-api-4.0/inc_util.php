<?php
/*------------------------------------------------------------------------------
 (c)(r) 2008-2018 IT-Service Robert Frunzke
--------------------------------------------------------------------------------
------------------------------------------------------------------------------*/

// remove magic_quotes, when the setting is in effect - inefficient, but necessary
if( get_magic_quotes_gpc() ) {
	function undoMagicQuotes( $array, $topLevel=true ) {
		$newArray = array();
		foreach( $array as $key => $value ) {
			if( !$topLevel ) $key = stripslashes($key);
			if( is_array($value) ) $newArray[$key] = undoMagicQuotes($value, false);
			else $newArray[$key] = stripslashes($value);
		}
		return $newArray;
	}
	$_GET = undoMagicQuotes( $_GET );
	$_POST = undoMagicQuotes( $_POST );
	$_COOKIE = undoMagicQuotes( $_COOKIE );
	$_REQUEST = undoMagicQuotes( $_REQUEST );
}


// automatically normalize all request input data to unicode NFC
if( !class_exists('Normalizer') ) {
	die('php intl module is required');
}
function mj_normalize_unicode_input( $s ) {
	if( preg_match('/[\x80-\xFF]/S',$s) && !\Normalizer::isNormalized($s) ) {
		$n = \Normalizer::normalize($s);
		if( isset($n[0]) ) return $n;
	}
	return $s;
}
$a = array(&$_FILES,/*&$_ENV,*/&$_GET,&$_POST,/*&$_COOKIE,&$_SERVER,*/&$_REQUEST);
foreach( $a[0] as &$r ) $a[] = array(&$r['name'], &$r['type']);
unset($r);
unset($a[0]);
$len = count($a)+1;
for( $i=1; $i<$len; ++$i ) {
	foreach( $a[$i] as &$r ) {
		if( is_array($r) ) $a[$len++] =& $r;
		else $r = mj_normalize_unicode_input($r);
	}
	unset($r);
	unset($a[$i]);
}


if( !function_exists('mb_ucfirst') ) {
	function mb_ucfirst( $string, $encoding='UTF-8' ) {
		$firstChar = mb_substr($string,0,1,$encoding);
		$then = mb_substr($string,1,mb_strlen($string,$encoding)-1,$encoding);
		return mb_strtoupper($firstChar,$encoding).$then;
	}
}

function mj_json_encode( $v, $force_object=false ) {
	return json_encode( $v, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|($force_object?JSON_FORCE_OBJECT:0) );
}

/**
 * exception with a separate details string
 */
class mjException extends Exception {

	private $details = '';

	public function __construct( $message, $details='', $previous=null ) {
		parent::__construct($message,0,$previous);
		if( !is_string($details) ) $details = 'details JSON='.mj_json_encode($details);
		$this->details = $details;
	}

	/**
	 * Return additional details of this exception
	 * @return string
	 */
	public function getDetails() {
		return $this->details;
	}

}

/**
 * exception from manja server with error_code and error_string
 */
class mjServerException extends mjException {

	private $error_code;
	private $error_string;

	public function __construct( $error_code, $error_string, $formatted_message, $previous=null ) {
		$this->error_code = $error_code;
		$this->error_string = $error_string;
		$details = $error_code.': '.$error_string;
		$message = $formatted_message ? $formatted_message : $details;
		parent::__construct($message,$details,$previous);
	}

	/**
	 * Return Manja Server Error Code
	 * @return string
	 */
	public function getErrorCode() {
		return $this->error_code;
	}

	/**
	 * Return Manja Server Error String
	 * @return string
	 */
	public function getErrorString() {
		return $this->error_string;
	}

}


/**
 * exception with http response code & a separate details string
 */
class mjHTTPException extends mjException {

	private $http_code = 500;
	private $http_allow_methods = array();

	public function __construct( $http_code, $message='', $details='', $previous=null, $http_allow_methods=array('GET','HEAD') ) {
		$this->http_code = $http_code;
		$this->http_allow_methods = $http_allow_methods;
		parent::__construct($message,$details,$previous);
	}

	/**
	 * Returns the HTTP status code of this exception
	 * @return int
	 */
	public function getHTTPCode() {
		return $this->http_code;
	}

	/**
	 * Return the HTTP status message of the HTTP status code of this exception
	 * @return string
	 */
	public function getHTTPMessage() {
		return self::getHTTPMessageForCode($this->http_code);
	}

	/**
	 * Return the HTTP methods (alternatively) allowed for request.
	 * - used for code 405 only !
	 * @return array
	 */
	public function getHTTPAllowMethods() {
		return $this->http_allow_methods;
	}

	/**
	 * Sends HTTP response headers to client, depending on current code & extras
	 */
	public function sendHTTPResponseHeaders() {
		http_send_response_header($this->http_code,$this->getHTTPMessage());
		if( $this->http_code===405 ) header('Allow: '.implode(', ',$this->getHTTPAllowMethods()));
	}

	/**
	 * Return the HTTP status message of the HTTP status code 
	 * @param int $http_code
	 */
	public static function getHTTPMessageForCode( $http_code ) {
		switch( $http_code ) {
		case 200: return 'OK';
		case 201: return 'Created';
		case 204: return 'No Content';
		case 206: return 'Partial Content';
		case 301: return 'Moved Permanently';
		case 302: return 'Found';
		case 303: return 'See Other';
		case 304: return 'Not Modified';
		case 307: return 'Temporary Redirect';
		case 400: return 'Bad Request';
		case 401: return 'Unauthorized';
		case 403: return 'Forbidden';
		case 404: return 'Not Found';
		case 405: return 'Method Not Allowed';
		case 416: return 'Requested Range Not Satisfiable';
		case 500: return 'Internal Server Error';
		case 501: return 'Not Implemented';
		case 502: return 'Bad Gateway';
		case 503: return 'Service Unavailable';
		case 504: return 'Gateway Timeout';
		default:
			return 'Unknown';
		}
	}

}


/**
 * 
 */
interface iMjCachedUser {

}

/**
 * 
 */
interface iMjUserCache {
	/**
	 *
	 * @param int $user_id
	 * @return iMjCachedUser|null
	 */
	public function get( $user_id );

	/***
	 *
	 * @param int[] $user_ids
	 */
	public function prefetch( $user_ids );

	/***
	 * 
	 */
	public function invalidate();
}


// like htmlspecialchars(), but array values will be escaped too, but array keys will not be escaped
function htmlspecialchars_r( $v ) {
	if( is_array($v) ) {
		foreach( $v as $i=>&$d ) $d = htmlspecialchars_r($d);
		return $v;
	} else {
		return htmlspecialchars($v);
	}
}

// like htmlspecialchars_decode(), but array values will be escaped too, but array keys will not be escaped
function htmlspecialchars_decode_r( $v ) {
	if( is_array($v) ) {
		foreach( $v as $i=>&$d ) $d = htmlspecialchars_decode_r($d);
		return $v;
	} else {
		return htmlspecialchars_decode($v);
	}
}

// like addslashes(), but array values will be escaped too, but array keys will not be escaped
function addslashes_r( $v ) {
	if( is_array($v) ) {
		foreach( $v as $i=>&$d ) $d = addslashes_r($d);
		return $v;
	} else {
		return addslashes($v);
	}
}

// like urlencode(), but array values will be escaped too, but array keys will not be escaped
function urlencode_r( $v ) {
	if( is_array($v) ) {
		foreach( $v as $i=>&$d ) $d = urlencode_r($d);
		return $v;
	} else {
		return urlencode($v);
	}
}

// like rawurlencode(), but array values will be escaped too, but array keys will not be escaped
function rawurlencode_r( $v ) {
	if( is_array($v) ) {
		foreach( $v as $i=>&$d ) $d = rawurlencode_r($d);
		return $v;
	} else {
		return rawurlencode($v);
	}
}

// like str_replace(), but array values will be str_replace'd too, array keys will not be str_replace'd
function str_replace_r( $a, $b, $v ) {
	if( is_array($v) ) {
		foreach( $v as $i=>&$d ) $d = str_replace_r($a,$b,$d);
		return $v;
	} else {
		return str_replace($a,$b,$v);
	}
}

// like intval(), but array values will be converted too, array keys will not be converted
function intval_r( $v, $base=0 ) {
	if( is_array($v) ) {
		foreach( $v as $i=>&$d ) $d = intval_r($d,$base);
		return $v;
	} else {
		return intval($v,$base);
	}
}

function http_send_response_header( $code, $message=null ) {
	if( $message===null ) $message = mjHTTPException::getHTTPMessageForCode($code);
	header( (isset($_SERVER['SERVER_PROTOCOL'])?$_SERVER['SERVER_PROTOCOL']:'HTTP/1.0').' '.$code.' '.$message);
}

function http_send_response( $data, $allow_gzip_compression ) {
	if( headers_sent() ) {
		// just dump, no compression, no length header
		echo $data;
	} else {
		$dlen = strlen($data);
		$do_compress = false;
		if( $allow_gzip_compression ) {
			if( $dlen < 8192 ) {
				$do_compress = false; // no need to waste cpu resources on compressing small data
			} else if( isset($_SERVER['HTTP_ACCEPT_ENCODING']) ) {
				if( strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'x-gzip') !== false ) $do_compress = 'x-gzip';
				else if( strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false ) $do_compress = 'gzip';
			}
			if( $do_compress!==false && isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'],'MSIE 8.')!==false ) {
				// IE8 does not like the gzip header
				// - if we remove it, then the IE7 mode in IE8 would not work
				// - so disable encoding at all
				$do_compress = false;
			}
			header( 'Vary: Accept-Encoding, User-Agent' );
		} else {
			//header( 'Vary: Accept-Encoding, User-Agent' );
			header( 'Vary: User-Agent' );
		}
		if( $do_compress ) {
			// note: zlib uses a performance-optimized compression routine up to level=3, anything above this level is rather slow
			$zdata = gzencode($data,3,FORCE_GZIP);
			header( 'Content-Encoding: '.$do_compress );
			header( 'Content-Length: '.strlen($zdata) );
			echo $zdata;
		} else {
			//header( 'Content-Encoding: identity' );
			header( 'Content-Length: '.$dlen );
			echo $data;
		}
	}
}

// DEPRECATED - still here for compatibility
function echo_gzipped( $data ) {
	http_send_response($data,true);
}

function http_header_date_str( $time ) {
	// https://tools.ietf.org/html/rfc7231#section-7.1.1.1 states:
	// [..] a fixed-length and single-zone subset of the date and time
	// specification used by the Internet Message Format [RFC5322].
	// https://tools.ietf.org/html/rfc5322#section-3.3
	return gmdate('D, d M Y H:i:s \G\M\T',$time);
}

function mj_csv_escape($value) {
   $triggers = array( '=', '+', '-', '@', '|' );//, '%' );
   if( in_array( mb_substr(trim($value),0,1), $triggers, true ) ) {
      return "'".$value."'";
   }
   return $value;   
}
/**
 * @see http://techblog.thescore.com/2014/11/19/are-your-cache-control-directives-doing-what-they-are-supposed-to-do/
 * 
 * @param string $directive - one of 'off', 'private' or 'public'
 * @param number $max_age - max age of cache items in seconds
 * @param number|null $custom_last_modified_time - optional, time of last modification
 */
function http_cache_control_headers( $directive='off', $max_age=0, $custom_last_modified_time=null, $custom_expires_time=null, $custom_cache_tag=null ) {
	if( headers_sent() ) {
		// no more chance to send cache control headers - so, skip silently 
		return;
	}
	switch( $directive ) {
	case 'off':
		header('Cache-Control: no-cache, no-store, max-age='.(int)$max_age.', must-revalidate, proxy-revalidate');
		//header('Cache-Control: no-cache, no-store, max-age='.(int)$max_age.', must-revalidate');
		break;
	case 'private':
		header('Cache-Control: private, max-age='.(int)$max_age.', must-revalidate, proxy-revalidate');
		//header('Cache-Control: private, max-age='.(int)$max_age.', must-revalidate, no-transform');
		break;
	case 'public':
		if( $max_age>=0 )	header('Cache-Control: '.$directive.', max-age='.(int)$max_age);
		else 				header('Cache-Control: '.$directive.', no-cache, max-age=0');
		break;
	}
	$now = time();
	// last modification happened "one second ago", but never "right now", ...
	$lmodt = $custom_last_modified_time===null ? ($now-1) : $custom_last_modified_time;
	header('Last-Modified: '.http_header_date_str($lmodt));
	// custom ETag
	if( $custom_cache_tag!==null ) {
		header('ETag: "'.$custom_cache_tag.'"');
	}
	// and if max_age==0, then set expires=T-1s (to work around difficult off-by-one errors in proxies and browsers)
	$expt = $custom_expires_time===null ? ($now+($max_age===0?-1:$max_age)) : $custom_expires_time;
	header('Expires: '.http_header_date_str($expt));
}

function http_check_modified( $doc_mtime, $doc_etag ) {
	$got_if_none_match = http_check_if_none_match($doc_etag);
	if( $got_if_none_match || ( $got_if_none_match===null && !http_check_if_modified_since($doc_mtime) ) ) return false;
	return true;
}

function http_check_if_modified_since( $doc_mtime ) {
	if( $doc_mtime===null ) $doc_mtime = 0;
	$if_modified_since = http_get_if_modified_since_header_value();
	return !( $if_modified_since!==null && $if_modified_since >= $doc_mtime );
}

function http_check_if_none_match( $doc_etag ) {
	$tags = http_get_if_none_match_header_tags();
	if( $tags===null ) return null;
	else if( $tags===true ) return true; // "If-None-Match: *" => match "ANY" ressource
	return isset($tags[$doc_etag]);	//in_array($doc_etag,$tags);
}

function http_get_if_modified_since_header_value() {
	return isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : null;
}
function http_get_if_none_match_header_tags() {
	$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : null;
	if( $if_none_match===null ) return null;
	if( $if_none_match==='*' ) return true; // "If-None-Match: *" => match "ANY" ressource
	$tags = array();
	foreach( explode(',',$if_none_match) as $tag ) {
		$tag = trim($tag);
		// remove superfluous flag for "weak comparison algorithm" - see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/If-None-Match
		if( strncmp($tag,'W/',2)===0 ) $tag = substr($tag,2);
		$tag = trim($tag,'"');
		$tags[$tag] = $tag;
	}
	return $tags;
}

// sends "content-disposition" header with a filename; its quoting depends on the user agent
function http_filename_headers( $filename, $type='attachment' ) {
	if( isset($_SERVER['HTTP_USER_AGENT']) ) {
		$ua = $_SERVER['HTTP_USER_AGENT'];
		if( strpos($ua,'MSIE') !== false ) {
			// IE: will accept percent encoded UTF-8 filenames
			return header('Content-Disposition: '.$type.'; filename="'.rawurlencode($filename).'"');
		} else if( strpos($ua,'Safari') !== false ) {
			// Safari
			$enc_fn = $filename;
			$enc_fn = strtr($enc_fn,',','_'); // the comma is not safe in a header
			return header('Content-Disposition: '.$type.'; filename='.$enc_fn);
		}
	}
	// RFC 5987:
	$enc_fn = rawurlencode($filename);
	$enc_fn = strtr($enc_fn,',','_'); // the comma is still not safe in "RFC 5987"
	header('Content-Disposition: '.$type.'; filename*=UTF-8\'\''.$enc_fn);
}

// transforms the php.ini notation for numbers (like '2M') to an integer (2*1024*1024 in this case)
function ini_size_to_num( $v, $multiplier=1024 ) {
	$v = trim($v);
	$num = substr($v,0,-1);
	switch( strtoupper(substr($v,-1)) ){
	case 'P': $num *= $multiplier;
	case 'T': $num *= $multiplier;
	case 'G': $num *= $multiplier;
	case 'M': $num *= $multiplier;
	case 'K': $num *= $multiplier;
		break;
	case '0': case '1': case '2': case '3': case '4':
	case '5': case '6': case '7': case '8': case '9':
		return $v; // no suffix
	}
	return $num;
}

function formatted_size( $size ) {
	$size = intval($size);
	$einheit = 'B';
	if( $size > 4096 ) {
		$size = intval($size/1024);
		$einheit = 'kB';
		if( $size > 4096 ) {
			$size = intval($size/1024);
			$einheit = 'MB';
		}
	}
	return $size.$einheit;
}

function _mj_parse_config_file( $path, $die_on_error ) {
	if( !is_file($path) ) {
		if( $die_on_error ) die('configuration file not found or not readable: '.$path);
		return false;
	}
	$config = parse_ini_file($path,true,INI_SCANNER_RAW);//);//,INI_SCANNER_RAW);
	if( !is_array($config) || count($config)===0 ) {
		if( $die_on_error ) die('syntax error in configuration file: '.$path);
		return false;
	}
	return $config;
}

function _mj_merge_config_arrays( $config1, $config2 ) {
	$config = array();
	//foreach( array_keys(array_merge($config1,$config2)) as $sk ) {
	foreach( array_keys($config2+$config1) as $sk ) {
		$c1 = isset($config1[$sk]) ? $config1[$sk] : array();
		$c2 = isset($config2[$sk]) ? $config2[$sk] : array();
		//$config[$sk] = array_merge($c1,$c2);
		$config[$sk] = $c2+$c1;//array_merge($c1,$c2);
	}
	return $config;
}

function mj_parse_config( $dir, $fn, $die_on_error=true ) {
	// read default config
	$config1_count = 0;
	if( ($config1=_mj_parse_config_file($dir.'/defaults.'.$fn,false))===false ) $config1 = array();
	else $config1_count = count($config1);
	// read site/local config
	if( ($config2=_mj_parse_config_file($dir.'/'.$fn,$die_on_error))===false ) {
		if( $config1_count===0 ) return false;
		return $config1;
	}
	// & merge them
	return $config1_count===0 ? $config2 : _mj_merge_config_arrays($config1,$config2);
}

function mj_get_value_list_from_config_section( $section, $search_key ) {
	$val_list = array();
	$search_key_len = strlen($search_key);
	foreach( $section as $ik=>$val ) {
		$ikl = strlen($ik);
		if( $ikl>=$search_key_len && strncmp($search_key,$ik,$search_key_len)===0 ) {
			$sfx = substr($ik,$search_key_len);
			if( !isset($sfx[0]) ) $val_list[''] = $val;
			else if( $sfx[0]==='.' ) $val_list[substr($sfx,1)] = $val;
		}
	}
	return $val_list;
}

function mj_write_config( $path, $config ) {
	$lines = array();
	foreach( $config as $section_key=>$section ) {
		$lines[] = '['.$section_key.']';
		foreach( $section as $key=>$value ) $lines[] = $key.'="'.str_replace('"','\\"',$value).'"';
		$lines[] = "\n";
	}
	$out = '; NOTE: this file should be edited using UTF-8 encoding'."\n"
		 . implode("\n",$lines);
	return file_put_contents($path,$out,LOCK_EX)!==false;
}

// true, if we can write to file at given path, false otherwise
// - similar to php's "is_writable()", but if the file does not exist yet, then this will
//   also check the permissions of the directory
function mj_can_write_file( $path ) {
	if( is_file($path) ) return is_writable($path);
	$p = strrpos($path,'/');
	$dir_path = $p===false ? '' : substr($path,0,$p);
	return is_dir($dir_path) && is_writable($dir_path);
}

// clean filename from a request (allows only a-z, 0-9, - and _)
// - removes all invalid characters
function mj_clean_request_filename( $name ) {
	return preg_replace('/[^a-zA-Z0-9_-]/S','',$name);
}

// clean filename from a request (allows only a-z, 0-9, - and _)
// - replaces all invalid characters with _
function mj_clean_request_filename2( $name ) {
	return preg_replace('/[^a-zA-Z0-9_-]/S','_',$name);
}


/**
 * convert special characters in filename to underscores
 * @param string $name
 * @return string
 */
function mj_make_filename( $name ) {
	return strtr($name,array('/'=>'_','\\'=>'_',':'=>'_','<'=>'_','>'=>'_','|'=>'_'));
	//return strtr($name,array('/'=>'_','\\'=>'_',':'=>'_',';'=>'_','<'=>'_','>'=>'_','|'=>'_'));
	//return str_replace(array('/','\\',':','<','>','|'),array('_','_','_','_','_','_'),$name);
}

/**
 * extract language part of a locale (e.g. locale="de_DE" -> language="de")
 * @param string $locale
 * @return string
 */
function mj_extract_language_from_locale( $locale ) {
	$x = explode('_',$locale,2);
	if( !isset($x[1]) ) die('invalid locale: '.$locale);
	return $x[0];
}

// get localized ini value, e.g. if section=foo, key=bar, locale=de_DE, language=de
// then the value of the first of the following keys that exist will be returned:
//  [foo] bar_de_DE
//  [foo] bar_de
//  [foo] bar
//  <default>
function mj_get_localized_ini_value( $ini, $section, $key, $locale, $language, $default ) {
	if( isset($ini[$section]) ) return mj_get_localized_ini_value2($ini[$section],$key,$locale,$language,$default);
	return $default;
}

function mj_get_localized_ini_value2( $s, $key, $locale, $language, $default ) {
	if( isset($s[$key.'_'.$locale]) ) return $s[$key.'_'.$locale];
	if( isset($s[$key.'_'.$language]) ) return $s[$key.'_'.$language];
	if( isset($s[$key]) ) return $s[$key];
	return $default;
}

/**
 * generates a random password string, according to configured options
 * - type is either 'sys' or 'user'
 * - see config.ini section [passwords] for available options
 */
function mj_generate_password( $passwords_cfg, $type ) {
	if( $type!='user' && $type!='sys' ) $type = 'user';
	$pass = '';
	// NOTE: here we avoid the common mistakeable chars: I l O 0
	$chars_alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
	$chars_alpha_len = mb_strlen($chars_alpha,'UTF-8');
	$chars_numeric = '123456789';
	$chars_numeric_len = mb_strlen($chars_numeric,'UTF-8');
	$chars_special = isset($passwords_cfg['special_chars']) ? $passwords_cfg['special_chars'] : '+-*.,$!?#_/';
	$chars_special_len = mb_strlen($chars_special,'UTF-8');
	$length = isset($passwords_cfg[$type.'_generation_length']) ? (int)$passwords_cfg[$type.'_generation_length'] : 10;
	$count_numeric = isset($passwords_cfg[$type.'_generation_count_numeric']) ? (int)$passwords_cfg[$type.'_generation_count_numeric'] : 2;
	$count_special = isset($passwords_cfg[$type.'_generation_count_special']) ? (int)$passwords_cfg[$type.'_generation_count_special'] : 1;
	// add alpha chars
	$length = $length - $count_special - $count_numeric;
	if( $length < 1 ) $length = 1;
	for( $i=0; $i<$length; ++$i ) $pass .= mb_substr($chars_alpha,mt_rand(0,$chars_alpha_len-1),1,'UTF-8');
	// add numeric chars
	for( $i=0; $i<$count_numeric; ++$i ) {
		$pass_len = mb_strlen($pass,'UTF-8');
		$pos = mt_rand(0,$pass_len);
		$pass = mb_substr($pass,0,$pos,'UTF-8') . mb_substr($chars_numeric,mt_rand(0,$chars_numeric_len-1),1,'UTF-8') . ($pos===$pass_len?'':mb_substr($pass,$pos,$pass_len-$pos,'UTF-8'));
	}
	// add special chars
	for( $i=0; $i<$count_special; ++$i ) {
		$pass_len = mb_strlen($pass,'UTF-8');
		$pos = mt_rand(0,$pass_len);
		$pass = mb_substr($pass,0,$pos,'UTF-8') . mb_substr($chars_special,mt_rand(0,$chars_special_len-1),1,'UTF-8') . ($pos===$pass_len?'':mb_substr($pass,$pos,$pass_len-$pos,'UTF-8'));
	}
	return $pass;
}

function mj_generate_random_unique_password() {
	return 'x'.time().'-'.mt_rand().'.'.substr(md5('A'.mt_rand().'#'.time()),0,16);
}

function mj_validate_password( $passwords_cfg, $password ) {
	if( !isset($passwords_cfg['enable_validation']) || $passwords_cfg['enable_validation']!=='yes' ) return true;
	$min_length = (int)$passwords_cfg['validation_min_length'];
	$pass_len = mb_strlen($password,'UTF-8');
	if( $pass_len < $min_length ) return false;
	$special_chars = isset($passwords_cfg['special_chars']) ? $passwords_cfg['special_chars'] : '+-*.,$!?#_/';
	$min_count_numeric = isset($passwords_cfg['validation_min_count_numeric']) ? (int)$passwords_cfg['validation_min_count_numeric'] : 1;
	$min_count_special = isset($passwords_cfg['validation_min_count_special']) ? (int)$passwords_cfg['validation_min_count_special'] : 1;
	$chars_alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	$chars_numeric = '0123456789';
	$cnt_alpha = 0;
	$cnt_numeric = 0;
	$cnt_special = 0;
	$cnt_unknown = 0;
	for( $i=0; $i<$pass_len; ++$i ) {
		$c = mb_substr($password,$i,1,'UTF-8');
		if( mb_strpos($chars_alpha,$c,0,'UTF-8')!==false ) $cnt_alpha ++;
		else if( mb_strpos($chars_numeric,$c,0,'UTF-8')!==false ) $cnt_numeric ++;
		else if( mb_strpos($special_chars,$c,0,'UTF-8')!==false ) $cnt_special ++;
		else $cnt_unknown ++;
	}
	return $cnt_numeric>=$min_count_numeric && $cnt_special>=$min_count_special && $cnt_unknown===0;
}

/**
 * render old email template
 * - deprecated!!!
 * - will be replaced by "skin engine" soon
 * @param string $text
 * @param array $replacements
 * @return string
 */
function mj_render_old_email_template( $text_template, array $replacements ) {
	$tmpr = array();
	foreach( $replacements as $k=>$v ) $tmpr['%%'.$k.'%%'] = $v;
	$text_template = trim(str_replace("\r","",$text_template))."\n\n";
	return strtr($text_template,$tmpr);
}


if( !function_exists('mb_strcasecmp') ) {
	function mb_strcasecmp( $str1, $str2, $encoding=null ) {
		if( $encoding===null ) $encoding = mb_internal_encoding();
		return strcmp( mb_strtolower($str1,$encoding), mb_strtolower($str2,$encoding) );
	}
}

function is_valid_email( $email ) {
	return preg_match('/^[[:alnum:]][a-z0-9_.+-]*@[a-z0-9.-]+$/i',trim($email));
}

if( !function_exists('array_fill_keys') ) {
	// only PHP >= 5.2.0
	function array_fill_keys( $keys, $value ) {
		return array_combine($keys,array_fill(0,count($keys),$value));
	}
}

function array_cast_to_int( $arr ) {
	/*$r = array();
	foreach( $arr as $k=>$v ) $r[$k] = (int)$v;
	return $r;
	*/
	foreach( $arr as &$v ) $v = (int)$v;
	return $arr;
}
function array_cast_to_int2( $arr ) {
	$r = array();
	foreach( $arr as $v ) $r[] = (int)$v;
	return $r;
}
function array_cast_to_int_and_filter_g0( $arr ) {
	$r = array();
	foreach( $arr as $k=>$v ) {
		$i = (int)$v;
		if( $i > 0 ) $r[$k] = $i;
	}
	return $r;
}

function to_camel_case( $str, $capitalise_first_char=false ) {
	if( !isset($str[0]) ) return $str;
	if( $capitalise_first_char ) $str[0] = strtoupper($str[0]);
	$func = create_function('$c','return strtoupper($c[1]);');
	return preg_replace_callback('/_([a-z0-9])/S',$func,$str);
}

function mj_pluralize( $e ) {
	$l = strlen($e);
	if( $l>1 && $e[$l-1]==='y' ) {
		if( $l>2 && ( $e[$l-2]==='a' || $e[$l-2]==='e' || $e[$l-2]==='i' || $e[$l-2]==='o' || $e[$l-2]==='u' ) ) return $e.'s';	// e.g. day->days, key->keys, ?iy->?iys, boy->boys, guy->guys
		return substr($e,0,$l-1).'ies'; // e.g. category->categories, dependency->dependencies, ...
	}
	return $e.'s';	// e.g. article->articles
}

function mj_singularize( $e ) {
	$l = strlen($e);
	if( $l>1 && $e[$l-1]==='s' ) {
		if( $l>2 && $e[$l-2]==='y' ) {								// e.g. days->day, guys->guy, ...
			return substr($e,0,$l-1);
		} else if( $l>3 && $e[$l-3]==='i' && $e[$l-2]==='e' ) {		// e.g. categories->category, dependencies->dependency, ...
			return substr($e,0,$l-3).'y';
		}
		// anything else ...
		return substr($e,0,$l-1);									// e.g. articles->article
	}
	return $e;
}

function array_qsort2( &$array, $column=0, $order='ASC' ) {
	$oper = $order==='ASC' ? '>':'<';
    if( !is_array($array) ) return;
    usort( $array, create_function('$a,$b',"return (\$a['$column'] $oper \$b['$column']);") );
    reset( $array );
}

function array_assoc2indexed( $assoc_array, $id_key='id', $value_key='value' ) {
	$r = array();
	foreach( $assoc_array as $k=>$v ) $r[] = array( $id_key=>$k, $value_key=>$v );
	return $r;
}

function clip( $v, $min_v, $max_v ) {
	return $v < $min_v ? $min_v : ( $v > $max_v ? $max_v : $v );
}

function mj_upload_error_string( $error_code ) {
	switch( $error_code ) {
	case UPLOAD_ERR_OK:			return 'no error';
	case UPLOAD_ERR_INI_SIZE:	return 'uploaded file exceeds upload_max_filesize directive in php.ini';
	case UPLOAD_ERR_FORM_SIZE:	return 'uploaded file exceeds MAX_FILE_SIZE directive that was specified in the HTML form';
	case UPLOAD_ERR_PARTIAL:	return 'uploaded file was only partially uploaded';
	case UPLOAD_ERR_NO_FILE:	return 'no file uploaded';
	case UPLOAD_ERR_NO_TMP_DIR:	return 'no temporary folder';
	case UPLOAD_ERR_CANT_WRITE:	return 'failed to write to disk';
	case UPLOAD_ERR_EXTENSION:	return 'upload stopped by extension';
	}
	return 'unknown error';
} 


function array_swap_values( $arr, $value1, $value2 ) {
	$index1 = array_search($value1,$arr);
	$index2 = array_search($value2,$arr);
	$arr[$index1] = $value2;
	$arr[$index2] = $value1;
	return $arr;
}

function mj_split_on_last_occurence( $delimiter, $str ) {
	if( ($p=strrpos($str,$delimiter))!==false ) return array(substr($str,0,$p),substr($str,$p+1));
	return array($str);
}




if( !function_exists('mj_mergesort') ) {
	function mj_mergesort( &$array, $cmp_function ) {
		$ca = count($array);
		if( $ca<2 ) return;
		// Split the array in half
		$halfway = $ca / 2;
		$array1 = array_slice($array,0,$halfway);
		$array2 = array_slice($array,$halfway);
		// Recurse to sort the two halves
		mj_mergesort($array1,$cmp_function);
		mj_mergesort($array2,$cmp_function);
		// If all of $array1 is <= all of $array2, just append them.
		$a1end = end($array1);
		if( call_user_func_array($cmp_function,array(&$a1end,&$array2[0])) < 1 ) {
			$array = array_merge($array1,$array2);
			return;
		}
		// Merge the two sorted arrays into a single sorted array
		$ca1 = count($array1);
		$ca2 = count($array2);
		$array = array();
		$ptr1 = $ptr2 = 0;
		while( $ptr1 < $ca1 && $ptr2 < $ca2 ) {
			if( call_user_func_array($cmp_function,array(&$array1[$ptr1],&$array2[$ptr2])) < 1 ) {
				$array[] = $array1[$ptr1++];
			} else {
				$array[] = $array2[$ptr2++];
			}
		}
		// Merge the remainder
		while( $ptr1 < $ca1 ) $array[] = $array1[$ptr1++];
		while( $ptr2 < $ca2 ) $array[] = $array2[$ptr2++];
	}
}


function is_signed_int( $str ) {
	return preg_match('/^-?\\d+$/',$str)===1;
}


function get_localized_string2( $val, $region, $default_region=null ) {
	if( is_string($val) ) {
		if( isset($val[0]) && $val[0]==='{' ) {
			// is a json string
			$val = json_decode($val,true);
		} else {
			// is a plain string
			return (string)$val;
		}
	}
	if( is_array($val) ) {
		if( isset($val[$region]) ) return (string)$val[$region];
		if( $default_region!==null && isset($val[$default_region]) ) return (string)$val[$default_region];
		if( isset($val['']) ) return (string)$val[''];
	}
	return (string)$val;
}



function mj_random_bytes( $length=32 ) {
	$length = max($length,1);
	if( function_exists('random_bytes')) return random_bytes($length);
	else if( function_exists('mcrypt_create_iv') ) return mcrypt_create_iv($length,MCRYPT_DEV_URANDOM);
	else if( function_exists('openssl_random_pseudo_bytes') ) return openssl_random_pseudo_bytes($length);
	throw new mjException('no cryptographic RNG available');
}

/*
function mj_salt() {
	return substr(strtr(base64_encode(mj_random_bytes(32)),'+','.'),0,44);
}
*/


