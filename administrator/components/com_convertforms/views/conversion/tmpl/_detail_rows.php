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
 * Shared renderer for a submission detail list, used by both the edit-page Identity
 * block (inside the Status card) and the Context card. Each row's icon, label and
 * description come straight from its Detail class, so adding a column is enough.
 *
 * Expects in scope:
 *   $submission    ConvertForms\Submission\Submission  The submission context object.
 *   $columns       string[]                            Detail column keys, in display order.
 *   $rowOverrides  array (optional)                    column => ['label','value','icon','desc','noCopy']
 *                                                      to render a custom row (e.g. an editable
 *                                                      input) instead of the Detail's value.
 *
 * The copy-to-clipboard behaviour is bound once, page-wide, in edit_info.php.
 */

$rowOverrides = isset($rowOverrides) ? $rowOverrides : [];

$infoRows = [];

foreach ($columns as $column)
{
    // A row override supplies its own label/value (e.g. an editable form input).
    if (isset($rowOverrides[$column]))
    {
        $override    = $rowOverrides[$column];
        $infoRows[]  = [
            'icon'   => isset($override['icon']) ? $override['icon'] : '',
            'label'  => isset($override['label']) ? $override['label'] : '',
            'desc'   => isset($override['desc']) ? $override['desc'] : '',
            'value'  => isset($override['value']) ? $override['value'] : '',
            'noCopy' => !empty($override['noCopy']),
        ];
        continue;
    }

    $prop = $submission->getProp($column);

    // Most columns render their rich HTML value; a couple are special-cased.
    switch ($column)
    {
        // Plain value — getValueHTML() would link the ID back to this same edit screen.
        case 'id':
            $value = $prop->getValue();
            break;

        // Pass true to render the country flag alongside the name.
        case 'country_name':
            $value = $prop->getValueHTML(1);
            break;

        default:
            $value = $prop->getValueHTML();
    }

    $infoRows[] = [
        'icon'   => $prop->getIcon(),
        'label'  => $prop->getLabel(),
        'desc'   => $prop->getDescription(),
        'value'  => $value,
        'noCopy' => false,
    ];
}
?>
<div class="items d-flex flex-column">
    <?php foreach ($infoRows as $row) { ?>
        <div class="flex gap-2 align-items-start cf-detail-row">
            <div class="info-label">
                <?php if (!empty($row['icon'])): ?>
                    <span class="<?= $row['icon'] ?>" aria-hidden="true"></span>
                <?php endif; ?>
                <?= $row['label'] ?>
                <?php if (!empty($row['desc'])): ?>
                <span class="cf-detail-info" title="<?= htmlspecialchars($row['desc'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($row['desc'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="cf-icon-info" aria-hidden="true"></span>
                </span>
                <?php endif; ?>
            </div>
            <div class="cf-detail-value">
                <?= $row['value'] ?>
            </div>
            <?php if (empty($row['noCopy']) && !empty(strip_tags((string) $row['value']))): ?>
            <button type="button" class="cf-copy-btn" aria-label="<?= Text::_('COM_CONVERTFORMS_COPY_TO_CLIPBOARD') ?>">
                <svg class="cf-icon-copy" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <svg class="cf-icon-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
            <?php endif; ?>
        </div>
    <?php } ?>
</div>
