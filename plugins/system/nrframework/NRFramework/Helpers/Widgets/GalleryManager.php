<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework\Helpers\Widgets;

defined('_JEXEC') or die;

use Tassos\Framework\File;
use Tassos\Framework\Image;
use Tassos\Framework\Functions;
use Tassos\Framework\Security\SafePath;
use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File as JoomlaCMSFile;
use Joomla\Filesystem\Path;

/**
 * @deprecated  Superseded by GalleryManager2. Retained only so that installs
 *              still running an older Advanced Custom Fields keep working: the
 *              framework ships inside several products and only ever moves
 *              forward, so a site can be on a new framework with an ACF that
 *              still calls this class. Do not add features here; security fixes
 *              only. Remove once no supported release references it.
 */
class GalleryManager
{
	/**
	 * How long the files can stay in the temp folder.
	 * 
	 * After each save a clean up is run and all files older
	 * than this value in days are removed.
	 * 
	 * @var  int
	 */
	private static $temp_files_cleanup_days = 1;
	
	/**
	 * Process an already-uploaded source image: build derivatives (original,
	 * thumbnail), apply watermark if configured, and return the relative paths.
	 * Callers (e.g. \Tassos\Framework\FileUpload::uploadFromRequest) own the
	 * $_FILES → disk step and pass the resulting absolute path in.
	 *
	 * @param	string	$source						Absolute path of the uploaded source file
	 * @param	array	$upload_settings			The upload settings
	 * @param	array	$media_uploader_file_data	Media uploader related file settings
	 * @param   array   $resizeSettings				The resize settings
	 *
	 * @return	mixed	Array of relative paths on success, false on failure
	 */
	public static function upload($source, $upload_settings, $media_uploader_file_data, $resizeSettings)
	{
		if (!$source)
		{
			return false;
		}

		$source_file_path = $source;
		
		$ds = DIRECTORY_SEPARATOR;
		
		// If the file came from the Media Manager file and we are copying it, fix its filename
		if ($media_uploader_file_data['is_media_uploader_file'])
		{
			$media_uploader_file_data['media_uploader_filename'] = self::getFilePathFromMediaUploaderFile($media_uploader_file_data['media_uploader_filename']);
		}

		$source_image_relative = '';
		$original_image_relative = '';

		// Create source image by cloning the original image
		$original_image_extension = pathinfo($source, PATHINFO_EXTENSION);
		$original_image_destination = str_replace('.' . $original_image_extension, '_original.' . $original_image_extension, $source);

		// Thumbnail file name
		$thumb_image_destination = str_replace('.' . $original_image_extension, '_thumb.' . $original_image_extension, $source);

		// Check whether to copy and resize the original image
		if ($resizeSettings['original_image_resize'])
		{
			if ($resizeSettings['original_image_resize_width'] && $resizeSettings['original_image_resize_height'])
			{
				$original_image_full = Image::resize($source, $resizeSettings['original_image_resize_width'], $resizeSettings['original_image_resize_height'], Image::DEFAULT_JPEG_QUALITY, 'crop', $original_image_destination, true);
			}
			else if ($resizeSettings['original_image_resize_width'])
			{
				$original_image_full = Image::resizeAndKeepAspectRatio($source, $resizeSettings['original_image_resize_width'], Image::DEFAULT_JPEG_QUALITY, $original_image_destination, true);
			}
			else if ($resizeSettings['original_image_resize_height'])
			{
				$original_image_full = Image::resizeByHeight($source, $resizeSettings['original_image_resize_height'], $original_image_destination, Image::DEFAULT_JPEG_QUALITY, true);
			}
			
			$original_image_relative = str_replace(JPATH_ROOT . $ds, '', $original_image_full);

			// Delete raw image as not needed
			JoomlaCMSFile::delete($source);
		}
		else
		{
			// Original image must always be cloned by the resized original image
			$original_image_full = File::move($source, $original_image_destination);
			$original_image_relative = str_replace(JPATH_ROOT . $ds, '', $original_image_full);
		}

		// Generate thumbnails
		if (!$thumb_data = self::generateThumbnail($original_image_full, $thumb_image_destination, $resizeSettings))
		{
			return false;
		}

		// Absolute path of the generated thumbnail. It always lives in the same
		// temp folder as the source, so deriving it from $thumb_image_destination
		// keeps upload() independent of where that temp folder happens to be.
		$thumbnail_full = implode($ds, [dirname($thumb_image_destination), $thumb_data['resized_filename']]);

		// Add watermark image
		if (isset($upload_settings['watermark']['type']) && $upload_settings['watermark']['type'] !== 'disabled')
		{
			// Clone source image from original image and hash it
			$source_image_full = File::copy($original_image_full, $source_file_path, false, true);
			$source_image_relative = str_replace(JPATH_ROOT . $ds, '', $source_image_full);
			
			// Add watermark to original image
			$payload = array_merge($upload_settings['watermark'], [
				'source' => $original_image_full
			]);
			\Tassos\Framework\Image::applyWatermark($payload);

			if (isset($upload_settings['watermark']['apply_on_thumbnails']) && $upload_settings['watermark']['apply_on_thumbnails'])
			{
				// Add watermark to the thumbnail
				$payload = array_merge($upload_settings['watermark'], [
					'source' => $thumbnail_full
				]);
				\Tassos\Framework\Image::applyWatermark($payload);
			}
		}

		return [
			'source' => $source_image_relative ? $source_image_relative : '',
			'original' => $original_image_relative,
			'thumbnail' => str_replace(JPATH_ROOT . $ds, '', $thumbnail_full)
		];
	}

