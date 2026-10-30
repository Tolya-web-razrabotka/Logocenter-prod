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

namespace ConvertForms;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\Filesystem\Path;
use Joomla\Filesystem\File as JoomlaFile;
use ConvertForms\Helper;
use ConvertForms\Form;
use ConvertForms\Submission\Submission as SubmissionContext;
use Joomla\CMS\Factory;
use Tassos\Framework\File;

/**
 * Export submissions to CSV and JSON
 */
class Export
{
    /**
     * Export submissions to CSV or JSON file
     *
     * @param  array $options   The export options
     *
     * @return array
     */
    public static function export($options)
    {
        // Increase memory size and execution time to prevent PHP errors on datasets > 20K
        set_time_limit(300); // 5 Minutes
        ini_set('memory_limit', '-1');

        $options = new Registry($options);
        $csv_delimiter = self::sanitizeCSVDelimiter($options->get('csv_delimiter', ','));
        $columns = array_values(array_filter((array) $options->get('columns', [])));

        // No explicit selection (the export modal with everything unchecked, or a cron/webhook
        // URL with no columns[]): fall back to the form's default columns instead of failing.
        if (!$columns)
        {
            $columns = self::getDefaultColumns($options->get('filter_form_id'));
        }

        // Load submissions model
        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_convertforms/models');
        $model = BaseDatabaseModel::getInstance('Conversions', 'ConvertFormsModel', ['ignore_request' => true]);

        // When we're exporting certain IDs, there's no need to check the state.
        if (strpos($options->get('filter_search', ''), 'id:') !== false || $options->get('filter_state') == '*')
        {
            $filter_state = 'skip';
        } else 
        {
            $filter_state = $options->get('filter_state');
        }

        $model->setState('filter.state', $filter_state);
        $model->setState('filter.join_campaigns', 'skip');
        // The forms join only matters when the form_name column is exported; skip it
        // otherwise so the common case keeps its lighter query.
        $model->setState('filter.join_forms', in_array('form_name', $columns, true) ? null : 'skip');
        $model->setState('list.limit', $options->get('limit', 0));
        $model->setState('list.start', $options->get('offset', 0));
        $model->setState('list.direction', 'asc');
        $model->setState('filter.search', $options->get('filter_search'));
        $model->setState('filter.form_id', $options->get('filter_form_id'));
        $model->setState('filter.period', $options->get('filter_period', ''));
        $model->setState('filter.created_from', $options->get('filter_created_from', ''));
        $model->setState('filter.created_to', $options->get('filter_created_to', ''));
        $model->setState('filter.timezone', $options->get('filter_timezone', ''));

        // Proceed only if we have submissions
        if (!$submissions = $model->getItems())
        {
            $error = Text::sprintf('COM_CONVERTFORMS_NO_RESULTS_FOUND', strtolower(Text::_('COM_CONVERTFORMS_SUBMISSIONS')));
            throw new \Exception($error);
        }

        foreach ($submissions as &$submission)
        {
            self::prepareSubmission($submission, $columns);
        }
        unset($submission);

        $export_type = $options->get('export_type', 'csv');

        $pagination = $model->getPagination();
        $firstRun = $pagination->pagesCurrent == 1;
        $append = !$firstRun || $options->get('export_append', false);

        // Check whether the path does exist. If not, attemp to create it.
        $export_path = $options->get('export_path', Helper::getTempPath());

		if (!File::createDirs($export_path, false))
		{
            throw new \Exception('Export path does not exist');
		}

        $filename = Path::clean($export_path . DIRECTORY_SEPARATOR) . $options->get('filename', 'convertforms_submissions.' . $export_type);

        // Batch de-duplication keys off the submission ID (the first CSV column or the
        // 'id' key in JSON), so it must be skipped when the ID column is not exported.
        $check_for_duplicates = !$columns || ($export_type == 'json' ? in_array('id', $columns) : reset($columns) === 'id');

        switch ($export_type)
        {
            case 'json':
                self::toJSON($submissions, $filename, $append, $check_for_duplicates);
                break;

            default:
                $excel_security = (bool) Helper::getComponentParams()->get('excel_security', true);
                self::toCSV($submissions, $filename, $append, $excel_security, $check_for_duplicates, $csv_delimiter);
                break;
        }

        return [
            'options'    => $options,
            'pagination' => $pagination,
            'filename'   => $filename
        ];
    }

