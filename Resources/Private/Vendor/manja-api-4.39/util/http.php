<?php
declare(strict_types=1);
/**
 * Utility functions for HTTP specific tasks
 *
 * @package   ManjaWeb
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */


/**
 * HTTP Utils
 */
class mjHttpUtil {


	/**
	 * Return the HTTP status message of the HTTP status code
	 * @param int $http_code
	 */
	public static function getHTTPMessageForCode( int $http_code ) : string {
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
		case 412: return 'Precondition Failed';
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


	/**
	 * Send HTTP response header
	 *
	 * @param int $code
	 * @param string|null $message
	 */
	public static function SendResponseHeader( int $code, ?string $message=null ) {
		if( $message===null ) $message = self::getHTTPMessageForCode($code);
		header(mj_arr_val($_SERVER,'SERVER_PROTOCOL','HTTP/1.0').' '.$code.' '.$message);
	}


	/**
	 * Determine whether response should be sent gzip-encoded.
	 *
	 * @param int $response_body_len
	 *
	 * @return string|null				null or string value to use in "Content-Encoding" header.
	 */
	public static function GetContentEncodingForGZipResponse( int $response_body_len ) : ?string {
		$encoding = null;
		if( $response_body_len >= 8192 && isset($_SERVER['HTTP_ACCEPT_ENCODING']) ) {
			if( strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'x-gzip') !== false ) $encoding = 'x-gzip';
			else if( strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false ) $encoding = 'gzip';
			if( $encoding!==null && isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'],'MSIE 8.')!==false ) {
				// IE8 does not like the gzip header
				// - if we remove it, then the IE7 mode in IE8 would not work
				// - so disable encoding at all
				$encoding = null;
			}
		}
		return $encoding;
	}

	/**
	 * Send HTTP response data
	 *
	 * @param string $body
	 * @param bool $allow_gzip_compression
	 */
	public static function SendResponse( string $body, bool $allow_gzip_compression ) {
		if( headers_sent() ) {
			// just dump, no compression, no length header
			echo $body;
		} else {
			$response_body_len = strlen($body);

			$encoding = null;
			if( $allow_gzip_compression ) {
				$encoding = self::GetContentEncodingForGZipResponse($response_body_len);
				header('Vary: Accept-Encoding, User-Agent');
			}

			if( $encoding!==null ) {
				// note: zlib uses a performance-optimized compression routine up to level=3, anything above this level is rather slow
				$zdata = gzencode($body,3);
				header('Content-Encoding: '.$encoding);
				header('Content-Length: '.strlen($zdata));
				echo $zdata;
			} else {
				header('Content-Length: '.$response_body_len);
				echo $body;
			}

			// $encoding = $allow_gzip_compression ? self::GetContentEncodingForGZipResponse($response_body_len) : null;
			// if( $encoding!==null ) {
			// 	// note: zlib uses a performance-optimized compression routine up to level=3, anything above this level is rather slow
			// 	$zdata = gzencode($body,3);
			// 	header('Vary: Accept-Encoding, User-Agent');
			// 	header('Content-Encoding: '.$encoding);
			// 	header('Content-Length: '.strlen($zdata));
			// 	echo $zdata;
			// } else {
			// 	header('Vary: User-Agent');
			// 	header('Content-Length: '.$response_body_len);
			// 	echo $body;
			// }
		}
	}

	/**
	 * @see https://tools.ietf.org/html/rfc2616#section-14.27
	 * @see http://techblog.thescore.com/2014/11/19/are-your-cache-control-directives-doing-what-they-are-supposed-to-do/
	 *
	 * @param string $directive					one of 'off', 'private' or 'public'
	 * @param int $max_age						max age of cache items in seconds
	 * @param int|null $last_modified			optional time of last modification
	 * @param int|null $expires					optional expires time
	 * @param string|null $cache_tag			optional ETag (surrounded in double quotes)
	 * @param bool $weak_cache_tag				optional: true if cache tag is for weak validation only (see https://developer.mozilla.org/en-US/docs/Web/HTTP/Conditional_requests#weak_validation)
	 */
	public static function SendCacheControlHeaders( string $directive='off', int $max_age=0, ?int $last_modified=null, ?int $expires=null, ?string $cache_tag=null, bool $weak_cache_tag=false ) {
		if( headers_sent() ) {
			// no more chance to send cache control headers - so, skip silently
			// \ManjaAppContext::LogMsg(\Logger::ERROR,'mjHttpUtil::SendCacheControlHeaders(): headers were sent already!?');
			return;
		}
		switch( $directive ) {
		case 'off':
			header('Cache-Control: no-cache, no-store');
			break;
		case 'private':
			header('Cache-Control: private, max-age='.(int)$max_age.', must-revalidate, proxy-revalidate');
			break;
		case 'public':
			if( $max_age>=0 )	header('Cache-Control: '.$directive.', max-age='.(int)$max_age);//.', must-revalidate, proxy-revalidate');
			else 				header('Cache-Control: '.$directive.', no-cache, max-age=0');
			break;
		}
		$now = time();
		// last modification happened "one second ago", but never "right now", ...
		$lmodt = $last_modified===null ? ($now-1) : $last_modified;
		header('Last-Modified: '.self::GetFormattedDateString($lmodt));
		// custom ETag
		if( $cache_tag!==null ) header('ETag: '.($weak_cache_tag?'W/':'').'"'.$cache_tag.'"');
		// and if max_age==0, then set expires=T-1s (to work around difficult off-by-one errors in proxies and browsers)
		$expt = $expires===null ? ($now+($max_age===0?-1:$max_age)) : $expires;
		header('Expires: '.self::GetFormattedDateString($expt));
	}


