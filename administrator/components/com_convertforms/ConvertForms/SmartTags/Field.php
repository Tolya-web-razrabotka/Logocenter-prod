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

class Field extends SmartTag
{
	/**
	 * Run only when we have a valid submissions object.
	 *
	 * @return boolean
	 */
	public function canRun()
	{
		return isset($this->data['submission']) ? parent::canRun() : false;
	}

	/**
	 * Fetch field value
	 * 
	 * @param   string  $key
	 * 
	 * @return  string
	 */
	public function fetchValue($key)
	{
		// Separate key parts into an array as it's very likely to have a key in the format: field.label
		$keyParts = explode('.', $key);
		$fieldName = strtolower($keyParts[0]);

		if (!$field = $this->findField($fieldName))
		{
			return;
		}

		$special_param = isset($keyParts[1]) ? $keyParts[1] : null;

		// In case of a dropdown and radio fields, make also the label and the calc-value properties available. 
		// This is rather useful when we want to display the dropdown's selected text rather than the dropdown's value.
		// TODO: Remove label,calcvalue,calc-value when we drop support for old syntax
		if (in_array($special_param, ['label', 'calcvalue', 'calc-value', 'selected']) && in_array($field->class->getName(), ['dropdown', 'radio']))
		{
			// Add support for selected.label and selected.calcvalue
			if ($special_param === 'selected')
			{
				if (isset($keyParts[2]) && in_array($keyParts[2], ['label', 'calcvalue']))
				{
					$special_param = $keyParts[2] === 'calcvalue' ? 'calc-value' : $keyParts[2];
				}
			}

			foreach ($field->class->getOptions() as $choice)
			{
				if ($field->value !== $choice['value'])
				{
					continue;
				}

				// Special case: Keep old syntax: calcvalue
				$special_param = $special_param == 'calcvalue' ? 'calc-value' : $special_param;

				if (isset($choice[$special_param]))
				{
					return $choice[$special_param];
				}
			}
		}

		// We need to return the value of the field
		switch ($special_param)
		{
			case 'raw':
				// The raw value as saved in the database.
				return $field->class->prepareRawValue($field->submitted_value);

			case 'html':
				// The value as transformed to be shown in HTML.
				return $field->class->prepareValueHTML($field->submitted_value);
			
			case 'label_':
				// Return the label of the field
				return $field->label;
			
			default:
				// The value in plain text. Arrays will be shown comma separated.
				return $field->class->prepareValue($field->submitted_value);
		}
	}

	/**
	 * Find field by key or name
	 * 
	 * @param   string  $subject
	 * 
	 * @return  mixed
	 */
	private function findField($subject)
	{
		$formFields = $this->data['submission']->fields_;
		$subject = strtolower($subject);

		return current(array_filter($formFields, function($field) use ($subject)
		{	
			$fieldOptions = $field->class->getField();
			return ($fieldOptions->key == $subject || (isset($fieldOptions->name) && strtolower($fieldOptions->name) == $subject));
		}));
	}
}