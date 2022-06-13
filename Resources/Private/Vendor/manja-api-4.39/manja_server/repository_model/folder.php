<?php
declare(strict_types=1);
/**
 * Manja Repository Model - an abstract model of data in typical repository scheme on manja server
 *
 * Notes:
 *    node ID syntax:
 *      d<int> 			Document in Manja Repository
 *      <int>			Folder in Manja Repository
 *
 * @package ManjaRepositoryModel
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */


/**
 * Folder in repository model
 *
 */
class MjCFolder extends MjCNode {

	/**
	 * folder id = category id
	 *
	 * @var int
	 */
	protected $folder_id;

	/**
	 * children folder nodes cache
	 *
	 * @var MjCFolder[]|null
	 */
	private $folders = null;

	/**
	 * number of child documents
	 *
	 * @var int|null
	 */
	private $document_count = null;


	public function __construct( MjCRepository $repository, array $attributes=[] ) {
		parent::__construct($repository,$attributes);
		$this->folder_id = (int)$attributes['folder_id'];
	}

	public function __destruct() {
		$this->folders = null;
		parent::__destruct();
	}

	public function GetFolderId() : int {
		return $this->folder_id;
	}

	public function GetFolders( int $offs=0, int $count=2147483637 ) : array {
		if( $this->folders===null ) $this->folders = $this->repository->GetSubFolderNodesByFolderId($this->folder_id);
		return $offs==0 && $count==2147483637 ? $this->folders : array_slice($this->folders,$offs,$count);
	}

	public function GetTotalFolderCount() : int {
		if( $this->folders===null ) $this->folders = $this->repository->GetSubFolderNodesByFolderId($this->folder_id);
		return count($this->folders);
	}

	public function GetDocuments( int $offs=0, int $count=2147483637 ) : array {
		$result = [];
		$media_list = $this->repository->GetServer()->FolderMediaList($this->folder_id,$offs,$count,MjCRepository::GetDocumentNodeRequiredMetaIds());
		foreach( $media_list['media_ids'] as $cmid ) {
			$doc_meta = mj_arr2_val($media_list,'meta',$cmid,[]);
			$doc_fn = isset($doc_meta[1]) ? mj_make_filename($doc_meta[1][0]) : null;
			if( $doc_fn!==null && $doc_fn!=='' ) $result[] = $this->repository->CreateDocumentNode2($cmid,$this->folder_id,$doc_meta,$doc_fn);
			// else .. item has no actual filename -> silently ignore this item (does this situation really occur????)
		}
		$this->document_count = intval($media_list['result_count'],10);
		return $result;
	}

	public function GetTotalDocumentCount() : int {
		if( $this->document_count===null ) $this->GetDocuments(0,1); // fetch one document and determine the count
		return $this->document_count;
	}

	/**
	 * @return MjCNode[]
	 */
	public function GetChildren( int $offs=0, int $count=2147483637 ) : array {
		$result_folders = $this->GetFolders($offs,$count);
		$count -= count($result_folders);
		$offs  -= count($this->folders);
		if( $offs < 0 ) $offs = 0;
		if( $count <= 0 ) return $result_folders;
		return array_merge( $result_folders, $this->GetDocuments($offs,$count) );
	}

	/**
	 * @return int
	 */
	public function GetTotalChildrenCount() : int {
		return $this->GetTotalFolderCount() + $this->GetTotalDocumentCount();
	}

	/**
	 * @param string $path_segment
	 *
	 * @return MjCNode|null
	 */
	public function GetChildByPathSegment( string $path_segment, bool $populate_cache=true ) : ?MjCNode {
		// match against subfolder names
		if( $this->folders===null ) {
			if( $populate_cache ) {
				$this->folders = $this->repository->GetSubFolderNodesByFolderId($this->folder_id);
			} else {
				// do not instantiate all sub-folder node objects, but just search through sub-category names and return single result node
				// - this improves performance for simple single "MjCRepository::GetNodeByPath(path)" requests, which are called often in interfaces
				if( ($child_folder=$this->repository->TryGetSubFolderNodeByPathSegment($this->folder_id,$path_segment))!==null ) return $child_folder;
			}
		}
		if( $this->folders!==null ) {
			foreach( $this->folders as $child_folder ) if( $path_segment===$child_folder->path_segment ) return $child_folder;
		}
		// return document node, if the path matches to a document in folder...
		return $this->repository->TryGetDocumentNodeByPathSegment($this->folder_id,$path_segment); // MjCDocument or null
	}

