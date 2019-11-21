<?php

defined('TYPO3_MODE') or die();

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

use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

call_user_func(
    static function () {
        $extKey = 'fal_manja';
        $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath($extKey);

        // Register driver
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers'][$extKey] = [
            'class' => \Jokumer\FalManja\Driver\ManjaDriver::class,
            'shortName' => \Jokumer\FalManja\Driver\FalManja::DRIVER_SHORT_NAME,
            'flexFormDS' => 'FILE:EXT:fal_manja/Configuration/FlexForms/ManjaDriver.xml',
            'label' => 'Manja Digital Asset Management'
        ];
        // Cache configuration
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey])) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey] = [
                'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
                'options' => [
                    'defaultLifetime' => 0
                ],
            ];
        }

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
            '<INCLUDE_TYPOSCRIPT: source="FILE:' . $extPath . 'Configuration/TSconfig/Static/BackendForms.ts">'
        );

        /* @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher $signalSlotDispatcher */
        $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(Dispatcher::class);

        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class,
            'recordUpdated',
            \Jokumer\FalManja\Signal\FileIndexRepository::class,
            'recordUpdatedOrCreated'
        );
        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class,
            'recordCreated',
            \Jokumer\FalManja\Signal\FileIndexRepository::class,
            'recordUpdatedOrCreated'
        );
        $signalSlotDispatcher->connect(
            \TYPO3\CMS\Core\Resource\ResourceFactory::class,
            'preProcessStorage',
            \Jokumer\FalManja\Signal\ResourceFactory::class,
            'preProcessStorage'
        );
    }
);
