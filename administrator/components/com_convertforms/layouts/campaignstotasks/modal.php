<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 *
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 *
 * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
?>
<dialog id="cf-campaigns-to-tasks-modal" class="cf-c2t-modal">
    <div id="cf-campaigns-to-tasks" class="cf-campaigns-to-tasks">
        <header class="cf-c2t-modal-header">
            <h2><?php echo Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_MODAL_TITLE'); ?></h2>
            <button type="button" class="cf-c2t-modal-close" data-action="close" aria-label="<?php echo Text::_('JCLOSE'); ?>">&times;</button>
        </header>
        <div class="cf-c2t-modal-body">
            <div class="cf-c2t-confirm">
                <p><?php echo Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_CONFIRM'); ?></p>
            </div>
            <div class="cf-campaigns-to-tasks-progress" hidden>
                <div class="cf-campaigns-to-tasks-bar"><span></span></div>
                <p class="cf-campaigns-to-tasks-status"></p>
                <p class="cf-campaigns-to-tasks-summary"></p>
                <div class="cf-campaigns-to-tasks-reports-wrap" hidden>
                    <h4><?php echo Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_REPORTS_TITLE'); ?></h4>
                    <div class="cf-campaigns-to-tasks-tabs" role="tablist"></div>
                    <ul class="cf-campaigns-to-tasks-reports"></ul>
                    <p class="cf-campaigns-to-tasks-reports-empty" hidden><?php echo Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_REPORTS_EMPTY'); ?></p>
                </div>
            </div>
        </div>
        <footer class="cf-c2t-modal-footer">
            <button type="button" class="btn btn-primary" data-action="start"><?php echo Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_START'); ?></button>
            <button type="button" class="btn btn-link cf-c2t-cancel" data-action="close"><?php echo Text::_('JCANCEL'); ?></button>
        </footer>
    </div>
</dialog>
