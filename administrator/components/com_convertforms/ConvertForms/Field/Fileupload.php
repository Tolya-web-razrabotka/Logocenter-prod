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

namespace ConvertForms\Field;

defined('_JEXEC') or die('Restricted access');

use ConvertForms\Helper;
use ConvertForms\FileStorage;
use Tassos\Framework\File;
use Tassos\Framework\Mimes;
use Tassos\Framework\SmartTags\SmartTags;
use Tassos\Framework\FileUpload as FrameworkFileUpload;
use Tassos\Framework\Security\SafePath;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Utility\Utility;

class FileUpload extends \ConvertForms\Field
{
	/**
	 * The default upload folder
	 *
	 * @var string
	 */
	protected $default_upload_folder = '/media/com_convertforms/uploads/{randomid}_{file.basename}';

	/**
	 * Filename template used inside the external storage base when the field names no
	 * subfolder of its own.
	 *
	 * @var string
	 */
	protected $default_external_subpath = '{randomid}_{file.basename}';

	/**
	 *  Remove common fields from the form rendering
	 *
	 *  @var  mixed
	 */
	protected $excludeFields = [
		'size',
		'value',
		'browserautocomplete',
		'placeholder',
		'inputcssclass'
	];

	/**
	 *  Set field object
	 *
	 *  @param  mixed  $field  Object or Array Field options
	 */
	public function setField($field)
	{
		parent::setField($field);

		$field = $this->field;
		$field->label_id = 'label-' . $field->input_id;

		if (!isset($field->limit_files)) 
		{
			$field->limit_files = 1;
		}

		if (!isset($field->upload_types) || empty($field->upload_types)) 
		{
			$field->upload_types = 'image/*';
		}

		// Accept multiple values
		if ((int) $field->limit_files != 1)
		{
			$field->input_name .= '[]';
		}
	
		return $this;
	}