	/**
	 * Moves all given `tmp` items over to the destination folder.
	 * 
	 * @param   array   $value
	 * @param   object  $field
	 * @param   string  $destination_folder
	 * 
	 * @return  void
	 */
	public static function moveTempItemsToDestination($value, $field, $destination_folder)
	{
		if (!$destination_folder)
		{
			return;
		}

		// Create destination folder if missing
		if (!File::createDirs($destination_folder))
		{
			return;
		}

		// Make field params use Registry
		if (!$field->fieldparams instanceof Registry)
		{
			$field->fieldparams = new Registry($field->fieldparams);
		}

		/**
		 * Prepare the items for backwards compatibility
		 */
		$items = is_string($value) ? json_decode($value, true) ?? [['value' => $value]] : $value;

		$items = isset($items['items']) ? $items['items'] : $items;

		if (isset($items['value']))
		{
			$items = [$items];
		}

		$limit_files = (int) $field->fieldparams->get('limit_files', 0);

		// Handle single file
		if ($limit_files === 1 && is_array($items))
		{
			$items = [reset($items)];
		}

		$ds = DIRECTORY_SEPARATOR;

		// Move all files from `tmp` folder over to the `upload folder`
		foreach ($items as $key => &$item)
		{
			/**
			 * Skip invalid files.
			 * 
			 * These "files" can appear when we try to move files
			 * over to the destination folder when the gallery manager
			 * is still working to upload queueed files.
			 * 
			 * Also skip any items that have no value.
			 */
			if ($key === 'ITEM_ID' || empty($item['thumbnail']))
			{
				continue;
			}
			
			$moved = false;

			// Ensure thumbnail in temp folder file exists
			$thumbnail_clean = pathinfo($item['thumbnail'], PATHINFO_BASENAME);
			$thumbnail_path = self::toAbsolute($item['thumbnail']);
			// Move thumbnail image
			if (self::isGalleryTempPath($item['thumbnail']) && file_exists($thumbnail_path))
			{
				// Move thumbnail
				$thumb = File::move($thumbnail_path, $destination_folder . $thumbnail_clean);

				// Update thumbnail file name
				$item['thumbnail'] = pathinfo($thumb, PATHINFO_BASENAME);

				$moved = true;
			}

			// Check if we have uploaded the full image as well and set it
			$image_clean = pathinfo($item['image'], PATHINFO_BASENAME);
			$image_path = self::toAbsolute($item['image']);
			// Move original image
			if (self::isGalleryTempPath($item['image']) && file_exists($image_path))
			{
				// Move image
				$image = File::move($image_path, $destination_folder . $image_clean);

				// Update image file name
				$item['image'] = pathinfo($image, PATHINFO_BASENAME);

				$moved = true;
			}

			// Ensure source in temp folder file exists
			$item['source'] = isset($item['source']) ? $item['source'] : '';

			// If source does not exist, create it from the original image, only if watermark is enabled
			if (!$item['source'] && $field->fieldparams->get('watermark.type', 'disabled') !== 'disabled')
			{
				// Create source from original image
				$source = File::copy($image_path, $image_path, false, true);

				// Update source file name
				$item['source'] = pathinfo($source, PATHINFO_BASENAME);
				
				$moved = true;
			}
			
			// Move source image
			$source_clean = pathinfo($item['source'], PATHINFO_BASENAME);
			$source_path = self::toAbsolute($item['source']);
			if (self::isGalleryTempPath($item['source']) && file_exists($source_path))
			{
				// Move source
				$thumb = File::move($source_path, $destination_folder . $source_clean);

				// Update source file name
				$item['source'] = pathinfo($thumb, PATHINFO_BASENAME);

				$moved = true;
			}

			if ($moved)
			{
				// Update destination path
				self::updateDestinationPath($item, $destination_folder);
			}
		}

		return $items;
	}

