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

namespace ConvertForms\SmartTags;

defined('_JEXEC') or die('Restricted access');

use NRFramework\SmartTags\SmartTag;

/**
 * @deprecated Use {submission.fields} instead. The implementation lives in
 *             Submission::getFields(); this class only keeps the old tag working.
 */
class All_fields extends SmartTag
{
	/**
	 * Get All Fields value
	 *
	 * @return  string
	 */
	public function getAll_fields()
	{
		// Pass our parsedOptions through so option mutations made by subclasses
		// (eg All_fields_filled forcing excludeempty) reach the Submission tag.
		$options = (array) $this->options;
		$options['options'] = $this->parsedOptions;

		$submission = new Submission($this->factory, $options);
		$submission->setData($this->data);

		return $submission->getFields();
	}
}
