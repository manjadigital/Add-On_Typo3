<?php
declare(strict_types=1);
/**
 * Manja Server: rarely used definitions of log actions and entities
 *
 * @package ManjaWeb
 * @copyright 2008-2021 IT-Service Robert Frunzke
 *
 */

class ManjaServerLogDefs {

	public const ALA_NONE = 0;
	public const ALA_LOGIN = 1;
	public const ALA_SESSION_CREATE = 2;
	public const ALA_LOGOUT = 3;
	public const ALA_SEARCH = 4;
	public const ALA_MEDIA_DOWNLOAD = 5;
	public const ALA_MEDIA_UPLOAD = 6;
	public const ALA_MEDIA_DELETE = 7;
	public const ALA_MEDIA_EDIT_META = 8;
	public const ALA_MEDIA_ASSIGN_CATEGORY = 9;
	public const ALA_INDEX_LIST_FILTER = 10;
	public const ALA_INDEX_LIST_CREATE = 11;
	public const ALA_OBJECT_DELETE = 12;
	public const ALA_OBJECT_SEND_USER = 13;
	public const ALA_OBJECT_SEND_GUEST = 14;
	public const ALA_OBJECT_UPDATE = 15;
	public const ALA_OBJECT_ITEMS_INSERT = 16;
	public const ALA_OBJECT_ITEMS_REMOVE = 17;
	public const ALA_CLIENT_DATA_SET = 18;
	public const ALA_TREE_ADD = 19;
	public const ALA_TREE_UPDATE = 20;
	public const ALA_TREE_DELETE = 21;
	public const ALA_CATEGORY_ADD = 22;
	public const ALA_CATEGORY_UPDATE = 23;
	public const ALA_CATEGORY_DELETE = 24;
	public const ALA_CATEGORY_MOVE = 25;
	public const ALA_USER_GROUP_ADD = 26;
	public const ALA_USER_GROUP_UPDATE = 27;
	public const ALA_USER_GROUP_DELETE = 28;
	public const ALA_USER_ADD = 29;
	public const ALA_USER_UPDATE = 30;
	public const ALA_USER_PASSWORD = 31;
	public const ALA_USER_DELETE = 32;
	public const ALA_ACL_ITEM_ADD = 33;
	public const ALA_ACL_ITEM_UPDATE = 34;
	public const ALA_ACL_ITEM_DELETE = 35;
	public const ALA_ACL_ITEM_MOVE = 36;
	public const ALA_ACL_DELETE = 37;
	public const ALA_ACL_COPY = 38;
	public const ALA_ARCHIVE_CREATE = 39;
	public const ALA_ARCHIVE_GET = 40;
	public const ALA_MAINTENANCE = 41;
	public const ALA_MEDIA_CUSTOM_PREVIEW_UPLOAD = 42;
	public const ALA_MEDIA_CUSTOM_PREVIEW_REMOVE = 43;
	public const ALA_DLF_ADD = 44;
	public const ALA_DLF_UPDATE = 45;
	public const ALA_DLF_DELETE = 46;
	public const ALA_PF_ADD = 47;
	public const ALA_PF_UPDATE = 48;
	public const ALA_PF_DELETE = 49;
	public const ALA_MG_ADD = 50;
	public const ALA_MG_UPDATE = 51;
	public const ALA_MG_DELETE = 52;
	public const ALA_MG_MOVE = 53;
	public const ALA_MD_ADD = 54;
	public const ALA_MD_UPDATE = 55;
	public const ALA_MD_DELETE = 56;
	public const ALA_MD_MOVE = 57;
	public const ALA_CLIENT_DATA_REMOVE = 58;
	public const ALA_EXPORT_CHANNEL_ADD = 59;
	public const ALA_EXPORT_CHANNEL_UPDATE = 60;
	public const ALA_EXPORT_CHANNEL_DELETE = 61;
	public const ALA_MEDIA_EXPORT = 62;
	public const ALA_CATEGORY_SORT = 63;
	public const ALA_CATEGORY_CUSTOM_PREVIEW_UPLOAD = 64;
	public const ALA_CATEGORY_CUSTOM_PREVIEW_REMOVE = 65;
	public const ALA_MEDIA_VERSION_DOWNLOAD = 66;
	public const ALA_MEDIA_VERSION_PROMOTE = 67;
	public const ALA_LOGIN_FAILED = 68;
	public const ALA_MEDIA_XMP_UPLOAD = 69;
	public const ALA_MEDIA_RESTORE = 70;
	public const ALA_MEDIA_XFDF_UPDATE = 71;
	public const ALA_OBJECT_NOTE = 72;
	public const ALA_MEDIA_CLONE = 73;
	public const ALA_LOGIN_TOKEN_CREATED = 74;
	public const ALA_USER_USERDATA_UPDATED = 75;
	public const ALA_USER_SESSION_CREATE = 76;
	public const ALA_USER_AUTH_ELEVATED = 77;
	public const ALA_OBJECT_CREATE = 78;
	public const ALA_OBJECT_RELATIONS_SET = 79;
	public const ALA_OBJECT_RELATIONS_DELETE = 80;
	public const ALA_MEDIA_DELETE_PERMANENTLY = 81;
	public const ALA_USER_AVATAR_UPLOAD = 82;
	public const ALA_USER_AVATAR_REMOVE = 83;
	public const ALA_MAX = 84;


