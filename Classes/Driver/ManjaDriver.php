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
 * ManjaDriver
 *
 * @since   1.1.0
 * @since   2.0.0 moved some functionality to abstract class and made this final
 * @since   2.0.0 added function to receive metadata for any document from the manja server
 */
final class ManjaDriver extends AbstractManjaDriver
{

    /**
     * getDocumentMetaData
     *
     * returns the mtea properties for a manja document
     *
     * @param string $fileIdentifier
     * @param array $metaIds
     * @return array
     */
    public function getDocumentMetaData(
        string $fileIdentifier,
        array $metaIds
    ): array {
        $metaData = [];

        if ($fileIdentifier === '' || count($metaIds) === 0) {
            return $metaData;
        }

        $nodeId = $this->getDocumentIdByIdentifier($fileIdentifier);

        if ($nodeId === null) {
            return $metaData;
        }

        $metaData = $this->getManjaServer()
            ->MediaMetaList(
                [$nodeId],
                $metaIds
            );

        return $metaData[$nodeId] ?? $metaData;
    }
}
