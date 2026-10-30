<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\SmartTags;

use Tassos\Framework\User;

defined('_JEXEC') or die('Restricted access');

class IP extends SmartTag
{
    /**
     * Returns the IP address of the visitor.
     * 
     * @return  string
     */
    public function getIP()
    {
        return \Joomla\Utilities\IpHelper::getIp();
    }
}