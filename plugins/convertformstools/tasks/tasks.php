<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 * 
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

defined('_JEXEC') or die('Restricted access');

use ConvertForms\Tasks\ModelTasks;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Language\Text;

class PlgConvertFormsToolsTasks extends CMSPlugin
{
    /**
     *  Application Object
     *
     *  @var  object
     */
    protected $app;

    /**
     *  Every time the form is saved in the backend, move actions information from #__convertforms to the #__convertforms_tasks table.
     *  
     *  @param   string  $context  The context of the content passed to the plugin (added in 1.6)
     *  @param   object  $article  A JTableContent object
     *  @param   bool    $isNew    If the content has just been created
     *
     *  @return  void
     */
    public function onContentAfterSave($context, $article, $isNew, $data = [])
    {
        if ($context !== 'com_convertforms.form' || !$this->app->isClient('administrator'))
        {
            return;
        }

        $params = json_decode($article->params, true);

        if (!isset($params['tasks']))
        {
            return;
        }

        $form_id = $article->id;
        $actions = json_decode($params['tasks'], true);

        // Remove deleted tasks from the database
        $existingActions = ModelTasks::getItems($form_id, true);

        if (!empty($existingActions))
        {
            if ($actionsToDelete = array_diff(array_keys($existingActions), array_column($actions, 'id')))
            {
                $table = ModelTasks::getTable();

                foreach ($actionsToDelete as $id)
                {
                    $table->delete($id);
                }
            }
        }

        if ($actions)
        {
            foreach ($actions as $ordering => $action)
            {
                $action['ordering'] = $ordering;
                $action['form_id'] = $form_id;
    
                ModelTasks::save($action);
            }
        }

        // Remove tasks information from the form row
        unset($params['tasks']);
        $article->params = json_encode($params);
        $article->store();
    }

    /**
     *  Add plugin fields to the form
     *
     *  @param   JForm   $form  
     *  @param   object  $data
     *
     *  @return  boolean
     */
    public function onConvertFormsFormPrepareForm($form, $data)
    {
        // Load form's tasks
        $data->tasks = $data->id ? ModelTasks::getItems($data->id, false, true) : [];

        // Load form fields
        $form->loadFile(__DIR__ . '/form/form.xml');
    }

    /**
     * Run actions when a new submissions comes in
     *
     * @param  object $submission
     * 
     * @return void
     */
    public function onConvertFormsSubmissionAfterSave($submission)
    {
        $form_tasks = ModelTasks::getItems($submission->form_id);

        $actions = new \ConvertForms\Tasks\Tasks($form_tasks, $submission);
        $actions->run();
    }

    /**
     * Handle form export - add tasks and connections to the exported form data
     *
     * @param   object  $form  The form object being exported
     *
     * @return  void
     */
    public function onConvertFormsExport(&$form)
    {
        if (!isset($form->id))
        {
            return;
        }

        // Get tasks for this form
        $tasks = ModelTasks::getItems($form->id);

        // Get unique connections used by these tasks
        $connections = \ConvertForms\Tasks\Helper::getFormConnections($tasks);

        // Store under a 'tasks' key to keep export data organized
        $form->tasks = [
            'tasks' => $tasks,
            'connections' => $connections
        ];
    }

    /**
     * Handle form import - process tasks and connections for imported forms
     *
     * @param   object  $form  The imported form object with tasks and connections data
     *
     * @return  void
     */
    public function onConvertFormsImport($form)
    {
        // Check if tasks data exists
        if (!isset($form->tasks) || !is_array($form->tasks))
        {
            return;
        }

        // Extract tasks and connections from the nested structure
        $tasks = isset($form->tasks['tasks']) ? $form->tasks['tasks'] : [];
        $connections = isset($form->tasks['connections']) ? $form->tasks['connections'] : [];

        if (empty($tasks))
        {
            return;
        }

        // Import connections and tasks
        $unavailableApps = $this->importConnectionsAndTasks($form->id, $connections, $tasks);
        
        // Display warning for unavailable apps with form context
        $this->displayUnavailableAppsWarning($unavailableApps, $form->name, $form->id, 'import');
    }

