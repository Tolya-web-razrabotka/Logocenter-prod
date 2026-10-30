<?php
/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

// No direct access to this file
defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Tassos\Framework\HTML;

class JFormFieldTFLatLongMapSelector extends TextField
{
	/**
	 * Method to get the field input markup.
	 *
	 * @return  string  The field input markup.
	 */
	public function getInput()
	{
		$this->class = 'tf-lat-long-map-value tf-map-editor--value';

		// Setup properties
		$this->readonly = $this->get('readonly', false) ? 'readonly' : '';
		$this->value    = $this->checkCoordinates($this->value, null) ? $this->value : $this->get('default', '36.892587, 27.287793');
		$this->hint     = $this->get('hint', 'NR_ENTER_COORDINATES');

		HTMLHelper::script('plg_system_nrframework/controls/latlongmapselector.js', ['version' => 'auto', 'relative' => true]);

		$payload = [
			'readonly' => $this->readonly,
			'disabled' => $this->disabled,
			'name' => $this->name,
			'required' => $this->required,
			'css_class' => 'tf-lat-long-map-selector',
			'id' => $this->id,
			'value' => $this->value
		];

		return \Tassos\Framework\Widgets\Helper::render('OpenStreetMap', $payload) . parent::getInput();
	}

	/**
	 *  Method to get field parameters
	 *
	 *  @param   string  $val      Field parameter
	 *  @param   string  $default  The default value
	 *
	 *  @return  string
	 */
	public function get($val, $default = '')
	{
		return (isset($this->element[$val]) && (string) $this->element[$val] != '') ? (string) $this->element[$val] : $default;
	}

	/**
	 * Checks the validity of the coordinates
	 */
	private function checkCoordinates($coordinates)
	{
		return (preg_match("/^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/", $coordinates));
	}
}