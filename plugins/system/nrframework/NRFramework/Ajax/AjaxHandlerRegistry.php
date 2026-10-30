<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Ajax;

defined('_JEXEC') or die;

class AjaxHandlerRegistry
{
    /**
     * Registered AJAX handlers
     * 
     * @var array
     */
    private static $handlers = [];

    /**
     * Register an AJAX handler
     * 
     * @param string $name Handler identifier
     * @param string $class Full class name
     * @param string $method Method name to call (defaults to 'init')
     *
     * @return void
     */
    public static function register($name, $class, $method = 'init')
    {
        self::$handlers[strtolower($name)] = [
            'class' => $class,
            'method' => $method
        ];
    }

    /**
     * Get a registered handler (case insensitive)
     * 
     * @param string $name Handler identifier
     * @return array|null Handler configuration or null if not found
     */
    public static function getHandler($name)
    {
        $name = strtolower($name);
        return isset(self::$handlers[$name]) ? self::$handlers[$name] : null;
    }

    /**
     * Execute a registered handler
     * 
     * @param string $name Handler identifier
     * @return mixed Result of method execution
     * 
     * @throws Exception If handler not found or method doesn't exist
     */
    public static function executeHandler($name)
    {
        if (!$handler = self::getHandler($name))
        {
            throw new \Exception('Handler not registered: ' . $name);
        }

        if (!class_exists($handler['class']))
        {
            throw new \Exception('Handler class not found: ' . $handler['class']);
        }

        $instance = new $handler['class']();
        $method = $handler['method'];

        if (!method_exists($instance, $method))
        {
            throw new \Exception('Method not found: ' . $handler['class'] . '::' . $method);
        }

        return $instance->$method();
    }
}