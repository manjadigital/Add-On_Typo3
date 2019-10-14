<?php
declare(strict_types = 1);

namespace Jokumer\FalManja\Signal;

use Jokumer\FalManja\Driver\FalManja;
use TYPO3\CMS\Core\Messaging\FlashMessage;

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

/**
 * ResourceFactory
 *
 * @since 2.0.0 introduced first time
 */
final class ResourceFactory extends AbstractResourceFactory
{

    /**
     * preProcessStorage
     * @inheritDoc
     *
     * @param \TYPO3\CMS\Core\Resource\ResourceFactory $resourcefactory
     * @param int                                      $uid
     * @param array                                    $recordData
     * @param string                                   $fileIdentifier
     *
     * @return array
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function preProcessStorage(
        \TYPO3\CMS\Core\Resource\ResourceFactory $resourcefactory,
        int $uid,
        array $recordData,
        string $fileIdentifier = null
    ): array {
        if ($recordData['driver'] === FalManja::DRIVER_TYPE) {
            preg_match('/^(\d+):\//', $recordData['processingfolder'], $matches, PREG_OFFSET_CAPTURE);

            if (
                count($matches) === 0 ||
                ($recordData['is_writable'] === 0 && (int)$matches[1][0] === $uid)
            ) {
                $message = 'ReadOnly-Storage (' . $recordData['name']
                           . '): The processed folder must be local! Processed folder is set to default \''
                           . FalManja::PROCESSING_FOLDER_DEFAULT . '\' which stores processed files in typo3temp.';

                $this->addFlashMessage($message, FlashMessage::WARNING);

                $recordData['processingfolder'] = FalManja::PROCESSING_FOLDER_DEFAULT;
            }
        }

        return [
            $resourcefactory,
            $uid,
            $recordData,
            $fileIdentifier
        ];
    }
}
