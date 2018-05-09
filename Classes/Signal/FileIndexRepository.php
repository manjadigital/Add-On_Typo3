<?php
namespace Jokumer\FalManja\Signal;

/***
 *
 * This file is part of an "anders und sehr" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2017 Markus Hölzle <m.hoelzle@andersundsehr.com>, anders und sehr GmbH
 * Stefan Lamm <s.lamm@andersundsehr.com>, anders und sehr GmbH
 *
 ***/

use Jokumer\FalManja\Driver\ManjaDriver;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Type\File\ImageInfo;

/**
 * Signals for metadata update
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>
 * @author Stefan Lamm <s.lamm@andersundsehr.com>
 * @package AUS\AusDriverAmazonS3\Signal
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
        return GeneralUtility::makeInstance(MetaDataRepository::class);
    }
}
