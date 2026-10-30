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

use ConvertForms\Tasks\ModelTasks;
use ConvertForms\Tasks\Apps;
use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('bootstrap.popover');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$user = Factory::getUser();

// Always render the "adminForm" wrapper so the toolbar's New group (Import,
// Start from scratch) can submit its task against it; Joomla.submitbutton()
// silently no-ops when that form is missing. Only the container's contents
// differ: the onboarding empty state when there are no forms at all (any state,
// including trashed) and no active filter/search, otherwise the normal list so
// users can still reach filtered/trashed forms.
?>

<form action="<?php echo Route::_('index.php?option=com_convertforms&view=forms'); ?>" class="clearfix" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container">
        <?php if ($this->isEmptyState) : ?>
        <?php
            echo ConvertForms\Helper::layoutRender('emptystate', [
                'icon'    => 'icon-list-view',
                'title'   => Text::_('COM_CONVERTFORMS_EMPTY_FORMS_TITLE'),
                'content' => Text::_('COM_CONVERTFORMS_EMPTY_FORMS_TEXT'),
                'button'  => Text::_('COM_CONVERTFORMS_EMPTY_FORMS_BUTTON'),
                'url'     => Route::_('index.php?option=com_convertforms&task=form.add'),
            ]);
        ?>
        <?php else : ?>
        <?php
            echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this));
        ?>
        <table class="adminlist nrTable table">
            <thead>
                <tr>
                    <th class="text-center" width="2%"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
                    <th width="3%" class="nowrap hidden-phone" align="center">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                    </th>
                    <th>
                        <?php echo HTMLHelper::_('searchtools.sort', 'NR_NAME', 'a.name', $listDirn, $listOrder); ?>
                    </th>
                    <th width="13%">
                        <?php echo Text::_('COM_CONVERTFORMS_TASKS') ?>
                    </th>
                    <th width="13%" class="text-center">
                        <?php echo Text::_('COM_CONVERTFORMS_SAVE_DATA') ?>
                    </th>
                    <th width="13%" class="text-center">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_CONVERTFORMS_SUBMISSIONS', 'leads', $listDirn, $listOrder); ?>
                    </th>
                    <th width="20%" class="text-center"></th>
                    <th width="5%" class="nowrap text-center hidden-phone">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($this->items)) { ?>
                    <?php foreach($this->items as $i => $item): ?>
                        <?php
                            $leads = \ConvertForms\Form::getSubmissionsTotal($item->id);
                            $canChange = $user->authorise('core.edit.state', 'com_convertforms.form.' . $item->id);
                            $leadsURL  = Uri::base() . 'index.php?option=com_convertforms&view=conversions&filter.period&filter.form_id='. $item->id;
                        ?>
                        <tr class="row<?php echo $i % 2; ?>">
                            <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                            <td class="text-center">
                                <?php
                                    $options = [
                                        'task_prefix' => 'forms.',
                                        'disabled' => !$canChange,
                                        'id' => 'state-' . $item->id
                                    ];

                                    echo (new PublishedButton)->render((int) $item->state, $i, $options);
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo Route::_('index.php?option=com_convertforms&task=form.edit&id='.$item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?>"><?php echo $this->escape($item->name); ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                    $appsAll = [];
                                    $appsFirst5 = [];

                                    if ($tasks = ModelTasks::getItems($item->id))
                                    {
                                        $apps = array_map(function($task)
                                        {
                                            return $task['state'] == '1' ? $task['app'] : null;
                                        }, $tasks);

                                        $appsAll = array_unique(array_filter($apps));
                                        $appsFirst5 = array_slice($appsAll, 0, 5, true);
                                    }
                                ?>

                                <?php if ($appsFirst5) { ?>
                                    <div class="tasks-logo-list">
                                        <?php
                                            foreach ($appsFirst5 as $appIndex => $app)
                                            {
                                                try
                                                {
                                                    $appClass = Apps::getApp($app);
                                                    $isAvailable = Apps::exists($app);
                                                    $appName = $appClass->lang('ALIAS');
                                                    $logo = $appClass->getLogo();
                                                }
                                                catch (\Exception $e)
                                                {
                                                    // App plugin not available - show in disabled state
                                                    $isAvailable = false;
                                                    $appName = ucwords(str_replace('_', ' ', $app));
                                                    $logo = 'https://www.tassos.gr/images/apps/' . $app . '.png';
                                                }

                                                $statusClass = $isAvailable ? 'tasks-logo-list__icon' : 'tasks-logo-list__icon tasks-logo-list__icon--disabled';
                                                $uniqueId = 'app-' . $app . '-' . $appIndex . '-' . $item->id;
                                                
                                                if ($isAvailable)
                                                {
                                                    echo '<img class="' . $statusClass . '" alt="' . $appName . '" src="' . $logo . '" title="' . $appName . '"/>';
                                                }
                                                else
                                                {
                                                    echo '<span class="app-unavailable" aria-describedby="tip-' . $uniqueId . '">';
                                                    echo '<img class="' . $statusClass . '" alt="' . $appName . '" src="' . $logo . '"/>';
                                                    echo '<span class="app-unavailable__badge" aria-hidden="true">!</span>';
                                                    echo '<div role="tooltip" id="tip-' . $uniqueId . '" class="app-unavailable__tooltip">';
                                                    echo Text::sprintf('COM_CONVERTFORMS_TASKS_PLUGIN_UNAVAILABLE', $appName);
                                                    echo '</div>';
                                                    echo '</span>';
                                                }
                                            }
                                        ?>

                                        <?php if (count($appsAll) > count($appsFirst5)) { ?>
                                            <span class="tasks-logo-list__overflow">+<?php echo count($appsAll) - count($appsFirst5); ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>

                            </td>
                            <td class="text-center">
                                <?php 
                                    $saveToDb = isset($item->save_data_to_db) ? $item->save_data_to_db : true;
                                ?>
                                <span title="<?php echo Text::_('COM_CONVERTFORMS_SAVE_DATA_' . (int) $saveToDb) ?> ">
                                    <?php echo Text::_($saveToDb ? 'JYES' : 'JNO'); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php
                                    $total = number_format($leads);
                                ?>
                                <a href="<?php echo $leadsURL ?>">
                                    <span class="badge badge-info bg-info hasPopover" data-placement="top" data-content="<?php echo Text::sprintf("COM_CONVERTFORMS_FORM_LEADS", $total) ?>">
                                        <?php echo $total; ?>
                                    </span>
                                </a>
                            </td>
                            <td class="text-center">
                                <ul class="item-icons">
                                    <li>
                                        <a class="hasPopover <?php echo ($leads == 0) ? "disabled" : "" ?>" 
                                            data-placement="top"
                                            data-content="<?php echo Text::_("COM_CONVERTFORMS_VIEW_LEADS") ?>"
                                            href="<?php echo Uri::base() ?>index.php?option=com_convertforms&view=conversions&filter.form_id=<?php echo $item->id ?>&filter.state"><span class="icon icon-users"></span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="hasPopover"
                                            data-placement="top"
                                            data-content="<?php echo Text::_("COM_CONVERTFORMS_FORM_CREATE_MODULE") ?>"
                                            href="<?php echo Uri::base() ?>index.php?option=com_modules&task=module.add&eid=<?php echo $this->moduleID ?>">
                                            <span class="icon icon-cube"></span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="hasPopover copyToClipboard"
                                            data-clipboard="{convertforms <?php echo $item->id ?>}"
                                            data-placement="top"
                                            data-content="<?php echo Text::sprintf("COM_CONVERTFORMS_FORM_CLIPBOARD_SHORTCODE", "{convertforms ".$item->id."}") ?>"
                                            href='#'>
                                            <span class="icon icon-link disable-click"></span>
                                        </a>
                                    </li>
                                </ul>
                            </td>
                            <td class="text-center"><?php echo $item->id ?></td>
                        </tr>
                    <?php endforeach; ?>  
                <?php } else { ?>
                    <tr>
                        <td align="center" colspan="9">
                            <div align="center">
                                <?php echo ConvertForms\Helper::noItemsFound("forms"); ?>
                                - 
                                <a href="javascript://" onclick="Joomla.submitbutton('form.add')"><?php echo Text::_("COM_CONVERTFORMS_CREATE_NEW") ?></a>   
                            </div>
                        </td>
                    </tr>
                <?php } ?>        
            </tbody>
        </table>

        <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>

        <div>
            <input type="hidden" name="task" value="" />
            <input type="hidden" name="boxchecked" value="0" />
            <?php echo HTMLHelper::_('form.token'); ?>
        </div>
    </div>
</form>

<?php
// The onboarding empty state needs none of the list chrome below — the promo
// footer and the list-only submit script — so stop here.
if ($this->isEmptyState)
{
    return;
}
?>

<?php include_once(JPATH_COMPONENT_ADMINISTRATOR . '/layouts/footer.php'); ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var adminForm = document.getElementById("adminForm");
        var originalSubmitbutton = Joomla.submitbutton;

        if (adminForm && typeof originalSubmitbutton === "function") {
            Joomla.submitbutton = function(task) {
                originalSubmitbutton.call(Joomla, task);

                if (task === "forms.export") {
                    window.setTimeout(function() {
                        var taskInput = adminForm.querySelector('input[name="task"]');

                        if (taskInput) {
                            taskInput.value = "";
                        }
                    }, 0);
                }
            };
        }

        document.addEventListener("click", function(e) {

            if (!e.target.classList.contains("copyToClipboard")) {
                return;
            }

            e.preventDefault();
            var data = e.target.dataset.clipboard;
            copyTextToClipboard(data, function(success) {
                if (success)  {
                    Joomla.renderMessages({"success": ["Shortcode " + data + " copied to clipboard"]});
                }
            });
        });

        function copyTextToClipboard(text, callback) {
            var textArea = document.createElement("textarea");
            textArea.style.position = 'fixed';
            textArea.style.top = 0;
            textArea.style.left = 0;
            textArea.style.width = '2em';
            textArea.style.height = '2em';
            textArea.style.background = 'transparent';
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();

            try {
                var success = document.execCommand('copy');
                callback(success);
            } catch (err) {
                callback(false);
            }

            document.body.removeChild(textArea);
        }
    })
</script>