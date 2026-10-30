<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class Module
{
	/**
	 * Get a module data.
	 *
	 * @param  integer  $value
	 * @param  string   $selector
	 * @param  array    $options
	 *
	 * @return object
	 */
    public static function getData($value, $selector = 'id', $options = ['access' => 1])
    {
		if (!$value)
		{
			return;
		}

        $db = Factory::getDbo();

        $query = $db->getQuery(true);

        $query
            ->select($db->quoteName(['params']))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName($selector) . ' = ' . $db->quote($value));

        if (count($options))
        {
            foreach ($options as $column => $value)
            {
                $query->where($db->quoteName($column) . ' = ' . $db->quote($value));
            }
        }

        $db->setQuery($query);

        if (!$result = $db->loadResult())
        {
            return;
        }

        return new \Joomla\Registry\Registry($result);
    }
}