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
if (!defined('TYPO3')) {
    die ('Access denied.');
}

call_user_func(
    function () {
        $extKey = 'fal_manja';

        // Register driver
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers'][$extKey] = [
            'class' => \Jokumer\FalManja\Driver\ManjaDriver::class,
            'shortName' => \Jokumer\FalManja\Driver\FalManja::DRIVER_SHORT_NAME,
            'flexFormDS' => 'FILE:EXT:fal_manja/Configuration/FlexForms/ManjaDriver.xml',
            'label' => 'Manja Digital Asset Management'
        ];
        // Cache configuration
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey])) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey] = [
                'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
                'options' => [
                    'defaultLifetime' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::UNLIMITED_LIFETIME
                ],
            ];
        }

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
            '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:fal_manja/Configuration/TSconfig/Static/BackendForms.ts">'
        );

        $extractorRegistry = \TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::getInstance();
        $extractorRegistry->registerExtractionService(\Jokumer\FalManja\Ressource\ManjaExtractor::class);
    }
);