	public function InvalidateChildCaches() {
		$this->folders = null;
		$this->document_count = null;
	}

	/**
	 * Create child folder node
	 *
	 * @return int|null new folder id or null on error
	 */
	public function CreateFolder( string $name ) : ?int {
		$after_id = 0;
		$sort_alphabetically_into_parent = !$this->repository->GetAutoSortFolderChildren();
		if( ($tmp=$this->repository->GetServer()->CategoryAdd($name,$this->repository->GetRootTreeId(),$this->folder_id,$after_id,$sort_alphabetically_into_parent))===false ) return null;
		$this->InvalidateChildCaches();
		$this->repository->EnsureFolderChildrenSorted($this->folder_id);
		$this->repository->InvalidateCategoryCaches();
		return (int)$tmp['cat_id'];
	}

	/**
	 * Create child document node
	 *
	 * @param string $name
	 * @param resource|string $data
	 * @param int $size if $data is a stream, then this should be set to the size of the input, may be -1
	 *
	 * @return string|null new node id or null on error
	 */
	public function CreateDocument( string $name, $data, int $size=-1 ) : ?string {
		$cat_id_tree_1 = $this->repository->GetRootTreeId()===1 ? $this->folder_id : 1;
		if( is_resource($data) ) {
			// $data is a stream, for example it could be php://input
			$tmp = $this->repository->GetServer()->MediumUploadFromStream($data,$size,null,$cat_id_tree_1,$name);
		} else if( $data==='' && $size===0 ) {
			// special marker for an "empty" input stream -> this will create a zero-sized asset
			$tmp = $this->repository->GetServer()->MediumUploadFromStream(null,0,null,$cat_id_tree_1,$name);
		} else {
			// $data is a path to a (temporary) file on this host
			$tmp = $this->repository->GetServer()->MediumUpload($data,null,$cat_id_tree_1,$name);
		}
		if( $tmp===false ) return null;
		$new_media_id = $tmp['media_id'];
		if( $this->repository->GetRootTreeId() !== 1 ) {
			$categories = [ $this->repository->GetRootTreeId() => $this->folder_id ];
			if( ($tmp=$this->repository->GetServer()->MediaUpdate([$new_media_id],[],$categories))===false ) return null;
		}
		$this->InvalidateChildCaches();
		$this->document_count ++;
		return 'd'.$new_media_id;
	}

	/**
	 * Delete this folder
	 *
	 * @param bool $rebuild_temp_file_references
	 *
	 * @return bool true on success
	 */
	public function DeleteSelf( bool $rebuild_temp_file_references=true ) : bool {
		$parent_node = $this->GetParentNode();
		if( ($tmp=$this->repository->GetServer()->CategoryDelete($this->folder_id))===false ) return false;
		if( $parent_node!==null ) $parent_node->InvalidateChildCaches();
		$this->repository->InvalidateCategoryCaches();
		return true;
	}

	/**
	 * Rename folder
	 *
	 * @param string $new_name
	 *
	 * @return bool true on success
	 */
	public function Rename( string $new_name ) : bool {
		$sort_alphabetically_into_parent = !$this->repository->GetAutoSortFolderChildren();
		if( ($tmp=$this->repository->GetServer()->CategoryUpdate($this->folder_id,$new_name,$sort_alphabetically_into_parent))===false ) return false;

		//$old_ps = $this->path_segment;

		$this->path_segment = $this->attributes['filename'] = mj_make_filename($new_name);

		if( ($parent_node=$this->GetParentNode())!==null ) {
			$parent_node->InvalidateChildCaches();
			$this->repository->EnsureFolderChildrenSorted($parent_node->GetFolderId());
		}
		$this->repository->InvalidateCategoryCaches();
		return true;
	}


