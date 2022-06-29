<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'FAL Manja',
    'description' => 'Provides a Manja driver for TYPO3 File Abstraction Layer.',
    'category' => 'plugin',
    'author' => 'Falk Roeder, Joerg Kummer',
    'author_email' => 'mail@falk-roeder.de, service@enobe.de, post@manjadigital.de',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'alpha',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '4.41.4',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Jokumer\\FalManja\\' => 'Classes',
        ],
        'classmap' => [
            'Resources/Private/Vendor/manja-api-4.39',
        ],
        'files' => [
            'Resources/Private/Vendor/manja-api-4.39/util/util.php',
        ],
    ],
];
