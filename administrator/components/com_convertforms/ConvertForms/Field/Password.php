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

namespace ConvertForms\Field;

defined('_JEXEC') or die('Restricted access');

class Password extends \ConvertForms\Field\Text
{
	/**
	 * Prepare value to be displayed to the user as HTML/text
	 *
	 * @param  mixed $value
	 *
	 * @return string
	 */
	public function prepareValueHTML($value)
	{
		// Mask passwords in the submissions list view using the Joomla built-in PasswordField
		if (!empty($value) && $this->app->isClient('administrator') && $this->app->input->get('view') == 'conversions')
		{
			$this->app->getDocument()->getWebAssetManager()->addInlineStyle('
				.password-group .form-control {
					padding: .3rem .6rem !important;
					font-size: .9rem !important;
					background-color: transparent !important;
				}
			');

			$field   = new \Joomla\CMS\Form\Field\PasswordField;
			$element = new \SimpleXMLElement('<field name="psw' . uniqid() . '" type="password" size="1" readonly="1"/>');
	
			$field->setup($element, $value);
	
			$html = $field->__get('input');

			$html = str_replace('input-password-toggle', 'btn-sm input-password-toggle', $html);

			return $html;
		}

		return str_repeat('*', strlen($value));
	}
}