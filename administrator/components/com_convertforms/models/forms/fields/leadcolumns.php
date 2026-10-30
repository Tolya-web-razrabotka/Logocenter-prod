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
use ConvertForms\Submission\Detail\Base as DetailBase;

class JFormFieldLeadColumns extends JFormFieldCheckboxes
{
    /**
     * Method to get a list of options for a list input.
     *
     * @return      array           An array of options.
     */
    protected function getOptions()
    {
        $formID = $this->getFormID();

        $form_fields = ConvertForms\Helper::getColumns($formID);

        $optionsForm = [];

        foreach ($form_fields as $key => $field)
        {
            // User-defined form fields are prefixed with "param_"; everything else is a
            // built-in submission Detail, which carries its own icon and group.
            $isField = strpos($field, 'param_') === 0;

            $isPro = false;

            if ($isField)
            {
                $label      = ucfirst(str_replace('param_', '', $field));
                $icon       = 'icon-pencil';
                $group      = '';
                $groupLabel = Text::_('COM_CONVERTFORMS_FORM_FIELD');
            }
            else
            {
                $detail     = DetailBase::make($field, null);
                $label      = $detail->getLabel();
                $icon       = $detail->getIcon();
                $group      = $detail::getGroup();
                $groupLabel = $group ? Text::_('COM_CONVERTFORMS_DETAIL_GROUP_' . strtoupper($group)) : '';

                // Pro-only tracking columns render as locked upgrade rows in the Free edition.
                $isPro = $detail::isProOnly() && !ConvertForms\Helper::isPro();
            }

            $optionsForm[] = (object) [
                'value'      => $field,
                'text'       => $label,
                'icon'       => $icon,
                'group'      => $group,
                'groupLabel' => $groupLabel,
                'isField'    => $isField,
                'isPro'      => $isPro
            ];
        }

        return $optionsForm;
    }

    protected function getInput()
    {
        Factory::getDocument()->addScriptDeclaration(<<<'JS'
        (function() {
            function cfSubmissionColumnsApply(that) {
                var task = document.querySelector('input[type="hidden"][name="task"]');
                if (task) { task.value = ''; }
                that.closest('form').submit();
            }
            window.cfSubmissionColumnsApply = cfSubmissionColumnsApply;

            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.cf-colpicker').forEach(function(picker) {
                    var countNum = picker.querySelector('.cf-colpicker-count-num');
                    var resetBtn = picker.querySelector('.cf-colpicker-reset');

                    function updateCount() {
                        if (countNum) {
                            countNum.textContent = picker.querySelectorAll('.cf-colpicker-cb:checked').length;
                        }
                    }

                    // Reset is hidden until the user changes something, so it reads as
                    // "undo my changes" rather than a no-op on the saved state.
                    function markDirty() {
                        if (resetBtn) resetBtn.style.display = '';
                    }

                    function moveItem(item, toShown) {
                        var cb   = item.querySelector('.cf-colpicker-cb');

                        // Locked Pro rows have no checkbox — they can't be shown/hidden.
                        if (!cb) { return; }

                        var icon = item.querySelector('.cf-colpicker-item-toggle .cf-eye');
                        var key  = toShown ? 'shown' : 'hidden';
                        var list = picker.querySelector('.cf-colpicker-group[data-group="' + key + '"] .cf-colpicker-list');
                        cb.checked   = toShown;
                        icon.dataset.visible = toShown ? '1' : '0';

                        // Form fields always sit at the top of the hidden list, so a freshly
                        // hidden field drops in above the first detail rather than at the bottom.
                        if (!toShown && item.dataset.field === '1') {
                            var firstDetail = null;
                            list.querySelectorAll('.cf-colpicker-item').forEach(function(child) {
                                if (!firstDetail && child !== item && child.dataset.field !== '1') {
                                    firstDetail = child;
                                }
                            });
                            if (firstDetail) {
                                list.insertBefore(item, firstDetail);
                            } else {
                                list.appendChild(item);
                            }
                        } else {
                            list.appendChild(item);
                        }

                        updateCount();
                        markDirty();
                    }

                    // Snapshot the initial (last-saved) layout so Reset can restore both the
                    // shown/hidden split and the order, reusing the existing item elements.
                    var itemsByValue = {};
                    var initialState = [];
                    picker.querySelectorAll('.cf-colpicker-item').forEach(function(item) {
                        itemsByValue[item.dataset.value] = item;
                        initialState.push({
                            value: item.dataset.value,
                            shown: item.closest('.cf-colpicker-group').dataset.group === 'shown'
                        });
                    });

                    if (resetBtn) {
                        resetBtn.addEventListener('click', function() {
                            var shownList  = picker.querySelector('.cf-colpicker-group[data-group="shown"] .cf-colpicker-list');
                            var hiddenList = picker.querySelector('.cf-colpicker-group[data-group="hidden"] .cf-colpicker-list');
                            initialState.forEach(function(state) {
                                var item = itemsByValue[state.value];
                                if (!item) return;
                                var cb = item.querySelector('.cf-colpicker-cb');
                                if (!cb) return; // Locked Pro rows never move.
                                cb.checked = state.shown;
                                item.querySelector('.cf-eye').dataset.visible = state.shown ? '1' : '0';
                                (state.shown ? shownList : hiddenList).appendChild(item);
                            });
                            updateCount();
                            resetBtn.style.display = 'none';
                        });
                    }

                    picker.querySelectorAll('.cf-colpicker-item-toggle').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var item = this.closest('.cf-colpicker-item');
                            var cb   = item.querySelector('.cf-colpicker-cb');

                            // Locked Pro rows have no checkbox — leave the toggle to the
                            // framework's data-pro-only handler, which opens the upgrade modal.
                            if (!cb) { return; }

                            moveItem(item, !cb.checked);
                        });
                    });

