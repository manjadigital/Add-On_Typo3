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

use Jokumer\FalManja\Driver\FalManja;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

call_user_func(static function () {
    $extKey = 'fal_manja';
    $lang = 'LLL:EXT:fal_manja/Resources/Private/Language/locallang_be.xlf:';

    //## extend TCA for file metadata
    $tempColumns = [
        'is_manja'    => [
            'exclude' => true,
            'label'   => $lang . 'sys_file_metadata.is_manja',
            'config'  => [
                'type' => 'passthrough',
            ],
        ],
        'subject'     => [
            'exclude' => false,
            'label'   => $lang . 'sys_file_metadata.subject',
            'config'  => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'coverage'    => [
            'exclude' => false,
            'label'   => $lang . 'sys_file_metadata.coverage',
            'config'  => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'contributor' => [
            'exclude' => false,
            'label'   => $lang . 'sys_file_metadata.contributor',
            'config'  => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'created'     => [
            'exclude' => false,
            'label'   => $lang . 'sys_file_metadata.created',
            'config'  => [
                'type'       => 'input',
                'renderType' => 'inputDateTime',
                'eval'       => 'datetime,int'
            ],
        ],
        'changed'     => [
            'exclude' => true,
            'label'   => $lang . 'sys_file_metadata.changed',
            'config'  => [
                'type'       => 'input',
                'renderType' => 'inputDateTime',
                'eval'       => 'datetime,int'
            ],
        ]
    ];

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $tempColumns);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
        'sys_file_metadata',
                                                                         'is_manja, subject, coverage, contributor, created, changed',
                                                                         '',
        'after:alternative'
    );

    $GLOBALS['TCA']['sys_file_metadata']['interface']['showRecordFieldList'] .= 'subject, coverage, contributor, created, changed';

    /**
     *  using subtype_value_field to hide all
     * fields we added for manja documents in all
     * other storages which are not based on manja driver
     */

    /*We need a valid BE_USER object for quering storage repository */
    if ($GLOBALS['BE_USER'] === null) {
        $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER']->start();
    }

    /** @var StorageRepository $storageRepository */
    $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
    $allStorages = $storageRepository->findAll();

    $subtypesConfiguration = [
        'subtype_value_field'  => 'is_manja',
        'subtypes_excludelist' => []
    ];

    foreach ($allStorages as $storage) {
        if ($storage->getDriverType() !== FalManja::DRIVER_TYPE) {
            $subtypesConfiguration['subtypes_excludelist'][$storage->getUid()]
                = 'subject, coverage, contributor, created, changed';
        }
    }

    foreach ($GLOBALS['TCA']['sys_file_metadata']['types'] as $type => $conf) {
        $GLOBALS['TCA']['sys_file_metadata']['types'][$type] = array_merge($conf, $subtypesConfiguration);
    }
});