	/**
	 * Saves the tags for each item.
	 * 
	 * @param   array  $value
	 * 
	 * @return  array
	 */
	public static function saveItemTags($value = [])
	{
		if (!is_array($value))
		{
			return $value;
		}

		foreach ($value as &$item)
		{
			if (!isset($item['tags']) || !is_string($item['tags']))
			{
				$item['tags'] = [];
				continue;
			}

			if (!$itemTags = json_decode($item['tags'], true))
			{
				$item['tags'] = [];
				continue;
			}

			if (!is_array($itemTags))
			{
				$item['tags'] = [];
				continue;
			}

			if (!$itemTags)
			{
				$item['tags'] = [];
				continue;
			}

			// Make $itemTags an array of strings
			$itemTags = array_map(function($tag) {
				return (string) $tag;
			}, $itemTags);

			/**
			 * Creates the new tags in the #__tags table.
			 * 
			 * This returns an array of the new tag ids. If a tag isn't new (doesn't have #new# prefix), it will return 0 as its id.
			 * 
			 * We will now store the IDs returned as the tags for the item.
			 */
			$item['tags'] = self::createTagsFromField($itemTags);
		}

		return $value;
	}

    /**
     * Create any new tags by looking for #new# in the strings
     *
     * @param   array  $tags  Tags text array from the field
     *
     * @return  mixed   If successful, metadata with new tag titles replaced by tag ids. Otherwise false.
     *
     * @since   3.1
     */
    public static function createTagsFromField($tags)
    {
        if (empty($tags) || $tags[0] == '')
		{
            return;
        }

		// We will use the tags table to store them
		$tagTable = Factory::getApplication()->bootComponent('com_tags')->getMVCFactory()->createTable('Tag', 'Administrator');

		$newTags = [];

		foreach ($tags as $key => $tag)
		{
			// Remove the #new# prefix that identifies new tags
			$tagText = str_replace('#new#', '', $tag);

			if ($tagText === $tag)
			{
				$newTags[] = (int) $tag;
			}
			else
			{
				// Clear old data if exist
				$tagTable->reset();

				// Try to load the selected tag
				if ($tagTable->load(['title' => $tagText]))
				{
					$newTags[] = (int) $tagTable->id;
				}
				else
				{
					// Prepare tag data
					$tagTable->id          = 0;
					$tagTable->title       = $tagText;
					$tagTable->published   = 1;
					$tagTable->description = '';

					$tagTable->language = '*';
					$tagTable->access   = 1;

					// Make this item a child of the root tag
					$tagTable->setLocation($tagTable->getRootId(), 'last-child');

					// Try to store tag
					if ($tagTable->check())
					{
						// Assign the alias as path (autogenerated tags have always level 1)
						$tagTable->path = $tagTable->alias;

						if ($tagTable->store())
						{
							$newTags[] = (int) $tagTable->id;
						}
					}
				}
			}
		}

		// At this point $newTags is an array of all tag ids
        return $newTags;
    }

