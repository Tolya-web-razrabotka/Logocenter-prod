<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 * 
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace ConvertForms\Validation;

use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\CMS\Factory;

defined('_JEXEC') or die('Restricted access');

/**
 * Validation Helper Class
 * 
 * Handles the loading and execution of form validation rules.
 * This class scans the Rules directory and executes validation methods
 * on all available validation rule classes.
 */
class Helper
{
    /**
     * Executes a specified method across all validation rules
     *
     * @param  string  $method      The method to execute (validate|formRender)
     * @param  array   $form        The form configuration array
     * @param  array   $submission  The form submission data (optional)
     * 
     * @throws \Exception When validation fails
     * @return void
     */
    public static function run($method, &$form = null, $submission = null)
    {
        // Get all validation rule files from the Rules directory
        $files = Folder::files(__DIR__ . '/Rules');

        // Loop through each validation rule file
        foreach ($files as $file)
        {
            // Build the full class name
            $class = 'ConvertForms\\Validation\\Rules\\' . File::stripExt($file);

            // Check if class exists and has the requested method
            if (class_exists($class) && method_exists($class, $method))
            {
                // Initialize the validation rule
                $rule = new $class($form, $submission);

                // Execute the requested method
                $result = $rule->$method();

                // If validation fails, throw the rule's error
                if ($method == 'validate' && $result === false)
                {
                    $rule->throwError();
                }
            }
        }
    }

    /**
     * Validates form submission against all validation rules
     *
     * @param  array  $form        The form configuration
     * @param  array  $submission  The submission data
     * 
     * @return void
     */
    public static function validate($form, $submission)
    {
        self::run('validate', $form, $submission);
    }

    /**
     * Executes pre-render logic for all validation rules
     * Only runs on the frontend/site
     *
     * @param  array  $form        The form configuration
     * @param  array  $submission  The submission data (optional)
     * 
     * @return void
     */
    public static function formBeforeRender()
    {
        $app = Factory::getApplication();

        $app->registerEvent('onConvertFormsFormBeforeRender', function(&$event)
        {
            $form = $event->getArgument(0);

            self::run('onFormBeforeRender', $form);

            $event->setArgument(0, $form);
        });
    }
}