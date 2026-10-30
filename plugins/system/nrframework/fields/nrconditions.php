<?php
/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Tassos\Framework\Extension;

class JFormFieldNRConditions extends GroupedlistField
{
	/**
	 * Method to get the field option groups.
	 *
	 * @return  array  The field option objects as a nested array in groups.
	 */
	protected function getGroups()
	{
		$include_rules = empty($this->element['include_rules']) ? [] : explode(',', $this->element['include_rules']);
		$exclude_rules = empty($this->element['exclude_rules']) ? [] : explode(',', $this->element['exclude_rules']);
		$exclude_rules_pro = empty($this->element['exclude_rules_pro']) ? false : $this->element['exclude_rules_pro'];

		$groups[''][] = HTMLHelper::_('select.option', null, Text::_('NR_CB_SELECT_CONDITION'));

		$conditionsList = \Tassos\Framework\Conditions\ConditionsHelper::getConditionsList();
		
		foreach ($conditionsList as $conditions)
		{
			foreach ($conditions['conditions'] as $conditionName => $condition)
			{
				$skip_condition = false;

				/**
				 * Checks conditions that have multiple components as dependency.
				 * Check for multiple given components for a particular condition, i.e. acymailing can be loaded via com_acymailing or com_acym
				 */
				$multiple_components = explode('|', $conditionName);
				if (count($multiple_components) >= 2)
				{
					foreach ($multiple_components as $component)
					{
						$skip_condition = false;

						if (!$conditionName = $this->getConditionName($component))
						{
							$skip_condition = true;
							continue;
						}
					}
				}
				
				// If the condition must be skipped, skip it
				if ($skip_condition)
				{
					continue;
				}

				// Checks for a single condition whether its component exists and can be used.
				if (!$conditionName = $this->getConditionName($conditionName))
				{
					continue;
				}

				// If its excluded, skip it
				if (!$exclude_rules_pro && !empty($exclude_rules) && in_array($conditionName, $exclude_rules))
				{
					continue;
				}

				// If its not included, skip it
				if (!$exclude_rules_pro && !empty($include_rules) && !in_array($conditionName, $include_rules))
				{
					continue;
				}

				$is_excluded_and_pro = ((count($exclude_rules) && in_array($conditionName, $exclude_rules)) || (count($include_rules) && !in_array($conditionName, $include_rules))) && $exclude_rules_pro;

				$value = $conditionName;
				$disabled = false;

				if ($is_excluded_and_pro)
				{
					$disabled = true;
					$value = '__';
				}

				// Add condition to the group
				$groups[$conditions['title']][] = HTMLHelper::_('select.option', $value, $condition['title'], 'value', 'text', $disabled);
			}
		}

		// Merge any additional groups in the XML definition.
		return array_merge(parent::getGroups(), $groups);
	}

	/**
	 * Returns the parsed condition name.
	 * 
	 * i.e. $condition: com_k2#Component\K2Item
	 * will return: Component\K2Item
	 * 
	 * @param   string  $condition
	 * 
	 * @return  mixed
	 */
	private function getConditionName($condition)
	{
		$conditionNameParts = explode('#', $condition);

		if (count($conditionNameParts) >= 2 && !Extension::isEnabled($conditionNameParts[0]))
		{
			return false;
		}
		
		return isset($conditionNameParts[1]) ? $conditionNameParts[1] : $conditionNameParts[0];
	}
}