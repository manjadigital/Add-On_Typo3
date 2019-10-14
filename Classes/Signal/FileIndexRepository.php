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
 * (c) 2017 Markus Hölzle, Stefan Lamm
 * (c) 2018-present Joerg Kummer, Falk Röder
 *
 * @author Markus Hölzle <m.hoelzle@andersundsehr.com>, anders und sehr GmbH
 * @author Stefan Lamm <s.lamm@andersundsehr.com>, anders und sehr GmbH
 * @author J. Kummer <typo3@enobe.de>
 * @author Falk Röder <mail@falk-roeder.de>
 *
 ***/

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * FileIndexRepository
 *
 * Signals for metadata update
 *
 * @since 1.0.0 introduced first time
 * @since 2.0.0 moved some functionality to abstract class and made this final
 * @since 2.0.0 added feature mapping of manja meta data to TYPO3 meta data
 */
final class FileIndexRepository extends AbstractFileIndexRepository
{

    /**
     * @var array
     */
    protected $datTimeFields = [109, 113];

    /**
     * @var string
     */
    protected $mappingKey = 'meta_mapping';

    /**
     * Record updated or created
     * {@inheritDoc}
     */
    public function recordUpdatedOrCreated(
        array $data
    ): void {
        $this->initialize((int)$data['storage']);

        if ($this->storage === null) {
            return;
        }

        /** @var MetaDataRepository $metaDataRepository */
        $metaDataRepository = $this->getMetaDataRepository();

        /**
         * $t3MetaData['is_manja'] is used in TCA to
         * distinguish between documents in manja storage and all other documents.
         * To be more specific: it is used to add all manja related meta data fields
         * only to those metdata records, which belong to a manja document by using
         * the TCA feature of subtype_value_field
         *
         * @var array $t3MetaData
         */
        $t3MetaData = $metaDataRepository->findByFileUid($data['uid']);
        $t3MetaData['is_manja'] = 1;

        $this->getManjaMetaData(
            $data['identifier'],
            $t3MetaData
        );

        if ($data['type'] === File::FILETYPE_IMAGE) {
            $file = $this->storage->getFile($data['identifier']);
            $fileNameAndPath = $file->getForLocalProcessing(false);
            /** @var ImageInfo $imageInfo */
            $imageInfo = GeneralUtility::makeInstance(ImageInfo::class, $fileNameAndPath);
            $t3MetaData['width'] = $imageInfo->getWidth();
            $t3MetaData['height'] = $imageInfo->getHeight();
        }

        $metaDataRepository->update($data['uid'], $t3MetaData);
    }

    /**
     * getManjaMetaData
     *
     * gets meta data of an document from manja server
     * and fills the sys_file_metadata array with the mapped values
     *
     * @param string $fileIdentifier
     * @param array  $t3MetaData
     */
    public function getManjaMetaData(
        string $fileIdentifier,
        array &$t3MetaData
    ): void {
        if (
            !isset($this->configuration[$this->mappingKey]) ||
            count($this->configuration[$this->mappingKey]) === 0) {
            return;
        }

        $metaIds = [];
        foreach ($this->configuration[$this->mappingKey] as $metaId) {
            $metaIds[] = $metaId;
        }

        $metaData = $this->manjaDriver->getDocumentMetaData(
            $fileIdentifier,
            $metaIds
        );

        foreach ($this->configuration[$this->mappingKey] as $metaDataField => $meta_id) {
            if (!isset($metaData[$meta_id][0])) {
                continue;
            }

            if (in_array((int)$meta_id, $this->datTimeFields, true)) {
                $dtime = \DateTime::createFromFormat(
                        'Y-m-d H:i:s',
                        $metaData[$meta_id][0],
                        new \DateTimeZone('UTC')
                    );

                $t3MetaData[$metaDataField] = $dtime ? $dtime->getTimestamp() : 0;

                continue;
            }

            if (is_array($metaData[$meta_id])) {
                $t3MetaData[$metaDataField] = implode(', ', $metaData[$meta_id]);
            } else {
                $t3MetaData[$metaDataField] = $metaData[$meta_id];
            }
        }
    }
}