	/**
	 * Copy/Clone folder to specified location in tree
	 *
	 * @param string $trg_path					target parent folder
	 * @param string|null $new_name				target name of cloned folder
	 * @param string|null $overwrite_mode		TODO
	 *
	 * @return MjCNode							cloned folder node
	 */
	public function CopyToPath( string $trg_path, string $new_name=null, string $overwrite_mode=null ) : MjCNode {
		$trg_parent_node = $this->repository->GetNodeByPathString($trg_path);
		if( !($trg_parent_node instanceof MjCFolder) ) throw new MjCNotSupportedException('object at target path must be a folder: trg_path='.$trg_path);
		$trg_parent_folder = $trg_parent_node;

		$trg_segment_name = $new_name===null ? $this->path_segment : mj_make_filename($new_name);
		if( ($trg_existing_node=$trg_parent_folder->GetChildByPathSegment($trg_segment_name))!==null ) {
			// there already exists a node at target
			throw new MjCObjectExistsAtTargetException('object exists at clone target: path='.$trg_path.'; segment='.$trg_segment_name);
		}

		// // TODO
		// throw new MjCNotSupportedException('copying folders is not yet fully supported: path='.$trg_path.'; segment='.$trg_segment_name);

		// clone the folder
		$cloned_folder_id = $trg_parent_folder->CreateFolder($trg_segment_name);
		$cloned_folder = $this->repository->GetFolderNodeByFolderId($cloned_folder_id);

		// clone our children into cloned folder
		$childs_trg_path = $cloned_folder->GetPath();
		foreach( $this->GetChildren() as $tc ) {
			$tc->CopyToPath((string)$childs_trg_path,$tc->GetPathSegment(),$overwrite_mode);
		}

		return $cloned_folder;
	}


	/**
	 * Move folder to new location and optionally rename the node
	 *
	 * @param string $trg_path
	 * @param string|null $new_name
	 * @param string|null $overwrite_mode
	 *
	 * @return bool
	 */
	public function MoveToPath( string $trg_path, string $new_name=null, string $overwrite_mode=null ) : bool {
		$trg_parent_node = $this->repository->GetNodeByPathString($trg_path);
		if( !($trg_parent_node instanceof MjCFolder) ) throw new MjCNotSupportedException('object at target path must be a folder: trg_path='.$trg_path);
		$trg_parent_folder = $trg_parent_node;

		$trg_segment_name = $new_name===null ? $this->path_segment : mj_make_filename($new_name);
		if( ($trg_existing_node=$trg_parent_folder->GetChildByPathSegment($trg_segment_name))!==null ) {
			// there already exists a node at target
			if( $overwrite_mode==='replace-target' ) {
				// remove target - it will be completely replaced with this folder and all its contents
				$trg_existing_node->DeleteSelf();
				// continue below ...
			} else if( $overwrite_mode==='integrate-into-target' ) {
				if( $trg_existing_node instanceof MjCFolder ) {
					// integrate into target folder (instead of replacing it)
					$childs_trg_path = $trg_existing_node->GetPath();
					foreach( $this->GetChildren() as $tc ) $tc->MoveToPath((string)$childs_trg_path,$tc->GetPathSegment(),$overwrite_mode);
					// and delete self
					$this->DeleteSelf();
					return true;
				} else if( $trg_existing_node instanceof MjCDocument ) {
					// remove target - it will be completely replaced with this folder and all its contents
					$trg_existing_node->DeleteSelf();
					// continue below ...
				}
			} else {
				throw new MjCObjectExistsAtTargetException('object exists at target: path='.$trg_path.'; segment='.$trg_segment_name );
			}
		}
		$new_parent_cat_id = $trg_parent_folder->GetFolderId();
		if( ($old_parent_node=$this->GetParentNode())!==null ) $old_parent_node->InvalidateChildCaches();
		$trg_parent_folder->InvalidateChildCaches();
		$after_id = 0;
		$sort_alphabetically_into_parent = !$this->repository->GetAutoSortFolderChildren();
		if( ($tmp=$this->repository->GetServer()->CategoryMove($this->folder_id,$new_parent_cat_id,$after_id,$sort_alphabetically_into_parent))===false ) return false;
		$this->path_segment = $this->attributes['filename'] = mj_make_filename($tmp['name']);
		if( $new_name!==null ) {
			if( ($tmp=$this->repository->GetServer()->CategoryUpdate($this->folder_id,$new_name))===false ) return false;
			$this->path_segment = $this->attributes['filename'] = mj_make_filename($new_name);
		}
		$this->repository->EnsureFolderChildrenSorted($new_parent_cat_id);
		$this->repository->InvalidateCategoryCaches();
		return true;
	}

	public function SetTime( string $type, $ts ) : bool {
		//if( $ts instanceof \DateTime ) $ts = $ts->format('Y-m-d H:i:s.uP');
		if( $type==='modified' ) {
			// not supported yet
			return false;
		} else if( $type==='created' ) {
			// not supported yet
			return false;
		}
		throw new MjCInvalidArgumentError('MjCFolder::SetTime() called with unknown type='.$type);
	}

	public function IsFolder() : bool {
		return true;
	}

}

