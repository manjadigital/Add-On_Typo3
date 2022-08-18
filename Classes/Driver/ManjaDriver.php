<?php
declare(strict_types = 1);

namespace Jokumer\FalManja\Driver;

/***
 *
 * This file is part of the "FalManja" Extension for TYPO3 CMS.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 * If it's not there, see <https://www.gnu.org/licenses/>.
 *
 * (c) 2018-present Joerg Kummer, Falk Röder
 *
 * @author J. Kummer <typo3@enobe.de>
 * @author Falk Röder <mail@falk-roeder.de>
 *
 ***/

// use Psr\Http\Message\ResponseInterface;
use Jokumer\FalManja\Service\ManjaService;
use MjCPath;
// use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
// use TYPO3\CMS\Core\Resource\Driver\StreamableDriverInterface;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileException;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class ManjaDriver
 *
 * @since 2.0.0 introduced first time
 */
class ManjaDriver extends AbstractHierarchicalFilesystemDriver //implements StreamableDriverInterface
{

     /**
     * @const string
     */
    public const DRIVER_TYPE = 'fal_manja';

    /**
     * @const string
     */
    public const DRIVER_SHORT_NAME = 'fal_manja';

    /**
     * @const string
     */
    public const PROCESSING_FOLDER_DEFAULT = '/typo3temp/assets/_processed_manja';

    /**
     * Own manja connector instance
     *
     * @var \Jokumer\FalManja\Service\ManjaService
     */
    private $manjaService = null;

    /**
     * API manja server instance
     *
     * @var \ManjaServer
     */
    private $manjaServer = null;

    /**
     * API manja repository instance
     *
     * @var \MjCRepository
     */
    private $manjaRepository = null;

    /**
     * Cache
     *
     * @var VariableFrontend
     */
    private $cache;

    /**
     * CacheControl
     *
     * @var string
     */
    private $cacheControl = '';


    
    /**
     * @var FlashMessageService
     */
    protected $flashMessageService;

    /**
     * CacheManager
     *
     * @var \TYPO3\CMS\Core\Cache\CacheManager
     */
    protected $cacheManager;

    /**
     * ObjectManager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Define root folder identfifier
     * Default would be '/'
     */
    protected const ROOT_FOLDER_IDENTIFIER = '/';


    /**
     * @var \MjCDocument[]
     */
    private $_folders_documents_lru_cache = [];

    /**
     * @var \MjCDocument[]
     */
    private $_documents_by_path_lru_cache = [];

    /**
     * @var \MjFolder[]
     */
    private $_folders_recursive_cache = [];


    /**
     * Fully initialized driver instances, accessible by storage uid
     * @var AbstractManjaDriver[] 
     */
    private static $_instances_by_storage_uid = [];


    /**
     * Initialize this driver and expose the capabilities for the repository to use
     * Exclude CAPABILITY_WRITABLE which should be set to '0' cause modification of files and folders are not supported
     *
     * @param array $configuration
     */
    public function __construct(array $configuration = []) {
        parent::__construct($configuration);
        $this->capabilities = ResourceStorage::CAPABILITY_BROWSABLE | ResourceStorage::CAPABILITY_PUBLIC | ResourceStorage::CAPABILITY_HIERARCHICAL_IDENTIFIERS;        
    }

    /**
     * Processes the configuration for this driver.
     */
    public function processConfiguration()
    {
        if (!empty($this->configuration['cacheControl'])) {
            $this->cacheControl = $this->configuration['cacheControl'];
        }
    }

    /**
     * getDocumentMetaData
     *
     * returns the mtea properties for a manja document
     *
     * @param string $fileIdentifier
     * @param array $metaIds
     * @return array
     */
    public function getDocumentMetaData(string $fileIdentifier,array $metaIds): array {
        $metaData = [];

        if ($fileIdentifier === '' || count($metaIds) === 0) {
            return $metaData;
        }

        $nodeId = $this->getDocumentIdByIdentifier($fileIdentifier);

        if ($nodeId === null) {
            return $metaData;
        }

        $metaData = $this->getManjaServer()
            ->MediaMetaList(
                [$nodeId],
                $metaIds
            );

        return $metaData[$nodeId] ?? $metaData;
    }

