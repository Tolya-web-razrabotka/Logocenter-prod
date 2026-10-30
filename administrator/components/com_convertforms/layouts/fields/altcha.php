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

use ConvertForms\Helper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

extract($displayData);

$app = Factory::getApplication();
$doc = $app->getDocument();

$wa = $doc->getWebAssetManager();

// Must not be `async`: the shim needs the `$altcha` global this module creates, and only a deferred
// module is guaranteed to execute before it.
$wa->registerScript('com_convertforms.altcha', 'com_convertforms/vendor/altcha.js', [], ['type' => 'module']);
$wa->useScript('com_convertforms.altcha');

$wa->registerScript('com_convertforms.altcha.shim', 'com_convertforms/altcha.js', [], ['defer' => true], ['com_convertforms.altcha']);
$wa->useScript('com_convertforms.altcha.shim');

$language = strtolower($app->getLanguage()->getTag());

$doc->addScriptOptions('com_convertforms.altcha', [
	'language' => $language,
	'strings'  => [
		'label'                => Text::_('COM_CONVERTFORMS_FIELD_ALTCHA_VERIFY_LABEL'),
		'verifying'            => Text::_('COM_CONVERTFORMS_FIELD_ALTCHA_VERIFYING'),
		'verified'             => Text::_('COM_CONVERTFORMS_FIELD_ALTCHA_VERIFIED'),
		'verificationRequired' => Text::_('COM_CONVERTFORMS_FIELD_ALTCHA_VERIFICATION_REQUIRED'),
		'expired'              => Text::_('COM_CONVERTFORMS_FIELD_ALTCHA_EXPIRED'),
		'error'                => Text::_('COM_CONVERTFORMS_FIELD_ALTCHA_ERROR'),
		'waitAlert'            => Text::_('COM_CONVERTFORMS_FIELD_ALTCHA_WAIT_ALERT')
	]
]);

Helper::addStyleDeclarationOnce(
	'altcha-widget.cf-altcha {
		--altcha-color-base: transparent;
		--altcha-border-width: 0;
		--altcha-border-radius: 0;
	}
	altcha-widget.cf-altcha .altcha-main {
		padding: 0;
	}
	altcha-widget.cf-altcha .altcha-error {
		padding: 10px 0 0 0;
	}'
);

$challengeurl = Helper::getAjaxUrl([
	'task'                     => 'field.ajax',
	'field_type'               => 'altcha',
	'field_key'                => $field->key,
	'form_id'                  => (int) $field->form['id'],
	Session::getFormToken()    => 1,
]);
?>
<altcha-widget
	name="<?php echo htmlspecialchars($class->getInputName(), ENT_QUOTES, 'UTF-8'); ?>"
	id="<?php echo htmlspecialchars($field->input_id, ENT_QUOTES, 'UTF-8'); ?>"
	class="cf-altcha"
	challenge="<?php echo htmlspecialchars($challengeurl, ENT_QUOTES, 'UTF-8'); ?>"
	auto="onload"
	configuration='<?php echo htmlspecialchars(json_encode(['hideFooter' => true, 'hideLogo' => true, 'language' => $language]), ENT_QUOTES, 'UTF-8'); ?>'
></altcha-widget>