	/**
	 *  Validate field value
	 *
	 *  @param   mixed  $value           The field's value to validate
	 *
	 *  @return  mixed                   True on success, throws an exception on error
	 */
	public function validate(&$value)
	{
		$is_required 	   = $this->field->get('required');
		$max_files_allowed = $this->field->get('limit_files', 1);
		$allowed_types     = $this->field->get('upload_types');

		$upload_folder_type = $this->field->get('upload_folder_type', 'auto');
		$is_external        = false;
		$base               = JPATH_ROOT;

		// Absent on every form saved before this setting existed, which is why it is read
		// with a default rather than assumed present.
		if ($this->field->get('upload_storage', 'public') === 'private')
		{
			

			// Either no base is configured, or this is the Free edition, where the block
			// above is stripped out. Falling through would drop the file into the web
			// root, publishing what the form was set up to keep private. Deliberately
			// outside the Pro markers.
			if (!$is_external)
			{
				// The visitor gets the generic destination failure: the real reason is a
				// setting they cannot see, and naming it tells anyone who can reach the form
				// how the component is configured. The administrator gets it in the log.
				//
				// Worded to hold in the Free edition too, where the field can only have been
				// configured elsewhere.
				Helper::log(
					'Convert Forms: the "' . $this->field->get('name') . '" field stores uploads outside the site,'
						. ' but external storage is not available, so the upload was refused.'
						. ' Set the Uploads Base Path in Convert Forms Options, under Security.',
					'error'
				);

				$this->throwError(Text::_('COM_CONVERTFORMS_UPLOAD_INVALID_DESTINATION'));
			}
		}

		if (!$is_external)
		{
			$upload_folder = $upload_folder_type == 'auto' ? $this->default_upload_folder : $this->field->get('upload_folder', $this->default_upload_folder);
		}

		// Remove null and empty values
		$value = is_array($value) ? $value : (array) $value;
		$value = array_filter($value);

		// We expect a not empty array
		if ($is_required && empty($value))
		{
			$this->throwError(Text::_('COM_CONVERTFORMS_FIELD_REQUIRED'));
		}

		// Do we have the correct number of files?
		if ($max_files_allowed > 0 && count($value) > $max_files_allowed)
		{
			$this->throwError(Text::sprintf('COM_CONVERTFORMS_UPLOAD_MAX_FILES_LIMIT', $max_files_allowed));
		}

		// Validate file paths
		foreach ($value as $key => &$source_file)
		{
			// Resolve the submitted reference (file_token or legacy base64 path)
			// to an absolute path inside the temp folder. The framework's
			// FileUpload resolves tokens first and falls back to legacy decoding.
			try
			{
				$source_file = FrameworkFileUpload::resolveReference($source_file, [Helper::getTempPath()]);
			}
			catch (\Throwable $th)
			{
				$this->throwError(Text::_('COM_CONVERTFORMS_UPLOAD_FILE_IS_MISSING'));
			}

			$source_file_info = File::pathinfo($source_file);
			$source_basename = $source_file_info['basename'];

			if (!file_exists($source_file))
			{
				$this->throwError(Text::sprintf('COM_CONVERTFORMS_UPLOAD_FILE_IS_MISSING', $source_basename));
			}

			// Although the file is already checked during upload, make another sanity check here. 
			File::checkMimeOrDie($allowed_types, ['tmp_name' => $source_file]);

			// Remove the random ID added to file's name during upload process
			$source_file_info['filename'] = preg_replace('#cf_(.*?)_(.*?)#', '', $source_file_info['filename']);
			$source_file_info['basename'] = preg_replace('#cf_(.*?)_(.*?)#', '', $source_basename);
			$source_file_info['index'] = $key + 1;

			// Replace Smart Tags in the upload folder value
			// Unfortunately at this time, we don't have submitted data available yet and so, we can't replace field-based Smart Tags. See @todo below.
			$SmartTags = new SmartTags();
			$SmartTags->add($source_file_info, 'file.');
			$destination_file = $base . DIRECTORY_SEPARATOR . ltrim($SmartTags->replace($upload_folder), '/\\');

			// If the upload folder has no filename component, append the source filename.
			// Trailing slash is checked explicitly because pathinfo() reports a phantom extension
			// when a literal Smart Tag containing a dot is the last segment (eg. {field.category}/).
			$is_folder_path = substr($destination_file, -1) === DIRECTORY_SEPARATOR || substr($destination_file, -1) === '/';
			$destination_file_info = File::pathinfo(rtrim($destination_file, '/' . DIRECTORY_SEPARATOR));
			if ($is_folder_path || !isset($destination_file_info['extension']))
			{
				$destination_file = implode(DIRECTORY_SEPARATOR, [$destination_file_info['dirname'], $destination_file_info['basename'], $source_basename]);
			}

			// The folder template carries Smart Tags whose values come from the visitor,
			// so the resolved destination is re-checked here. File::move() would refuse it
			// too, but only after the whole submission, and with an opaque message.
			if (strpos($destination_file, '{') === false && !SafePath::isWithinLexical($destination_file, $base))
			{
				$this->throwError(Text::_('COM_CONVERTFORMS_UPLOAD_INVALID_DESTINATION'));
			}

			// The upload folder may contain submission-level Smart Tags such as {submission.id}. These
			// cannot be resolved before the submission has been saved and has an ID, so the move is
			// deferred: the conversion model finalizes it right after the submission is created, via
			// ConvertForms\DeferredUploads. The validated value keeps the temp path until then.
			if (strpos($destination_file, '{submission.') !== false)
			{
				\ConvertForms\DeferredUploads::defer([
					'field'      => $this,
					'field_name' => $this->field->get('name'),
					'key'        => $key,
					'source'     => $source_file,
					'template'   => $destination_file,
					'base'       => $base,
					'external'   => $is_external
				]);

				continue;
			}

			// Move uploaded file to the destination folder after the form passes all validation checks.
			// Thus, if an error is triggered by another field, the file will remain in the temp folder and the user will be able to re-submit the form.
			$this->app->registerEvent('onConvertFormsSubmissionBeforeSave', function(&$data) use ($key, $source_file, $destination_file, $base, $is_external)
			{
				try
				{
					set_time_limit(300); // 5 Minutes
					ini_set('memory_limit', '-1');

					// get the data
					$tmpData = $data->getArgument('0');

					// Resolve field-based Smart Tags now that the submitted data is available.
					// @todo We need to prepare $data with ConvertFormsModelConversion->prepare() and pass it down to Submission::replaceSmartTags() in order for submitted values to be prepared with each field prepare() method.
					$destination_file = $this->resolveRuntimeTags($destination_file, $tmpData['params']);

					// The tags just resolved carry submitted values, so this is the first
					// point the final destination can be checked.
					if (!SafePath::isWithinLexical($destination_file, $base))
					{
						$this->throwError(Text::_('COM_CONVERTFORMS_UPLOAD_INVALID_DESTINATION'));
					}

					// Move uploaded file from the temp folder to the destination folder.
					$destination_file = File::move($source_file, $destination_file, false, false, $is_external ? [$base] : []);

					// Add field name to the tmpData array
					$tmpData['field_name'] = $this->field->get('name');

					

					// Give a chance to manipulate the file with a hook.
					// We can move the file to another folder, rename it, resize it or even uploaded it to a cloud storage service.
					// The path may sit outside JPATH_ROOT. Whatever a listener sets goes through
					// toStoredValue() below, so moving it back inside the site still stores a
					// site-relative value.
					$this->app->triggerEvent('onConvertFormsFileUpload', [&$destination_file, $tmpData]);

					// A reference the reader can confine: site-relative inside the site, marked
					// and base-relative outside it.
					$destination_file = FileStorage::toStoredValue($destination_file);

					// Update fields' value
					$tmpData['params'][$this->field->get('name')][$key] = $destination_file;

					// Set back the new value to $data object
					$data->setArgument(0, $tmpData);

				} catch (\Throwable $th)
				{
					// The message names the absolute destination, and this one is echoed
					// straight back to an anonymous submitter.
					Helper::log(
						'Convert Forms: the "' . $this->field->get('name') . '" field could not store an upload. '
							. $th->getMessage(),
						'error'
					);

					$this->throwError(Text::_('COM_CONVERTFORMS_UPLOAD_INVALID_DESTINATION'));
				}
			});
		}
	}