    private function connectServer() {
        // Process connection to manja - instantiates manja server class
        if( $this->manjaService===null ) {
            $this->manjaService = new ManjaService($this->configuration);
        }
        if( $this->manjaServer===null ) {
            $this->manjaServer = $this->manjaService->processConnection();
        }
        if( $this->manjaRepository===null && $this->manjaService->getConnectionStatus() ) {

            $root_tree_id = (int)( $this->configuration['tree_id'] ?? 1 );
            $root_folder_id = (int)( $this->configuration['root_folder_id'] ?? 1 );
            if( $root_tree_id!==1 && $root_folder_id===1 ) {
                // the default will not work on trees with tree_id > 1,
                // -> must query actual root folder id:
                $root_folder_id = $this->getRootCategoryId($root_tree_id);
            }

            $this->manjaRepository = GeneralUtility::makeInstance(
                \MjCRepository::class,
                $this->manjaServer,
                $root_tree_id,
                $root_folder_id,
            );
        }
    }

    private function getRootCategoryId( int $tree_id ) : int {
        $tree_list = $this->manjaServer->TreeList();
        if( !isset($tree_list[$tree_id]) ) throw new InvalidConfigurationException('invalid tree_id='.$tree_id);
        $cfg = $tree_list[$tree_id];
        return (int)$cfg['root_id'];
    }

    protected function getManjaServer() : \ManjaServer {
        if( $this->manjaServer===null ) {
            $this->connectServer();
        }
        return $this->manjaServer;
    }

    protected function getManjaRepository() : \MjCRepository {
        if( $this->manjaRepository===null ) {
            $this->connectServer();
        }
        return $this->manjaRepository;
    }

    /**
     * Initializes this object. This is called by the storage after the driver
     * has been attached.
     */
    public function initialize()
    {
        // Get cache
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->cacheManager = $this->objectManager->get(CacheManager::class);
        if ($this->cacheManager->hasCache('fal_manja')) {
            $this->cache = $this->cacheManager->getCache('fal_manja');
        }
        // dont connect yet - connect on demand only


        if( $this->storageUid!==null ) {
            self::$_instances_by_storage_uid[$this->storageUid] = $this;
        }
    }


    public static function getInstanceByStorageUID( int $storageUid ) : ?ManjaDriver {
        return self::$_instances_by_storage_uid[$storageUid] ?? null;
    }


    /**
     * Merges the capabilities merged by the user at the storage
     * configuration into the actual capabilities of the driver
     * and returns the result.
     *
     * @param int $capabilities
     * @return int
     */
    public function mergeConfigurationCapabilities(
        $capabilities
    ) {
        // See constructor method where the capabilities for the repository are exposed
        $this->capabilities &= $capabilities;
        return $this->capabilities;
    }

    /**
     * Returns the identifier of the root level folder of the storage.
     *
     * @return string
     */
    public function getRootLevelFolder()
    {
        return '/';
    }

    /**
     * Returns the identifier of the default folder new files should be put into.
     *
     * @return string
     */
    public function getDefaultFolder()
    {
        return $this->getRootLevelFolder();
    }

    private static function getUnixFromManjaTimestamp( string $manja_ts ) : int {
        $tm = new \DateTime($manja_ts,new \DateTimeZone('UTC'));
		return $tm->getTimestamp();
    }


    /**
     * Returns information about a folder
     *
     * @param string $folderIdentifier
     * @return array
     * @throws FolderDoesNotExistException
     */
    public function getFolderInfoByIdentifier($folderIdentifier)
    {
        try {
            $folderPath = new \MjCPath($folderIdentifier);
            $node = $this->getManjaRepository()->GetNodeByPath($folderPath);
            return [
                'identifier' => $folderPath->GetFolderPathString(),
                'name' => $node->GetAttribute('filename'),  //$folderPath->GetBasename(),
                'ctime' => self::getUnixFromManjaTimestamp($node->GetAttribute('created')),
                'mtime' => self::getUnixFromManjaTimestamp($node->GetAttribute('modified')),
                'storage' => $this->storageUid
            ];
        } catch( \MjCObjectNotFoundException $e ) {
            throw new FolderDoesNotExistException( 'Folder "' . $folderIdentifier . '" does not exist.', 1314516810, $e );
        }
    }

