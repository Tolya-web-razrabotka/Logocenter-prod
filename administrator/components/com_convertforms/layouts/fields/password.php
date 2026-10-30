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
use Joomla\CMS\Language\Text;

extract($displayData);

?>

<div class="cf-password">
   <?php include __DIR__ . '/text.php'; ?>
   <button class="cf-password-toggle" type="button" title="<?= Text::_('COM_CONVERTFORMS_TOGGLE_PASS_VISIBILITY')  ?>" aria-label="<?= Text::_('COM_CONVERTFORMS_TOGGLE_PASS_VISIBILITY')  ?>"> 
		<span class="cf-password-visibility">
			<?= Helper::icon('Visibility', ['size' => 24]); ?>
		</span>
		<span class="cf-password-visibility-off cf-hide">
			<?= Helper::icon('VisibilityOff', ['size' => 24]); ?>
		</span>
   </button>
</div>