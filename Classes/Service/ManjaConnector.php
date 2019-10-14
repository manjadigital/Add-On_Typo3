<?php
declare(strict_types = 1);

namespace Jokumer\FalManja\Service;

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
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class ManjaConnector
 *
 * @since 1.0.0
 */
class ManjaConnector implements SingletonInterface
{
    /**
     * The manja connection as status
     *
     * @var bool
     */
    protected $manjaConnection = false;

    /**
     * The manja server instance
     *
     * @var \ManjaServer
     */
    protected $manjaServer;

    /**
     * The host to use for connecting.
     *
     * @var string
     */
    protected $connectionHost = '';

    /**
     * The port to use for connecting.
     *
     * @var int
     */
    protected $connectionPort = 0;

    /**
     * The username to use for connecting.
     *
     * @var string
     */
    protected $connectionUsername = '';

    /**
     * The password to use for connecting.
     *
     * @var string
     */
    protected $connectionPW = '';

    /**
     * The client_id to use for connecting to the host.
     *
     * @var string
     */
    protected $connectionClientId = '';

    /**
     * The tree_id to use for connecting to the host.
     *
     * @var int
     */
    protected $connectionTreeId = 0;

    /**
     * The server connection timeout in seconds.
     * Default is 20 seconds
     *
     * @var int
     */
    protected $connectionTimeout = 20;

    /**
     * The server stream timeout in seconds
     * Default is 3600s=1h
     *
     * @var int
     */
    protected $connectionStreamTimeout = 3600;

    /**
     * Enable ssl encrypted communication to manja server
     *
     * @var bool
     */
    protected $connectionUseSSL = false;

    /**
     * Use sessions
     *
     * @var bool
     */
    protected $connectionUseSessions = true;

    /**
     * Initialize connector, set configurations from driver
     *
     * @param array $configuration
     */
    public function __construct(array $configuration = [])
    {
        $this->connectionHost = $configuration['host'];
        $this->connectionPort = $configuration['port'];
        $this->connectionUsername = $configuration['username'];
        $this->connectionPW = $configuration['password'];
        $this->connectionClientId = $configuration['client_id'];
        $this->connectionTreeId = $configuration['tree_id'];
        $this->connectionTimeout = $configuration['timeout'];
        $this->connectionStreamTimeout = $configuration['stream_timeout'];
        $this->connectionUseSSL = $configuration['use_ssl'];
        $this->connectionUseSessions = $configuration['use_session'];
    }

    /**
     * Processes the connection for ManjaServer
     *
     * @return \ManjaServer
     */
    public function processConnection()
    {
        // Create server object
        $this->manjaServer = new \ManjaServer($this->connectionClientId, $this->connectionHost, $this->connectionPort);
        // Configure timeouts - optional
        $this->manjaServer->ConfigureTimeouts($this->connectionTimeout, $this->connectionStreamTimeout);
        // Setup error callback - callback may be a function name or an array(object,methodname)
        $this->manjaServer->SetErrorCallback([$this, 'mj_error_callback']);
        // Enable simplified error handling - print message and exit script on any error
        $this->manjaServer->SetDieOnError(false);
        // Connect
        $this->manjaServer->Connect();
        // Enable SSL mode if required
        if ($this->connectionUseSSL) {
            $this->manjaServer->SSL();
        }
        // Use explicit error handling for login or session resume
        $this->manjaServer->SetErrorCallback(null);
        $this->manjaServer->SetDieOnError(false);
        // Authenticate
        if ($this->connectionUseSessions) {
            // Use sessions - a session_id will be tracked in a cookie
            $valid = false;
            $session_id = isset($_COOKIE['mj_session_id']) ? $_COOKIE['mj_session_id'] : '';
            if ($session_id != '') {
                // Try to resume a session
                if ($this->manjaServer->SessionResume($session_id) !== false) {
                    $valid = true;
                    $this->manjaConnection = true;
                    setcookie('mj_session_id', $session_id, time() + 86400, '/');
                }
            }
            if (!$valid) {
                // Login
                if ($this->manjaServer->Login($this->connectionUsername, $this->connectionPW) === 0) {
                    // Check creation or edit requests of sys_file_storage with empty values to avoid error messages, returns notice
                    if (isset($_REQUEST['edit']['sys_file_storage'])) {
                        if ($this->connectionHost && !$this->connectionUsername && !$this->connectionPW) {
                            $message = LocalizationUtility::translate('error.sys_file_storage.login.empty', 'fal_manja');
                            $this->addFlashMessage($message, FlashMessage::NOTICE);
                        } elseif ($this->connectionHost) {
                            $message = LocalizationUtility::translate('error.sys_file_storage.login.fail.1525816286', 'fal_manja');
                            $this->addFlashMessage($message, FlashMessage::ERROR);
                        }
                    } else {
                        $message = LocalizationUtility::translate('error.sys_file_storage.login.fail.1525816287', 'fal_manja');
                        $this->addFlashMessage($message, FlashMessage::ERROR);
                    }
                } else {
                    //$this->addFlashMessage('OK - FAL ManjaDriver: Login succeed 1', FlashMessage::OK);
                    $this->manjaConnection = true;
                }
                // Create new session
                $tmp = $this->manjaServer->SessionCreate();
                $session_id = $tmp['session_id'];
                setcookie('mj_session_id', $session_id, time() + 86400, '/');
            }
        } else {
            // Login for each query - if no sessions. Check creation or edit requests of sys_file_storage with empty values to avoid error messages
            if (!isset($_REQUEST['edit']['sys_file_storage']) && $this->manjaServer->Login($this->connectionUsername, $this->connectionPW) === 0) {
                $message = LocalizationUtility::translate('error.sys_file_storage.login.fail.1525816288', 'fal_manja');
                $this->addFlashMessage($message, FlashMessage::ERROR);
            } else {
                $this->manjaConnection = true;
            }
        }
        // Switch back to automatic error handling for further requests
        $this->manjaServer->SetErrorCallback([$this, 'mj_error_callback']);
        $this->manjaServer->SetDieOnError(false);
        return $this->manjaServer;
    }

    /**
     * Error callback
     * - Adds Manja error message to TYPO3 flash message queue as warning
     * - Sets Manja connection status to false
     * - Check creation or edit requests of sys_file_storage with empty values to avoid error messages, returns notice
     *
     * @param bool $die_on_error
     * @param string $error_code
     * @param string $error_string
     */
    public function mj_error_callback($die_on_error, $error_code, $error_string)
    {
        if (isset($_REQUEST['edit']['sys_file_storage']) && (!$this->connectionHost)) {
            $errorMessage = LocalizationUtility::translate('error.sys_file_storage.host.empty', 'fal_manja');
            $this->addFlashMessage($errorMessage, FlashMessage::NOTICE);
        } else {
            $errorMessage = LocalizationUtility::translate('error', 'fal_manja') . ': ' . $error_code . ': ' . htmlspecialchars($error_string);
            $this->addFlashMessage($errorMessage, FlashMessage::ERROR);
        }
        // Set Manja connection status
        $this->manjaConnection = false;
    }

    /**
     * Returns Manja connection status
     *
     * @return bool
     */
    public function getConnectionStatus()
    {
        return $this->manjaConnection;
    }

    /**
     * Adds message to TYPO3 flash message queue
     *
     * @param string $message
     * @param FlashMessage::class $severity
     */
    protected function addFlashMessage($message, $severity = FlashMessage::OK)
    {
        /** @var $flashMessage FlashMessage */
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            '',
            $severity
        );
        /** @var $flashMessageService FlashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        /** @var $defaultFlashMessageQueue FlashMessageQueue */
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }
}