    /**
     * Handle form duplication - copy tasks from original form to new form
     *
     * @param   int  $newFormId      The newly created form ID
     * @param   int  $originalFormId The original form ID being duplicated
     *
     * @return  void
     */
    public function onConvertFormsDuplicate($newFormId, $originalFormId)
    {
        // Get tasks from the original form
        $tasks = ModelTasks::getItems($originalFormId);

        if (empty($tasks))
        {
            return;
        }

        $unavailableApps = [];

        // When duplicating, connections already exist in the database
        // We simply reuse the same connection IDs
        foreach ($tasks as $task)
        {
            // Check if the app plugin is available before attempting to create the task
            $appInfo = $this->getAppInfo($task['app']);
            
            if (!$appInfo['available'])
            {
                // Track unavailable apps for consolidated reporting
                if (!in_array($appInfo['label'], $unavailableApps))
                {
                    $unavailableApps[] = $appInfo['label'];
                }
            }

            \ConvertForms\Tasks\Helper::createTaskFromExisting($task, $newFormId);
        }
        
        // Get form name for warning message
        $form = \ConvertForms\Form::load($newFormId, false, true);
        $formName = $form ? $form['name'] : 'Form';
        
        // Display warning for unavailable apps with form context
        $this->displayUnavailableAppsWarning($unavailableApps, $formName, $newFormId, 'copy');
    }

    /**
     * Import connections and tasks for a form
     *
     * @param   int    $formId       The new form ID
     * @param   array  $connections  Array of connections from export
     * @param   array  $tasks        Array of tasks from export
     *
     * @return  array  Array of unavailable app names
     */
    private function importConnectionsAndTasks($formId, $connections, $tasks)
    {
        $unavailableApps = [];
        
        // Map old connection IDs to new/existing connection IDs
        $connectionIdMap = [];

        // Process connections first
        foreach ($connections as $connection)
        {
            $oldConnectionId = $connection['id'];
            
            // Decrypt the params
            $params = json_decode($connection['params'], true);
            if (is_array($params))
            {
                $params = \ConvertForms\Tasks\ConnectionEncryption::decryptConnectionParams($params);
            }

            // Extract API key to check if connection exists
            $apiKey = \ConvertForms\Tasks\ConnectionEncryption::extractApiKey($params);

            $newConnectionId = null;

            // Check if a connection with this API key already exists
            if ($apiKey)
            {
                $existingConnectionId = \ConvertForms\Tasks\Helper::findConnectionByApiKey($connection['app'], $apiKey);
                
                if ($existingConnectionId)
                {
                    // Reuse existing connection
                    $newConnectionId = $existingConnectionId;
                }
            }

            // If no existing connection found, create a new one
            if (!$newConnectionId)
            {
                $newConnectionId = \ConvertForms\Tasks\Connections::add(
                    $connection['app'],
                    $connection['title'],
                    $params
                );
            }

            // Map old ID to new ID
            $connectionIdMap[$oldConnectionId] = $newConnectionId;
        }

        // Now import tasks with updated connection IDs
        foreach ($tasks as $task)
        {
            // Check if the app plugin is available before attempting to create the task
            $appInfo = $this->getAppInfo($task['app']);
            
            if (!$appInfo['available'])
            {
                // Track unavailable apps for consolidated reporting
                if (!in_array($appInfo['label'], $unavailableApps))
                {
                    $unavailableApps[] = $appInfo['label'];
                }
            }

            \ConvertForms\Tasks\Helper::createTaskFromExisting($task, $formId, $connectionIdMap);
        }
        
        return $unavailableApps;
    }

    /**
     * Get app information including availability and label
     *
     * @param   string  $appAlias  The app alias/name
     *
     * @return  array  Array with 'available' (bool) and 'label' (string) keys
     */
    private function getAppInfo($appAlias)
    {
        try
        {
            $app = \ConvertForms\Tasks\Apps::getApp($appAlias);
            return [
                'available' => true,
                'label' => $app->getName()
            ];
        }
        catch (\Exception $e)
        {
            // If we can't get the app, return formatted version of the alias
            return [
                'available' => false,
                'label' => ucwords(str_replace('_', ' ', $appAlias))
            ];
        }
    }

    /**
     * Display a consolidated warning message for unavailable apps
     *
     * @param   array   $unavailableApps  Array of app names that are unavailable
     * @param   string  $formName         The form name
     * @param   int     $formId           The form ID
     * @param   string  $action           Action (import/copy)
     *
     * @return  void
     */
    private function displayUnavailableAppsWarning($unavailableApps, $formName, $formId, $action)
    {
        if (empty($unavailableApps))
        {
            return;
        }

        $appsList = implode(', ', $unavailableApps);

        $pluginsList = array_map(function($appName) {
            return $appName;
        }, $unavailableApps);
        $pluginsList = implode(', ', $pluginsList);
        
        $message = Text::sprintf(
            'COM_CONVERTFORMS_TASKS_UNAVAILABLE_APPS_WARNING_WITH_FORM',
            $formName,
            $formId,
            Text::_(($action === 'copy' ? 'COM_CONVERTFORMS_COPIED' : 'COM_CONVERTFORMS_IMPORTED')),
            $appsList,
            $pluginsList
        );
        
        $this->app->enqueueMessage($message, 'warning');
    }
}