	/**
	 * Updates the destination path for the image and its thumbnail to the final destination folder.
	 * 
	 * @param   array   $item
	 * @param   string  $destination_folder
	 * 
	 * @return  mixed
	 */
	private static function updateDestinationPath(&$item, $destination_folder)
	{
		$ds = DIRECTORY_SEPARATOR;

		// Ensure destination folder is a relative path
		$destination_folder = ltrim(rtrim(str_replace(JPATH_ROOT, '', $destination_folder), $ds), $ds);

		$item = array_merge($item, [
			'source' => !empty($item['source']) && !Functions::startsWith($item['source'], $destination_folder) ? implode($ds, [$destination_folder, $item['source']]) : $item['source'],
			'thumbnail' => !Functions::startsWith($item['thumbnail'], $destination_folder) ? implode($ds, [$destination_folder, $item['thumbnail']]) : $item['thumbnail'],
			'image' => !Functions::startsWith($item['image'], $destination_folder) ? implode($ds, [$destination_folder, $item['image']]) : $item['image']
		]);
	}

	/**
	 * Sets the custom field item id > field id value "source" to given source image path for the original image path
	 */
	public static function setItemFieldSource($item_id, $field_id, $sourceImagePath, $originalImagePath)
	{
		// Get "value" column from #__fields_values where item_id = $item_id and $field_id = $field_id
		$db = Factory::getDbo();

		$query = $db->getQuery(true)
			->select($db->qn('value'))
			->from($db->qn('#__fields_values'))
			->where($db->qn('item_id') . ' = ' . $db->q($item_id))
			->where($db->qn('field_id') . ' = ' . $db->q($field_id));

		$db->setQuery($query);

		$value = $db->loadResult();

		// If value is empty, return
		if (!$value)
		{
			return;
		}

		// Decode value
		$value = json_decode($value, true);

		// If value is empty, return
		if (!$value)
		{
			return;
		}

		// If value is not an array, return
		if (!is_array($value))
		{
			return;
		}

		// If value has no items, return
		if (!isset($value['items']))
		{
			return;
		}

		// Loop all items until we find a "image" = $originalImagePath and then set 'source' = $sourceImagePath
		foreach ($value['items'] as $key => &$item)
		{
			if ($item['image'] !== $originalImagePath)
			{
				continue;
			}

			$item['source'] = $sourceImagePath;
		}

		// Update value
		$query = $db->getQuery(true)
			->update($db->qn('#__fields_values'))
			->set($db->qn('value') . ' = ' . $db->q(json_encode($value)))
			->where($db->qn('item_id') . ' = ' . $db->q($item_id))
			->where($db->qn('field_id') . ' = ' . $db->q($field_id));

		$db->setQuery($query);

		$db->execute();
	}
	
	/**
	 * Media Uploader files look like: https://example.com/images/sampledata/parks/banner_cradle.png
	 * We remove the first part (https://example.com/images/) and keep the other part (relative path to image).
	 * 
	 * @param   string  $filename
	 * 
	 * @return  string
	 */
	private static function getFilePathFromMediaUploaderFile($filename)
	{
		$filenameArray = explode('images/', $filename, 2);
		unset($filenameArray[0]);
		$new_filepath = join($filenameArray);
		return 'images/' . $new_filepath;
	}

