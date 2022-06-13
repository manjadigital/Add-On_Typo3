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
class ManjaServerDefs {

	// media classes
	public const MC_UNKNOWN = 0;
	public const MC_IMAGE = 1;
	public const MC_VIDEO = 2;
	public const MC_AUDIO = 3;
	public const MC_TEXT = 4;
	//public const MC_COMBINED = 5; // unused
	public const MC_CONTAINER = 6;
	public const MC_OTHER = 7;

	// acl item actions
	public const ACL_ITEM_FIND = 1;
	public const ACL_ITEM_VIEW_LOWRES = 2;		// also means: previews with watermark only (if watermarks enabled)
	public const ACL_ITEM_VIEW_HIGHRES = 3;		// also means: previews without watermark
	public const ACL_ITEM_DOWNLOAD = 4;
	public const ACL_ITEM_UPLOAD = 5;
	public const ACL_ITEM_EDIT = 6;
	public const ACL_ITEM_EDIT_STRUCTURE = 7;
	public const ACL_ITEM_DELETE = 8;

	// Collaboration & Annotations Add On
	public const ACL_ITEM_ANNOTS_VIEW = 51;
	public const ACL_ITEM_ANNOTS_EDIT = 52;
	public const ACL_ITEM_ANNOTS_ADMIN = 53;

	public const ACL_ITEM_CUSTOM_BASE = 1000;

	// custom action ranges
	public const ACL_ITEM_CUSTOM_DLF_FIRST =      1; // actual acl_action = ACL_ITEM_CUSTOM_BASE + ACL_ITEM_CUSTOM_DLF_FIRST
	public const ACL_ITEM_CUSTOM_DLF_LAST =      20;
	public const ACL_ITEM_CUSTOM_EXPCH_FIRST =  100;
	public const ACL_ITEM_CUSTOM_EXPCH_LAST =   120;


	// meta data types
	public const MT_STRING = 0;		// strict single line text
	public const MT_TEXT = 1;		// common text, which may contain line breaks
	public const MT_INT = 2;		// integer numbers
	public const MT_REAL = 3;		// real numbers
	public const MT_BINARY = 4;		// binary data
	public const MT_DATE = 5;		// date, format YYYY-MM-DD
	public const MT_TIME = 6;		// time, format HH:MM:SS (24 hours)
	public const MT_DATETIME = 7;	// datetime, format YYYY-MM-DD HH:MM:SS
	public const MT_REF_MEDIA = 8;	// reference to media_id (based on MT_INT)
	public const MT_OBJECT = 9;		// json object

	// media status values
	public const MS_EMPTY = 0;					// media created, but still empty
	public const MS_DELETED = 1;				// media was deleted - not available anymore
	public const MS_UPLOAD_PROGRESS = 2;		// media data is uploading
	public const MS_UPLOAD_SUSPENDED = 3;		// upload was suspended
	public const MS_UPLOAD_BROKEN = 4;			// upload was broken - media is not available or empty
	public const MS_UPLOAD_SUCCEEDED = 5;		// upload succeeded, no meta-data yet
	public const MS_POSTPROCESSING = 6;			// media data is being postprocessed (e.g. thumbnail images will be generated)
	public const MS_POSTPROCESSING_FAILED = 7;	// post-processing failed, media will not be available
	public const MS_AVAILABLE = 8;				// media is completely imported, postprocessing is finished

	// media meta status values
	public const MMS_NONE = 0;				// meta-data not processed yet
	public const MMS_META_PARSING = 1;		// reading and writing meta data
	public const MMS_META_PARSE_DONE = 2;	// reading and writing meta-data is finished (either successful or with failure)
	public const MMS_META_ANALYSING = 3;	// analysing meta-data (splitting words, building index)
	public const MMS_AVAILABLE = 4;			// meta-data is written, analysed, indexed and finally available for distribution

	// media plugin capabilities
	public const MPC_READ_BITMAP = 1;
	public const MPC_WRITE_BITMAP = 2;
	public const MPC_READ_MULTI_PAGE_BITMAP = 4;
	public const MPC_READ_BITMAP_IS_COMPLEX = 8;
	public const MPC_READ_MULTI_PAGE_BITMAP_DIMENSIONS = 16;
	public const MPC_POST_PARSE_METADATA = 2048;
	public const MPC_WRITE_BITMAP_ALPHA = 4096;
	public const MPC_PROVIDE_COMPARE_CONTENT = 8192;
	public const MPC_WRITE_STREAM_CONVERTED_RAW_DATA = 16384;
	public const MPC_WRITE_TRUE_STREAM_CONVERTED_RAW_DATA = 32768;
	public const MPC_WRITE_BITMAP_CLIPPING_PATH = 65536;
	public const MPC_WRITE_MULTI_PAGE_BITMAPS_DOC = 131072;
	public const MPC_READ_MULTI_PAGE_BITMAPS_DOC = 262144;
	public const MPC_READ_PDF = 524288;
	public const MPC_CREATE_INTERMEDIATE_FORMAT = 1048576;
	public const MPC_TRANSCODE_VIDEO = 2097152;
	public const MPC_TRANSCODE_AUDIO = 4194304;
	public const MPC_READ_VIDEO_FRAMES = 8388608;

	// meta plugin capabilities
	public const MDPC_READ = 1;
	public const MDPC_WRITE = 2;
	public const MDPC_WRITE_EMBED = 4;

}

