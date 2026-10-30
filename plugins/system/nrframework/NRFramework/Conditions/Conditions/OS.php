<?php

/**
 * @author          Tassos.gr <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Conditions\Conditions;

defined('_JEXEC') or die;

use Tassos\Framework\WebClient;
use Tassos\Framework\Functions;
use Tassos\Framework\Conditions\Condition;

class OS extends Condition
{
    /**
     *  Check the client's operating system
     *
     *  @return bool
     */
    public function prepareSelection()
    {
        $selection = Functions::makeArray($this->getSelection());

        // backwards compatibility check
        // replace 'iphone' and 'ipad' selection values with 'ios'
        return array_map(function($os_selection)
        {
            if ($os_selection === 'iphone' || $os_selection === 'ipad')
            {
                return 'ios';
            }
            return $os_selection;
        }, $selection);
    }

    /**
     *  Returns the assignment's value
     * 
     *  @return string OS name
     */
	public function value()
	{   
		return WebClient::getOS();
	}
}