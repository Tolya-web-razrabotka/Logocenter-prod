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

use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Layout\LayoutHelper;
use ConvertForms\Submission\Submission as SubmissionContext;
use ConvertForms\Submission\Detail\Base as DetailBase;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

$user    = Factory::getUser();
$columns = $this->state->get('filter.columns');

$doc = Factory::getDocument();

$doc->addStyleDeclaration('
    .js-stools .js-stools-container-filters .chzn-container.active:not(.chzn-with-drop) .chzn-single {
        border: 1px solid rgba(0,0,0,0.2);
    }
    .js-stools .js-stools-container-filters .chzn-container.active .chzn-single {
        border: 1px solid #2384D3;
    }
');

$doc->addScriptDeclaration('
document.addEventListener("DOMContentLoaded", function() {
    Joomla.submitbutton = function(task) {
        let form = document.getElementById("adminForm");

        // The onboarding empty state renders no "adminForm". Bail out instead of
        // passing null to submitform(), which would throw.
        if (!form) {
            return;
        }

        Joomla.submitform(task, form);

        // Since the export runs in the background and without reloading the page we need to manually reset the task input.
        if (task == "conversions.export") {
            form.task.value = "";
        }
    }
});
');

// Show a native tooltip with the full value when a cell is truncated. Only cells
// whose content is actually clipped get a title, so short cells stay quiet. The
// action cells (checkbox/status) are skipped. Recomputed on resize.
$doc->addScriptDeclaration('
document.addEventListener("DOMContentLoaded", function() {
    var table = document.querySelector(".cf-submissions-table");
    if (!table) {
        return;
    }

    var cells = table.querySelectorAll("td:not(.center):not(.text-center), thead th");

    function refreshTitles() {
        cells.forEach(function(cell) {
            if (cell.scrollWidth > cell.clientWidth + 1) {
                if (!cell.hasAttribute("title")) {
                    cell.setAttribute("title", cell.textContent.trim());
                }
            } else if (cell.hasAttribute("title")) {
                cell.removeAttribute("title");
            }
        });
    }

    refreshTitles();

    var resizeTimer;
    window.addEventListener("resize", function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(refreshTitles, 150);
    });
});
');

// Only show the onboarding empty state when there are no submissions at all (in
// any state, including trashed) and no active filter/search. Otherwise render the
// normal list so users can still reach filtered/trashed submissions.
if ($this->isEmptyState)
{
    echo ConvertForms\Helper::layoutRender('emptystate', [
        'icon'    => 'icon-users',
        'title'   => Text::_('COM_CONVERTFORMS_EMPTY_SUBMISSIONS_TITLE'),
        'content' => Text::_('COM_CONVERTFORMS_EMPTY_SUBMISSIONS_TEXT'),
        'button'  => Text::_('COM_CONVERTFORMS_EMPTY_SUBMISSIONS_BUTTON'),
        'url'     => Route::_('index.php?option=com_convertforms&task=form.add'),
    ]); 

    return;
}
?>

<form action="<?php echo Route::_('index.php?option=com_convertforms&view=conversions'); ?>" class="clearfix" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container">
    <?php
        echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this));
    ?>

    <div class="cf-submissions-scroll">
    <table class="adminlist nrTable table cf-submissions-table">
        <thead>
            <tr>
                <th width="2%" class="center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
                <th width="3%" class="nowrap hidden-phone" align="center">
                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                </th>
                <?php foreach ($columns as $key => $column) { ?>
                    <th class="nowrap col_<?php echo $column; ?>">
                        <?php 
                            $isParam = (strpos($column, 'param_') !== false);

                            if ($isParam)
                            {
                                $columnLabel = ucfirst(str_replace('param_', '', $column));
                            }
                            else
                            {
                                $columnLabel = DetailBase::make($column, null)->getLangKey();
                            }

                            // Only real submissions-table columns can be sorted. Derived/alias
                            // columns (country_name, user_login_name, form_name, param_*) render
                            // as a plain, non-sortable label.
                            if (in_array(strtolower($column), ConvertForms\Helper::getSubmissionTableColumns(), true))
                            {
                                echo HTMLHelper::_('searchtools.sort', $columnLabel, 'a.' . $column, $listDirn, $listOrder);
                            }
                            else
                            {
                                echo htmlspecialchars(Text::_($columnLabel));
                            }
                        ?>
                    </th>                            
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php if (count($this->items)) { ?>
                <?php foreach($this->items as $i => $item): ?>
                    <?php 
                        $canChange = $user->authorise('core.edit.state', 'com_convertforms.conversion.' . $item->id);
                        $submissionContext = new SubmissionContext($item, SubmissionContext::CONSUMER_LIST);
                    ?>
                    <tr class="row<?php echo $i % 2; ?> <?php echo isset($item->params->sync_error) ? 'error' : '' ?>">
                        <td class="center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                        <td class="text-center">
                            <?php
                                $options = [
                                    'task_prefix' => 'conversions.',
                                    'disabled' => !$canChange,
                                    'id' => 'state-' . $item->id
                                ];

                                echo (new PublishedButton)->render((int) $item->state, $i, $options);
                            ?>
                        </td>
                        <?php $i = 0; foreach ($columns as $key => $column) { 
                                // Convert to lower case to always match the field in case it has been renamed.
                                $column = strtolower($column);
                                $params = [];

                                if (!is_null($item->params))
                                {
                                    foreach ($item->params as $key => $value)
                                    {
                                        $params[strtolower($key)] = $value;
                                    }
                                }

                                $isParam = (strpos($column, 'param_') !== false);
                                $columnName = $isParam ? str_replace('param_', '' , $column) : $column;

                                $value = false;
                                $col_class = !$isParam ? 'nowrap col_' . $column : $column;
                            ?>
                            <td class="<?php echo $col_class; ?>">
                                <?php 
                                    if ($isParam)
                                    {
                                        if (isset($item->prepared_fields[$columnName]))
                                        {
                                            $value = $item->prepared_fields[$columnName]->value_html;
                                        }
                                    }
                                    else
                                    {
                                        // Submission detail columns (including notes) resolve through their Detail class.
                                        $value = $submissionContext->getProp($columnName)->getValueHTML();
                                    }
                                ?>

                                <?php echo $value; ?>

                                <?php if ($i == 0 && isset($item->params->sync_service) && isset($item->params->sync_error) && $key == 0) { ?>
                                    <span class="hasPopover icon icon-info" 
                                        data-placement="top"
                                        data-title="<?php echo Text::_("PLG_CONVERTFORMS_" . $item->params->sync_service . "_ALIAS"); ?>"
                                        data-content="<?php echo $item->params->sync_error ?>"
                                        style="color:red;">
                                    </span>
                                <?php } ?>

                                <?php $i++; ?>
                            </td>                            
                        <?php } ?>
                    </tr>
                <?php endforeach; ?>  
            <?php } else { ?>
                <tr>
                    <td align="center" colspan="<?php echo count($columns) + 2 ?>">
                        <div align="center">
                            <?php echo ConvertForms\Helper::noItemsFound(); ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>  
        </tbody>
    </table>
    </div>

        <div class="pagination"><?php echo $this->pagination->getListFooter(); ?></div>

        <div>
            <input type="hidden" name="task" value="" />
            <input type="hidden" name="boxchecked" value="0" />
            <?php echo HTMLHelper::_('form.token'); ?>
        </div>
    </div>
</form>
<?php include_once(JPATH_COMPONENT_ADMINISTRATOR . '/layouts/footer.php'); ?>