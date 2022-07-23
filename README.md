# Manja Digital Asset Management driver for TYPO3 File Abstraction Layer

This extension provides the necessary interfaces to connect a TYPO3 instance to Manja Digital Asset Management

Includes Manja API 4.0

## Requirements

* TYPO3 CMS 11.5
* PHP >= 7.4

## License

This TYPO3 extension is licensed under GPL version 2 or any later version.

## Installation

### Install via Composer

If extension will be available at [packagist](https://packagist.org/packages/manja/typo3-storage-connector) you can require it via composer

`composer require manja/typo3-storage-connector`

### Add file storage

To connect Manja Digital Asset Management with TYPO3 you need to create a new file storage at root level.

![AddFileStorageNew](Resources/Public/Documentation/02_AddFileStorageNew.png)  
_Add new file storage_

Name your new file storage and select `Manja Digital Asset Management` as driver, which will open further fields to setup your file storage.

![AddFileStorageDriver](Resources/Public/Documentation/03_AddFileStorageDriver.png)  
_Add file storage driver_

Setup your file storage with given credientlials in first tab `General` for at least `host`, `port`, `username`, `password`, `client id` and `tree id`.

Settings for `timeout`, `stream timeout`, `use SSL`, and `use session` has default values, which can be modified.

Checkbox for `Is default storage` is not enabled by default.  
Normally folder `fileadmin` is auto created and the default storage.

Checkbox for `Automaticly extract metadata after upload` is enabled by default.

Enter `Folder for manipulated and temporary images etc.` for temporary folder of processed files. The entered value needs to be in the form `{storageId}:/{path/tho/folder}`
where {storageId} must be the ID of a writable storage. If you leave it empty, or set it to a wrong value, a warning is shown and a default value of `0:/typo3temp/assets/_processed_manja` will be used.
Any file from Manja server which is used in any modified versions (croped, resized, etc.) will be stored automaticly by TYPO3 in this folder.

![AddFileStorageSettings](Resources/Public/Documentation/04_AddFileStorageSettings.png)  
_Add file storage settings_

Checkbox for `is writable` in tab `Access capabilities` has no effect, cause this file storage driver does not support modification of files at Manja server via TYPO3 backend.

#### Configure Mapping of metadata

If any document is loaded from the Manja Server to TYPO3, the extension downloads also basic Metadata Fields of the document and writes them into TYPO3 Database. The field Mapping has a defaulot configuration, you might want to change this in Tab `Metadata`.
![ConfigureMetaDataMapping](Resources/Public/Documentation/04_02_AddFileStorageSettings.png)  
_Configure MetaData Mapping_

### Add file mount and access for editors

For backend editors (if you have) you usually need to add file mounts to get access to such folders inside your file storage.  
Therefore you should create a new file mount entry in your TYPO3 root level.

![AddFileMountNew](Resources/Public/Documentation/05_AddFileMountNew.png)  
_Add new file mount_

Enter a label name and select your newly created file storage from the list.

![AddFileMountDriver](Resources/Public/Documentation/06_AddFileMountDriver.png)  
_Add file mount driver_

Add settings for file mount and select a folder which exists at Manja server, to grant access for editors. The first entry `/` is for root level and all its subfolders.

![AddFileMountSettings](Resources/Public/Documentation/07_AddFileMountSettings.png)  
_Add file mount settings_

Add your newly created file mount to backend editors access settings, which can be edited for each editor separately or by its group at root level.

![AddFileMountToEditors](Resources/Public/Documentation/08_AddFileMountToEditors.png)  
_Add file mount to editors_

## Access files and folder at Manja server

In TYPO3 backend modul `Filelist` editors have access to all file mounts and its subfolders, which are configurated.

First time you select folders from Manja server, it will take extra time to load images from server and generate thumbnails for preview in TYPO3 backend, if you have choosen to show thumbnails in your profile.

![BackendModulFileList](Resources/Public/Documentation/09_BackendModulFileList.png)  
_Backend modul file list_

Such files and folders can be reached also in `Filebrowser`, which comes up, if editors want to add or select media files to any content element.

Files and folders from Manja Server are readable only and not editable via TYPO3 - therefore all folders has a locked symbol.

The search in `Filelist` and `Filebrowser` searches for file name only per default in TYPO3. To search and sort by further informations you need a thirdparty extension for your TYPO3 installation.

## Changelog ###
### 2.0.0 ###
**BREAKING CHANGES**
* [TASK] Drop support for TYPO3 < 8.7 and PHP < 7.2

**OTHER CHANGES**
* [FEATURE] Add metaData Mapping to driver configuration
* [FIX] Fix problems with _\_processed_\_ folder


## Development

### Manja Digital Asset Management

IT-Service Robert Frunzke [www.manjadigital.de](https://www.manjadigital.de)

### Developers

Robert Frunzke mail@manjadigital.de
Martin Hoff m.hoff@manjadigital.de
Falk Röder mail@falk-roeder.de  
Jörg Kummer service@enobe.de

### Git repository

Private repository at [gitlab](https://manjagit.virtomain.de/manja/Add-On_Typo3) during development.  
A public repository is planned by manjadigital.

