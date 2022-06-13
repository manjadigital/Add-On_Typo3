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
 * Node in repository model
 *
 */
abstract class MjCNode {

	/**
	 *
	 * @var MjCRepository
	 */
	protected $repository;

	/**
	 *
	 * @var array|null
	 */
	protected $attributes;

	/**
	 *
	 * @var string
	 */
	protected $node_id;

	/**
	 *
	 * @var int|null
	 */
	private $parent_folder_id;

	/**
	 *
	 * @var string
	 */
	protected $path_segment;

	/**
	 * Construct a node
	 *
	 * @param MjCRepository $repository
	 * @param array $attributes
	 */
	public function __construct( MjCRepository $repository, array $attributes=[] ) {
		$this->repository = $repository;
		$this->attributes = $attributes;
		$this->node_id = $attributes['node_id'];
		$this->parent_folder_id = $attributes['parent_folder_id'];
		$this->path_segment = $attributes['filename'];
	}

	public function __destruct() {
		unset($this->repository);
	}

	/**
	 * Get repository instance
	 *
	 * @return MjCRepository
	 */
	public function GetRepository() : MjCRepository {
		return $this->repository;
	}

	/**
	 * Get id of node
	 *
	 * @return string
	 */
	public function GetNodeId() : string {
		return $this->node_id;
	}

	/**
	 * Get parents node id
	 *
	 * @return string|null
	 */
	public function GetParentNodeId() : ?string {
		return $this->parent_folder_id===null ? null : (string)$this->parent_folder_id;
	}

	/**
	 * Get parents folder id
	 *
	 * @return int|null
	 */
	public function GetParentFolderId() : ?int {
		return $this->parent_folder_id===null ? null : $this->parent_folder_id;
	}

	/**
	 * Get nodes parent node instance
	 *
	 * @return MjCFolder|null
	 */
	public function GetParentNode() : ?MjCFolder {
		return $this->parent_folder_id===null ? null : $this->repository->GetFolderNodeByFolderId($this->parent_folder_id);
	}

	/**
	 * Get path of node
	 *
	 * @return MjCPath
	 */
	public function GetPath() : MjCPath {
		if( ($parent_node=$this->GetParentNode())===null ) return new MjCPath();	// path of root node
		return $parent_node->GetPath()->GetAppended($this->path_segment);			// compound path
	}

	/**
	 * Get a named attribute
	 *
	 * @param string $key
	 * @return mixed|null
	 */
	public function GetAttribute( string $key ) {
		return mj_arr_val($this->attributes,$key,null);
	}

	/**
	 * Get all named attributes
	 *
	 * @return array
	 */
	public function GetAttributes() : array {
		return $this->attributes;
	}

	/**
	 * Get path segment of node
	 * NOTE: this usually matches filename
	 *
	 * @return string
	 */
	public function GetPathSegment() : string {
		return $this->path_segment;
	}


	/**
	 * delete node on repository
	 *
	 */
	abstract public function DeleteSelf() : bool;

	/**
	 * rename node on repository
	 *
	 * @param string $new_name					new name of node after renaming
	 *
	 * @return bool								returns whether renaming was successful
	 */
	abstract public function Rename( string $new_name ) : bool;

	/**
	 * Copy/Clone node to specified location in tree
	 *
	 * @param string $trg_path					target parent node
	 * @param string|null $new_name				target name of cloned node
	 * @param string|null $overwrite_mode		TODO
	 *
	 * @return MjCNode							cloned node
	 */
	abstract public function CopyToPath( string $trg_path, string $new_name=null, string $overwrite_mode=null ) : MjCNode;

	/**
	 * move node to new location in tree
	 *
	 * @param string $trg_path					new parent node after move
	 * @param string|null $new_name				new name of node after move
	 * @param string|null $overwrite_mode		either null, 'replace-target' or 'integrate-into-target'
	 *
	 * @return bool								returns whether moving was successful
	 */
	abstract public function MoveToPath( string $trg_path, string $new_name=null, string $overwrite_mode=null ) : bool;

	/**
	 * set file created or modified timestamps
	 * (note: altering timestamps is not possible on all entities)
	 *
	 * @param string $type						either 'modified' or created
	 * @param DateTime|int|string $ts			timestamp
	 *
	 * @return bool								true if timestamp was updated to specified value
	 */
	abstract public function SetTime( string $type, $ts ) : bool;

	/**
	 * return whether node instance is a folder
	 *
	 * @return bool
	 */
	abstract public function IsFolder() : bool;

}