	/**
	 * Generates thumbnail
	 * 
	 * @param   string   $source			 	 Source image path.
	 * @param   string   $destination		 	 Destination image path.
	 * @param   array    $resizeSettings	 	 Resize Settings.
	 * @param   string   $destination_folder	 Destination folder.
	 * @param   boolean  $unique_filename		 Whether the thumbnails will have a unique filename.
	 * 
	 * @return  array
	 */
	public static function generateThumbnail($source = '', $destination = '', $resizeSettings = [], $destination_folder = null, $unique_filename = true)
	{
		if (!$destination)
		{
			$parts = pathinfo($source);
			$destination_folder = !is_null($destination_folder) ? $destination_folder : $parts['dirname'] . DIRECTORY_SEPARATOR;
			$destination = $destination_folder . $parts['filename'] . '_thumb.' . $parts['extension'];
		}

		$resized_image = null;

		$thumb_width = isset($resizeSettings['thumb_width']) ? (int) $resizeSettings['thumb_width'] : null;
		$thumb_height = isset($resizeSettings['thumb_height']) ? (int) $resizeSettings['thumb_height'] : null;

		// If thumbnail width is null, and we have item height set, we are resizing by height
		if (is_null($thumb_width) && $thumb_height && !is_null($thumb_height))
		{
			$resized_image = Image::resizeByHeight($source, $thumb_height, $destination, Image::DEFAULT_JPEG_QUALITY, $unique_filename, true, 'resize');
		}
		else
		{
			if (is_null($thumb_width) || !$thumb_width)
			{
				return;
			}

			/**
			 * If height is zero, then we suppose we want to keep aspect ratio.
			 * 
			 * Resize with width & height: If thumbnail height is not set
			 * Resize and keep aspect ratio: If thumbnail height is set
			 */
			$resized_image = $thumb_height && !is_null($thumb_height)
				?
				Image::resize($source, $thumb_width, $thumb_height, Image::DEFAULT_JPEG_QUALITY, $resizeSettings['thumb_resize_method'], $destination, $unique_filename, true, 'resize')
				:
				Image::resizeAndKeepAspectRatio($source, $thumb_width, Image::DEFAULT_JPEG_QUALITY, $destination, $unique_filename, true, 'resize');

		}
		
		if (!$resized_image)
		{
			return;
		}

		return [
			'filename' => basename($source),
			'resized_filename' => basename($resized_image)
		];
	}

	/**
	 * Deletes an uploaded files: source, original, and thumbnail.
	 *
	 * @param   string  $source			The source image path.
	 * @param   string  $original		The original image path.
	 * @param   string  $thumbnail		The thumbnail image path.
	 *
	 * @return  bool
	 */
	public static function deleteFile($source = null, $original = null, $thumbnail = null)
	{
		return [
			'deleted_source_image' => self::findAndDeleteFile($source),
			'deleted_original_image' => self::findAndDeleteFile($original),
			'deleted_thumbnail' => self::findAndDeleteFile($thumbnail)
		];
	}

	/**
	 * Deletes the file.
	 * 
	 * @param   string  $filepath
	 * 
	 * @return  mixed
	 */
	private static function findAndDeleteFile($filepath)
	{
		if (!$filepath)
		{
			return;
		}

		return \Tassos\Framework\File::delete($filepath);
	}

	/**
	 * Cleans the temp folders.
	 *
	 * Removes any file 1 day or older from the gallery temp area
	 * (`…/tassos/acf/gallery`).
	 *
	 * @return  void
	 */
	public static function clean()
	{
		// Match the base FileUpload::tempDir() writes to
		$tmp = rtrim(File::getTempFolder(), '/\\');

		$dir = $tmp . implode(DIRECTORY_SEPARATOR, ['', 'tassos', 'acf', 'gallery']);

		if (!is_dir($dir))
		{
			return;
		}

		self::cleanOldFiles($dir);

		// Prune the drained leaf and its emptied 'acf' parent (stop at 'tassos').
		\Tassos\Framework\FileUpload::pruneEmptyDir($dir);
		\Tassos\Framework\FileUpload::pruneEmptyDir(dirname($dir));
	}

	/**
	 * Recursively delete files older than the cleanup threshold under $dir.
	 *
	 * @param   string  $dir
	 *
	 * @return  void
	 */
	private static function cleanOldFiles($dir)
	{
		if (!is_dir($dir))
		{
			return;
		}

		$threshold = 60 * 60 * 24 * self::$temp_files_cleanup_days;

		foreach (array_diff(scandir($dir), ['.', '..', '.DS_Store', 'index.html']) as $entry)
		{
			$path = $dir . DIRECTORY_SEPARATOR . $entry;

			if (is_dir($path))
			{
				self::cleanOldFiles($path);
				\Tassos\Framework\FileUpload::pruneEmptyDir($path);
				continue;
			}

			if ((time() - (int) @filemtime($path)) >= $threshold)
			{
				@unlink($path);
			}
		}
	}

