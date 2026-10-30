<?php

/**
 *  @author          Tassos.gr <info@tassos.gr>
 *  @link            https://www.tassos.gr
 *  @copyright       Copyright © 2024 Tassos All Rights Reserved
 *  @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework\Conditions\Conditions;

defined('_JEXEC') or die;

use Tassos\Framework\Conditions\Condition;
/**
 * @deprecated Use the NewVisitor condition instead.
 */
class ReturningNewVisitor extends Condition
{

	public function pass()
	{
		// Get visitor instance
		$visitor = new \Tassos\Framework\Visitor();

		// Create and update cookies as needed
		$visitor->createOrUpdateCookie();

		// Check if user is new
		$isNew = $visitor->isNew();

		return $this->operator === 'new' ? $isNew : !$isNew;
	}
}