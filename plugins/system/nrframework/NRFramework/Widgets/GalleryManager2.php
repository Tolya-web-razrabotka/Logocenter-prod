<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework\Widgets;

defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Tassos\Framework\Helpers\Widgets\GalleryManager2 as GalleryManagerHelper;
use Tassos\Framework\Security\SafePath;
use Tassos\Framework\FileUpload;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;

/**
 *  Gallery Manager
 */
class GalleryManager2 extends Widget
{
	/**
	 * Widget default options
	 *
	 * @var array
	 */
	protected $widget_options = [
		// The uploaded images
		'value' => [],
		
		// The input name
		'name' => '',

		// Context of the field
		// module, default
		'context' => 'default',

		// The field ID associated to this Gallery Manager, used to retrieve the field settings on AJAX actions
		'field_id' => null,

		// The item ID associated to this Gallery Manager, used to retrieve the field settings on AJAX actions
		'item_id' => null,

		/**
		 * Max file size in MB.
		 * 
		 * Defults to 0 (no limit).
		 */
		'max_file_size' => 0,

		/**
		 * How many files we can upload.
		 * 
		 * Defaults to 0 (no limit).
		 */
		'limit_files' => 0,

		// Allowed upload file types
		'allowed_file_types' => '.jpg, .jpeg, .png, .webp, image/*',

		/**
		 * Original Image
		 */
		// Original image resize width
		'original_image_resize_width' => null,

		// Original image resize height
		'original_image_resize_height' => null,

		/**
		 * Thumbnails
		 */
		// Thumbnails width
		'thumb_width' => null,

		// Thumbnails height
		'thumb_height' => null,

		// Thumbnails resize method (crop, stretch, fit)
		'thumb_resize_method' => 'crop',

		// The list of tags already available for this gallery
		'tags' => [],

		// Open AI API Key
		'openai_api_key' => '',

		// The widget name
		'widget' => 'GalleryManager2'
	];

	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->prepare();
	}

	private function prepare()
	{
		$this->includeTempFiles();
		
		// Set css class for readonly state
		if ($this->options['readonly'])
		{
			$this->options['css_class'] .= ' readonly';
		}

		// Adds a css class when the gallery contains at least one item
		if (is_array($this->options['value']) && count($this->options['value']))
		{
			$this->options['css_class'] .= ' dz-has-items';
		}

		// Get the Open AI API key
		$this->options['openai_api_key'] = \Tassos\Framework\Helpers\Settings::getValue('openai_api_key');

		// Load translation strings
        Text::script('NR_GALLERY_MANAGER_CONFIRM_DELETE_ALL_SELECTED');
        Text::script('NR_GALLERY_MANAGER_CONFIRM_DELETE_ALL');
        Text::script('NR_GALLERY_MANAGER_CONFIRM_DELETE');
        Text::script('NR_GALLERY_MANAGER_FILE_MISSING');
        Text::script('NR_GALLERY_MANAGER_REACHED_FILES_LIMIT');
        Text::script('NR_GENERATE_IMAGE_DESC_TO_ALL_IMAGES_CONFIRM');

		$this->prepareTags();
	}

	/**
	 * Find and include temp files in the gallery.
	 * 
	 * @return  void
	 */
	private function includeTempFiles()
	{
		$ds = DIRECTORY_SEPARATOR;

		$tempFolder = GalleryManagerHelper::getFullTempFolder($this->options['context'], $this->options['field_id'], $this->options['item_id']);

		if (!is_dir($tempFolder))
		{
			return;
		}

		$files = Folder::files($tempFolder, '.', false, false, ['.', '..', 'index.html', 'index.php']);

		if (!$files)
		{
			return;
		}

		// Normalize field_id/item_id through int so missing/null/'' all become
		// '0' and match the server-side $input->getInt() values used in
		// ajax_delete's expected_context.
		$tokenContext = [
			'field_id' => (string) (int) $this->options['field_id'],
			'item_id'  => (string) (int) $this->options['item_id'],
			'context'  => (string) $this->options['context'],
		];

		foreach ($files as $filename)
		{
			$fullPath = $tempFolder . $ds . $filename;
			$token = '';

			try
			{
				$token = FileUpload::issueToken($fullPath, $tempFolder, $tokenContext);
			}
			catch (\Throwable $e) {}

			$this->options['value'][] = [
                // Stored form: JPATH_ROOT-relative, or absolute off-root.
                'source' => GalleryManagerHelper::upload($fullPath),
                'source_token' => $token,
                'original' => '',
                'original_token' => '',
                'exists' => true,
                'caption' => '',
                'thumbnail' => '',
                'thumbnail_token' => '',
                'slideshow' => '',
                'slideshow_token' => '',
                'alt' => '',
                'tags' => json_encode([]),
				'temp' => true
			];
		}
	}

	private function prepareTags()
	{
		if (!is_array($this->options['value']))
		{
			return;
		}

		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select([$db->quoteName('id'), $db->quoteName('title')])
			->from($db->quoteName('#__tags'))
			->where($db->quoteName('published') . ' = 1')
			->where($db->quoteName('level') . ' > 0');

		$db->setQuery($query);
		$tags = $db->loadAssocList('id', 'title');

		$this->options['tags'] = $tags;
	}

	/**
	 * The upload task called by the AJAX hanler
	 *
	 * @return  void
	 */
	protected function ajax_upload()
	{
        // Increase memory size and execution time to prevent PHP errors on datasets > 20K
        set_time_limit(300); // 5 Minutes
        ini_set('memory_limit', '-1');
		
		$input = Factory::getApplication()->input;

		$random_suffix = $input->get('random_suffix', 'false') === 'true' ? true : false;

		// Make sure we have a valid context
		if (!$context = $input->get('context'))
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_CONTEXT_ERROR');
		}

		$field_id = $input->getInt('field_id');
		$item_id  = $input->getInt('item_id');

		$tempFolder = GalleryManagerHelper::getFullTempFolder($context, $field_id, $item_id);
		\Tassos\Framework\File::createDirs($tempFolder);

		try
		{
			$absolute = FileUpload::uploadFromRequest(new Registry([
				'upload_folder'   => $tempFolder,
				'upload_types'    => $this->widget_options['allowed_file_types'],
				'allow_unsafe'    => false,
				'filename_suffix' => $random_suffix,
			]));

			if (!$source = GalleryManagerHelper::upload($absolute))
			{
				$this->exitWithMessage('NR_GALLERY_MANAGER_ERROR_CANNOT_UPLOAD_FILE');
			}
			// Issue an encrypted token for secure file reference
			$source_token = '';

			// $source is JPATH_ROOT-relative, or absolute off-root.
			$fullPath = SafePath::toAbsolute($source);

			$tokenContext = [
				'field_id' => (string) (int) $field_id,
				'item_id'  => (string) (int) $item_id,
				'context'  => (string) $context,
			];

			$source_token = FileUpload::issueToken($fullPath, $tempFolder, $tokenContext);

			echo json_encode([
				'source_token' => $source_token,
				'source' => $source
			]);
		}
		catch (\Throwable $e)
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_ERROR_CANNOT_UPLOAD_FILE');
		}
	}

	/**
	 * The delete task called by the AJAX hanlder
	 *
	 * @return void
	 */
	protected function ajax_delete()
	{
        // Increase memory size and execution time to prevent PHP errors on datasets > 20K
        set_time_limit(300); // 5 Minutes
        ini_set('memory_limit', '-1');

		$input = Factory::getApplication()->input;

		if (!$context = $input->get('context'))
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_CONTEXT_ERROR');
		}

		$field_id = $input->getInt('field_id');
		$item_id = $input->getInt('item_id');

		if (!$field_data = GalleryManagerHelper::getSettings($context, $field_id, $item_id))
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_INVALID_FIELD_DATA');
		}

		// Build allowed bases from field config
		$allowedBases = [];

		try
		{
			$tempFolder = GalleryManagerHelper::getFullTempFolder($context, $field_id, $item_id);
			if (is_dir($tempFolder))
			{
				$allowedBases[] = $tempFolder;
			}
		}
		catch (\Throwable $e) {}

		// Add the storage folder based on field configuration
		$allowedBases = array_merge($allowedBases, self::getStorageBases($field_data, $context, $item_id));

		$expectedContext = [
			'field_id' => (string) (int) $field_id,
			'item_id'  => (string) (int) $item_id,
			'context'  => (string) $context,
		];

		// Resolve and delete each path via FileUpload::resolveReferenceWithContext.
		// Tokens get their embedded context verified against $expectedContext;
		// legacy plaintext paths skip the context check and are gated only by
		// $allowedBase
		$pathKeys = ['source', 'slideshow', 'original', 'thumbnail'];
		$deleted = [];

		foreach ($pathKeys as $key)
		{
			$tokenParam = $key . '_token';
			$token = $input->getString($tokenParam, '');
			$path = $input->getString($key, '');
			$reference = $token ?: $path;

			if (!$reference)
			{
				$deleted[$key] = false;
				continue;
			}

			try
			{
				$absolutePath = FileUpload::resolveReferenceWithContext($reference, $allowedBases, $expectedContext);
				$deleted[$key] = \Tassos\Framework\File::delete($absolutePath);
			}
			catch (\Throwable $e)
			{
				$deleted[$key] = false;
			}
		}

		echo json_encode(['success' => $deleted]);
	}
	
	/**
	 * Compute the allowed storage base directories from the gallery field configuration.
	 *
	 * @param   Registry  $field_data  The field configuration
	 * @param   string    $context     The context ('default' or 'module')
	 * @param   int       $item_id     The item ID
	 *
	 * @return  array  Array of absolute paths
	 */
	private static function getStorageBases($field_data, $context, $item_id)
	{
		$ds = DIRECTORY_SEPARATOR;
		$bases = [];

		if ($context === 'module')
		{
			$base = JPATH_ROOT . $ds . 'media' . $ds . 'spgallery';
			$bases[] = \Joomla\Filesystem\Path::clean($item_id ? $base . $ds . $item_id : $base);
		}
		else
		{
			$folderType = $field_data->get('upload_folder_type', 'auto');

			if ($folderType === 'custom')
			{
				$uploadFolder = $field_data->get('upload_folder', 'media/acfgallery');
				if ($uploadFolder)
				{
					// Strip smart tags to get the static base directory
					$baseFolder = preg_replace('/\{.*$/', '', $uploadFolder);
					$baseFolder = rtrim(trim(ltrim($baseFolder, '/\\'), '/\\'), '/\\');
					if ($baseFolder)
					{
						$bases[] = \Joomla\Filesystem\Path::clean(JPATH_ROOT . $ds . $baseFolder);
					}
				}
			}
			else
			{
				$bases[] = \Joomla\Filesystem\Path::clean(JPATH_ROOT . $ds . 'media' . $ds . 'acfgallery');
			}
		}

		return $bases;
	}

	public function ajax_generate_caption()
	{
        set_time_limit(300); // 5 Minutes
        ini_set('memory_limit', '-1');
		
		$fullURL = Uri::root() . Factory::getApplication()->input->getString('image');

		$imageToText = new \Tassos\Framework\AI\TextGeneration\ImageToText();
		$generated = $imageToText->generate($fullURL);

		echo json_encode($generated);
	}

	/**
	 * Exits the page with given message.
	 * 
	 * @param   string  $translation_string
	 * 
	 * @return  void
	 */
	private function exitWithMessage($translation_string)
	{
		http_response_code('500');
		die(Text::_($translation_string));
	}
}