	/**
	 * Absolute temp directory uploads are written to before being moved to
	 * their final destination on save: <tmp_path>/tassos/acf/gallery/<item_id>/<field_id>
	 *
	 * This widget backs the ACF Gallery field exclusively, so the 'namespace' is
	 * always 'acf'
	 *
	 * @param   string|int  $field_id  The field id (optional).
	 * @param   string|int  $item_id   The owning item id (optional).
	 *
	 * @return  string
	 */
	public static function getFullTempFolder($field_id = '', $item_id = '')
	{
		return \Tassos\Framework\FileUpload::tempDir(['acf', 'gallery', $item_id, $field_id]);
	}

	/**
	 * JPATH_ROOT-relative form of getFullTempFolder(), used when building the
	 * web-facing paths stored against each gallery item.
	 *
	 * @param   string|int  $field_id  The field id (optional).
	 * @param   string|int  $item_id   The owning item id (optional).
	 *
	 * @return  string
	 */
	public static function getTempFolder($field_id = '', $item_id = '')
	{
		return ltrim(str_replace(JPATH_ROOT, '', self::getFullTempFolder($field_id, $item_id)), '/\\');
	}

	/**
	 * JPATH_ROOT-relative root of the gallery temp area, independent of
	 * item/field. Used to recognise still-in-temp items at save time, when the
	 * item/field are not part of the (content-save) request.
	 *
	 * @return  string
	 */
	public static function getTempRootRelative()
	{
		return ltrim(str_replace(JPATH_ROOT, '', self::getFullTempFolder()), '/\\');
	}

	/**
	 * Whether a stored, JPATH_ROOT-relative path points at a file still sitting
	 * in the gallery temp area (i.e. not yet moved to its final destination).
	 *
	 * @param   string  $relative
	 *
	 * @return  bool
	 */
	public static function isGalleryTempPath($stored)
	{
		if (!is_string($stored) || $stored === '')
		{
			return false;
		}

		// Compare absolute paths: the stored path may be relative or absolute.
		return Functions::startsWith(self::toAbsolute($stored), Path::clean(self::getFullTempFolder()));
	}

	/**
	 * Resolve a stored gallery path (JPATH_ROOT-relative or already-absolute)
	 * to an absolute filesystem path.
	 *
	 * @param   string  $stored
	 *
	 * @return  string
	 */
	public static function toAbsolute($stored)
	{
		return SafePath::toAbsolute($stored);
	}

	/**
	 * Deletes a specific tag from every gallery item.
	 * 
	 * @param   int     $tag_id
	 * @param   string  $context
	 * 
	 * @return  void
	 */
	public static function deleteTagFromFieldsValues($tag_id = null, $context = '')
	{
		if (!$tag_id)
		{
			return;
		}

		if ($context === '')
		{
			self::deleteTagFromCustomFieldsByTagId($tag_id);
			self::deleteTagFromSubformCustomFieldsByTagId($tag_id);
		}
	}

