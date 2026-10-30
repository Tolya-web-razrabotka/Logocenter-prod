<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

// No direct access to this file
defined('_JEXEC') or die;

use Joomla\Registry\Registry;

require_once __DIR__ . '/componentitems.php';

class JFormFieldJShoppingComponentItems extends JFormFieldComponentItems
{
    /**
     * Get query preset configuration for JShopping
     *
     * @return array Configuration array
     */
    protected function getPresetConfig()
    {
        return [
            'table' => 'jshopping_products',
            'column_id' => 'product_id',
            'column_title' => 'name_' . $this->getLanguage(),
            'column_state' => 'product_publish'
        ];
    }

    public function init()
    {
        // Set preset so base class can pick it up
        $this->element['preset'] = 'jshopping';

        parent::init();
    }

	/**
     *  JoomShopping is using different columns per language. Therefore, we need to use their API to get the default language code.
     *
     *  @return  string
     */
    private function getLanguage($default = 'en-GB')
    {	
		// Silent inclusion.
        @include_once JPATH_SITE . '/components/com_jshopping/lib/factory.php';

        // JoomShopping 5.0+ fix
        @include_once JPATH_SITE . '/components/com_jshopping/bootstrap.php';

        if (!class_exists('JSFactory'))
        {
            return $default;
        }

		return JSFactory::getConfig()->defaultLanguage;
    }
}