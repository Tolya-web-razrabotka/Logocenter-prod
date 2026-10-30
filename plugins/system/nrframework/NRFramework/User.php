<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework;

defined('_JEXEC') or die('Restricted access');

use Tassos\Framework\Cache;
use Joomla\CMS\Factory;

class User
{
    /**
     * Return the user object
     *
     * @param  mixed $id  The primary key of the user
     *
     * @return mixed object on success, null on failure
     */
    public static function get($id = null)
    {
        // Return current active user
        if (is_null($id))
        {
            return Factory::getUser();
        }

        // Prevent Joomla from displaying a warning from missing user by checking if the user exists first
        if (!self::exists($id))
        {
            return;
        }
        
        return Factory::getUser($id);
    }

    /**
     * Checks whether the user does exist in the database
     *
     * @param  integer $id  The primary key of the user
     *
     * @return bool
     */
    public static function exists($id)
    {
        $hash = 'user' . $id;

        if (Cache::has($hash))
        {
            return Cache::get($hash);
        }

        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select('count(id)')
            ->from('#__users')
            ->where('id = ' . $db->quote($id));
        $db->setQuery($query);

        // Cache result
        return Cache::set($hash, $db->loadResult());
    }

    /**
     * Get the IP address of the user
     * 
     * @deprecated Use directly \Joomla\Utilities\IpHelper::getIp();
     *
     * @return string
     */
    public static function getIP()
    {
        return \Joomla\Utilities\IpHelper::getIp();
    }
}