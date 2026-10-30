<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

// No direct access to this file
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class JFormFieldNR_PRO extends FormField
{
    /**
     *  Method to render the input field
     *
     *  @return  string
     */
    protected function getInput()
    {   
        $label = (string) $this->element['label'];
        $buttonClass = isset($this->element['buttonClass']) ? (string) $this->element['buttonClass'] : null;
        $isFeatureMode = !is_null($label) && !empty($label);

        // Backwards compatibility for fields with type="nr_pro" and have no buttonClass value
        if (is_null($buttonClass))
        {
            $buttonClass = 'btn-sm';
        }

        $buttonText = $isFeatureMode ? 'NR_UNLOCK_PRO_FEATURE' : 'NR_UPGRADE_TO_PRO';

        Tassos\Framework\HTML::renderProOnlyModal();

        $html = '<a style="float:none;" class="btn btn-danger' . (!empty($buttonClass) ? ' ' . $buttonClass : '') . '" href="#" data-pro-only="' . Text::_($label) . '">';
        $html .= '<span class="icon-lock mr-2 me-1"></span> ';
        $html .= Text::_($buttonText) . '</a>';

        return $html;
    }
}