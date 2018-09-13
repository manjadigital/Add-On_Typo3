<?php
namespace Jokumer\FalManja\Signal;

use Jokumer\FalManja\Driver\ManjaDriver;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Type\File\ImageInfo;

/**
 * Class FileIndexRepository
 * Signals for metadata update
 *
 * @package TYPO3
 * @subpackage tx_falmanja
 * @author (c) 2017 Markus Hölzle <m.hoelzle@andersundsehr.com>, anders und sehr GmbH
 * @author Stefan Lamm <s.lamm@andersundsehr.com>, anders und sehr GmbH
 * @author Falk Roeder <mail@falk-roeder.de>
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileIndexRepository
{

    /**
     * Record updated or created
     * 
     * @param array $data
     * @return void|null
     */
    public function recordUpdatedOrCreated($data)
    {
        if ($data['type'] === File::FILETYPE_IMAGE) {
            $storage = $this->getStorage($data['storage']);
            // only process on our driver type where data was missing
            if ($storage->getDriverType() !== ManjaDriver::DRIVER_TYPE) {
                return null;
            }
            $file = $storage->getFile($data['identifier']);
            $fileNameAndPath = $file->getForLocalProcessing(false);
            /** @var ImageInfo $imageInfo */
            $imageInfo = GeneralUtility::makeInstance(ImageInfo::class, $fileNameAndPath);
            /** @var MetaDataRepository $metaDataRepository */
            $metaDataRepository = $this->getMetaDataRepository();
            $metaData = $metaDataRepository->findByFileUid($data['uid']);
            $metaData['width'] = $imageInfo->getWidth();
            $metaData['height'] = $imageInfo->getHeight();
            $metaDataRepository->update($data['uid'], $metaData);
        }
    }

    /**
     * Get storage
     * 
     * @param int $uid
     * @return \TYPO3\CMS\Core\Resource\ResourceStorage
     */
    protected function getStorage($uid)
    {
        return ResourceFactory::getInstance()->getStorageObject($uid);
    }

    /**
     * Get meta data repository
     * 
     * @return \TYPO3\CMS\Core\Resource\Index\MetaDataRepository
     */
    protected function getMetaDataRepository()
    {
        /** @var MetaDataRepository $metaDataRepository */
        $metaDataRepository = GeneralUtility::makeInstance(MetaDataRepository::class);
        return $metaDataRepository;
    }
}