                    picker.querySelectorAll('.cf-colpicker-toggle-all').forEach(function(link) {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            var action = this.dataset.action;
                            var group  = this.closest('.cf-colpicker-group');
                            group.querySelectorAll('.cf-colpicker-item').forEach(function(item) {
                                moveItem(item, action === 'show');
                            });
                        });
                    });

                    var searchInput = picker.querySelector('.cf-colpicker-search');
                    if (searchInput) {
                        searchInput.addEventListener('input', function() {
                            var q = this.value.toLowerCase().trim();
                            var anyVisible = false;
                            picker.querySelectorAll('.cf-colpicker-item').forEach(function(item) {
                                var label = (item.dataset.label || '');
                                var show = (!q || label.indexOf(q) >= 0);
                                item.style.display = show ? '' : 'none';
                                if (show) anyVisible = true;
                            });
                            if (q) {
                                picker.querySelectorAll('.cf-colpicker-group').forEach(function(group) {
                                    var items = group.querySelectorAll('.cf-colpicker-item');
                                    var shown = Array.prototype.filter.call(items, function(i) { return i.style.display !== 'none'; }).length;
                                    group.style.display = shown > 0 ? '' : 'none';
                                });
                            } else {
                                picker.querySelectorAll('.cf-colpicker-group').forEach(function(group) {
                                    group.style.display = '';
                                });
                            }
                            var noResults = picker.querySelector('.cf-colpicker-no-results');
                            if (noResults) {
                                noResults.style.display = anyVisible ? 'none' : '';
                                var term = noResults.querySelector('.cf-colpicker-no-results-term');
                                if (term && !anyVisible) term.textContent = this.value.trim();
                            }
                        });

                        var clearBtn = picker.querySelector('.cf-colpicker-clear-search');
                        if (clearBtn) {
                            clearBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                searchInput.value = '';
                                searchInput.dispatchEvent(new Event('input'));
                                searchInput.focus();
                            });
                        }
                    }

                    var closeBtn = picker.querySelector('.cf-colpicker-close');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', function() {
                            var panel = picker.closest('.chooseColumnsOptions');
                            if (panel) panel.classList.remove('show');
                        });
                    }

                    // Drag to reorder — only the "Shown in table" list. The visible columns
                    // submit in DOM order, so reordering here sets the table column order.
                    var shownList = picker.querySelector('.cf-colpicker-group[data-group="shown"] .cf-colpicker-list');
                    if (shownList) {
                        var dragEl = null;

                        function getDragAfter(y) {
                            var items = Array.prototype.slice.call(
                                shownList.querySelectorAll('.cf-colpicker-item:not(.cf-colpicker-dragging)')
                            );
                            return items.reduce(function(closest, child) {
                                var box = child.getBoundingClientRect();
                                var offset = y - box.top - box.height / 2;
                                if (offset < 0 && offset > closest.offset) {
                                    return { offset: offset, element: child };
                                }
                                return closest;
                            }, { offset: -Infinity, element: null }).element;
                        }

                        // Only allow dragging when the grab starts on the handle.
                        shownList.addEventListener('mousedown', function(e) {
                            var item = e.target.closest('.cf-colpicker-item');
                            if (item && e.target.closest('.cf-colpicker-drag')) {
                                item.setAttribute('draggable', 'true');
                            }
                        });

                        shownList.addEventListener('dragstart', function(e) {
                            var item = e.target.closest('.cf-colpicker-item');
                            if (!item) return;
                            dragEl = item;
                            item.classList.add('cf-colpicker-dragging');
                            e.dataTransfer.effectAllowed = 'move';
                            try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
                        });

                        shownList.addEventListener('dragover', function(e) {
                            if (!dragEl) return;
                            e.preventDefault();
                            e.dataTransfer.dropEffect = 'move';
                            var after = getDragAfter(e.clientY);
                            if (after == null) {
                                shownList.appendChild(dragEl);
                            } else {
                                shownList.insertBefore(dragEl, after);
                            }
                        });

                        shownList.addEventListener('drop', function(e) { e.preventDefault(); });

                        function endDrag() {
                            if (dragEl) {
                                dragEl.classList.remove('cf-colpicker-dragging');
                                dragEl.removeAttribute('draggable');
                                dragEl = null;
                                markDirty();
                            }
                        }
                        shownList.addEventListener('dragend', endDrag);
                        shownList.addEventListener('mouseup', function(e) {
                            var item = e.target.closest('.cf-colpicker-item');
                            if (item && !dragEl) item.removeAttribute('draggable');
                        });
                    }
                });

                document.addEventListener('click', function(e) {
                    // Ignore clicks inside an open modal (e.g. the Pro upgrade modal, which
                    // is appended to <body> outside the picker). Otherwise dismissing that
                    // modal reads as an outside click and closes the column picker too.
                    if (e.target.closest('.tf-pro-only-modal, .modal, .modal-backdrop')) {
                        return;
                    }
                    document.querySelectorAll('.chooseColumns').forEach(function(widget) {
                        var panel = widget.querySelector('.chooseColumnsOptions');
                        if (panel && panel.classList.contains('show') && !widget.contains(e.target)) {
                            panel.classList.remove('show');
                        }
                    });
                });
            });
        })();
        JS);

        $options = $this->getOptions();
        $values  = is_array($this->value) ? $this->value : (array) $this->value;

        // Locked Pro columns (Free edition) — counted separately so the footer total
        // reflects only the columns the user can actually toggle.
        $proOptions = array_filter($options, function ($opt) { return !empty($opt->isPro); });

        $visibleOptions = [];
        $hiddenOptions  = [];

        foreach ($options as $opt)
        {
            // Pro-locked columns can never be shown — they always sit in the hidden list.
            if (!$opt->isPro && in_array($opt->value, $values))
            {
                $visibleOptions[] = $opt;
            }
            else
            {
                $hiddenOptions[] = $opt;
            }
        }

        usort($visibleOptions, static function ($a, $b) use ($values) {
            return array_search($a->value, $values) <=> array_search($b->value, $values);
        });

        // Hidden list ordering: all form fields first (in their natural form order), then the
        // built-in details in the shared canonical order (Groups::getColumns) — the same order
        // the Export Submissions "Submission Details" group uses.
        $detailOrder = array_flip(\ConvertForms\Submission\Detail\Groups::getColumns());

        usort($hiddenOptions, static function ($a, $b) use ($detailOrder) {
            if ($a->isField !== $b->isField)
            {
                return $a->isField ? -1 : 1;
            }

            if ($a->isField)
            {
                return 0;
            }

            // Locked Pro rows sink to the bottom of the hidden list.
            if ($a->isPro !== $b->isPro)
            {
                return $a->isPro ? 1 : -1;
            }

            return ($detailOrder[$a->value] ?? PHP_INT_MAX) <=> ($detailOrder[$b->value] ?? PHP_INT_MAX);
        });

        $fieldName = $this->name; // already ends with [] from Joomla's getName() because $multiple = true

        $renderItems = static function (array $items, bool $isVisible) use ($fieldName): string
        {
            $html = '';
            foreach ($items as $opt)
            {
                $value   = htmlspecialchars($opt->value, ENT_QUOTES, 'UTF-8');
                $label   = htmlspecialchars($opt->text, ENT_QUOTES, 'UTF-8');
                $checked = $isVisible ? ' checked' : '';
                $visible = $isVisible ? '1' : '0';

                if (!empty($opt->icon))
                {
                    $iconHtml = '<span class="cf-colpicker-item-icon ' . htmlspecialchars($opt->icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></span>';
                }
                else
                {
                    $iconHtml = '<span class="cf-colpicker-item-icon cf-colpicker-item-icon--empty" aria-hidden="true"></span>';
                }

                $badgeHtml = !empty($opt->groupLabel)
                    ? '<span class="cf-colpicker-badge">' . htmlspecialchars($opt->groupLabel, ENT_QUOTES, 'UTF-8') . '</span>'
                    : '';

                // Locked Pro row: rendered like a normal hidden row (drag handle + eye
                // toggle, kept for alignment and disabled via the hidden-list styling) but
                // with no checkbox — so it can't be selected or persisted — and a
                // data-pro-only toggle that opens the upgrade modal instead of toggling.
                if (!empty($opt->isPro))
                {
                    $html .= '<li class="cf-colpicker-item cf-colpicker-item--pro" data-value="' . $value . '" data-field="0" data-detail-group="' . htmlspecialchars($opt->group, ENT_QUOTES, 'UTF-8') . '" data-label="' . strtolower(strip_tags($opt->text)) . '">'
                        . '<span class="cf-colpicker-drag" aria-hidden="true"></span>'
                        . $iconHtml
                        . '<span class="cf-colpicker-item-label">' . $label . '</span>'
                        . '<span class="cf-pro-badge">' . Text::_('NR_PRO') . '</span>'
                        . $badgeHtml
                        . '<button type="button" class="cf-colpicker-item-toggle" data-pro-only="' . $label . '"><span class="cf-eye" data-visible="0" aria-hidden="true"></span></button>'
                        . '</li>';

                    continue;
                }

                $html .= '<li class="cf-colpicker-item" data-value="' . $value . '" data-field="' . ($opt->isField ? '1' : '0') . '" data-detail-group="' . htmlspecialchars($opt->group, ENT_QUOTES, 'UTF-8') . '" data-label="' . strtolower(strip_tags($opt->text)) . '">'
                    . '<span class="cf-colpicker-drag" aria-hidden="true"></span>'
                    . $iconHtml
                    . '<span class="cf-colpicker-item-label">' . $label . '</span>'
                    . $badgeHtml
                    . '<button type="button" class="cf-colpicker-item-toggle"><span class="cf-eye" data-visible="' . $visible . '" aria-hidden="true"></span></button>'
                    . '<input type="checkbox" class="cf-colpicker-cb" name="' . $fieldName . '" value="' . $value . '"' . $checked . '>'
                    . '</li>';
            }
            return $html;
        };

        $html = '<div class="chooseColumns">'
            // Records which form these checkboxes were rendered for. The model only persists a
            // submitted selection when this matches the current form, so a browser refresh that
            // replays a form-switch POST (carrying the previous form's columns) can't overwrite
            // the newly selected form's saved columns.
            . '<input type="hidden" name="filter[columns_form]" value="' . (int) $this->getFormID() . '">'
            . '<a class="btn btn-secondary" data-bs-toggle="collapse" data-toggle="collapse" href="#" data-target=".chooseColumnsOptions" data-bs-target=".chooseColumnsOptions">'
            . Text::_('COM_CONVERTFORMS_CHOOSE_COLUMNS')
            . '</a>'
            . '<div class="collapse chooseColumnsOptions">'
            . '<div class="cf-colpicker">'
            . '<div class="cf-colpicker-header">'
            . '<div class="cf-colpicker-title">'
            . '<span>' . Text::_('COM_CONVERTFORMS_COLUMN_VISIBILITY') . '</span>'
            . '<a class="cf-doc-icon" href="https://www.tassos.gr/docs/convert-forms/submission-management/customize-columns" target="_blank" rel="noopener" aria-label="' . Text::_('NR_READMORE') . '" title="' . Text::_('NR_READMORE') . '"><span class="cf-icon-info" aria-hidden="true"></span></a>'
            . '<button type="button" class="cf-colpicker-close" aria-label="Close">&times;</button>'
            . '</div>'
            . '<input type="text" class="cf-colpicker-search" placeholder="' . Text::_('COM_CONVERTFORMS_SEARCH_PROPERTY') . '">'
            . '</div>'
            . '<div class="cf-colpicker-groups-scroll">'
            . '<div class="cf-colpicker-no-results" style="display:none">'
            . '<span class="cf-colpicker-no-results-icon"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="7" r="5"/><path d="M11 11l3.5 3.5"/></svg></span>'
            . '<p class="cf-colpicker-no-results-title">' . sprintf(Text::_('COM_CONVERTFORMS_NO_PROPERTIES_MATCH'), '<strong class="cf-colpicker-no-results-term"></strong>') . '</p>'
            . '<p class="cf-colpicker-no-results-hint">' . Text::_('COM_CONVERTFORMS_NO_PROPERTIES_MATCH_HINT') . '</p>'
            . '<a href="#" class="cf-colpicker-clear-search">' . Text::_('COM_CONVERTFORMS_CLEAR_SEARCH') . '</a>'
            . '</div>'
            . '<div class="cf-colpicker-group" data-group="shown">'
            . '<div class="cf-colpicker-group-header">'
            . '<span>' . Text::_('COM_CONVERTFORMS_SHOWN_IN_TABLE') . '</span>'
            . '<a href="#" class="cf-colpicker-toggle-all" data-action="hide">' . Text::_('COM_CONVERTFORMS_HIDE_ALL') . '</a>'
            . '</div>'
            . '<ul class="cf-colpicker-list">' . $renderItems($visibleOptions, true) . '</ul>'
            . '</div>'
            . '<div class="cf-colpicker-group" data-group="hidden">'
            . '<div class="cf-colpicker-group-header">'
            . '<span>' . Text::_('COM_CONVERTFORMS_HIDDEN_IN_TABLE') . '</span>'
            . '<a href="#" class="cf-colpicker-toggle-all" data-action="show">' . Text::_('COM_CONVERTFORMS_SHOW_ALL') . '</a>'
            . '</div>'
            . '<ul class="cf-colpicker-list">' . $renderItems($hiddenOptions, false) . '</ul>'
            . '</div>'
            . '</div>'
            . '<div class="cf-colpicker-footer">'
            . '<span class="cf-colpicker-count"><strong class="cf-colpicker-count-num">' . count($visibleOptions) . '</strong> ' . sprintf(Text::_('COM_CONVERTFORMS_COLUMNS_OF_SHOWN'), count($options) - count($proOptions)) . '</span>'
            . '<div class="cf-colpicker-footer-actions">'
            . '<button class="btn btn-sm btn-default cf-colpicker-reset" type="button" style="display:none">' . Text::_('NR_RESET') . '</button>'
            . '<button class="btn btn-sm btn-success cf-colpicker-save" type="button" onclick="cfSubmissionColumnsApply(this);">' . Text::_('NR_SAVE') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        // The locked Pro rows carry data-pro-only anchors; render the framework's
        // upgrade modal (once) so clicking them opens it.
        if ($proOptions)
        {
            ob_start();
            \NRFramework\HTML::renderProOnlyModal('com_convertforms');
            $html .= ob_get_clean();
        }

        return $html;
    }

    private function getFormID()
    {
        return $this->form->getData()->get('filter.form_id');
    }
}