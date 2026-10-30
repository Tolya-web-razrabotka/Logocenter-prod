<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Ajax;

defined('_JEXEC') or die;

class HandlerAutoLoader
{
    /**
     * Auto-register AJAX handlers from the Handlers directory
     * 
     * @return void
     */
    public static function register()
    {
        $handlers_dir = __DIR__ . '/Handlers/';
        
        if (!is_dir($handlers_dir))
        {
            return;
        }

        foreach (glob($handlers_dir . '*Handler.php') as $file) 
        {
            $filename = basename($file, '.php');
            $class_name = 'Tassos\\Framework\\Ajax\\Handlers\\' . $filename;
            
            // Convert NoticeHandler -> notice (singular form)
            $handler_name = strtolower(str_replace('Handler', '', $filename));
            
            // Only register if class exists and has init method
            if (class_exists($class_name) && method_exists($class_name, 'init'))
            {
                AjaxHandlerRegistry::register($handler_name, $class_name);
            }
        }
    }
}