<?php
declare(strict_types = 1);

namespace Jokumer\FalManja\Driver;

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
 * FalManja
 *
 * only purpose of this class is, to define constants
 * which might be used in different classes.
 * So no multiple declarations in different classes are needed.
 * Using constants of a class outside the class itself
 * should be considered bad practice
 *
 * @since   2.0.0 introduced first time
 */
final class FalManja
{

    /**
     * @const string
     */
    public const DRIVER_TYPE = 'fal_manja';

    /**
     * @const string
     */
    public const DRIVER_SHORT_NAME = 'fal_manja';

    /**
     * @const string
     */
    public const PROCESSING_FOLDER_DEFAULT = '0:/typo3temp/assets/_processed_manja';
}
