<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

// No direct access to this file
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

require_once JPATH_PLUGINS . '/system/nrframework/helpers/fieldlist.php';

class JFormFieldModules extends NRFormFieldList
{
    /**
     * Method to get a list of options for a list input.
     *
     * @return   array   An array of options.
     */
    protected function getOptions()
    {
        $db = $this->db;

        $this->layout = 'joomla.form.field.list-fancy-select';

        $query = $db->getQuery(true);
        
        $query->select('*')
        ->from('#__modules')
        ->where('published=1')
        ->where('access !=3')
        ->order('title');
        
        $editingModuleId = $this->getEditingModuleID();

        $client = isset($this->element['client']) ? (int) $this->element['client'] : false;

        // If we're creating a new module, use the client_id from the browser
        $input = Factory::getApplication()->input;
        $url_client_id = $input->get('client_id', '');
        if ($url_client_id !== '')
        {
            $client = $url_client_id;
        }

        // If we're editing a module
        if ($editingModuleId)
        {
            // If it's an admin module
            $editingBackendModuleDetails = \Tassos\Framework\Helpers\Module::getData($editingModuleId, 'id', ['client_id' => 1]);
            // Show only admin modules
            if ($editingBackendModuleDetails)
            {
                $client = '1';
            }
        }

        if ($client !== false)
        {
            $query->where('client_id = ' . $client);
        }

        // Exclude currently editing module
        $excludeEditingModule = isset($this->element['excludeeditingmodule']) && (string) $this->element['excludeeditingmodule'] === 'true' ? true : false;
        if ($excludeEditingModule && $editingModuleId)
        {
            $query->where('id != ' . $editingModuleId);
        }

        $rows = $db->setQuery($query);
        $results = $db->loadObjectList();

        $options = array();

        if ($this->showSelect())
        {
            $options[] = HTMLHelper::_('select.option', "", '- ' . Text::_("NR_SELECT_MODULE") . ' -');
        }

        foreach ($results as $option)
        {
            $options[] = HTMLHelper::_('select.option', $option->id, $option->title . ' (' . $option->id . ')');
        }

        $options = array_merge(parent::getOptions(), $options);

        return $options;
    }
    
    private function getEditingModuleID()
    {
        $input = Factory::getApplication()->input;

        /**
         * First check the keys `request_option` and `request_layout` respectively instead of `option` and `layout`
         * in case an AJAX request needs to sends us these data but cannot use the `option` and `layout` as different
         * values are needed for the AJAX request to function properly.
         * 
         * i.e. ConditionBuilder's "Viewed Another Box" should not display the current box when fetching the rule fields
         * via AJAX. Since its not possible to get the current box ID after the AJAX has happened, we send the `id`
         * (popup ID) alongside the `request_option` (com_rstbox) and `request_layout` (edit) parameters.
         */
        $option = $input->get('request_option') ? $input->get('request_option') : $input->get('option');
        $layout = $input->get('request_layout') ? $input->get('request_layout') : $input->get('layout');

        if (in_array($option, ['com_modules', 'com_advancedmodules']) && $layout == 'edit')
        {
            return (int) $input->getInt('id');
        }

        return false;
    }
}