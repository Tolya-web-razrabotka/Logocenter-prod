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
use ConvertForms\Helper;

extract($displayData);

include_once JPATH_PLUGINS . '/system/nrframework/fields/tffileupload.php';

// Load ConvertForms file upload JavaScript with dependency on framework JavaScript
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

// Register and use ConvertForms fileupload JS with framework dependency
$wa->registerScript(
    'com_convertforms.fileupload',
    'com_convertforms/field_fileupload.js',
    [],
    [],
    ['plg_system_nrframework.fileupload.instance']  // Dependency on framework JS
);
$wa->useScript('com_convertforms.fileupload');

$value = isset($field->value) ? $field->value : null;
$label_id = isset($field->label_id) ? $field->label_id : 'label-' . $field->input_id;

$_field = new \JFormFieldTFFileUpload;

$element = new \SimpleXMLElement('
	<field
		id="' . $field->input_id . '"
		name="' . $field->input_name . '"
		label="' . htmlspecialchars($field->label ?? '', ENT_QUOTES, 'UTF-8') . '"
		hidelabel="' . (!empty($field->hidelabel) ? 'true' : 'false') . '"
		label_id="' . $label_id . '"
		required="' . (!empty($field->required) ? 'true' : 'false') . '"
		preview="' . (isset($field->layout) && $field->layout === 'thumbnail') . '"
		max_file_size="' . ($field->max_file_size ?? 10) . '"
		limit_files="' . ($field->limit_files ?? 5) . '"
		upload_types="' . ($field->upload_types ?? '*') . '"
		ajax_url="' . htmlspecialchars(Helper::getAjaxUrl(['task' => 'field.ajax', 'field_type' => 'fileupload']), ENT_QUOTES, 'UTF-8') . '"
		type="TFFileUpload"
	/>
');

$_field->setup($element, $value);

echo $_field->__get('input');