    /**
     * Returns information about a file.
     *
     * @param string $fileIdentifier
     * @param array $propertiesToExtract Array of properties which are be extracted
     *                                   If empty all will be extracted
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = [])
    {
        try {
            $file = $this->getDocumentByIdentifier($fileIdentifier);
            if ($file instanceof \MjCDocument) {
                $infos = [
                    'name' => $file->GetAttribute('filename'),
                    'identifier' => $fileIdentifier,
                    'identifier_hash' => $this->hashIdentifier($fileIdentifier),
                    'folder_hash' => $this->hashIdentifier((string)$file->GetPath()->GetDirname()),
                    'atime' => $GLOBALS['EXEC_TIME'],
                    'mtime' => self::getUnixFromManjaTimestamp($file->GetAttribute('modified')),
                    'ctime' => self::getUnixFromManjaTimestamp($file->GetAttribute('created')),
                    'mimetype' => $file->GetAttribute('content_type'),
                    'size' => (int)$file->GetAttribute('content_length'),
                    'storage' => $this->storageUid,
                    'fal_manja_document_id' => $file->GetAttribute('document_id'),
                    'extension' => explode('/', $file->GetAttribute('content_type'))
                ];
                $infos['extension'] = $infos['extension'][1];
                if(count($propertiesToExtract) == 0) {
                    return $infos;
                } else {
                    $retValue = [];
                    foreach($propertiesToExtract as $prop) {
                        $retValue[$prop] = $infos[$prop];
                    }
                    return $retValue;
                }
            }
        } catch( \MjCObjectNotFoundException $e ) {
            throw new \InvalidArgumentException( 'File "' . $fileIdentifier . '" does not exist.', 1314516809, $e );
        }
    }









    /**
     * Returns the permissions of a file/folder as an array
     * (keys r, w) of boolean flags
     *
     * @param string $identifier
     * @return array
     */
    public function getPermissions($identifier)
    {
        return [
            'r' => true,
            'w' => false
        ];
    }

    /**
     * Returns the number of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @return int Number of files in folder
     */
    public function countFilesInFolder($folderIdentifier, $recursive = false, array $filenameFilterCallbacks = [])
    {
        if( $recursive===false && $filenameFilterCallbacks===[] ) {
            // fast-path
            $folderPath = new MjCPath($folderIdentifier);
            /** @var \MjCFolder $folder */
            $folder = $this->getManjaRepository()->GetNodeByPath($folderPath);
            return $folder->GetTotalDocumentCount();
        }
        return count($this->getFilesInFolder($folderIdentifier, 0, 0, $recursive, $filenameFilterCallbacks));
    }

    /**
     * Returns the number of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @return int Number of folders in folder
     */
    public function countFoldersInFolder($folderIdentifier, $recursive = false, array $folderNameFilterCallbacks = [])
    {
        if( $recursive===false && $folderNameFilterCallbacks===[] ) {
            // fast-path
            $folderPath = new MjCPath($folderIdentifier);
            /** @var \MjCFolder $folder */
            $folder = $this->getManjaRepository()->GetNodeByPath($folderPath);
            return $folder->GetTotalFolderCount();
        }
        return count($this->getFoldersInFolder($folderIdentifier, 0, 0, $recursive, $folderNameFilterCallbacks));
    }

    /**
     * Returns the identifier of a file inside the folder
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return string file identifier
     */
    public function getFileInFolder($fileName, $folderIdentifier)
    {
        $folderPath = new \MjCPath($folderIdentifier);
        return (string)$folderPath->GetAppended($fileName);
    }


