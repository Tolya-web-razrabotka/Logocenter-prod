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

use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class JFormFieldTFPhoneControl extends TextField
{
    /**
     * Returns control input.
     * 
     * @return  string
     */
    protected function getInput()
    {
        $this->assets();
        
        $aria_label = $this->element['aria_label'] ? (string) $this->element['aria_label'] : '';
        $class = $this->element['class'] ? (string) $this->element['class'] : '';
        $input_class = $this->element['input_class'] ? (string) $this->element['input_class'] : '';
        $inputmask = $this->element['inputmask'] ? (string) $this->element['inputmask'] : '';
        $country_selector_enabled = $this->element['country_selector_enabled'] ? $this->element['country_selector_enabled'] == '1' : true;

        $value = $this->value;

        if (is_object($value))
        {
            $value = (array) $value;
        }

        $decodedValue = is_string($value) ? json_decode($value, true) : false;
        if (is_string($value) && is_array($decodedValue))
        {
            $value = $decodedValue;
        }
        else if (is_scalar($value))
        {
            $value = [
                'code' => '',
                'value' => $value
            ];
        }

        // If no country selector is enabled, return the formatted
        if (!is_null($country_selector_enabled) && !$country_selector_enabled)
        {
            $data = $this->getCountriesData($this->value);
            $this->value = (isset($data['calling_code']) ? '+' . $data['calling_code'] : '') . $value['value'];
            return parent::getInput();
        }

        // Enqueue the country data as JS object
        Factory::getDocument()->addScriptOptions('tf_phonecontrol_data', $this->getCountriesData($value['value']));

        $browserautocomplete = isset($this->element['autocomplete']) ? (string) $this->element['autocomplete'] : '';
        
        $payload = [
            'name' => $this->name,
            'id' => $this->id,
            'value' => $value,
            'class' => $class,
            'input_class' => $input_class,
            'inputmask' => $inputmask,
            'required' => $this->required,
            'readonly' => $this->readonly,
            'placeholder' => (string) $this->element['placeholder'],
            'browserautocomplete' => $browserautocomplete && $browserautocomplete !== '1' ? $browserautocomplete : '',
            'aria_label' => $aria_label
        ];

        $layout = new FileLayout('phonecontrol', JPATH_PLUGINS . '/system/nrframework/layouts/controls');
        return $layout->render($payload);
    }

    /**
     * Load field assets.
     * 
     * @return  void
     */
    private function assets()
    {
        HTMLHelper::stylesheet('plg_system_nrframework/vendor/choices.min.css', ['relative' => true, 'version' => 'auto']);
        HTMLHelper::script('plg_system_nrframework/vendor/choices.min.js', ['relative' => true, 'version' => 'auto']);
        
        HTMLHelper::stylesheet('plg_system_nrframework/controls/phone.css', ['relative' => true, 'version' => 'auto']);
        HTMLHelper::script('plg_system_nrframework/controls/phone.js', ['relative' => true, 'version' => 'auto']);
    }

    private function getCountriesData($value = '')
    {
        $countries = Tassos\Framework\Countries::getCountriesData();

        $countries = array_map(function($country) {
            return [
                'name' => $country['name'],
                'calling_code' => $country['calling_code']
            ];
        }, $countries);

        if ($value && is_array($value) && isset($value['code']))
        {
            foreach ($countries as $country_code => $country)
            {
                if ($country_code !== $value['code'])
                {
                    continue;
                }

                return $country;
            }
        }

        return $countries;
    }
}