	/**
	 * If-Modified-Since: HTTP-date
	 * @see https://tools.ietf.org/html/rfc2616#section-14.25
	 * @return int|null
	 */
	public static function GetIfModifiedSinceHeaderValue() : ?int {
		return isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : null;
	}



	/**
	 * If-None-Match: "*" | 1#entity-tag
	 * @see https://tools.ietf.org/html/rfc2616#section-14.26
	 * @return array|bool|null
	 */
	public static function GetIfNoneMatchHeaderTags() {
		$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : null;
		if( $if_none_match===null ) return null;
		if( $if_none_match==='*' ) return true; // "If-None-Match: *" => match "ANY" ressource
		$tags = [];
		foreach( explode(',',$if_none_match) as $tag ) {
			$tag = trim($tag);
			// remove superfluous flag for "weak comparison algorithm" - see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/If-None-Match
			if( strncmp($tag,'W/',2)===0 ) $tag = trim(ltrim(substr($tag,2)),'"');
			else if( $tag[0]==='"' ) $tag = trim($tag,'"');
			$tags[$tag] = $tag;
		}
		return $tags;
	}


	/**
	 * If-Range: entity-tag | HTTP-date
	 * @see https://tools.ietf.org/html/rfc2616#section-14.27
	 * @return int|string|null		either string of entity-tag, numeric timestamp for HTTP-date or null if header n/a
	 */
	public static function GetIfRangeHeaderValue() {
		if( !isset($_SERVER['HTTP_IF_RANGE']) ) return null;
		$tag = trim($_SERVER['HTTP_IF_RANGE']);
		if( strncmp($tag,'W/',2)===0 ) return trim(ltrim(substr($tag,2)),'"');
		else if( $tag[0]==='"' ) return trim($tag,'"');
		return strtotime($tag);
	}



	/**
	 * Sends "content-disposition" header with a filename; its quoting depends on the user agent
	 *
	 * @param string $filename
	 * @param string $type
	 * @return void
	 */
	public static function SendFilenameHeaders( string $filename, string $type='attachment' ) {
		if( isset($_SERVER['HTTP_USER_AGENT']) ) {
			$ua = $_SERVER['HTTP_USER_AGENT'];
			if( strpos($ua,'MSIE')!==false ) {
				// IE: will accept percent encoded UTF-8 filenames
				return header('Content-Disposition: '.$type.'; filename="'.rawurlencode($filename).'"');
			} else if( strpos($ua,'Safari/')!==false && strpos($ua,'Chrome/')===false ) {
				// Safari (but not Chrome, which also has "Safari/xx" in UA string)
				$enc_fn = strtr($filename,',','_'); // the comma is not safe in a header
				return header('Content-Disposition: '.$type.'; filename='.$enc_fn);
			}
		}
		// RFC 5987:
		$enc_fn = rawurlencode($filename);
		$enc_fn = strtr($enc_fn,',','_'); // the comma is still not safe in "RFC 5987"
		header('Content-Disposition: '.$type.'; filename*=UTF-8\'\''.$enc_fn);
	}





