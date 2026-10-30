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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

extract($displayData);

Text::script('COM_CONVERTFORMS_RECAPTCHA_NOT_LOADED');
HTMLHelper::_('script', 'com_convertforms/recaptcha_v2_checkbox.js', ['version' => 'auto', 'relative' => true]);
HTMLHelper::_('script', 'https://www.google.com/recaptcha/api.js?onload=ConvertFormsInitCheckboxReCaptcha&render=explicit&hl=' . Factory::getLanguage()->getTag());
?>

<div class="nr-recaptcha g-recaptcha"
	data-sitekey="<?php echo $site_key; ?>"
	data-theme="<?php echo $theme; ?>"
	data-size="<?php echo $size; ?>">
</div>