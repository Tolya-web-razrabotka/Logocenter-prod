<?php

/**
 * @author          Tassos.gr <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Conditions\Conditions;

defined('_JEXEC') or die;

use Tassos\Framework\Conditions\Condition;

class Browser extends Condition
{
    /**
	 *  Returns the assignment's value
	 * 
	 *  @return string Browser name
	 */
	public function value()
	{
		return $this->factory->getBrowser()['name'];
    }
}