	/**
	 * Resolve the runtime Smart Tags of an upload destination in a single pass: field-based
	 * tags from the submitted values and, when the created submission is available,
	 * submission-based tags such as {submission.id}.
	 *
	 * Only scalar submission properties are exposed on purpose. Routing through
	 * ConvertForms\SmartTags::replace() here would fire onConvertFormsGetSubmissionSmartTags
	 * listeners (eg. PDF generation) before the submission holds its final file paths.
	 *
	 * @param   string       $destination  The destination path holding unresolved tags.
	 * @param   array        $params       The submitted values.
	 * @param   object|null  $submission   The created submission, when available.
	 *
	 * @return  string
	 */
	public function resolveRuntimeTags($destination, $params, $submission = null)
	{
		$SmartTags = new SmartTags();
		$SmartTags->add($params, 'field.');

		if (!is_null($submission))
		{
			$SmartTags->add(array_filter(get_object_vars($submission), 'is_scalar'), 'submission.');
		}

		return $SmartTags->replace($destination);
	}

	

	/**
	 * Event fired before the field options form is rendered in the backend
	 *
	 * @param  object $form
	 *
	 * @return void
	 */
	protected function onBeforeRenderOptionsForm($form)
	{
		// Set the maximum upload size limit to the respective options form field
		$max_upload_size_str = HTMLHelper::_('number.bytes', Utility::getMaxUploadSize());
		$max_upload_size_int = (int) $max_upload_size_str;

		$form->setFieldAttribute('max_file_size', 'max', $max_upload_size_int);

		$desc_lang_str = $form->getFieldAttribute('max_file_size', 'description');
		$desc = Text::sprintf($desc_lang_str, $max_upload_size_str);
		$form->setFieldAttribute('max_file_size', 'description', $desc);
	}

	/**
	 * Ajax method triggered by System Plugin during file upload.
	 *
	 * @param	string	$form_id
	 * @param	string	$field_key
	 *
	 * @return	array
	 */
	public function onAjax($form_id, $field_key)
	{
		$action = $this->app->input->get('action', null, '');
		if (!$action)
		{
			$this->uploadDie('COM_CONVERTFORMS_UPLOAD_ERROR_INVALID_ACTION');
		}

		$allowedActions = ['upload', 'delete'];
		if (!in_array($action, $allowedActions))
		{
			$this->uploadDie('COM_CONVERTFORMS_UPLOAD_ERROR_INVALID_ACTION');
		}

		switch ($action)
		{
			case 'upload':
				$this->actionUpload($form_id, $field_key);
				return;

			case 'delete':
				$this->actionDelete($form_id, $field_key);
				return;
		}
	}

