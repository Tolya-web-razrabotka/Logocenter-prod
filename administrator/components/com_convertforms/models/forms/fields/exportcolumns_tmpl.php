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

/**
 * Markup of the "Properties to export" picker. Included by JFormFieldExportColumns::getInput().
 *
 * Receives from the including scope:
 *   $groups     array   ['identity' => ['label' => ..., 'items' => [...]], 'form_fields' => [...], 'context' => [...]]
 *   $fieldName  string  The checkbox input name (e.g. 'columns[]')
 *   $formID     int     The selected form ID, used by the JS persistence key
 */

$selected = 0;
$total    = 0;

foreach ($groups as $group)
{
    foreach ($group['items'] as $item)
    {
        // Locked Pro rows aren't selectable, so they don't count toward the totals.
        if (!empty($item->isPro))
        {
            continue;
        }

        $total++;
        $selected += $item->checked ? 1 : 0;
    }
}

?>
<div class="cf-export-picker" data-form-id="<?php echo (int) $formID; ?>">
    <?php // The JS moves this badge next to the field's label that Joomla renders. ?>
    <span class="cf-export-picker__badge" data-template="<?php echo htmlspecialchars(Text::_('COM_CONVERTFORMS_EXPORT_N_SELECTED'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::sprintf('COM_CONVERTFORMS_EXPORT_N_SELECTED', $selected); ?></span>
    <p class="cf-export-picker__hint"><?php echo Text::_('COM_CONVERTFORMS_EXPORT_PROPERTIES_HINT'); ?></p>
    <input type="text" class="cf-export-picker__search" placeholder="<?php echo htmlspecialchars(Text::_('COM_CONVERTFORMS_SEARCH_PROPERTY'), ENT_QUOTES, 'UTF-8'); ?>">
    <p class="cf-export-picker__no-results" hidden><?php echo Text::_('COM_CONVERTFORMS_EXPORT_NO_PROPERTIES_FOUND'); ?></p>
    <div class="cf-export-picker__groups">
    <?php foreach ($groups as $groupKey => $group) : ?>
        <?php if (!$group['items']) continue; ?>
        <div class="cf-export-group" data-group="<?php echo $groupKey; ?>">
            <div class="cf-export-group__header">
                <button type="button" class="cf-export-group__toggle">
                    <span class="cf-export-group__chevron" aria-hidden="true"></span>
                    <span class="cf-export-group__title"><?php echo $group['label']; ?></span>
                    <span class="cf-export-group__count"></span>
                </button>
                <a href="#" class="cf-export-group__select-all"
                    data-select-label="<?php echo htmlspecialchars(Text::_('COM_CONVERTFORMS_EXPORT_SELECT_ALL'), ENT_QUOTES, 'UTF-8'); ?>"
                    data-deselect-label="<?php echo htmlspecialchars(Text::_('COM_CONVERTFORMS_EXPORT_DESELECT_ALL'), ENT_QUOTES, 'UTF-8'); ?>"></a>
            </div>
            <ul class="cf-export-group__list">
                <?php foreach ($group['items'] as $item) : ?>
                    <?php
                    // Locked Pro row: rendered like a normal row (checkbox kept for
                    // alignment) but with no submittable name and data-pro-only on the row,
                    // so a click opens the upgrade modal and its preventDefault stops the
                    // checkbox from toggling. The JS also excludes .cf-export-item--pro rows
                    // from counts/select-all.
                    $isProItem = !empty($item->isPro);
                    ?>
                    <li class="cf-export-item<?php echo $isProItem ? ' cf-export-item--pro' : ($item->checked ? ' is-checked' : ''); ?>" data-label="<?php echo htmlspecialchars(strtolower(strip_tags($item->text)), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isProItem ? ' data-pro-only="' . htmlspecialchars($item->text, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                        <label class="cf-export-item__main">
                            <?php if ($isProItem) : ?>
                                <input type="checkbox">
                            <?php else : ?>
                                <input type="checkbox" name="<?php echo $fieldName; ?>" value="<?php echo htmlspecialchars($item->value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $item->checked ? ' checked' : ''; ?>>
                            <?php endif; ?>
                            <span class="cf-export-item__label"><?php echo htmlspecialchars($item->text, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($isProItem) : ?>
                                <span class="cf-pro-badge"><?php echo htmlspecialchars(Text::_('NR_PRO'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item->description)) : ?>
                                <span class="cf-export-item__info" title="<?php echo htmlspecialchars($item->description, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($item->description, ENT_QUOTES, 'UTF-8'); ?>">
                                    <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="7"/><line x1="8" y1="7.5" x2="8" y2="11.5"/><circle cx="8" cy="4.6" r="0.9" fill="currentColor" stroke="none"/></svg>
                                </span>
                            <?php endif; ?>
                        </label>
                        <span class="cf-export-item__badge"><?php echo htmlspecialchars($item->fieldtype, ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                    <?php // endif ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
    </div>
    <p class="cf-export-picker__footer">
        <?php echo Text::sprintf(
            'COM_CONVERTFORMS_EXPORT_COLUMNS_COUNT',
            '<span class="cf-export-picker__selected">' . $selected . '</span>',
            '<span class="cf-export-picker__total">' . $total . '</span>'
        ); ?>
    </p>
</div>
