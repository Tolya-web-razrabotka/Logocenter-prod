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
use Joomla\CMS\Uri\Uri;
use Tassos\Framework\Helpers\Widgets\GalleryManager as GalleryManagerHelper;
use Tassos\Framework\Image;
use Tassos\Framework\FileUpload;
use Tassos\Framework\Security\FileReference;
use Joomla\CMS\Language\Text;

/**
 *  Gallery Manager
 *
 * @deprecated  Superseded by GalleryManager2. Retained only so that installs
 *              still running an older Advanced Custom Fields keep working: the
 *              framework ships inside several products and only ever moves
 *              forward, so a site can be on a new framework with an ACF that
 *              still calls this widget. Do not add features here; security
 *              fixes only. Remove once no supported release references it.
 */
class GalleryManager extends Widget
{
	/**
	 * Widget default options
	 *
	 * @var array
	 */
	protected $widget_options = [
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
		'allowed_file_types' => '.jpg, .jpeg, .png, .gif, .webp, image/*, image/webp',

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
		'widget' => 'GalleryManager'
	];

	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->prepare();
	}

	private function prepare()
	{
		// Set gallery items
		$this->options['gallery_items'] = is_array($this->options['value']) ? $this->options['value'] : [];

		// Set css class for readonly state
		if ($this->options['readonly'])
		{
			$this->options['css_class'] .= ' readonly';
		}

		// Adds a css class when the gallery contains at least one item
		if (count($this->options['gallery_items']))
		{
			$this->options['css_class'] .= ' dz-has-items';
		}

		// Get the Open AI API key
		$this->options['openai_api_key'] = \Tassos\Framework\Helpers\Settings::getValue('openai_api_key');

		// Load translation strings
        Text::script('NR_GALLERY_MANAGER_CONFIRM_REGENERATE_IMAGES');
        Text::script('NR_GALLERY_MANAGER_CONFIRM_DELETE_ALL_SELECTED');
        Text::script('NR_GALLERY_MANAGER_CONFIRM_DELETE_ALL');
        Text::script('NR_GALLERY_MANAGER_CONFIRM_DELETE');
        Text::script('NR_GALLERY_MANAGER_FILE_MISSING');
        Text::script('NR_GALLERY_MANAGER_REACHED_FILES_LIMIT');
        Text::script('NR_GENERATE_IMAGE_DESC_TO_ALL_IMAGES_CONFIRM');

		$this->prepareTags();
	}

	private function prepareTags()
	{
		if (!is_array($this->options['gallery_items']))
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

	private function getSettings($context)
	{
		// Make sure we have a valid context
		if (!$context)
		{
			return false;
		}

		$field_data = [];

		$input = Factory::getApplication()->input;

		if ($context === 'default')
		{
			// Make sure we have a valid field id
			if (!$field_id = $input->getInt('field_id'))
			{
				$this->exitWithMessage('NR_GALLERY_MANAGER_FIELD_ID_ERROR');
			}

			if (!$field_data = \Tassos\Framework\Helpers\CustomField::getData($field_id))
			{
				$this->exitWithMessage('NR_GALLERY_MANAGER_INVALID_FIELD_DATA');
			}
		}
		else if ($context === 'module')
		{
			// Make sure we have a valid item id
			if (!$item_id = $input->getInt('item_id'))
			{
				$this->exitWithMessage('NR_GALLERY_MANAGER_ITEM_ID_ERROR');
			}
			
			if (!$field_data = \Tassos\Framework\Helpers\Module::getData($item_id))
			{
				$this->exitWithMessage('NR_GALLERY_MANAGER_INVALID_FIELD_DATA');
			}

			$field_data->set('style', $field_data->get('provider', 'grid'));
		}

		return $field_data;
	}

	/**
	 * The upload task called by the AJAX hanler
	 *
	 * @return  void
	 */
	protected function ajax_upload()
	{
		$input = Factory::getApplication()->input;

		// Make sure we have a valid context
		if (!$context = $input->get('context'))
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_CONTEXT_ERROR');
		}

		if (!$field_data = $this->getSettings($context))
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_INVALID_FIELD_DATA');
		}

		// get the media uploader file data, values are passed when we upload a file using the Media Uploader
		$media_uploader_file_data = [
			'is_media_uploader_file' => $input->get('media_uploader', false) == '1',
			'media_uploader_filename' => $input->getString('media_uploader_filename', '')
		];

		$style = $field_data->get('style', 'grid');

		$uploadSettings = [
			'allow_unsafe' => false,
			'allowed_types' => $field_data->get('allowed_file_types', $this->widget_options['allowed_file_types']),
			'style' => $style
		];

		// Add watermark
		if ($field_data->get('watermark.type', 'disabled') !== 'disabled')
		{
			$uploadSettings['watermark'] = (array) $field_data->get('watermark', []);
			$uploadSettings['watermark']['image'] = !empty($uploadSettings['watermark']['image']) ? explode('#', JPATH_SITE . DIRECTORY_SEPARATOR . $uploadSettings['watermark']['image'])[0] : null;
			$uploadSettings['watermark']['apply_on_thumbnails'] = $field_data->get('watermark.apply_on_thumbnails', false) === '1';
		}

		$field_data_array = $field_data->toArray();

		$resize_method = $field_data->get('resize_method', 'crop');
		$thumb_height = $field_data->get('thumb_height', null);

		switch ($style)
		{
			case 'slideshow':
				if (isset($field_data_array['slideshow_thumb_height']))
				{
					$thumb_height = $field_data_array['slideshow_thumb_height'];
				}

				if ($slideshow_resize_method = $field_data->get('slideshow_resize_method'))
				{
					$resize_method = $slideshow_resize_method;
				}
				break;
			case 'masonry':
				$thumb_height = null;
				break;
			case 'zjustified':
			case 'justified':
				$thumb_height = $field_data->get('justified_item_height', 200);
				break;
		}

		// resize image settings
		$resizeSettings = [
			'thumb_height' => $thumb_height,
			'thumb_resize_method' => $resize_method,
			
			// TODO: Remove this line when ACF is also updated, so we don't rely on this to resize the original image
			'original_image_resize' => false,

			'original_image_resize_width' => $field_data->get('original_image_resize_width'),
			'original_image_resize_height' => $field_data->get('original_image_resize_height')
		];

		/**
		 * For backwards compatibility.
		 * 
		 * TODO: Update this code block to not rely on "original_image_resize" to resize original image when removed from ACF.
		 */
		$resize_original_image_setting_value = $field_data->get('original_image_resize', null);
		if ($style === 'slideshow' && ($resizeSettings['original_image_resize_width'] || $resizeSettings['original_image_resize_height']))
		{
			$resize_original_image_setting_value = true;
		}

		if ($resize_original_image_setting_value)
		{
			$resizeSettings['original_image_resize_height'] = $style === 'slideshow' ? $resizeSettings['original_image_resize_height'] : null;
			$resizeSettings['original_image_resize'] = $style === 'slideshow' ? true : $resize_original_image_setting_value;
		}
		else if (is_null($resize_original_image_setting_value) && ($resizeSettings['original_image_resize_width'] || $resizeSettings['original_image_resize_height']))
		{
			$resizeSettings['original_image_resize'] = true;
		}
		if (!$resizeSettings['original_image_resize'])
		{
			$resizeSettings['original_image_resize_width'] = null;
			$resizeSettings['original_image_resize_height'] = null;
		}
		
		if (in_array($style, ['grid', 'masonry', 'slideshow']))
		{
			$resizeSettings['thumb_width'] = $field_data->get('thumb_width');
			
			$slideshow_thumb_width = $field_data->get('slideshow_thumb_width');
			if (!is_null($slideshow_thumb_width) && $style === 'slideshow')
			{
				$resizeSettings['thumb_width'] = $slideshow_thumb_width;
			}
		}

		$field_id = $input->getInt('field_id');
		$item_id  = $input->getInt('item_id');

		// Read $_FILES, validate, persist to the gallery temp folder.
		try
		{
			$source = FileUpload::uploadFromRequest(new Registry([
				'upload_folder' => GalleryManagerHelper::getFullTempFolder($field_id, $item_id),
				'upload_types'  => $uploadSettings['allowed_types'],
				'allow_unsafe'  => $uploadSettings['allow_unsafe'],
			]));
		}
		catch (\Throwable $e)
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_ERROR_INVALID_FILE');
		}

		// Build derivatives (original, thumbnail, optional watermark) from the uploaded source.
		if (!$uploaded_filenames = GalleryManagerHelper::upload($source, $uploadSettings, $media_uploader_file_data, $resizeSettings))
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_ERROR_CANNOT_UPLOAD_FILE');
		}

		// Issue encrypted tokens for secure file references
		$tokens = ['source_token' => '', 'original_token' => '', 'thumbnail_token' => ''];
		try
		{
			$tempFolder = GalleryManagerHelper::getFullTempFolder($field_id, $item_id);

			$tokenContext = [
				'field_id' => (string) (int) $field_id,
				'item_id'  => (string) (int) $item_id,
				'context'  => (string) $context,
			];

			foreach (['source', 'original', 'thumbnail'] as $key)
			{
				if (!empty($uploaded_filenames[$key]))
				{
					$fullPath = GalleryManagerHelper::toAbsolute($uploaded_filenames[$key]);
					$tokens[$key . '_token'] = FileUpload::issueToken($fullPath, $tempFolder, $tokenContext);
				}
			}
		}
		catch (\Throwable $e) {}

		echo json_encode(array_merge([
			'source' => $uploaded_filenames['source'],
			'original' => $uploaded_filenames['original'],
			'thumbnail' => $uploaded_filenames['thumbnail'],
			'is_media_uploader_file' => $media_uploader_file_data['is_media_uploader_file']
		], $tokens));
	}

	/**
	 * The delete task called by the AJAX hanlder
	 *
	 * @return void
	 */
	protected function ajax_delete()
	{
		$input = Factory::getApplication()->input;

		if (!$context = $input->get('context'))
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_CONTEXT_ERROR');
		}

		if (!$field_data = $this->getSettings($context))
		{
			$this->exitWithMessage('NR_GALLERY_MANAGER_INVALID_FIELD_DATA');
		}

		$field_id = $input->getInt('field_id');
		$item_id = $input->getInt('item_id');

		// Build allowed bases from field config
		$allowedBases = [GalleryManagerHelper::getFullTempFolder($field_id, $item_id)];

		// Add the storage folder based on field configuration
		$allowedBases = array_merge($allowedBases, $this->getStorageBases($field_data, $context, $item_id));

		$expectedContext = [
			'field_id' => (string) (int) $field_id,
			'item_id'  => (string) (int) $item_id,
			'context'  => (string) $context,
		];

		// Resolve and delete each path via FileUpload::resolveReferenceWithContext.
		// Tokens get their embedded context verified against $expectedContext;
		// legacy plaintext paths skip the context check and are gated only by
		// $allowedBases.
		$pathKeys = ['source', 'original', 'thumbnail'];
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
	private function getStorageBases($field_data, $context, $item_id)
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

	/**
	 * Resolve a client-supplied read input (source/original image) to an existing
	 * file confined to $allowedBases and matching $expectedContext. Invalid or
	 * out-of-base references resolve to false.
	 *
	 * @param   string  $reference        The client-supplied reference
	 * @param   array   $allowedBases     Absolute base directories it must sit within
	 * @param   array   $expectedContext  The field/item/context the token must match
	 *
	 * @return  string|false  The resolved absolute path, or false when unresolved
	 */
	private function resolveReadInput($reference, array $allowedBases, array $expectedContext)
	{
		if (!$reference)
		{
			return false;
		}

		try
		{
			return FileUpload::resolveReferenceWithContext($reference, $allowedBases, $expectedContext);
		}
		catch (\Throwable $e)
		{
			return false;
		}
	}

	/**
	 * This task allows us to regenerate the images.
	 *
	 * @return void
	 */
	protected function ajax_regenerate_images()
	{
		$input = Factory::getApplication()->input;

		// Make sure we have a valid context
		if (!$context = $input->get('context'))
		{
			echo json_encode(['success' => false, 'message' => Text::_('NR_GALLERY_MANAGER_CONTEXT_ERROR')]);
			die();
		}

		if (!$field_data = $this->getSettings($context))
		{
			echo json_encode(['success' => false, 'message' => Text::_('NR_GALLERY_MANAGER_INVALID_FIELD_DATA')]);
			die();
		}

		$field_id = $input->getInt('field_id');
		$item_id = $input->getInt('item_id');

		// Bases the client-supplied paths must stay within:
		// the gallery temp folder plus the field's configured storage folder(s).
		$allowedBases = [GalleryManagerHelper::getFullTempFolder($field_id, $item_id)];
		$allowedBases = array_merge($allowedBases, $this->getStorageBases($field_data, $context, $item_id));

		$expectedContext = [
			'field_id' => (string) (int) $field_id,
			'item_id'  => (string) (int) $item_id,
			'context'  => (string) $context,
		];

		$field_data_array = $field_data->toArray();

		$style = $field_data->get('style', 'grid');
		
		$resize_method = $field_data->get('resize_method', 'crop');
		$thumb_height = $field_data->get('thumb_height', null);

		switch ($style)
		{
			case 'slideshow':
				if (isset($field_data_array['slideshow_thumb_height']))
				{
					$thumb_height = $field_data_array['slideshow_thumb_height'];
				}

				if ($slideshow_resize_method = $field_data->get('slideshow_resize_method'))
				{
					$resize_method = $slideshow_resize_method;
				}
				break;
			case 'masonry':
				$thumb_height = null;
				break;
			case 'zjustified':
			case 'justified':
				$thumb_height = $field_data->get('justified_item_height', 200);
				break;
		}

		$resizeSettings = [
			'thumb_height' => $thumb_height,
			'thumb_resize_method' => $resize_method
		];

		if (in_array($style, ['grid', 'masonry', 'slideshow']))
		{
			$resizeSettings['thumb_width'] = $field_data->get('thumb_width');
			
			$slideshow_thumb_width = $field_data->get('slideshow_thumb_width');
			if (!is_null($slideshow_thumb_width) && $style === 'slideshow')
			{
				$resizeSettings['thumb_width'] = $slideshow_thumb_width;
			}
		}

		// TODO: Remove this line when ACF is also updated, so we don't rely on this to resize the original image
		$original_image_resize = false;

		$original_image_resize_width = $field_data->get('original_image_resize_width');
		$original_image_resize_height = $field_data->get('original_image_resize_height');

		/**
		 * For backwards compatibility.
		 * 
		 * TODO: Update this code block to not rely on "original_image_resize" to resize original image when removed from ACF.
		 */
		$resize_original_image_setting_value = $field_data->get('original_image_resize', null);
		if ($style === 'slideshow' && ($original_image_resize_width || $original_image_resize_height))
		{
			$resize_original_image_setting_value = true;
		}

		if ($resize_original_image_setting_value)
		{
			$original_image_resize_height = $style === 'slideshow' ? $original_image_resize_height : null;
			$original_image_resize = $style === 'slideshow' ? true : $resize_original_image_setting_value;
		}
		else if (is_null($resize_original_image_setting_value) && ($original_image_resize_width || $original_image_resize_height))
		{
			$original_image_resize = true;
		}
		if (!$original_image_resize)
		{
			$original_image_resize_width = null;
			$original_image_resize_height = null;
		}

		$watermarkSettings = [];
		// Add watermark
		if ($field_data->get('watermark.type', 'disabled') !== 'disabled')
		{
			$watermarkSettings = (array) $field_data->get('watermark', []);
			$watermarkSettings['image'] = !empty($watermarkSettings['image']) ? explode('#', JPATH_SITE . DIRECTORY_SEPARATOR . $watermarkSettings['image'])[0] : null;
			$watermarkSettings['apply_on_thumbnails'] = $field_data->get('watermark.apply_on_thumbnails', false) === '1';
		}
		$watermarkEnabled = isset($watermarkSettings['type']) && $watermarkSettings['type'] !== 'disabled';
		$thumbnailWatermarkEnabled = isset($watermarkSettings['type']) && $watermarkSettings['type'] !== 'disabled' && $watermarkSettings['apply_on_thumbnails'];

		$items = $input->get('items', null, 'ARRAY');
		$items = json_decode($items[0], true);

		// Parse all images
		if (is_array($items) && count($items))
		{
			foreach ($items as &$item)
			{
				$sourceImage = isset($item['source']) ? $item['source'] : '';
				$originalImage = isset($item['original']) ? $item['original'] : '';
				$thumbnailImage = isset($item['thumbnail']) ? $item['thumbnail'] : '';

				// Thumbnail is a write target that may not exist yet; confine it
				// lexically (false => skip the thumbnail steps below).
				$thumbnailImagePath = FileReference::landsInside($thumbnailImage, $allowedBases, false);

				// Source and original are read inputs; resolve them as existing
				// files within $allowedBases (out-of-base values => missing).
				$sourceImagePath = $this->resolveReadInput($sourceImage, $allowedBases, $expectedContext);
				$sourceImageExists = $sourceImagePath && file_exists($sourceImagePath);

				$originalImagePath = $this->resolveReadInput($originalImage, $allowedBases, $expectedContext);
				$originalImageExists = $originalImagePath && file_exists($originalImagePath);

				// If source image does not exist, watermark is enabled, create it by clothing the original image
				if (!$sourceImageExists && $watermarkEnabled && $originalImage && file_exists($originalImagePath))
				{
					// Create source from original image
					$sourceImagePath = \Tassos\Framework\File::copy($originalImagePath, $originalImagePath, false, true);
					$sourceImageExists = true;

					// Modify the database entry and add "source" image to item
					// We just need the relative path to file
					$_sourceImagePath = str_replace(JPATH_ROOT . DIRECTORY_SEPARATOR, '', $sourceImagePath);
					$item['source'] = $_sourceImagePath;
					$_originalImagePath = str_replace(JPATH_ROOT . DIRECTORY_SEPARATOR, '', $originalImagePath);
					GalleryManagerHelper::setItemFieldSource($item_id, $field_id, $_sourceImagePath, $_originalImagePath);
				}
				
				if (!$originalImageExists)
				{
					continue;
				}

				if (!$sourceImageExists)
				{
					$sourceImagePath = $originalImagePath;
				}

				/**
				 * Handle original image.
				 */
				// Generate original image by using the source image
				if ($original_image_resize_width && $original_image_resize_height)
				{
					$originalImagePath = Image::resize($sourceImagePath, $original_image_resize_width, $original_image_resize_height, Image::DEFAULT_JPEG_QUALITY, 'crop', $originalImagePath);
				}
				else if ($original_image_resize_width)
				{
					$originalImagePath = Image::resizeAndKeepAspectRatio($sourceImagePath, $original_image_resize_width, Image::DEFAULT_JPEG_QUALITY, $originalImagePath);
				}
				else if ($original_image_resize_height)
				{
					$originalImagePath = Image::resizeByHeight($sourceImagePath, $original_image_resize_height, $originalImagePath, Image::DEFAULT_JPEG_QUALITY);
				}

				$originalImageSourcePath = $originalImagePath;
				
				if ($watermarkEnabled)
				{
					$payload = array_merge($watermarkSettings, ['source' => $sourceImagePath, 'destination' => $originalImagePath]);
					Image::applyWatermark($payload);
				}

				/**
				 * Handle thumbnail image.
				 */
				// Only regenerate when the thumbnail destination was confined.
				if ($thumbnailImagePath)
				{
					// Generate thumbnail image by using the source image
					GalleryManagerHelper::generateThumbnail($sourceImagePath, $thumbnailImagePath, $resizeSettings, null, false);

					// Apply watermark to thumbnail image
					if ($watermarkEnabled && $thumbnailWatermarkEnabled)
					{
						$payload = array_merge($watermarkSettings, ['source' => $thumbnailImagePath]);
						Image::applyWatermark($payload);
					}
				}
			}
		}

		echo json_encode(['success' => true, 'message' => Text::_('NR_GALLERY_MANAGER_IMAGES_REGENERATED'), 'items' => $items]);
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

	public function ajax_generate_caption()
	{
        set_time_limit(300); // 5 Minutes
        ini_set('memory_limit', '-1');
		
		$fullURL = Uri::root() . Factory::getApplication()->input->getString('image');

		$imageToText = new \Tassos\Framework\AI\TextGeneration\ImageToText();
		$generated = $imageToText->generate($fullURL);

		echo json_encode($generated);
	}
}