	private function actionUpload($form_id, $field_key)
	{
		if (!$form_id || !$field_key)
		{
			$this->uploadDie('COM_CONVERTFORMS_UPLOAD_ERROR');
		}

		if (!$settings = \ConvertForms\Form::getFieldSettingsByKey($form_id, $field_key))
		{
			$this->uploadDie('COM_CONVERTFORMS_UPLOAD_ERROR_INVALID_FIELD');
		}

		FrameworkFileUpload::handleUpload(new Registry([
			'upload_folder'   => Helper::getTempPath(),
			'upload_types'    => $settings->get('upload_types'),
			'allow_unsafe'    => (bool) $settings->get('allow_unsafe', false),
			'filename_prefix' => 'cf_',
			'token_context'   => [
				'form_id'   => (string) $form_id,
				'field_key' => (string) $field_key,
			],
			// The staged file sits in the public temp folder until the submission moves
			// it, so neither its URL nor its path is handed to the browser. Our Dropzone
			// client reads file_token only.
			'expose_url'      => false,
			'emit_legacy'     => false,
		]));
	}

	private function actionDelete($form_id, $field_key)
	{
		FrameworkFileUpload::handleDelete(new Registry([
			'allowed_bases'    => [Helper::getTempPath()],
			'expected_context' => [
				'form_id'   => (string) $form_id,
				'field_key' => (string) $field_key,
			],
		]));
	}

	/**
	 * DropzoneJS detects errors based on the response error code.
	 *
	 * @param  string $error_message
	 *
	 * @return void
	 */
	private function uploadDie($error_message)
	{
		http_response_code('500');
		die(Text::_($error_message));
	}

	/**
	 * Prepare value to be displayed to the user as plain text
	 *
	 * @param  mixed $value
	 *
	 * @return string
	 */
	public function prepareValue($value)
	{
		if (!$value)
		{
			return;
		}

		$value = (array) $value;

		foreach ($value as $key => $stored)
		{
			$value[$key] = $this->buildFileURL($stored, $key);
		}

		return implode(', ', array_filter($value));
	}

	/**
	 * Prepare value to be displayed to the user as HTML/text
	 *
	 * @param  mixed $value
	 *
	 * @return string
	 */
	public function prepareValueHTML($value)
	{
		if (!$value)
		{
			return;
		}

		$links = (array) $value;
		$value = '';

		foreach ($links as $key => $stored)
		{
			$url = $this->buildFileURL($stored, $key);

			// An external URL carries no filename, so the label comes from the stored path.
			// Both escaped: filenames reach this from an upload.
			$label = File::pathinfo(FileStorage::isExternal($stored) ? FileStorage::toRelativePath($stored) : $url)['basename'];

			if ($label === '')
			{
				continue;
			}

			// A file with no URL is still a file the submission holds. Named rather than
			// dropped, which would read as though nothing was ever uploaded.
			$value .= $url === ''
				? '<div>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>'
				: '<div><a download href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
					. htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></div>';
		}

		return '<div class="cf-links">' . $value . '</div>';
	}

	/**
	 * Where Private + Auto puts a file: one folder per form, inside the base.
	 *
	 * The form id is resolved rather than left as a Smart Tag, because neither of the
	 * two replacement passes the destination goes through registers form tags.
	 *
	 * @return  string
	 */
	protected function defaultPrivateSubpath()
	{
		$form_id = (int) $this->field->get('form_id');

		if (!$form_id && isset($this->data['cf']['form_id']))
		{
			$form_id = (int) $this->data['cf']['form_id'];
		}

		return ($form_id ? $form_id . '/' : '') . $this->default_external_subpath;
	}

