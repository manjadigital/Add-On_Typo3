<?php
declare(strict_types = 1);

namespace Jokumer\FalManja\Signal;

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

use Jokumer\FalManja\Driver\FalManja;
use Jokumer\FalManja\Driver\ManjaDriver;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * AbstractFileIndexRepository
 *
 * @since 2.0.0 introduced the first time
 */
abstract class AbstractFileIndexRepository
{

    /**
     * @var \Jokumer\FalManja\Driver\ManjaDriver
     */
    protected $manjaDriver;

    /**
     * @var ResourceStorage
     */
    protected $storage;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * Initializes this object. This is called by the storage after the driver
     * has been attached.
     *
     * @param int $storageId
     */
    public function initialize( int $storageId ) : void {
        $this->initializeStorage($storageId);
        if ($this->storage !== null) {
            $this->configuration = $this->storage->getConfiguration();
            $this->initializeManjaDriver();
        }
    }

    /**
     * recordUpdatedOrCreated
     * Signal function called when a file were added or updated in manja storage
     *
     * @param array $data
     */
    abstract protected function recordUpdatedOrCreated(array $data): void;

    /**
     * Initialize storage
     *
     * @param int $storageId
     */
    protected function initializeStorage( int $storageId ) : void {
        $storageObject = ResourceFactory::getInstance()->getStorageObject($storageId);
        if ($storageObject->getDriverType() === FalManja::DRIVER_TYPE) {
            $this->storage = $storageObject;
        }
    }

    /**
     * Get meta data repository
     *
     * @return MetaDataRepository
     */
    protected function getMetaDataRepository() : MetaDataRepository {
        /** @var MetaDataRepository $metaDataRepository */
        $metaDataRepository = GeneralUtility::makeInstance(MetaDataRepository::class);
        return $metaDataRepository;
    }

    /**
     * initializeManjaDriver
     */
    protected function initializeManjaDriver() : void {
        if( $this->storage && ($storageRecord=$this->storage->getStorageRecord()) && ($storageRecord['uid']??false) ) {

            // grab & use the driver instance from storage
            // - but Typo3 ResourceStorage interface does not give us access to the driver (how stupid encapsulation is that!?),
            // - so, grab from our own instance list & match by storageUID (to avoid collisions - eg. when multiple manja storages are in use)
            if( ($storageDriver=ManjaDriver::getInstanceByStorageUID($storageRecord['uid']))!==null ) {
                $this->manjaDriver = $storageDriver;
                return;
            }
            
        }

        // WARN: this will create a second instance of ManjaDriver (with all things separate: connection, repository, caches, etc.)
        // -> very unscalable, very slow -> results in many unnecessary re-connects and so on
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->manjaDriver = $objectManager->get(ManjaDriver::class, $this->configuration);
        if ($this->manjaDriver !== null) {
            $this->manjaDriver->initialize();
        }
    }
}