	/**
	 * Deletes a specific tag from every gallery item custom field.
	 * 
	 * @param   int  $tag_id
	 * 
	 * @return  void
	 */
	private static function deleteTagFromCustomFieldsByTagId($tag_id = null)
	{
		if (!$tag_id)
		{
			return;
		}

		$db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('f.id as field_id, fv.item_id as item_id, fv.value as value')
            ->from('#__fields as f')
            ->join('LEFT', '#__fields_values AS fv ON fv.field_id = f.id')
            ->where('f.type = ' . $db->quote('acfgallery'));

        $db->setQuery($query);

        $fields = $db->loadAssocList();

		if (!$fields)
		{
			return;
		}

        foreach ($fields as $field)
        {
            if (!$decoded_value = json_decode($field['value'], true))
            {
                continue;
            }

            if (!isset($decoded_value['items']))
            {
                continue;
            }

            $update = false;

            foreach ($decoded_value['items'] as &$item)
            {
                if (!isset($item['tags']))
                {
                    continue;
                }

                if (!is_array($item['tags']))
                {
                    continue;
                }

                if (!count($item['tags']))
                {
                    continue;
                }

                $item['tags'] = array_values($item['tags']);

                if (($key = array_search($tag_id, $item['tags'])) !== false)
                {
                    $update = true;
                    unset($item['tags'][$key]);
                }

                $item['tags'] = array_values($item['tags']);
            }

            if (!$update)
            {
				continue;
			}
		
			$field['value'] = json_encode($decoded_value);

			// Update field value
			$query->clear()
				->update('#__fields_values')
				->set($db->quoteName('value') . ' = ' . $db->quote($field['value']))
				->where($db->quoteName('field_id') . ' = ' . $db->quote($field['field_id']))
				->where($db->quoteName('item_id') . ' = ' . $db->quote($field['item_id']));
			$db->setQuery($query);
			$db->execute();
        }
	}

	/**
	 * Deletes a specific tag from every gallery item that exists in a subform custom field.
	 * 
	 * @param   int  $tag_id
	 * 
	 * @return  void
	 */
	private static function deleteTagFromSubformCustomFieldsByTagId($tag_id = null)
	{
		if (!$tag_id)
		{
			return;
		}

		if (!$tag_id)
		{
			return;
		}

		$db = Factory::getDbo();

		// Get all ACF Gallery custom field IDs
		$query = $db->getQuery(true)
			->select('distinct f.id')
			->from('#__fields as f')
			->join('LEFT', '#__fields_values AS fv ON fv.field_id = f.id')
			->where('f.type = ' . $db->quote('acfgallery'));
			$db->setQuery($query);
		$gallery_field_ids = array_keys($db->loadAssocList('id'));

		if (!$gallery_field_ids)
		{
			return;
		}
		
		// Get all Subform custom fields
        $query->clear()
            ->select('f.id as field_id, fv.item_id as item_id, fv.value as value')
            ->from('#__fields as f')
            ->join('LEFT', '#__fields_values AS fv ON fv.field_id = f.id')
            ->where('f.type = ' . $db->quote('subform'));
        $db->setQuery($query);
        $subform_fields = $db->loadAssocList();

		foreach ($subform_fields as $subform_field)
		{
			if (!$subform_field_items = json_decode($subform_field['value'], true))
			{
				continue;
			}

			$update = false;

			foreach ($subform_field_items as $row => &$row_items)
			{
				if (!is_array($row_items))
				{
					continue;
				}

				foreach ($row_items as $field_name => &$field_value)
				{
					// Get the field id
					$field_id = str_replace('field', '', $field_name);
					
					// Check if its a gallery field
					if (!in_array($field_id, $gallery_field_ids))
					{
						continue;
					}

					if (!isset($field_value['items']))
					{
						continue;
					}

					foreach ($field_value['items'] as &$item)
					{
						if (!isset($item['tags']))
						{
							continue;
						}

						if (!is_array($item['tags']))
						{
							continue;
						}

						if (!count($item['tags']))
						{
							continue;
						}

						$item['tags'] = array_values($item['tags']);

						if (($key = array_search($tag_id, $item['tags'])) !== false)
						{
							$update = true;
							unset($item['tags'][$key]);
						}

						$item['tags'] = array_values($item['tags']);
					}
				}
			}

			if (!$update)
			{
				continue;
			}
			
			$subform_field['value'] = json_encode($subform_field_items);

			// Update subform field value
			$query->clear()
				->update('#__fields_values')
				->set($db->quoteName('value') . ' = ' . $db->quote($subform_field['value']))
				->where($db->quoteName('field_id') . ' = ' . $db->quote($subform_field['field_id']))
				->where($db->quoteName('item_id') . ' = ' . $db->quote($subform_field['item_id']));
			$db->setQuery($query);
			$db->execute();
		}
	}
}