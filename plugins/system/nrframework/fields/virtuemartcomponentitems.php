<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

// No direct access to this file
defined('_JEXEC') or die;

use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

require_once __DIR__ . '/componentitems.php';

class JFormFieldVirtueMartComponentItems extends JFormFieldComponentItems
{
    /**
     * Get query preset configuration for VirtueMart
     *
     * @return array Configuration array
     */
    protected function getPresetConfig()
    {
        $preset = isset($this->element['preset']) ? (string) $this->element['preset'] : 'virtuemart_products_en_gb';

        return [
            'table' => $preset,  // Language-specific table name
            'column_id' => 'virtuemart_product_id',
            'column_title' => 'product_name',
            'column_state' => 'p.published',
            'join' => '#__virtuemart_products as p ON i.virtuemart_product_id = p.virtuemart_product_id'
        ];
    }

    /**
     * Creates the handler payload for the AJAX request
     */
    protected function createAjaxHandlerPayload()
    {
        return [
            'handler' => 'ComponentItems',
            Session::getFormToken()  => 1,
            'preset' => 'virtuemart',
            'append_id_to_label' => $this->append_id_to_label ? 1 : 0
        ];
    }

    public function init()
    {
        // Get language-specific table name
        $lang = $this->getLanguage();

        // Set preset so base class can pick it up
        $this->element['preset'] = 'virtuemart_products_' . $lang;

        parent::init();
    }

	/**
     *  VirtueMart is using different tables per language. Therefore, we need to use their API to get the default language code
     *
     *  @return  string
     */
    private function getLanguage($default = 'en_gb')
    {	
		// Silent inclusion.
		@include_once JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php'; 

        if (!class_exists('VmConfig'))
		{
			return $default;
        }
            
        // Init configuration
		VmConfig::loadConfig();
		
        return VmConfig::$jDefLang;
    }

    protected function getItems()
    {
        $items = parent::getItems();

        // If text is not properly decoded, decode it
        $items = array_map(function($item) {
            $item->text = html_entity_decode($item->text);
            return $item;
        }, $items);
        
        return $items;
    }
}