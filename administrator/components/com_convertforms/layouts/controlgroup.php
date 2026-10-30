<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 * 
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

extract($displayData);

$cssclass = isset($field->cssclass) ? $field->cssclass : '';

// Load Input Masking
if (isset($field->inputmask) && is_array($field->inputmask) && ((!empty($field->inputmask['options']) && $field->inputmask['options'] !== 'custom') || ($field->inputmask['options'] === 'custom' && !empty($field->inputmask['custom']))))
{
	HTMLHelper::script('com_convertforms/vendor/inputmask.min.js', ['relative' => true, 'version' => 'auto']);
	HTMLHelper::script('com_convertforms/inputmask.js', ['relative' => true, 'version' => 'auto']);

	Text::script('COM_CONVERTFORMS_ERROR_INPUTMASK_INCOMPLETE');
}

$helpTextPosition = $form['params']->get('help_text_position', 'after');

$fieldsetTypes = ['checkbox', 'radio', 'termsofservice', 'recaptchaaio', 'hcaptcha', 'turnstile', 'altcha'];
$containerTag = in_array($field->type, $fieldsetTypes) ? 'fieldset' : 'div';

?>
<<?= $containerTag ?> class="cf-control-group <?php echo $cssclass; ?>" data-key="<?php echo $field->key; ?>" data-name="<?php echo $field->name; ?>" data-type="<?php echo $field->type ?>" <?php echo (isset($field->required) && $field->required) ? 'data-required' : '' ?>>
	<?php if ($containerTag === 'fieldset' && !empty($field->label)) { ?>
		<legend class="<?= (isset($field->hidelabel) && $field->hidelabel) ? 'cf-sr-only' : 'cf-label'; ?>">
			<?= $field->label ?>
			<?php if ($form['params']->get('required_indication', true) && $field->required) { ?>
				<span class="cf-required-label">*</span>
			<?php } ?>
		</legend>
	<?php } elseif (isset($field->hidelabel) && !$field->hidelabel && !empty($field->label)) { ?>
		<div class="cf-control-label">
			<?php if (!empty($field->label_id)) { ?>
				<div class="cf-label" id="<?php echo $field->label_id; ?>">
			<?php } else { ?>
				<label class="cf-label" for="<?php echo $field->input_id; ?>">
			<?php } ?>
				<?= $field->label ?>
				<?php if ($form['params']->get('required_indication', true) && $field->required) { ?>
					<span class="cf-required-label">*</span>
				<?php } ?>
			<?php if (!empty($field->label_id)) { ?>
				</div>
			<?php } else { ?>
				</label>
			<?php } ?>
		</div>
	<?php } ?>
	<div class="cf-control-input">
		<?php 
			if ($helpTextPosition == 'before')
			{
				include __DIR__ . '/helptext.php';
			}

			echo $field->input; 

			if ($helpTextPosition == 'after')
			{
				include __DIR__ . '/helptext.php';
			}
		?>
	</div>
</<?= $containerTag ?>>