	/**
	 * Get some details of HTTP request,
	 * - obeys various proxy headers.
	 *
	 * @return array
	 */
	public static function GetRequestStateDetails() : array {
		$ssl = null;
		$server_name = null;
		$server_port = null;
		$remote_host = null;
		$remote_port = null;
		if( mj_arr_val($_SERVER,'HTTP_FORWARDED')!=='' ) {
			$parts = explode(';',trim(str_replace('Forwarded:','',$_SERVER['HTTP_FORWARDED'])));
			foreach( $parts as $fwp ) {
				foreach( explode(',',trim($fwp)) as $fwpcsp ) {
					$kv = explode('=',trim($fwpcsp),2);
					if( isset($kv[1]) ) {
						$v = $kv[1];
						if( isset($v[0]) && $v[0]==='"' && substr($v,-1)==='"' ) $v = trim(substr($v,1,-1));
						if( strcasecmp($kv[0],'host')===0 ) {
							$server_name = $v;
						} else if( strcasecmp($kv[0],'proto')===0 ) {
							$ssl = $v==='https';
						} else if( strcasecmp($kv[0],'for')===0 ) {
							if( $remote_host===null ) $remote_host = array($v);
							else $remote_host[] = $v;
						}
					}
				}
			}
			if( is_array($remote_host) && isset($remote_host[0]) ) $remote_host = $remote_host[0];
		}
		if( $ssl===null ) {
			if( ($v=mj_arr_val($_SERVER,'HTTP_X_FORWARDED_PROTO'))!=='' ) {
				$tmp = explode(',',strtolower($v));
				$ssl = $tmp[0]==='https';
			} else if( isset($_SERVER['HTTPS']) ) {
				$ssl = $_SERVER['HTTPS']==='on';
			} else {
				$ssl = false;
			}
		}
		if( $server_name===null ) {
			if( ($v=mj_arr_val($_SERVER,'HTTP_X_FORWARDED_HOST'))!=='' ) {
				$server_name = $v;
			} else if( ($v=mj_arr_val($_SERVER,'HTTP_HOST'))!=='' ) {
				$server_name = $v;
				//$tmp = explode(':',$v);
				//$server_name = $tmp[0];
			} else if( isset($_SERVER['SERVER_NAME']) ) {
				$server_name = $_SERVER['SERVER_NAME'];
			} else {
				$server_name = '';
			}
		}
		if( $server_name!==null && ($x=strpos($server_name,':'))!==false ) {
			$server_port = (int)substr($server_name,$x+1);
			$server_name = substr($server_name,0,$x);
		}
		if( $server_port===null ) {
			if( ($v=mj_arr_val($_SERVER,'HTTP_X_FORWARDED_PORT'))!=='' ) {
				$server_port = intval($v,10);
			} else if( isset($_SERVER['SERVER_PORT']) && !isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ) {
				$server_port = (int)$_SERVER['SERVER_PORT'];
			} else {
				$server_port = $ssl ? 443 : 80;
			}
		}
		if( ($v=mj_arr_val($_SERVER,'MJWEBPROXIEDPORT'))!=='' ) {
			// QUIRK: temp. interop with docker nginx proxy setups -> requires a better solution!
			$proxied_port = intval($v,10);
			if( $proxied_port==$server_port ) {
				$server_port = $ssl ? 443 : 80;
			}
		}
		if( (!$ssl&&$server_port!==80) || ($ssl&&$server_port!==443) ) {
			$server_name .= ':'.$server_port;
		}
		if( $remote_host===null ) {
			if( ($v=mj_arr_val($_SERVER,'HTTP_X_FORWARDED_FOR'))!=='' ) {
				$tmp = explode(',',$v);
				$remote_host = $tmp[0];
			} else if( ($v=mj_arr_val($_SERVER,'HTTP_X_REAL_IP'))!=='' ) {
				$remote_host = $v;
			} else if( isset($_SERVER['REMOTE_ADDR']) ) {
				$remote_host = $_SERVER['REMOTE_ADDR'];
			} else {
				$remote_host = '';
			}
		}
		if( $remote_host!==null && ($x=strpos($remote_host,':'))!==false ) {
			$remote_port = intval(substr($remote_host,$x+1),10);
			$remote_host = substr($remote_host,0,$x);
		}
		if( $remote_port===null ) {
			if( isset($_SERVER['REMOTE_PORT']) ) {
				$remote_port = intval($_SERVER['REMOTE_PORT'],10);
			} else {
				$remote_port = 0;
			}
		}
		return [
			'server_name' => $server_name,
			'protocol' => $ssl?'https':'http',
			'remote_host' => $remote_host,
			'remote_port' => $remote_port
		];
	}