	public const ALET_NONE = 0;
	public const ALET_MEDIA = 1;
	public const ALET_INDEX_LIST = 2;
	public const ALET_CLIENT = 3;
	public const ALET_TREE = 4;
	public const ALET_CATEGORY = 5;
	public const ALET_USER_GROUP = 6;
	public const ALET_USER = 7;
	public const ALET_ACL_ITEM = 8;
	public const ALET_ACL = 9;
	public const ALET_ARCHIVE = 10;
	public const ALET_MAINTENANCE = 11;
	public const ALET_DOWNLOAD_FORMAT = 12;
	public const ALET_PREVIEW_FORMAT = 13;
	public const ALET_META_GROUP = 14;
	public const ALET_META_DEFINITION = 15;
	public const ALET_EXPORT_CHANNEL = 16;
	public const ALET_OBJECT = 17;
	public const ALET_MAX = 18;


	/**
	 * Get list of action ids that are available for given type.
	 *
	 * @param int $entity_type_id
	 * @return array
	 */
	public static function GetManjaActionIDsAvailableToEntityType( int $entity_type_id ) : array {
		switch( $entity_type_id ) {
		case ManjaServerLogDefs::ALET_NONE:				return [ManjaServerLogDefs::ALA_SEARCH];
		case ManjaServerLogDefs::ALET_MEDIA:			return [ManjaServerLogDefs::ALA_MEDIA_DOWNLOAD,ManjaServerLogDefs::ALA_MEDIA_UPLOAD,ManjaServerLogDefs::ALA_MEDIA_DELETE,ManjaServerLogDefs::ALA_MEDIA_RESTORE,ManjaServerLogDefs::ALA_MEDIA_EDIT_META,ManjaServerLogDefs::ALA_MEDIA_ASSIGN_CATEGORY,ManjaServerLogDefs::ALA_MEDIA_CUSTOM_PREVIEW_UPLOAD,ManjaServerLogDefs::ALA_MEDIA_CUSTOM_PREVIEW_REMOVE,ManjaServerLogDefs::ALA_MEDIA_EXPORT,ManjaServerLogDefs::ALA_MEDIA_VERSION_DOWNLOAD,ManjaServerLogDefs::ALA_MEDIA_VERSION_PROMOTE,ManjaServerLogDefs::ALA_MEDIA_XMP_UPLOAD];
		case ManjaServerLogDefs::ALET_INDEX_LIST:		return [ManjaServerLogDefs::ALA_INDEX_LIST_FILTER,ManjaServerLogDefs::ALA_INDEX_LIST_CREATE];
		case ManjaServerLogDefs::ALET_CLIENT:			return [ManjaServerLogDefs::ALA_CLIENT_DATA_SET,ManjaServerLogDefs::ALA_CLIENT_DATA_REMOVE];
		case ManjaServerLogDefs::ALET_TREE:				return [ManjaServerLogDefs::ALA_TREE_ADD,ManjaServerLogDefs::ALA_TREE_UPDATE,ManjaServerLogDefs::ALA_TREE_DELETE];
		case ManjaServerLogDefs::ALET_CATEGORY:			return [ManjaServerLogDefs::ALA_CATEGORY_ADD,ManjaServerLogDefs::ALA_CATEGORY_UPDATE,ManjaServerLogDefs::ALA_CATEGORY_DELETE,ManjaServerLogDefs::ALA_CATEGORY_MOVE,ManjaServerLogDefs::ALA_CATEGORY_SORT,ManjaServerLogDefs::ALA_CATEGORY_CUSTOM_PREVIEW_UPLOAD,ManjaServerLogDefs::ALA_CATEGORY_CUSTOM_PREVIEW_REMOVE];
		case ManjaServerLogDefs::ALET_USER_GROUP:		return [ManjaServerLogDefs::ALA_USER_GROUP_ADD,ManjaServerLogDefs::ALA_USER_GROUP_UPDATE,ManjaServerLogDefs::ALA_USER_GROUP_DELETE];
		case ManjaServerLogDefs::ALET_USER:				return [ManjaServerLogDefs::ALA_LOGIN,ManjaServerLogDefs::ALA_SESSION_CREATE,ManjaServerLogDefs::ALA_LOGOUT,ManjaServerLogDefs::ALA_USER_ADD,ManjaServerLogDefs::ALA_USER_UPDATE,ManjaServerLogDefs::ALA_USER_PASSWORD,ManjaServerLogDefs::ALA_USER_DELETE,ManjaServerLogDefs::ALA_LOGIN_FAILED,ManjaServerLogDefs::ALA_LOGIN_TOKEN_CREATED,ManjaServerLogDefs::ALA_USER_USERDATA_UPDATED];
		case ManjaServerLogDefs::ALET_ACL_ITEM:			return [ManjaServerLogDefs::ALA_ACL_ITEM_ADD,ManjaServerLogDefs::ALA_ACL_ITEM_UPDATE,ManjaServerLogDefs::ALA_ACL_ITEM_DELETE,ManjaServerLogDefs::ALA_ACL_ITEM_MOVE];
		case ManjaServerLogDefs::ALET_ACL:				return [ManjaServerLogDefs::ALA_ACL_DELETE,ManjaServerLogDefs::ALA_ACL_COPY];
		case ManjaServerLogDefs::ALET_ARCHIVE:			return [ManjaServerLogDefs::ALA_ARCHIVE_CREATE,ManjaServerLogDefs::ALA_ARCHIVE_GET];
		case ManjaServerLogDefs::ALET_MAINTENANCE:		return [ManjaServerLogDefs::ALA_MAINTENANCE];
		case ManjaServerLogDefs::ALET_DOWNLOAD_FORMAT:	return [ManjaServerLogDefs::ALA_DLF_ADD,ManjaServerLogDefs::ALA_DLF_UPDATE,ManjaServerLogDefs::ALA_DLF_DELETE];
		case ManjaServerLogDefs::ALET_PREVIEW_FORMAT:	return [ManjaServerLogDefs::ALA_PF_ADD,ManjaServerLogDefs::ALA_PF_UPDATE,ManjaServerLogDefs::ALA_PF_DELETE];
		case ManjaServerLogDefs::ALET_META_GROUP:		return [ManjaServerLogDefs::ALA_MG_ADD,ManjaServerLogDefs::ALA_MG_UPDATE,ManjaServerLogDefs::ALA_MG_DELETE,ManjaServerLogDefs::ALA_MG_MOVE];
		case ManjaServerLogDefs::ALET_META_DEFINITION:	return [ManjaServerLogDefs::ALA_MD_ADD,ManjaServerLogDefs::ALA_MD_UPDATE,ManjaServerLogDefs::ALA_MD_DELETE,ManjaServerLogDefs::ALA_MD_MOVE];
		case ManjaServerLogDefs::ALET_EXPORT_CHANNEL:	return [ManjaServerLogDefs::ALA_EXPORT_CHANNEL_ADD,ManjaServerLogDefs::ALA_EXPORT_CHANNEL_UPDATE,ManjaServerLogDefs::ALA_EXPORT_CHANNEL_DELETE];
		case ManjaServerLogDefs::ALET_OBJECT:			return [ManjaServerLogDefs::ALA_OBJECT_CREATE,ManjaServerLogDefs::ALA_OBJECT_UPDATE,ManjaServerLogDefs::ALA_OBJECT_DELETE,
																ManjaServerLogDefs::ALA_OBJECT_ITEMS_INSERT,ManjaServerLogDefs::ALA_OBJECT_ITEMS_REMOVE,
																ManjaServerLogDefs::ALA_OBJECT_RELATIONS_SET,ManjaServerLogDefs::ALA_OBJECT_RELATIONS_DELETE,
																ManjaServerLogDefs::ALA_OBJECT_SEND_USER,ManjaServerLogDefs::ALA_OBJECT_SEND_GUEST];
		}
		return [];
	}

}

