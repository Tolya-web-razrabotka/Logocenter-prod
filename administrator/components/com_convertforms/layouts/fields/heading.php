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

use Joomla\CMS\Factory;

extract($displayData);

// Load custom fonts into the document
\NRFramework\Fonts::loadFont($field->font_family);

$headingVars = [
	'heading-font-size' => (int) $field->font_size . 'px',
	'heading-font-family' => $field->font_family,
	'heading-line-height' => (int) $field->line_height . 'px',
	'heading-letter-spacing' => (int) $field->letter_spacing . 'px',
	'heading-text-align' => $field->content_alignment
];

$headingVars = ConvertForms\FieldsHelper::cssVarsToString($headingVars, '#cf_' . $form['id'] . ' .cf-control-group[data-key="' . $field->key . '"]');

// init vars
$link_start = $link_end = '';

// link
if ($field->use_link == '1')
{
	$link_atts = ($field->open_new_tab == '1') ? 'target="_blank"' : '';

	$link_start = '<a href="' . $field->link_url . '"' . $link_atts . '>';
	$link_end = '</a>';
}
?>
<<?php echo $field->heading_type; ?> class="cf-heading"><?php echo $link_start . $field->label . $link_end; ?></<?php echo $field->heading_type; ?>>

<?php 
    if (Factory::getApplication()->isClient('site'))
    {
        ConvertForms\Helper::addStyleDeclarationOnce($headingVars);
    } else 
    {
        // On backend add styles inline 
        echo '<style>' . $headingVars . '</style>';
    }
?>