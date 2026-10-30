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

use Joomla\CMS\Language\Text;

// Static preview of the ALTCHA widget shown in the backend form builder.
?>
<div class="cf-altcha-preview" style="display:inline-flex;align-items:center;gap:10px;font-size:14px;color:#444;align-self:flex-start;">
	<span style="width:18px;height:18px;border:2px solid #b9b9b9;border-radius:3px;display:inline-block;"></span>
	<span><?php echo Text::_('COM_CONVERTFORMS_FIELD_ALTCHA_VERIFY_LABEL'); ?></span>
</div>