	/**
	 * Prepare value for exports, webhooks and the API.
	 *
	 * In-site files keep their stored path, so existing exports are byte for byte what
	 * they were. External files get the download URL: the marker must not leave the
	 * database, and would be useless to a consumer anyway.
	 *
	 * Always the signed front-end link, however the export was started. An administrator
	 * URL carries a session token that is dead by the time anyone opens the spreadsheet.
	 *
	 * @param  mixed $value
	 *
	 * @return mixed
	 */
	public function prepareRawValue($value)
	{
		if (!$value)
		{
			return $value;
		}

		$values = (array) $value;

		foreach ($values as $key => $stored)
		{
			$values[$key] = FileStorage::toOutwardValue($stored, $this->tokenContext());
		}

		return $values;
	}

	/**
	 * Build the URL a stored file is reached through.
	 *
	 * Files inside the site keep their direct URL. External files are served by
	 * Convert Forms: in the administrator through an ACL-checked task, and on the site
	 * through a signed download link. The administrator variant matters for more than
	 * tidiness: the submissions list renders every row, so minting a long-lived
	 * bearer token per file there would put hundreds of them into a page that only
	 * authenticated staff can see anyway.
	 *
	 * @param   string  $stored  The stored submission value
	 * @param   int     $index   Which file within the field
	 *
	 * @return  string  The URL, or '' when the file cannot be resolved
	 */
	protected function buildFileURL($stored, $index = 0)
	{
		if (!FileStorage::isExternal($stored))
		{
			return Helper::absURL($stored);
		}

		$app = \Joomla\CMS\Factory::getApplication();

		if ($app->isClient('administrator') && $submission_id = $this->field->get('submission_id'))
		{
			return FileStorage::adminDownloadUrl($submission_id, $this->field->get('name'), $index);
		}

		return FileStorage::downloadUrl($stored, $this->tokenContext());
	}

	/**
	 * What a download token records about where the file came from.
	 *
	 * The endpoint reads it back to find the field again and apply its Download Access
	 * level, so the two callers have to agree on it exactly.
	 *
	 * @return  array
	 */
	protected function tokenContext()
	{
		return [
			FileStorage::CTX_FORM  => (string) $this->field->get('form_id'),
			FileStorage::CTX_FIELD => (string) $this->field->get('key')
		];
	}

	/**
	 *  Display a text before the form options
	 *
	 * 	@param   object  $form
	 * 
	 *  @return  string  The text to display
	 */
	protected function getOptionsFormHeader($form)
	{
		$html = '';

		$temp_folder = Helper::getTempPath();

		if (!@is_writable($temp_folder))
		{
			$html .= '
				<div class="alert alert-danger">
					' . Text::sprintf('COM_CONVERTFORMS_FILEUPLOAD_TEMP_FOLDER_NOT_WRITABLE', $temp_folder) . '
				</div>
			';
		}

		// Check if the Fileinfo PHP extension is installed required to detect the mime type.
		if (!extension_loaded('fileinfo') || !function_exists('mime_content_type'))
		{
			$html .= '
				<div class="alert alert-danger">
					' . Text::sprintf('COM_CONVERTFORMS_FILEUPLOAD_MIME_CONTENT_TYPE_MISSING') . '
				</div>
			';
		}

		return $html;
	}

	/**
	 * Event fired during form saving in the backend to help us validate user options.
	 *
	 * @param  object	$model			The Form Model
	 * @param  array	$form_data		The form data to be saved
	 * @param  array	$field_options	The field data
	 *
	 * @return bool
	 */
	public function onBeforeFormSave($model, $form_data, &$field_options)
	{
		$allowedFileTypes = array_filter(array_map('trim', explode(',', $field_options['upload_types'])));

		// Ensure the user has entered at least 1 file extension
		if (empty($allowedFileTypes))
		{
            $model->setError(Text::sprintf('COM_CONVERTFORMS_FILEUPLOAD_EMPTY_FILE_TYPES_NOT_ALLOWED'));
			return false;
		}

		// We do not allow empty file extensions
		if (in_array('.', $allowedFileTypes))
		{
            $model->setError(Text::sprintf('COM_CONVERTFORMS_FILEUPLOAD_EMPTY_FILE_EXTENSION_NOT_ALLOWED'));
			return false;
		}

		return parent::onBeforeFormSave($model, $form_data, $field_options);
	}
}
