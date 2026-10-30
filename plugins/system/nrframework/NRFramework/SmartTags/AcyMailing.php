<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\SmartTags;

defined('_JEXEC') or die('Restricted access');

class AcyMailing extends SmartTag
{
	/**
     * This is a Pro-only feature
     *
     * @var boolean
     */
    public $proOnly = true;

    /**
     * Returns the total number of subscribers of a specific list. 
     *
     * @return  mixed   Null if property is not found, mixed if property is found
     */
    public function getSubscribersCount()
    {
        if (!$list = $this->parsedOptions->get('list'))
        {
            return;
        }

        return \Tassos\Framework\Helpers\AcyMailingHelper::getListTotalSubscribers($list);
    }
}