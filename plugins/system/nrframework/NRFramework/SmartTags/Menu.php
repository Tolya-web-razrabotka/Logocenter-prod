<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\SmartTags;

defined('_JEXEC') or die('Restricted access');

use Joomla\Registry\Registry;

class Menu extends SmartTag
{
    /**
     * Read a property from the current active menu item
     *
     * @param   string  $key   The name of the property to return
     *
     * @return  mixed   Null if property is not found, mixed if property is found
     */
    public function fetchValue($key)
    {
        $menu = new Registry($this->app->getMenu()->getActive());

        return $menu->get($key);
    }
}