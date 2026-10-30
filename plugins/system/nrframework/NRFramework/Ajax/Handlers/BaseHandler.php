<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Ajax\Handlers;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\Input\Input;

defined('_JEXEC') or die;

/**
 * Base class for all AJAX handlers providing common functionality
 */
abstract class BaseHandler
{
    /**
     * The Joomla Application object
     * 
     * @var CMSApplication
     */
    protected $app;

    /**
     * The input object
     * 
     * @var Input
     */
    protected $input;

    /**
     * Constructor - Initialize common properties
     */
    public function __construct()
    {
        $this->app = Factory::getApplication();
        $this->input = $this->app->input;
    }

    /**
     * Main entry point for handlers
     * 
     * @return void
     */
    abstract public function init();

    /**
     * Ensure handler runs only in administrator area
     * 
     * @return void
     * @throws Exception if not in admin area
     */
    protected function requireAdmin()
    {
        if (!$this->app->isClient('administrator'))
        {
            $this->httpError(403, 'ADMIN_REQUIRED');
        }
    }

    /**
     * Output an HTTP error response and terminate execution
     * 
     * @param int $code The HTTP status code
     * @param string $message The error message
     * @return void
     */
    protected function httpError($code = 500, $message = 'INTERNAL_ERROR')
    {
        http_response_code($code);
        die($message);
    }

    /**
     * Output a JSON error response and terminate execution
     * 
     * @param string $message The error message
     * @param string $key The key to use for the message ('response' or 'message')
     * @return void
     */
    protected function jsonError($message = 'Invalid action.', $key = 'response')
    {
        echo json_encode([
            'error' => true,
            $key => $message
        ]);
        
        die();
    }

    /**
     * Output a JSON success response and optionally terminate execution
     * 
     * @param array $data The response data
     * @param bool $terminate Whether to terminate execution after output
     * @return void
     */
    protected function jsonResponse($data = [], $terminate = true)
    {
        echo json_encode($data);
        if ($terminate)
        {
            die();
        }
    }
}