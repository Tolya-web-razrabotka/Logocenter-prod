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

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldConvertForms extends ListField
{
    /**
     * Method to get a list of options for a list input.
     *
     * @return    array   An array of options.
     */
    protected function getOptions()
    {
        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_convertforms/models');

        $model = BaseDatabaseModel::getInstance('Forms', 'ConvertFormsModel', ['ignore_request' => true]);

        $state = isset($this->element['state']) ? (string) $this->element['state'] : 1;

        $model->setState('filter.state', explode(',', $state));

        $convertforms = $model->getItems();
        $options = array();

        $include = $this->getFilterAttribute('include');
        $exclude = $this->getFilterAttribute('exclude');

        foreach ($convertforms as $key => $convertform)
        {
            // Exclude wins over Include when a form appears in both lists.
            if (($include && !in_array($convertform->id, $include)) || in_array($convertform->id, $exclude))
            {
                continue;
            }

            $name = $convertform->state != 1 ? $convertform->name . ' (' . Text::_('JUNPUBLISHED') . ')' : $convertform->name;
            $options[] = HTMLHelper::_('select.option', $convertform->id, $name . ' (' . $convertform->id . ')');
        }

        // Sort options in alphabetical order
        usort($options, function($a, $b)
        {
            return strcmp($a->text, $b->text);
        });

        return array_merge(parent::getOptions(), $options);
    }

    /**
     * Reads a comma-separated list of form IDs from a field attribute.
     *
     * @param   string  $name  The attribute name.
     *
     * @return  array
     */
    private function getFilterAttribute($name)
    {
        $value = isset($this->element[$name]) ? (string) $this->element[$name] : '';

        return array_filter(array_map('intval', explode(',', $value)));
    }
}