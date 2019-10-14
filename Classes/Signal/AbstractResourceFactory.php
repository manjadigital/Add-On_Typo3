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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * AbstractResourceFactory
 *
 * @since 2.0.0 introduced first time
 */
abstract class AbstractResourceFactory
{

    /**
     * preProcessStorage
     *
     * check the configured process folder and sets it to a default if:
     * - storage is readOnly and processed folder is inside the same storage or
     * - the configured path is not a valid storage path
     *
     * @param \TYPO3\CMS\Core\Resource\ResourceFactory $resourcefactory
     * @param int $uid
     * @param array $recordData
     * @param string $fileIdentifier
     *
     * @return array
     */
    abstract public function preProcessStorage(
        \TYPO3\CMS\Core\Resource\ResourceFactory $resourcefactory,
        int $uid,
        array $recordData,
        string $fileIdentifier = null
    ): array;

    /**
     * Adds message to TYPO3 flash message queue
     *
     * @param string   $message
     * @param int|null $severity
     *
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function addFlashMessage(
        string $message,
        ?int $severity
    ): void {

        /** @var FlashMessage $flashMessage */
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            '',
            $severity ?? FlashMessage::OK
        );

        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);

        /** @var FlashMessageQueue $defaultFlashMessageQueue */
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }
}
