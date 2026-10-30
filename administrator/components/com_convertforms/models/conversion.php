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

use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use Joomla\Filter\InputFilter;
use ConvertForms\Form;
use ConvertForms\Helper;
use ConvertForms\FieldsHelper;
use ConvertForms\DeferredUploads;
use NRFramework\SmartTags;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Conversion Model Class
 */
class ConvertFormsModelConversion extends AdminModel
{
    /**
     *  The database object
     *
     *  @var  object
     */
    private $db;

    /**
     *  Field keys allowed to skip validation for the submission being processed.
     *
     *  @var  array
     */
    private $ignored_field_keys = [];

    /**
     *  Field keys the plugins reported as hideable on this form.
     *
     *  @var  array
     */
    private $hideable_field_keys = [];

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see     BaseDatabaseModel
     * @since   1.6
     */
    public function __construct($config = array())
    {
        parent::__construct($config);
        $this->db = Factory::getDbo();

        // Make sure our plugins are loaded. Without this line, the PDF plugin won't be able to catch the onContentAfterSave event.
        PluginHelper::importPlugin('convertformstools');
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param       type    The table type to instantiate
     * @param       string  A prefix for the table class name. Optional.
     * @param       array   Configuration array for model. Optional.
     * @return      Table   A database object
     * @since       2.5
     */
    public function getTable($type = 'Conversion', $prefix = 'ConvertFormsTable', $config = array()) 
    {
        return Table::getInstance($type, $prefix, $config);
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data
     *
     * @return  boolean
     */
    public function save($data)
    {
        $this->keepStoredUploads($data);

        return parent::save($data);
    }

    /**
     * Discard posted values for File Upload fields, keeping the stored ones.
     *
     * The edit screen renders them as editable text boxes and AdminModel::save() binds
     * whatever comes back. A stored value decides which file the uploaded-files cleaner
     * deletes and which file the download task streams, so only the upload pipeline may
     * write one.
     *
     * The front-end submit path reaches save() with params already JSON encoded, and
     * there is nothing stored to protect there yet.
     *
     * @param   array  $data  The form data, modified in place
     *
     * @return  void
     */
    private function keepStoredUploads(&$data)
    {
        if (!isset($data['params']) || !is_array($data['params']))
        {
            return;
        }

        $id     = isset($data['id']) ? (int) $data['id'] : 0;
        $stored = [];

        if ($id)
        {
            $table = $this->getTable();

            if (!$table->load($id))
            {
                return;
            }

            $decoded = json_decode($table->params, true);
            $stored  = is_array($decoded) ? $decoded : [];

            $form_id = $table->form_id;
        }
        else
        {
            $form_id = isset($data['form_id']) ? $data['form_id'] : 0;
        }

        foreach (array_keys(Form::fileUploadKeys($form_id)) as $name)
        {
            if (array_key_exists($name, $stored))
            {
                $data['params'][$name] = $stored[$name];

                continue;
            }

            unset($data['params'][$name]);
        }
    }

    /**
	 * Allows preprocessing of the JForm object.
	 *
	 * @param   JForm   $form   The form object
	 * @param   array   $data   The data to be merged into the form object
	 * @param   string  $group  The plugin group to be executed
	 *
	 * @return  void
	 *
	 * @since    3.6.1
	 */
	protected function preprocessForm(Joomla\CMS\Form\Form $form, $data, $group = 'content')
	{
        if (!isset($data->params))
        {
            return parent::preprocessForm($form, $data, $group);
        }

        // Ensure the form binds the data regardless the letter case of the field's name.
        // We should not rely on the field's name. Instead we need to switch over to field's IDs instead.
        $data->params = array_change_key_case($data->params);

        // @todo - What we do here is somehow a joke. We should consider moving the logic of preparing each field to the respective field class in the namespace.
        $data_ = clone $data;

        $this->prepare($data_);

        // Add form custom fields to form
        $fields = [];

        foreach ($data_->prepared_fields as $key => $field)
        {
            // Replace Smart Tags in field props.
            $field->options = SmartTags::getInstance()->replace($field->options);

            $field_class = 'input-xlarge';

            $type = $field->options->get('type');

            // Map of fields types that need to be transformed in order to be recognized by the XML parser.
            $transformFields = [
                'hidden'     => 'text',
                'datetime'   => 'text', // Joomla 6 introduced a new DateTime field which breaks our DateTime field. We need to transform it to text in order to avoid issues.
                'currency'   => 'NR_Currencies',
                'country'    => 'NR_Geo',
                'checkbox'   => 'checkboxes',
                'dropdown'   => 'list',
                'fileupload' => 'textlist',
                'confirm'    => $field->options->get('confirm_type')
            ];

            if (array_key_exists($type, $transformFields))
            {
                $type = $transformFields[$type];
            }

            // Radio fields doesn't accept Array as a value and we need to transform it into a string.
            if (in_array($type, ['radio']))
            {
                if (isset($data->params[$key]))
                {
                    $data->params[$key] = implode('', (array) $data->params[$key]);
                }
            }

            // Create the field
            $label = $field->class->getLabel();

            // Prepare the Telephone field
            if ($type === 'tel')
            {
                $type = 'TFPhoneControl';
            }

            // Get hint
            $hint = $field->options->get('placeholder', $label);

            $fld = new SimpleXMLElement('<field/>');
            $fld->addAttribute('name', $key);
            $fld->addAttribute('type', $type);
            $fld->addAttribute('label', $label);
            $fld->addAttribute('hint', $hint);
            $fld->addAttribute('description', $field->options->get('description', ''));
            $fld->addAttribute('rows', 10); // Used for textarea inputs
            $fld->addAttribute('filter', $field->options->get('filter', 'safehtml'));
            
            // Continue preparation for the TextList (File Upload) field
            if ($type == 'textlist')
            {
                $fld->addAttribute('view_url', 'true');
                $fld->addAttribute('submission_id', (int) $data->id);

                // Read-only because the save discards posted File Upload values anyway
                // (see keepStoredUploads()): a stored value decides which file the
                // cleaner deletes and the download task streams, so only the upload
                // pipeline may write one. An editable input would look saved and revert.
                $fld->addAttribute('readonly', 'true');
            }

            // Continue preparation for the Telephone field
            if ($type === 'TFPhoneControl')
            {
                $field_class = 'form-control';
                
                $fld->addAttribute('country_selector_enabled', $field->options->get('enable_country_selector') === '1');

                if (isset($data->params[$key]))
                {
                    $field_value = is_scalar($data->params[$key]) ? $data->params[$key] : json_encode($data->params[$key]);
                    $fld->addAttribute('value', $field_value);
                }
            }
            
            $fld->addAttribute('class', $field_class);
            
            if ($type == 'editor')
            {
                $fld->addAttribute('editor', $field->options->get('editor', Factory::getConfig()->get('editor')));
            }

            // Define options to list-based fields
            if (in_array($type, ['list', 'radio', 'checkboxes']) && $choices = $field->class->getOptions())
            {
                if ($type == 'list' && empty(!$field->options->get('placeholder')))
                {
                    array_unshift($choices, [
                        'label'    => trim($hint),
                        'value'    => '',
                        'selected' => true,
                        'disabled' => 'disabled'
                    ]);
                }

                foreach ($choices as $choice)
                {
                    $option = $fld->addChild('option', htmlspecialchars($choice['label']));
                    $option->addAttribute('value', strip_tags($choice['value']));

                    if (isset($choice['disabled']))
                    {
                        $option->addAttribute('disabled', true);
                    }
                }
            }

            // Get field's XML
            $fields[] = str_replace('<?xml version="1.0"?>', '', $fld->asXml());
        }
        
        $form->setField(new SimpleXMLElement('
            <fieldset name="params">
                <fields name="params">
                    ' . implode('', $fields) . '
                </fields>
            </fieldset>
        '));

		parent::preprocessForm($form, $data, $group);
	}

    /**
     * Method to get the record form.
     *
     * @param       array   $data           Data for the form.
     * @param       boolean $loadData       True if the form is to load its own data (default case), false if not.
     * @return      mixed   A JForm object on success, false on failure
     * @since       2.5
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_convertforms.conversion', 'conversion', array('control' => 'jform', 'load_data' => $loadData));

        if (empty($form)) 
        {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return    mixed    The data for the form.
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_convertforms.edit.conversion.data', array());

        if (empty($data))
        {
            $data = $this->getItem(null, false);
        }

        return $data;
    }

    /**
     *  Validate data before saving
     *
     *  @param   object  $form   The form to validate
     *  @param   object  $data   The data to validate
     *  @param   string  $group  
     *
     *  @return  array           The validated data
     */
    public function validate($form, $data, $group = null)
    {
        // Validate conversion edited via the backend
        if (Factory::getApplication()->isClient('administrator'))
        {
            return parent::validate($form, $data, $group);
        }

        // Make sure we have a valid Form data
        if (!isset($data['cf']) || empty($data['cf']))
        {
            throw new Exception('No submission data found');
        }

        // Make sure we have a valid Form ID passed
        if (!isset($data['cf']['form_id']) || !$formid = (int) $data['cf']['form_id'])
        {
            throw new Exception('Form ID is either missing or invalid');
        }

        // Let the user manipulate the post data before saved into the database.
        // @todo - Move PHP Scripts logic to a separate plugin.
        $payload = ['post' => &$data['cf']];
        Form::runPHPScript($formid, 'formprocess', $payload);

        // Get form from payload or load a new instance
        $form = isset($payload['form']) ? $payload['form'] : Form::load($formid);

        $error_message = null;

        // Reset: the listeners repopulate it on every submission.
        $this->hideable_field_keys = [];

        $result = Factory::getApplication()->triggerEvent('onConvertFormsSubmissionValidate', [&$data['cf'], &$error_message, $form, $this]);

        if (in_array(false, $result, true))
        {
            throw new \Exception(is_null($error_message) ? 'Error' : $error_message);
        }

        // Honeypot check @deprecated 
        if (isset($data['cf']['hnpt']) && !empty($data['cf']['hnpt']))
        {
            ConvertForms\Spam\Helper::error(Text::_('COM_CONVERTFORMS_VALIDATION_HONEYPOT_ERROR'), 'honeypot_v1');
        }

        // Run form validations
        // Run this by listening to the onConvertFormsSubmissionValidate event
        ConvertForms\Validation\Helper::validate($form, $data);

        // Make sure the right form is loaded
        if (is_null($form['id']))
        {
            throw new Exception('Unknown Form');
        }

        // Initialize the object that is going to be saved in the database
        $newData = [
            'form_id'     => $formid,
            'campaign_id' => $this->getCampaignId($form),
            'state'       => isset($form['params']['submission_state']) ? $form['params']['submission_state'] : 1
        ];

        $this->ignored_field_keys = $this->getIgnoredFieldKeys($data);

        // Let's validate submitted data
        foreach ($form['fields'] as $key => $form_field)
        {
            $field_name  = isset($form_field['name']) ? $form_field['name'] : null;
            $field_class = FieldsHelper::getFieldClass($form_field['type'], $form_field, $data);

            // Skip unknown/removed field types instead of fataling
            if (!$field_class)
            {
                continue;
            }

            $user_value  = (!is_null($field_name) && isset($data['cf'][$field_name])) ? $data['cf'][$field_name] : null;

            $field_key = isset($form_field['key']) ? (string) $form_field['key'] : '';

            // Skip validation only for fields the server accepted as hidden by conditional logic.
            if ($field_key !== '' && in_array($field_key, $this->ignored_field_keys, true))
            {
                continue;
            }

            // Validate and Filter user value. If an error occurs the submission aborts with an exception shown in the form
            $field_class->validate($user_value);

            // Skip unknown fields or fields with an empty value
            if (!$field_name || $user_value == '')
            {
                continue;
            }

            $newData['params'][$field_name] = $user_value;
        }

        return $newData;
    }

    /**
     *  Resolves which field keys may skip validation for this submission.
     *
     *  The browser posts overrides={"ignore":[...]} listing the fields conditional logic hid. That list is a
     *  claim from an unauthenticated request, so it is intersected with the keys the plugins reported
     *  as hideable. Keys outside that set are dropped and the field is validated normally.
     *
     *  @param   array  $data  The request data
     *
     *  @return  array  Field keys as strings
     */
    private function getIgnoredFieldKeys($data)
    {
        // Input::getArray() hands us an array for overrides[]=x, which json_decode() would reject with a TypeError.
        if (!isset($data['overrides']) || !is_string($data['overrides']))
        {
            return [];
        }

        $overrides = json_decode($data['overrides'], true);

        if (!is_array($overrides) || !isset($overrides['ignore']) || !is_array($overrides['ignore']))
        {
            return [];
        }

        $claimed = [];

        foreach ($overrides['ignore'] as $key)
        {
            // is_scalar() would let true through as "1" and match the field with key 1.
            if (is_string($key) || is_int($key))
            {
                $claimed[] = (string) $key;
            }
        }

        if (!$claimed)
        {
            return [];
        }

        return array_values(array_unique(array_intersect($claimed, $this->hideable_field_keys)));
    }

    /**
     *  Lets a plugin declare which of this form's fields it is able to hide.
     *
     *  Called by onConvertFormsSubmissionValidate listeners. Nothing declaring, such as a disabled
     *  conditional logic plugin, leaves the set empty and rejects every claim.
     *
     *  @param   array  $keys  Field keys
     *
     *  @return  void
     */
    public function addHideableFields($keys)
    {
        foreach ((array) $keys as $key)
        {
            if (is_string($key) || is_int($key))
            {
                $this->hideable_field_keys[] = (string) $key;
            }
        }
    }

    /**
     *  Create a new conversion based on the post data.
     *
     *  @return  object     The new conversion row object
     */ 
    public function createConversion($data)
    {
        PluginHelper::importPlugin('convertforms');
        PluginHelper::importPlugin('convertformstools');
        PluginHelper::importPlugin('system');

        // Validate data
        $data = $this->validate(null, $data);

        /** 
         * This event is rather useful for the following reasons:
         * 
         * 1. It allows us to make modifications to the submission after it passes the validation checks.
         * 2. It allows us to access and modify submission properties such as 'state' and 'form_id'. Eg: Store all submissions unpublished by default.
         * 
         * We may support this event in the PHP Scripts section too.
         */
        Factory::getApplication()->triggerEvent('onConvertFormsSubmissionBeforeSave', [&$data]);

        $form_data = Form::load($data['form_id']);

        $submission = null;

        // If we are not saving the data to the database, mock the submission in order to be used as expected.
        if (isset($form_data['params']['save_data_to_db']) && $form_data['params']['save_data_to_db'] == '0')
        {
            $submission = (object) array_merge($form_data, $data);
            $submission->id = 'unsaved_submission_' . uniqid();
            $submission->persisted = false;
            $submission->user_id = 0;

            // Request scoped and not a column: consumed by the Smart Tags rendered for this submission.
            $submission->hidden_field_keys = $this->ignored_field_keys;

            $submission->created = Factory::getDate()->toSql();
            $submission->modified = Factory::getDbo()->getNullDate();

            // Move uploads whose folder holds submission Smart Tags (eg {submission.id}) to their
            // final destination, resolved against the mocked submission ID.
            if (DeferredUploads::any())
            {
                DeferredUploads::finalize($submission, $data['params']);
                $submission->params = $data['params'];
            }

            $this->prepareItem($submission);

            PluginHelper::importPlugin('convertforms');
            PluginHelper::importPlugin('convertformstools');
            
            Factory::getApplication()->triggerEvent('onConvertFormsConversionAfterSave', [$submission, $this, 1]);
        }
        else
        {
            $params = $data['params'];

            // JSON_UNESCAPED_UNICODE encodes multibyte unicode characters literally.
            // Without: Τάσος => \u03a4\u03ac\u03c3\u03bf\u03c2
            // With:    Τάσος => Τάσος
            $data['params'] = json_encode($data['params'], JSON_UNESCAPED_UNICODE);

            // Everything seems fine. Let's save data to the database.
            if (!$this->save($data))
            {
                throw new Exception($this->getError());
            }

            /**
             * Move uploads whose folder holds submission Smart Tags (eg {submission.id}) to their
             * final destination, now that the submission ID exists. This runs before getItem() on
             * purpose: the submission below is then built from the final file paths, so Smart Tags,
             * email notifications, and the backend all point to the moved files with no extra syncing.
             */
            if (DeferredUploads::any())
            {
                $id = (int) $this->getState($this->getName() . '.id');

                DeferredUploads::finalize($this->getItem($id), $params);

                // Persist the final paths. Table::check() is skipped deliberately — it would stamp
                // the `modified` date on a submission that was just created.
                $table = $this->getTable();
                $table->load($id);
                $table->params = json_encode($params, JSON_UNESCAPED_UNICODE);
                $table->store();
            }

            $submission = $this->getItem();
            $submission->persisted = true;

            // Request scoped and not a column: consumed by the Smart Tags rendered for this submission.
            $submission->hidden_field_keys = $this->ignored_field_keys;
        }

        // Run user's PHP script after the form has been processed, stored into the database and all addons have run.
        // @todo - Move PHP Scripts logic into a separate plugin.
        $payload = ['submission' => &$submission];
        Form::runPHPScript($data['form_id'], 'afterformsubmission', $payload);

        /**
         * Why this event was created:
         * 
         * - Due to the fact that we cannot hook into "onConvertFormsSubmissionAfterSave" and manipulate the $submission object.
         *   If we try to do so, the updated $submission wont find its way to the other listeners hooked to this event.
         * 
         * When does this event run:
         * 
         * - Right after the submission has been saved to the database.
         * - Before other plugins hook to run their own code after the submission has been processed.
         * 
         * When it should be used:
         * 
         * - When we want to modify the submission object just before other listeners hook into "onConvertFormsSubmissionAfterSave"
         *   such as Email Notifications so they will get the updated $submission object.
         * 
         * Example Use Case:
         * 
         * - If we want to rename the uploaded files and use add the submission id to the filename, we must listen to this event,
         *   update the $submission object, rename the file, update the database and emails will use the updated file data.
         */
        Factory::getApplication()->triggerEvent('onConvertFormsSubmissionAfterSavePrepare', [&$submission]);

        /**
         * When does this event run:
         * 
         * - At the end of the submission process, after the $submission object has been finalized and its ready to be given
         *   out to plugins/code that hook to this event.
         * 
         * When it should be used:
         * 
         * - We dont want to modify the $submission data and plan to execute our code after the form has been submitted
         *   successfully.
         * 
         * Example Use Case:
         * - Send Email Notifications
         * - Submit data to Campaigns
         */
        Factory::getApplication()->triggerEvent('onConvertFormsSubmissionAfterSave', [&$submission]);
        
        return $submission;
    }

    /**
     *  Create a new conversion based on the post data.
     *
     *  @return  object     The new conversion row object
     */ 
    public function storeSpamSubmission($data)
    {
        // Get form from payload or load a new instance
        // $form_data = Form::load($data['form_id']);
        // if (isset($form_data['params']['save_data_to_db']) && $form_data['params']['save_data_to_db'] == '0')

        $form_id = (int) $data['cf']['form_id'];

        $form = Form::load($form_id);

        // Initialize the object that is going to be saved in the database
        $newData = [
            'form_id'     => $form_id,
            'campaign_id' => $this->getCampaignId($form),
            'state'       => 3,
           // 'params'      => $data['cf']
        ];
        

        // JSON_UNESCAPED_UNICODE encodes multibyte unicode characters literally. 
        // Without: Τάσος => \u03a4\u03ac\u03c3\u03bf\u03c2
        // With:    Τάσος => Τάσος
        $data['params'] = json_encode($data['params'], JSON_UNESCAPED_UNICODE);

        var_dump($data);
        die();

        // // Initialize the object that is going to be saved in the database
        // $newData = [
        //     'form_id'     => $formid,
        //     'campaign_id' => (int) $form['params']['campaign'],
        //     'state'       => isset($form['params']['submission_state']) ? $form['params']['submission_state'] : 1
        // ];

        // Everything seems fine. Let's save data to the database.
        if (!$this->save($data))
        {
            throw new Exception($this->getError());
        }

        return $this->getItem();
    }

    /**
     * Get a conversion item
     *
     * @param   interger  $pk       The conversion row primary key
     * @param   bool      $prepare  Whether to prepare the submission fields.
     *
     * @return  object              The conversion object
     */
    public function getItem($pk = null, $prepare = true)
    {
        if (!$item = parent::getItem($pk))
        {
            return;
        }

        $this->prepareItem($item, $prepare);

        return $item;
    }

    private function getCampaignId($form)
    {
        return isset($form['params']['campaign']) ? (int) $form['params']['campaign'] : 0;
    }

    /**
     * Prepares the submission item.
     * 
     * @param   object  $item     The submission item.
     * @param   bool    $prepare  Whether to prepare the submission fields.
     * 
     * @return  void
     */
    protected function prepareItem(&$item, $prepare = true)
    {
        PluginHelper::importPlugin('convertformstools');

        $app = Factory::getApplication();
        $input = $app->getInput();

        // The following check verifies we are in the submission editing page in the backend.
        // In this page, we have date pickers for the Date Created and Date Modified that already applies the timezone offset.
        // If we apply the offset again, the date will be wrong.
        // We cannot rely only on the $app->isClient('administrator') because the timezone must be applied after the submission is saved which is required by the PDF submission addon.
        $skipTimezone = $app->isClient('administrator') && $input->get('option') == 'com_convertforms' && $input->get('view') == 'conversion' && $input->get('layout') == 'edit' && empty($input->get('task'));

        if (!$skipTimezone)
        {
            $item->created  = Helper::formatDate($item->created);
            $item->modified = Helper::formatDate($item->modified);
        }

        if ($item->user_id)
        {
            $item->user_name = Factory::getUser($item->user_id)->name;
        }

        // Load Form & Campaign Model
        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_convertforms/models', 'ConvertFormsModel');

        $modelForm = BaseDatabaseModel::getInstance('Form', 'ConvertFormsModel', ['ignore_request' => true]);

        $item->form = $modelForm->getItem($item->form_id);
        $item->campaign = Helper::getCampaign($item->campaign_id);

        // On J4 we get infinite loop if we inject the prepared fields to $data.
        // Thus, we don't prepare the submission on this method in order to not pollute the $data object. 
        // Instead we use the preprocessForm() method with a cloned object.
        if ($prepare)
        {
            $this->prepare($item);
        }

        return $item;
    }

    /**
     * Prepares the submission by adding the submitted fields values.
     * 
     * @param   object  $submission
     * 
     * @return  void
     */
    public function prepare(&$submission)
    {
        if (!$submission || is_null($submission->id))
        {
            return;
        }

        // Note: Form may be already available in the $submission object.
        if (!$form = Form::load($submission->form_id, false, true))
        {
            return;
        }

        $fields = [];
        $allFields = [];

        // Make sure we're manipualating an array.
        $submission_params = array_change_key_case((array) $submission->params);

        foreach ($form['fields'] as $field)
        {
            // Carried into the field instance so File Upload can build download links
            // bound to this form, field and submission. Every consumer that renders a
            // value goes through prepared_fields, so this one injection covers them all.
            $field['form_id'] = $submission->form_id;
            $field['submission_id'] = $submission->id;

            // Make sure the type of field is valid
            if (!$class = FieldsHelper::getFieldClass($field['type'], $field))
            {
                continue;
            }

            $field_name = '';
            $submitted_value = '';

            if (isset($field['name']))
            {
                $field_name = strtolower($field['name']);
                $submitted_value = isset($submission_params[$field_name]) ? $submission_params[$field_name] : '';
            }

            $data_ = (object) [
                'class' => $class,
                'submitted_value' => $submitted_value
            ];

            $allFields[] = $data_;

            // Skip fields with no name like reCAPTCHA, HTML e.t.c
            if (!$field_name)
            {
                continue;
            }

            $data_->label = isset($field['label']) && !empty($field['label']) ? Text::_($field['label']) : $field['name'];
            $data_->options = new Registry($field);
            $data_->value = $class->prepareValue($submitted_value);
            $data_->value_html = $class->prepareValueHTML($submitted_value);
            $data_->value_raw = $class->prepareRawValue($submitted_value);

            $fields[$field_name] = $data_;
        }

        // @todo - Stop using 'prepare_fields' property and switch over to the standard 'fields' property.
        $submission->prepared_fields = $fields;
        $submission->fields_ = $allFields;
    }
}
