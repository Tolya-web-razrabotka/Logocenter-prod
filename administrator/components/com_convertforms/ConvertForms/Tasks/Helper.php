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

namespace ConvertForms\Tasks;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class Helper
{
    public static function readRepeatSelect($items)
    {
        if (!$items)
        {
            return;
        }

        return array_filter(array_map(function($item)
        {
            if (isset($item['value']))
            {
                return $item['value'];
            }
        }, $items));
    }

    public static function getAllowedCustomFieldsTypesInRepeater()
    {
        return [
            'acfarticles',
            'acfconvertforms',
            'acfcountry',
            'acfcurrency',
            'acfdownloadbutton',
            'acfemail',
            'acffacebook',
            'acfgravatar',
            'acfhtml5audio',
            'acfiframe',
            'acfmodule',
            'acfphp',
            'acfprogressbar',
            'acfqrcode',
            'acftelephone',
            'acftimepicker',
            'acftruefalse',
            'acftwitter',
            'acfvideo',
            'acfwhatsappctc',
            'calendar',
            'color',
            'editor',
            'integer',
            'list',
            'imagelist',
            'location',
            'radio',
            'checkboxes',
            'text',
            'textarea',
            'url',
            'user',
            'usergrouplist',
        ];
    }

    /**
     * Get all unique connections used by tasks
     *
     * @param   array  $tasks  Array of tasks
     *
     * @return  array  Array of connections with encrypted API keys
     */
    public static function getFormConnections($tasks)
    {
        if (empty($tasks))
        {
            return [];
        }

        // Get unique connection IDs to avoid duplicate queries
        // Multiple tasks can share the same connection, so we only want to fetch each connection once
        $connectionIds = [];
        foreach ($tasks as $task)
        {
            if (empty($task['connection_id']))
            {
                continue;
            }
            
            $connectionId = (int) $task['connection_id'];
            
            if (in_array($connectionId, $connectionIds))
            {
                continue;
            }
            
            $connectionIds[] = $connectionId;
        }

        if (empty($connectionIds))
        {
            return [];
        }

        $db = Factory::getDbo();
        
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__convertforms_connections')
            ->where('id IN (' . implode(',', $connectionIds) . ')');
        
        $db->setQuery($query);
        
        $connections = $db->loadAssocList();

        // Encrypt API keys in connection params
        foreach ($connections as &$connection)
        {
            $params = json_decode($connection['params'], true);
            
            if (is_array($params))
            {
                // Encrypt the API keys
                $params = ConnectionEncryption::encryptConnectionParams($params);
                $connection['params'] = json_encode($params);
            }
        }

        return $connections;
    }

    /**
     * Create a new task from existing task data
     * 
     * This is used when duplicating or importing tasks. It takes an existing task,
     * updates it with a new form ID, optionally remaps connection IDs, and saves it as a new record.
     *
     * @param   array  $taskData         The source task data to copy
     * @param   int    $newFormId        The form ID to assign to the new task
     * @param   array  $connectionIdMap  Map of old connection IDs to new ones [oldId => newId]
     *
     * @return  void
     */
    public static function createTaskFromExisting($taskData, $newFormId, $connectionIdMap = [])
    {
        // Assign to the new form
        $taskData['form_id'] = $newFormId;
        
        // Remap connection ID if a mapping is provided
        if (!empty($taskData['connection_id']) && isset($connectionIdMap[$taskData['connection_id']]))
        {
            $taskData['connection_id'] = $connectionIdMap[$taskData['connection_id']];
        }
        
        // Remove the original ID so a new record is created
        unset($taskData['id']);

        ModelTasks::save($taskData);
    }

    /**
     * Find an existing connection by app and API key
     *
     * @param   string  $app     The app name
     * @param   string  $apiKey  The API key to search for
     *
     * @return  int|null  The connection ID if found, null otherwise
     */
    public static function findConnectionByApiKey($app, $apiKey)
    {
        $db = Factory::getDbo();
        
        $query = $db->getQuery(true)
            ->select('id, params')
            ->from('#__convertforms_connections')
            ->where('app = ' . $db->quote($app));
        
        $db->setQuery($query);
        
        $connections = $db->loadAssocList();

        // Search through connections to find matching API key
        foreach ($connections as $connection)
        {
            $params = json_decode($connection['params'], true);
            $existingKey = ConnectionEncryption::extractApiKey($params);
            
            if ($existingKey === $apiKey)
            {
                return (int) $connection['id'];
            }
        }

        return null;
    }
}