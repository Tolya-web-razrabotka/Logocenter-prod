<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 * 
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace ConvertForms\SmartTags;

defined('_JEXEC') or die('Restricted access');

use NRFramework\SmartTags\SmartTag;
use Joomla\Registry\Registry;

/**
 * Smart Tag for retrieving form information from submissions.
 * 
 * This class provides access to form metadata and properties through Smart Tags.
 * Use this Smart Tag to retrieve information about the form used in a submission.
 * 
 * Usage examples:
 * - {form.id}    : Returns the ID of the form
 * - {form.name}  : Returns the title/name of the form
 */
class Form extends SmartTag
{
	/**
	 * Fetches a specific form property value.
	 * 
	 * This method retrieves form data from the submission data array
	 * and returns the requested property value. The form data is stored
	 * as a Registry object for easy property access.
	 * 
	 * @param   string  $key  The form property key to retrieve (e.g., 'id', 'name', 'description')
	 * 
	 * @return  mixed   The form property value, or null if the key doesn't exist or submission data is unavailable
	 */
	public function fetchValue($key)
	{
		if (!isset($this->data))
		{
			return;
		}

		$form = null;

		// First try to find the form object in $this->data['form']. Smart Tags may be used during form rendering.
		if (isset($this->data['form']) && !is_null($this->data['form']))
		{
			$form = $this->data['form'];
		} else 
		{
			// Smart Tags can also be used during submission processing. Try to get the form from the submission object.
			if (isset($this->data['submission']) && !is_null($this->data['submission']))
			{
				$form = $this->data['submission']->form;
			}
		}

		// Create a Registry object from the form data for easy property access
		$form = new Registry($form);

		// Return the requested form property value
		return $form->get($key);
	}
}