	/**
	 * Determine URL root path from current state in $_SERVER.
	 *
	 * @return string   url root path of manja installation, without trailing slash, empty string means manja is running at http doc root
	 */
	public static function GetURLRoot() : string {
		$url_root = ''; // at doc root
		if( isset($_SERVER['MJWEBROOT']) ) {
			// .. set by .htaccess rewrite condition
			$url_root = $_SERVER['MJWEBROOT'];
		} else if( isset($_SERVER['CONTEXT_PREFIX']) ) {
			// nice! apache 2.3 +
			$url_root = $_SERVER['CONTEXT_PREFIX'];
		// } else if( isset($_SERVER['PHP_SELF']) ) {
		}
		// var_dump($_SERVER); exit;
		return rtrim($url_root,'/');
	}


	/**
	 * Get date in a format suitable for use in HTTP headers
	 *
	 * @param int $time
	 * @return string
	 */
	public static function GetFormattedDateString( int $time ) : string {
		// https://tools.ietf.org/html/rfc7231#section-7.1.1.1 states:
		// [..] a fixed-length and single-zone subset of the date and time
		// specification used by the Internet Message Format [RFC5322].
		// https://tools.ietf.org/html/rfc5322#section-3.3
		return gmdate('D, d M Y H:i:s',$time).' GMT';
	}




	/**
	 * Checks whether document requested by current HTTP request was modified --- with regards doc_mtime and doc_etag of actual doc provided as parameters.
	 *
	 * @param int $doc_mtime			actual documents mtime
	 * @param string|null $doc_etag		if available: actual documents etag, null otherwise
	 * @return bool
	 */
	public static function CheckDocumentModified( int $doc_mtime, ?string $doc_etag ) : bool {
		$got_if_none_match = $doc_etag===null ? null : self::CheckIfNoneMatch($doc_etag);
		if( $got_if_none_match || ( $got_if_none_match===null && !self::CheckIfModifiedSince($doc_mtime) ) ) return false;
		return true;
	}

	/**
	 *
	 * @param string $doc_etag
	 * @return bool|null
	 */
	private static function CheckIfNoneMatch( string $doc_etag ) : ?bool {
		if( ($tags=self::GetIfNoneMatchHeaderTags())===null ) return null;
		else if( $tags===true ) return true;		// "If-None-Match: *" => match "ANY" ressource
		return isset($tags[$doc_etag]);
	}

	/**
	 *
	 * @param int $doc_mtime
	 * @return bool
	 */
	public static function CheckIfModifiedSince( int $doc_mtime ) : bool {
		return !( ($if_modified_since=self::GetIfModifiedSinceHeaderValue())!==null && $if_modified_since >= $doc_mtime );
	}



	/**
	 * Guess a document content type from its filename.
	 *
	 * @param string $fn
	 * @param string $add_charset		e.g. 'utf-8'
	 * @return string
	 */
	public static function GuessContentTypeFromFilename( string $fn, string $add_charset='' ) : string {
		$ct = self::_GuessContentTypeFromFilename0($fn);
		if( $add_charset!=='' ) $ct .= '; charset='.$add_charset;
		return $ct;
	}



	/**
	 * @param string $fn
	 * @return string
	 */
	private static function _GuessContentTypeFromFilename0( string $fn ) : string {
		// quick check for type:
		$sfx = mj_str_last_part('.',$fn);
		if( $sfx!==null ) {
			switch( $sfx ) {
			case 'mjs':
			case 'cjs':
			case 'js':			return 'text/javascript';
			//case 'js':			return 'application/javascript';

			case 'ts':			return 'application/x-typescript';

			case 'json':		return 'application/json';

			case 'css':			return 'text/css';
			case 'less':		return 'text/plain';

			case 'png':			return 'image/png';
			case 'jpg':			return 'image/jpeg';
			case 'ico':			return 'image/x-icon';
			case 'cur':			return 'image/x-icon';

			case 'pdf':			return 'application/pdf';
			case 'xps':
			case 'xod':			return 'application/vnd.ms-xpsdocument';

			case 'htm':
			case 'html':		return 'text/html';

			case 'woff':		return 'font/woff';
			case 'woff2':		return 'font/woff2';
			case 'ttf':			return 'font/ttf';
			case 'otf':			return 'font/otf';

			case 'svg':			return 'image/svg+xml';

			// pdftron-specific..
			// case 'cur':			return 'image/vnd.microsoft.icon';
			case 'appcache':	return 'text/cache-manifest';
			case 'pexe':		return 'application/x-pnacl';
			case 'wasm':		return 'application/wasm';
			}
		}
		return 'application/octet-stream';
	}

}
