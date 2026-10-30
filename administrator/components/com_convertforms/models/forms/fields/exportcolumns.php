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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use ConvertForms\Form;
use ConvertForms\Helper;
use ConvertForms\Submission\Detail\Base as DetailBase;
use ConvertForms\Submission\Detail\Groups;

/**
 * Renders the "Properties to export" picker of the Export Submissions modal.
 *
 * Options are grouped into Identity (checked by default), Form Fields (checked by
 * default) and Context (unchecked by default). The markup lives in
 * exportcolumns_tmpl.php, the behavior in media/com_convertforms/js/src/admin/export-columns.js
 * and the styling in media/com_convertforms/scss/admin/_export-columns.scss.
 */
class JFormFieldExportColumns extends JFormFieldCheckboxes
{
    /**
     * Method to get a list of options for a list input.
     *
     * @return  array  An array of options.
     */
    protected function getOptions()
    {
        $options = [];

        // Option values must match the keys ConvertForms\Export::prepareSubmission()
        // uses in its result array, so the columns whitelist can filter the export.

        // Identity columns lead the export (ID, status, dates, form). Most are checked
        // by default; a few noisy ones ship unchecked.
        foreach ($this->getIdentityColumns() as $column => $checked)
        {
            $options[] = (object) [
                'value'       => $column,
                'text'        => $this->getColumnLabel($column),
                'description' => $this->getColumnDescription($column),
                'group'       => 'identity',
                'fieldtype'   => $this->getColumnGroupLabel($column),
                'checked'     => $checked,
                'isPro'       => false
            ];
        }

        foreach ($this->getFormFields() as $field)
        {
            $options[] = (object) [
                'value'       => strtolower($field['name']),
                'text'        => isset($field['label']) && !empty($field['label']) ? Text::_($field['label']) : $field['name'],
                'description' => '',
                'group'       => 'form_fields',
                'fieldtype'   => ucfirst($field['type']),
                'checked'     => true,
                'isPro'       => false
            ];
        }

        $isPro = Helper::isPro();

        foreach ($this->getContextColumns() as $column)
        {
            // Pro-only tracking columns render as locked upgrade rows in the Free edition.
            $columnIsPro = !$isPro && DetailBase::make($column, null)::isProOnly();

            $options[] = (object) [
                'value'       => $column,
                'text'        => $this->getColumnLabel($column),
                'description' => $this->getColumnDescription($column),
                'group'       => 'context',
                'fieldtype'   => $this->getColumnGroupLabel($column),
                'checked'     => false,
                'isPro'       => $columnIsPro
            ];
        }

        return $options;
    }

    protected function getInput()
    {
        HTMLHelper::script('com_convertforms/export-columns.js', ['relative' => true, 'version' => 'auto']);

        $options = $this->getOptions();

        // Group order here drives both the picker layout and the exported column order.
        // Identity and Context items already arrive in the shared canonical order
        // (Groups::getColumnsBySection), matching the submissions-list Property Visibility picker.
        $groups = [
            'identity' => [
                'label' => Text::_('COM_CONVERTFORMS_DETAIL_SECTION_IDENTITY'),
                'items' => array_values(array_filter($options, function($option) { return $option->group === 'identity'; }))
            ],
            'form_fields' => [
                'label' => Text::_('COM_CONVERTFORMS_FORM_FIELDS'),
                'items' => array_values(array_filter($options, function($option) { return $option->group === 'form_fields'; }))
            ],
            'context' => [
                'label' => Text::_('COM_CONVERTFORMS_DETAIL_SECTION_CONTEXT'),
                'items' => array_values(array_filter($options, function($option) { return $option->group === 'context'; }))
            ]
        ];

        // Already ends with [] from Joomla's getName() because multiple="true"
        $fieldName = $this->name;

        // Used by the JS for the per-form localStorage persistence key.
        $formID = (int) $this->form->getData()->get('filter_form_id');

        // Locked Pro rows carry data-pro-only anchors; render the framework upgrade modal
        // (once) so clicking them opens it.
        $hasPro = (bool) array_filter($options, function ($option) { return !empty($option->isPro); });

        ob_start();
        include __DIR__ . '/exportcolumns_tmpl.php';

        if ($hasPro)
        {
            \NRFramework\HTML::renderProOnlyModal('com_convertforms');
        }

        return ob_get_clean();
    }

    /**
     * Returns the input fields of the form selected in the export modal.
     *
     * @return  array
     */
    private function getFormFields()
    {
        if (!$formID = (int) $this->form->getData()->get('filter_form_id'))
        {
            return [];
        }

        if (!$form = Form::load($formID, true, true))
        {
            return [];
        }

        return is_array($form['fields']) ? $form['fields'] : [];
    }

    /**
     * Returns the Identity section columns as column => checked-by-default, in the
     * shared canonical order. These lead the export above the form fields. A few noisy
     * columns ship unchecked: Form Name (usually implied by the export filter), Status
     * Label (redundant with the raw Status) and Notes (admin free-text).
     *
     * @return  array
     */
    private function getIdentityColumns()
    {
        $uncheckedByDefault = ['form_name', 'status_label', 'notes'];

        $columns = [];

        foreach (Groups::getColumnsBySection('identity') as $column)
        {
            $columns[$column] = !in_array($column, $uncheckedByDefault, true);
        }

        return $columns;
    }

    /**
     * Returns the Context section columns (visitor/environment/attribution), in the
     * shared canonical order. Unchecked by default in the picker.
     *
     * @return  array
     */
    private function getContextColumns()
    {
        return Groups::getColumnsBySection('context');
    }

    /**
     * Returns the translated label for a submission detail column.
     *
     * @param   string  $column
     *
     * @return  string
     */
    private function getColumnLabel($column)
    {
        return DetailBase::make($column, null)->getLabel();
    }

    /**
     * Returns the translated description (tooltip) for a submission detail column,
     * or an empty string when none is defined.
     *
     * @param   string  $column
     *
     * @return  string
     */
    private function getColumnDescription($column)
    {
        return DetailBase::make($column, null)->getDescription();
    }

    /**
     * Returns the translated group label (e.g. "Location", "Device") for a submission
     * detail column. Used as the column's badge so each property advertises the section
     * of submission data it belongs to instead of a generic "Meta" tag.
     *
     * @param   string  $column
     *
     * @return  string
     */
    private function getColumnGroupLabel($column)
    {
        $group = DetailBase::make($column, null)::getGroup();

        return Text::_('COM_CONVERTFORMS_DETAIL_GROUP_' . strtoupper($group));
    }
}