    /**
     * Builds the default export column whitelist for a form: the submissions table's own
     * DB columns (id, created, modified, form_id, state, ip, ...) plus the form's fields.
     *
     * Used when no explicit selection is provided (e.g. a scheduled-export cron/webhook
     * URL with no columns[]), so the export still runs instead of failing on an empty
     * selection. The columns are read straight from the table schema (in table order, so
     * 'id' leads and the CSV append de-duplication keeps working) rather than hardcoded.
     *
     * @param   int  $formID
     *
     * @return  array
     */
    public static function getDefaultColumns($formID)
    {
        // Derive the core columns from the submissions table schema, skipping non-exportable
        // ones: 'params' (JSON blob of field values/meta, not string-castable) and 'campaign_id'
        // (deprecated).
        $excluded = ['params', 'campaign_id'];
        $columns  = array_keys(Factory::getDbo()->getTableColumns('#__convertforms_conversions'));
        $columns  = array_values(array_filter($columns, function($column) use ($excluded) { return !in_array($column, $excluded, true); }));

        // Append the form's own fields, keyed by field name exactly as prepareSubmission() expects.
        if ($formID && $form = Form::load($formID, true, true))
        {
            foreach ((array) ($form['fields'] ?? []) as $field)
            {
                if (!empty($field['name']))
                {
                    $columns[] = strtolower($field['name']);
                }
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     *  Get a key value array with submission's submitted data
     *
     *  @param   object   $submission   The submission object
     *  @param   array    $columns      Whitelist of columns to export, in the desired order. Must be non-empty (guaranteed by export()).
     *
     *  @return  array
     */
    private static function prepareSubmission(&$submission, $columns = [])
    {
        $context = new SubmissionContext($submission, SubmissionContext::CONSUMER_EXPORT);
        $result  = [];

        foreach ($columns as $column)
        {
            // Form fields always return the raw value and let the export type decide how the value should be displayed.
            if (isset($submission->prepared_fields[$column]))
            {
                $result[$column] = $submission->prepared_fields[$column]->value_raw;
                continue;
            }

            // Submission detail columns (including notes) resolve through their Detail class.
            $result[$column] = $context->getProp($column)->getValueRaw();
        }

        $submission = $result;
    }

    /**
     * Create a JSON file with given data
     *
     * @param   array     $data           The data to populate the file   
     * @param   string    $destination    The path where the store the JSON file
     * @param   bool      $append         If true, given data will be appended to the end of the file.
     *
     * @return  void
     */
    private static function toJSON($data, $destination, $append = false, $check_for_duplicates = true)
    {
        $content = file_exists($destination) ? (array) json_decode(file_get_contents($destination), true) : [];
        $content = $append ? array_merge($content, $data) : $data;

        if ($content && $append && $check_for_duplicates)
        {   
            $newArr = [];

            foreach ($content as $row)
            {
                $newArr[$row['id']] = $row;
            }

            $content = $newArr;
        }

        $content = json_encode(array_values($content), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Save the file
        JoomlaFile::write($destination, $content);
    }

    /**
     *  Create a CSV file with given data
     *
     *  @param   array     $data            The data to populate the file   
     *  @param   string    $destination     The path where the store the CSV file
     *  @param   bool      $append          If true, given data will be appended to the end of the file.
     *  @param   boolean   $excel_security  If enabled, certain row values will be prefixed by a tab to avoid any CSV injection.
     *
     *  @param   string    $delimiter       The CSV delimiter character.
     *
     *  @return  void
     */
    private static function toCSV($data, $destination, $append = false, $excel_security = true, $check_for_duplicates = true, $delimiter = ',')
    {
        $resource = fopen($destination, $append ? 'a+' : 'w');

        if (!$append)
        {
            // Support UTF-8 on Microsoft Excel
            fputs($resource, "\xEF\xBB\xBF");
            
            // Add column names in the first line
            // Pass the enclosure/escape defaults explicitly — PHP 8.1+ deprecates omitting $escape.
            fputcsv($resource, array_keys($data[0]), $delimiter, '"', '\\');
        }

        // Get CSV content
        $existingRows = [];
        if ($append && $check_for_duplicates)
        {
            rewind($resource);

            while (($existingData = fgetcsv($resource, 0, $delimiter, '"', '\\')) !== false)
            {
                $existingRows[(int) $existingData[0]] = $existingData;
            }

            fseek($resource, 0, SEEK_END);
        }

        foreach ($data as $row)
        {
            if (!empty($existingRows) && isset($row['id']) && array_key_exists($row['id'], $existingRows))
            {
                continue;
            }

            // Always flatten array values before writing CSV rows.
            foreach ($row as &$value)
            {
                if (!is_array($value))
                {
                    continue;
                }
                
                $value = implode(', ', $value);
            }
            unset($value);

            // Prevent CSV Injection: https://vel.joomla.org/articles/2140-introducing-csv-injection
            if ($excel_security)
            {
                foreach ($row as &$value)
                {
                    $firstChar = substr($value, 0, 1);

                    // Prefixe values starting with a =, +, - or @ by a tab character
                    if (in_array($firstChar, array('=', '+', '-', '@')))
                    {
                        $value = '    ' . $value;
                    }
                }
                unset($value);
            }

            fputcsv($resource, $row, $delimiter, '"', '\\');
        }

        fclose($resource);
    }

    /**
     * Allow only supported delimiters in CSV exports.
     *
     * @param   string  $delimiter
     *
     * @return  string
     */
    private static function sanitizeCSVDelimiter($delimiter)
    {
        return in_array($delimiter, [',', ';'], true) ? $delimiter : ',';
    }

    /**
     * Redirects to the error layout and displays the given error message
     *
     * @param  string $error_message
     *
     * @return void
     */
    public static function error($error_message)
    {
        $app = Factory::getApplication();

        $optionsQuery = http_build_query(array_filter([
            'option' => 'com_convertforms',
            'view'   => 'export',
            'layout' => 'error',
            'error'  => $error_message,
            'tmpl'   => $app->input->get('tmpl')
        ]));
    
        $app->redirect('index.php?' . $optionsQuery);
    }

    /**
     * Verifies the export file does exist
     *
     * @param string $filename
     *
     * @return bool 
     */
    public static function exportFileExists($filename)
    {
        return file_exists(Helper::getTempPath() . $filename);
    }

    /**
     * Adds the Export popup to the page which can be triggered by toolbar buttons.
     *
     * @return void
     */
    public static function renderModal()
    {
        HTMLHelper::script('com_convertforms/export.js', ['relative' => true, 'version' => 'auto']);

        Factory::getDocument()->addScriptDeclaration('
            document.addEventListener("DOMContentLoaded", function() {
                new exportModal("' . Factory::getApplication()->input->get('view') . '");
            });
        ');

        $html = HTMLHelper::_('bootstrap.renderModal', 'cfExportSubmissions', [
            'backdrop' => 'static'
        ]);

        // This is the only way to add a custom CSS class to the popup container
        echo str_replace('modal hide', 'modal hide transparent', $html);
    }
}