    /**
     * Returns a list of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array of FileIdentifiers
     * @throws \RuntimeException
     */
    public function getFilesInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $filenameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        $folders = [$folderIdentifier];
        if( $recursive ) {
            $folders = $this->getFoldersInFolder($folderIdentifier, $start, $numberOfItems, $recursive, $filenameFilterCallbacks, $sort, $sortRev);
            array_unshift($folders, $folderIdentifier);
        }
        $all_documents = [];
        foreach($folders as $identifier) {
            $folderPath = new MjCPath($identifier);

            /** @var \MjCFolder $folder */
            $folder = $this->getManjaRepository()->GetNodeByPath($folderPath);
            $folderPathStr = $folderPath->GetFolderPathString();

            if( isset($this->_folders_documents_lru_cache[$folderPathStr]) ) {
                $documents = $this->_folders_documents_lru_cache[$folderPathStr];
            } else {
                $documents = $this->_folders_documents_lru_cache[$folderPathStr] = $folder->GetDocuments(0,2147483637);
                // add all docs to documents_by_path cache ...
                foreach( $documents as $document ) {
                    $documentPathStr = $folderPathStr . $document->GetPathSegment();
                    $this->_documents_by_path_lru_cache[$documentPathStr] = $document;
                }
            }    
            $all_documents = array_merge($all_documents, $documents);
        }
        $sortedResult = $this->getSortedResultList($all_documents,$sort,$sortRev);
        return $this->getIdentifiersFromResultList(
            $recursive?null:$folderPath,
            $sortedResult,
            $filenameFilterCallbacks,
            $pre_sliced?false:$start,
            $pre_sliced?false:$numberOfItems
        );
    }


    /**
     * Returns the identifier of a folder inside the folder
     *
     * @param string $folderName The name of the target folder
     * @param string $folderIdentifier
     * @return string folder identifier
     */
    public function getFolderInFolder($folderName, $folderIdentifier)
    {
        $folderPath = new MjCPath($folderIdentifier);
        return $folderPath->GetAppended($folderName)->GetFolderPathString();
    }


    private function getRecursiveFolders(\MjCFolder $parent, array &$result, bool $start = true) : array {        
        $subfolders = $parent->GetFolders();                
        foreach($subfolders as $folder ) {
            $result[] = $folder;
            $this->getRecursiveFolders($folder, $result, false);
        }
        return $start?$result:[];
    }
    /**
     * Returns a list of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array of Folder Identifier
     * @throws \RuntimeException
     */
    public function getFoldersInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $folderNameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {        
        $folderPath = new MjCPath($folderIdentifier);

        /** @var \MjCFolder $folder */
        $folder = $this->getManjaRepository()->GetNodeByPath($folderPath);

        // whether result list can be sliced early (or late after sort and filtering in getIdentifiersFromResultList)
        $pre_sliced = false;    //!$folderNameFilterCallbacks && !$sort;
        
        $subFolders = [];
        if($recursive) {
            if( isset($this->_folders_recursive_cache[$folderIdentifier]) ) {
                $subFolders = $this->_folders_recursive_cache[$folderIdentifier];
            } else {
                $subFolders = $this->_folders_recursive_cache[$folderIdentifier] = $this->getRecursiveFolders($folder, $subFolders);
            }
        } else {
            $subFolders = $folder->GetFolders(
                $pre_sliced ? (int)$start : 0,
                $pre_sliced ? ( (int)$numberOfItems===0 ? 2147483637 : (int)$numberOfItems ) : 2147483637
            );
        }

        $sortedResult = $this->getSortedResultList($subFolders,$sort,$sortRev);
        return $this->getIdentifiersFromResultList(
            $recursive?null:$folderPath,
            $sortedResult,
            $folderNameFilterCallbacks,
            $pre_sliced?false:$start,
            $pre_sliced?false:$numberOfItems
        );
    }



    /**
     * Sort the directory entries by a certain key
     *
     * @param \MjCNode[] $entries           Array of nodes
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array Sorted array of nodes
     */
    protected function  getSortedResultList( array $entries, $sort = '', $sortRev = false ) : array
    {
        $entriesToSort = [];
        /** @var \MjCNode $entry */
        foreach ($entries as $entry) {
            $isFolder = $entry->IsFolder();
            $fullPath = $entry->GetPath();
            $fullPathStr = $isFolder ? $fullPath->GetFolderPathString() : (string)$fullPath;

            switch ($sort) {
            case 'size':
                $sortingKey = '0';
                if( !$isFolder ) $sortingKey = (int)$entry->GetAttribute('content_length');
                // Add a character for a natural order sorting
                $sortingKey .= 'b';
                break;
            case 'rw':
                $perms = $this->getPermissions($fullPathStr);
                $sortingKey = ( $perms['r'] ? 'R' : '' ) . ($perms['w'] ? 'W' : '');
                break;
            case 'fileext':
                $sortingKey = mj_str_last_part('.',$fullPathStr);
                break;
            case 'tstamp':
                $sortingKey = $entry->GetAttribute('modified');
                break;
            case 'name':
            case 'file':
            default:
                $sortingKey = $fullPathStr;
            }
            $i = 0;
            while( isset($entriesToSort[$sortingKey.$i]) ) {
                $i++;
            }
            $entriesToSort[$sortingKey.$i] = $entry;
        }
        uksort($entriesToSort, 'strnatcasecmp');
        return $sortRev ? array_reverse($entriesToSort) : $entriesToSort;
    }



    /**
     * Get list of identifiers from list of nodes. Also, apply filters and slice nodes.
     * 
     * @param \MjCPath|null $containingFolderPath     null or path to folder containing all results
     * @param \MjCNode[] $entries                Array of nodes
     * @param array $filterMethods               Filter methods used to filter the items
     * @param int|bool $start
     * @param int|bool $numberOfItems
     * @param string[]                           list of identifiers
     */
    protected function getIdentifiersFromResultList( ?\MjCPath $containingFolderPath, array $entries, array $filterMethods, $start=0, $numberOfItems=0 ) : array
    {
        $identifiers = [];
        foreach ($entries as $entry) {
            $isFolder = $entry->IsFolder();
            $fullPath = $containingFolderPath ? $containingFolderPath->GetAppended($entry->GetPathSegment()) : $entry->GetPath();
            $fullPathStr = $isFolder ? $fullPath->GetFolderPathString() : (string)$fullPath;
            // $this->add2NodeCache($fullPath,$entry);
            if( $filterMethods ) {
                $filename = $entry->GetPathSegment();
                if ( !$this->applyFilterMethodsToDirectoryItem($filterMethods,$filename,$fullPathStr,(string)$fullPath->GetDirname()) ) {
                    continue;
                }
            }
            $identifiers[] = $fullPathStr;
        }
        if( $start || $numberOfItems ) return array_slice( $identifiers, (int)$start, (int)$numberOfItems===0 ? null : (int)$numberOfItems );
        return $identifiers;
    }






    /**
     * Applies a set of filter methods to a file name to find out if it should be used or not. This is e.g. used by
     * directory listings.
     *
     * @param array $filterMethods The filter methods to use
     * @param string $itemName
     * @param string $itemIdentifier
     * @param string $parentIdentifier
     * @return bool
     * @throws \RuntimeException
     */
    protected function applyFilterMethodsToDirectoryItem(array $filterMethods, $itemName, $itemIdentifier, $parentIdentifier) : bool
    {
        foreach ($filterMethods as $filter) {
            if (is_callable($filter)) {
                $result = call_user_func($filter, $itemName, $itemIdentifier, $parentIdentifier, [], $this);
                // We have to use -1 as the „don't include“ return value, as call_user_func() will return FALSE
                // If calling the method succeeded and thus we can't use that as a return value.
                if ($result === -1) {
                    return false;
                }
                if ($result === false) {
                    throw new \RuntimeException(
                        'Could not apply file/folder name filter ' . $filter[0] . '::' . $filter[1],
                        1476046425
                    );
                }
            }
        }
        return true;
    }











    /**
     * Checks if a given identifier is within a container, e.g. if
     * a file or folder is within another folder.
     * This can e.g. be used to check for web-mounts.
     *
     * Hint: this also needs to return TRUE if the given identifier
     * matches the container identifier to allow access to the root
     * folder of a filemount.
     *
     * @param string $folderIdentifier
     * @param string $identifier identifier to be checked against $folderIdentifier
     *
     * @return bool TRUE if $content is within or matches $folderIdentifier
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     */
    public function isWithin($folderIdentifier, $identifier) : bool
    {
        $folderPath = new MjCPath($folderIdentifier);
        $checkPath = new MjCPath($identifier);
        return mj_str_starts_with((string)$checkPath,(string)$folderPath);
    }

    /**
     * Checks if a file exists.
     *
     * @param string $fileIdentifier
     * @return bool
     */
    public function fileExists( $fileIdentifier ) : bool
    {
        try {
            $file = $this->getDocumentByIdentifier($fileIdentifier);
            return $file instanceof \MjCDocument;
        } catch( \MjCObjectNotFoundException $e ) {
            return false;
        }
    }



    /**
     * Checks if a folder exists.
     *
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExists( $folderIdentifier ) : bool
    {
        if ($folderIdentifier === self::ROOT_FOLDER_IDENTIFIER) return true;
        try {
            $folderPath = new \MjCPath($folderIdentifier);
            $folder = $this->getManjaRepository()->GetNodeByPath($folderPath);
            return $folder instanceof \MjCFolder;
        } catch( \MjCObjectNotFoundException $e ) {
            return false;
        }
    }








    /**
     * Creates a hash for a file.
     *
     * @param string $fileIdentifier
     * @param string $hashAlgorithm The hash algorithm to use
     * @return string
     */
    public function hash($fileIdentifier, $hashAlgorithm)
    {
        return $this->hashIdentifier($fileIdentifier);
    }

    /**
     * Returns a path to a local copy of a file for processing it. When changing the
     * file, you have to take care of replacing the current version yourself!
     *
     * @param string $fileIdentifier
     * @param bool $writable Set this to FALSE if you only need the file for read
     *                       operations. This might speed up things, e.g. by using
     *                       a cached local version. Never modify the file if you
     *                       have set this flag!
     * @return string The path to the file on the local disk
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true)
    {
        return $this->copyFileToTemporaryPath($fileIdentifier);
    }

    /**
     * Directly output the contents of the file to the output
     * buffer. Should not take care of header files or flushing
     * buffer before. Will be taken care of by the Storage.
     *
     * @param string $fileIdentifier
     */
    public function dumpFileContents($fileIdentifier)
    {
        /** @var \MjCDocument $document */
        $document = $this->getDocumentByIdentifier($fileIdentifier);
        if ($document instanceof \MjCDocument) {
            $document->SendToClient();
            // $handle = fopen('php://output', 'w');
            // fwrite($handle, $document->GetContent());
            // fclose($handle);
        }
    }

    /**
     * Returns the contents of a file. Beware that this requires to load the
     * complete file into memory and also may require fetching the file from an
     * external location. So this might be an expensive operation (both in terms of
     * processing resources and money) for large files.
     *
     * @param string $fileIdentifier
     * @return string The file contents
     */
    public function getFileContents($fileIdentifier)
    {
        /** @var \MjCDocument $document */
        $document = $this->getDocumentByIdentifier($fileIdentifier);
        if ($document instanceof \MjCDocument) {
            return $document->GetContent();
        }
    }


    //TODO: 
    // /**
    //  * Stream file using a PSR-7 Response object.
    //  *
    //  * @param string $fileIdentifier
    //  * @param array $properties
    //  * @return ResponseInterface
    //  */
    // public function streamFile(string $fileIdentifier, array $properties): ResponseInterface
    // {
    //     ob_start();
    //     try {
    //         /** @var \MjCDocument $document */
    //         $document = $this->getDocumentByIdentifier($fileIdentifier);
    //         if ($document instanceof \MjCDocument) {
    //             $stream_ressource = $document->GetStream();

    //             $http_resp_code = http_response_code();
    //             $http_headers = headers_list();

    //             $assoc_http_headers = [];
    //             foreach( $http_headers as $header_line ) {
    //                 $header_parts = explode(':',$header_line,2);
    //                 if( isset($header_parts[1]) ) {
    //                     if( isset($assoc_http_headers[$header_parts[0]]) ) $assoc_http_headers[$header_parts[0]][] = $header_parts[1];
    //                     else $assoc_http_headers[$header_parts[0]] = $header_parts[1];
    //                 }
    //             }

    //             return new Response(
    //                 new \TYPO3\CMS\Core\Http\Stream($stream_ressource,'r'),
    //                 $http_resp_code,
    //                 $assoc_http_headers
    //             );
    //         }
    //     } finally {
    //         ob_end_clean();
    //     }

    // }


    /**
     * Returns document object by file identifier (path)
     * Get \MjCDocument from cache or from repository and add to cache
     *
     * @param $fileIdentifier
     * @return mixed $document Instance of \MjCDocument or NULL if not found
     */
    protected function getDocumentByIdentifier( string $fileIdentifier ) : ?\MjCDocument
    {
        $documentPathStr = $fileIdentifier;
        if( isset($this->_documents_by_path_lru_cache[$documentPathStr]) ) {
            return $this->_documents_by_path_lru_cache[$documentPathStr];
        }

        $cacheIdentifier = $this->getDocumentCacheIdentifier($fileIdentifier);

        $document = $this->getDocumentCacheByCacheIdentifier($cacheIdentifier);
        if ($document instanceof \MjCDocument) {
            return $document;
        }

        $document = $this->getManjaRepository()->GetNodeByPathString($fileIdentifier);
        if ( !($document instanceof \MjCDocument) ) {
            return null;
        }

        // Add to cache
        $this->setDocumentCacheByCacheIdentifier(
            $cacheIdentifier,
            $document
        );

        return $this->_documents_by_path_lru_cache[$documentPathStr] = $document;
    }

    /**
     * Returns document id by file identifier (path)
     *
     * @param string $fileIdentifier
     * @return int|null $documentId
     */
    protected function getDocumentIdByIdentifier( string $fileIdentifier ): ?int
    {
        /** @var \MjCDocument $document */
        $document = $this->getDocumentByIdentifier($fileIdentifier);
        if ($document instanceof \MjCDocument) {
            return (int)$document->GetDocumentId();
        }
        return null;
    }

    /**
     * Cache manja document
     *
     * @param string $cacheIdentifier
     * @param \MjCDocument $document
     */
    protected function setDocumentCacheByCacheIdentifier( string $cacheIdentifier, \MjCDocument $document ) : void {
        if (!$this->cache->has($cacheIdentifier)) {
            $this->cache->set($cacheIdentifier, $document);
        }
    }

    /**
     * Returns a document from cache, by file identifier
     *
     * @param string $fileIdentifier
     * @return mixed
     */
    protected function getDocumentCacheByFileIdentifier($fileIdentifier)
    {
        $cacheIdentifier = $this->getDocumentCacheIdentifier($fileIdentifier);
        return $this->getDocumentCacheByCacheIdentifier($cacheIdentifier);
    }

    /**
     * Returns a document from cache, by cache identifier
     *
     * @param string $cacheIdentifier
     * @return mixed
     */
    protected function getDocumentCacheByCacheIdentifier( string $cacheIdentifier )
    {
        return ($result=$this->cache->get($cacheIdentifier))===false ? null : $result;
    }

    /**
     * Returns the cache identifier for a given file identifier
     *
     * @param string $fileIdentifier
     * @return string
     */
    protected function getDocumentCacheIdentifier($fileIdentifier): string
    {
        return sha1($this->storageUid . ':' . trim($fileIdentifier, '/'));
    }

    /**
     * Copies a file to a temporary path and returns that path.
     *
     * @param string $fileIdentifier
     * @return string The temporary path
     * @throws \RuntimeException
     */
    protected function copyFileToTemporaryPath($fileIdentifier): string
    {
        /** @var \MjCDocument $file */
        $document = $this->getDocumentByIdentifier($fileIdentifier);

        if ( !($document instanceof \MjCDocument) ) {
            throw new InvalidFileException(
                'File "' . $fileIdentifier . '" has to be instance of "\MjCDocument"',
                1519867205
            );
        }

        $temporaryPath = $this->getTemporaryPathForFile($fileIdentifier);
        // file_put_contents($temporaryPath,$document->GetContent(),LOCK_EX);

        $trg_stream = fopen($temporaryPath,'w+b');
        if (!flock($trg_stream, LOCK_EX)) {
			fclose($trg_stream);
			unlink($temporaryPath);
			throw new InvalidFileException('File "' . $fileIdentifier . '": Error while getting media item: Could not lock target file.');
		}

        $src_data = $document->GetContent();
        if( $src_data===false || $src_data===null ) {
			flock($trg_stream, LOCK_UN);
			fclose($trg_stream);
            if( true ) {
                // because throwing an exception here may result in failing to show whole directory listings - WTF!? ..
                // -> just succeed with an empty file for typo3 :(
                return $temporaryPath;
            } else {
                // fail with exception (this may result in failing to show whole directory listings - WTF!?)
                unlink($temporaryPath);
                throw new InvalidFileException('File "' . $fileIdentifier . '": Error while retrieving media data from file (source not available).');
            }
        }
    
        $trg_written = fwrite($trg_stream,$src_data);
        if( $trg_written===false || $trg_written!==strlen($src_data) ) {
			flock($trg_stream, LOCK_UN);
			fclose($trg_stream);
			unlink($temporaryPath);
			throw new InvalidFileException('File "' . $fileIdentifier . '": Error while writing media data to temporary file.');
        }


        // $src_stream = $document->GetStream();
        // if( $src_stream===null ) {
		// 	flock($trg_stream, LOCK_UN);
		// 	fclose($trg_stream);
        //     if( true ) {
        //         // because throwing an exception here may result in failing to show whole directory listings - WTF!? ..
        //         // -> just succeed with an empty file for typo3 :(
        //         return $temporaryPath;
        //     } else {
        //         // fail with exception (this may result in failing to show whole directory listings - WTF!?)
        //         unlink($temporaryPath);
        //         throw new InvalidFileException('File "' . $fileIdentifier . '": Error while streaming media to file (source not available).');
        //     }
        // }

        // $copy_res = stream_copy_to_stream($src_stream,$trg_stream);
        // if ($copy_res === false) {
		// 	flock($trg_stream, LOCK_UN);
		// 	fclose($trg_stream);
		// 	unlink($temporaryPath);
		// 	throw new InvalidFileException('File "' . $fileIdentifier . '": Error while streaming media to file.');
		// }

        if (!flock($trg_stream,LOCK_UN) || !fclose($trg_stream)) {
			// Maybe it's a bit much to throw an error on that, but at least it prevents errors down the line (e.g. while trying to read during upload)
			throw new InvalidFileException('File "' . $fileIdentifier . '": Error while getting media item: Could not unlock or close the target file.');
		}

        return $temporaryPath;
    }



    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @param string $folderIdentifier
     * @return bool TRUE if there are no files and folders within $folder
     */
    public function isFolderEmpty($folderIdentifier)
    {
        if( $this->countFoldersInFolder($folderIdentifier,false,[]) ) return false;
        if( $this->countFilesInFolder($folderIdentifier,false,[]) ) return false;
        return true;
    }

    /**
     * Checks if a file inside a folder exists
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return bool
     */
    public function fileExistsInFolder($fileName, $folderIdentifier)
    {
        $path = ( new MjCPath($folderIdentifier) )->GetAppended($fileName);
        return $this->fileExists((string)$path);
    }

    /**
     * Checks if a folder inside a folder exists.
     *
     * @param string $folderName
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExistsInFolder($folderName, $folderIdentifier)
    {
        $path = ( new MjCPath($folderIdentifier) )->GetAppended($folderName);
        return $this->folderExists($path->GetFolderPathString());
    }



    /*********** UNUSED FUNCTIONS *************************************************************************************/

    /**
     * Creates a folder, within a parent folder.
     * If no parent folder is given, a root level folder will be created
     *
     * @param string $newFolderName
     * @param string $parentFolderIdentifier
     * @param bool $recursive
     * @return string the Identifier of the new folder
     */
    public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = false)
    {
        // Feature not available
    }

    /**
     * Returns the public URL to a file.
     * Either fully qualified URL or relative to PATH_site (rawurlencoded).
     * @param string $identifier
     * @return string
     */
    public function getPublicUrl($identifier)
    {
        // Feature not available
    }

    /**
     * Renames a folder in this storage.
     *
     * @param string $folderIdentifier
     * @param string $newName
     * @return array A map of old to new file identifiers of all affected resources
     */
    public function renameFolder($folderIdentifier, $newName)
    {
        // Feature not available
    }

    /**
     * Removes a folder in filesystem.
     *
     * @param string $folderIdentifier
     * @param bool $deleteRecursively
     * @return bool
     * @throws FileOperationErrorException
     */
    public function deleteFolder($folderIdentifier, $deleteRecursively = false)
    {
        // Feature not available
    }

    /**
     * Adds a file from the local server hard disk to a given path in TYPO3s
     * virtual file system. This assumes that the local file exists, so no
     * further check is done here! After a successful the original file must
     * not exist anymore.
     *
     * @param string $localFilePath (within PATH_site)
     * @param string $targetFolderIdentifier
     * @param string $newFileName optional, if not given original name is used
     * @param bool $removeOriginal if set the original file will be removed
     *                                after successful operation
     * @return string the identifier of the new file
     */
    public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = true)
    {
        // Feature not available
    }

    /**
     * Creates a new (empty) file and returns the identifier.
     *
     * @param string $fileName
     * @param string $parentFolderIdentifier
     * @return string
     * @throws InvalidFileNameException
     */
    public function createFile($fileName, $parentFolderIdentifier)
    {
        // Feature not available
    }

    /**
     * Copies a file *within* the current storage.
     * Note that this is only about an inner storage copy action,
     * where a file is just copied to another folder in the same storage.
     *
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $fileName
     * @return string the Identifier of the new file
     */
    public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName)
    {
        // Feature not available
    }

    /**
     * Renames a file in this storage.
     *
     * @param string $fileIdentifier
     * @param string $newName The target path (including the file name!)
     * @return string The identifier of the file after renaming
     * @throws ExistingTargetFileNameException
     */
    public function renameFile($fileIdentifier, $newName)
    {
        // Feature not available
    }

    /**
     * Replaces a file with file in local file system.
     *
     * @param string $fileIdentifier
     * @param string $localFilePath
     * @return bool TRUE if the operation succeeded
     */
    public function replaceFile($fileIdentifier, $localFilePath)
    {
        // Feature not available
    }

    /**
     * Removes a file from the filesystem. This does not check if the file is
     * still used or if it is a bad idea to delete it for some other reason
     * this has to be taken care of in the upper layers (e.g. the Storage)!
     *
     * @param string $fileIdentifier
     * @return bool TRUE if deleting the file succeeded
     */
    public function deleteFile($fileIdentifier)
    {
        // Feature not available
    }

    /**
     * Moves a file *within* the current storage.
     * Note that this is only about an inner-storage move action,
     * where a file is just moved to another folder in the same storage.
     *
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFileName
     * @return string
     */
    public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName)
    {
        // Feature not available
    }

    /**
     * Folder equivalent to moveFileWithinStorage().
     *
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     * @return array All files which are affected, map of old => new file identifiers
     * @throws FolderDoesNotExistException
     */
    public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        // Feature not available
    }

    /**
     * Folder equivalent to copyFileWithinStorage().
     *
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     * @return bool
     */
    public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        // Feature not available
    }

    /**
     * Sets the contents of a file to the specified value.
     *
     * @param string $fileIdentifier
     * @param string $contents
     * @return int The number of bytes written to the file
     */
    public function setFileContents($fileIdentifier, $contents)
    {
        // Feature not available
    }

}
