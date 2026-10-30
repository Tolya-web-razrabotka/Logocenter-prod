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

use Tassos\Framework\File;
use Tassos\Framework\Security\SafePath;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Collects file uploads whose destination folder contains submission-level Smart Tags
 * (eg. {submission.id}) and moves them to their final folder once the submission has
 * been created and its ID is known.
 *
 * Such destinations cannot be resolved during the pre-save upload pass because the
 * submission row does not exist yet. Instead of moving the file twice, the File Upload
 * field records the pending move here and the conversion model finalizes it right after
 * the submission is saved — before the submission item is prepared — so every downstream
 * consumer (Smart Tags, email notifications, backend views) is built from the final path.
 */
class DeferredUploads
{
	/**
	 * The pending moves recorded during the current request.
	 *
	 * @var array
	 */
	private static $moves = [];

	/**
	 * Record a pending upload move.
	 *
	 * @param   array  $move  Expects: field (the FileUpload field instance), field_name,
	 *                        key (file index within the field), source (absolute temp path),
	 *                        template (absolute destination path with submission tags pending).
	 *
	 * @return  void
	 */
	public static function defer(array $move): void
	{
		self::$moves[] = $move;
	}

	/**
	 * Whether any pending moves have been recorded.
	 *
	 * @return  bool
	 */
	public static function any(): bool
	{
		return !empty(self::$moves);
	}

	/**
	 * Move every recorded upload to its final folder and update the submission params
	 * with the final relative paths.
	 *
	 * A failed move falls back to the field's own base so the file is never lost. When
	 * even that fails the submission is refused, rather than stored with a value pointing
	 * at the temp folder.
	 *
	 * @param   object  $submission  The created submission (or the mocked one when the form
	 *                               does not save to the database) — used to resolve
	 *                               submission-level Smart Tags.
	 * @param   array   $params      The submission params, updated in place.
	 *
	 * @return  void
	 */
	public static function finalize($submission, array &$params): void
	{
		$app = Factory::getApplication();

		foreach (self::$moves as $move)
		{
			$field = $move['field'];

			// Resolve the remaining runtime tags (field.* and submission.*) in one pass.
			$destination = $field->resolveRuntimeTags($move['template'], $params, $submission);

			$base        = isset($move['base']) ? $move['base'] : JPATH_ROOT;
			$is_external = !empty($move['external']);
			$extra_roots = $is_external ? [$base] : [];

			// The tags resolved above carry submitted values, so check the destination
			// before it reaches the filesystem.
			// Refused rather than skipped: continuing would leave the stored value
			// pointing at the file still sitting in the public temp folder.
			if (!SafePath::isWithinLexical($destination, $base))
			{
				Helper::log('Convert Forms: a deferred upload resolved to "' . $destination . '", outside its base "' . $base . '", and was refused.', 'error');

				throw new \RuntimeException(Text::_('COM_CONVERTFORMS_UPLOAD_INVALID_DESTINATION'));
			}

			try
			{
				$destination = File::move($move['source'], $destination, false, false, $extra_roots);
			}
			catch (\Throwable $th)
			{
				// Fall back to the field's own base so the file is never lost. For an
				// external field this must stay outside the site: falling back into
				// /media would publish a file the admin chose to keep private.
				Helper::log('Convert Forms: Deferred upload move failed, falling back to the base upload folder. ' . $th->getMessage());

				$fallback = $is_external
					? rtrim($base, '/\\') . DIRECTORY_SEPARATOR . basename($destination)
					: JPATH_ROOT . '/media/com_convertforms/uploads/' . basename($destination);

				try
				{
					$destination = File::move($move['source'], $fallback, false, false, $extra_roots);
				}
				catch (\Throwable $th)
				{
					// Nowhere left to put it. Keeping the temp path would store a value
					// pointing inside the site at a file the temp cleaner will remove.
					Helper::log('Convert Forms: a deferred upload could not be moved to "' . $fallback . '". ' . $th->getMessage(), 'error');

					throw new \RuntimeException(Text::_('COM_CONVERTFORMS_UPLOAD_INVALID_DESTINATION'));
				}
			}

			$tmpData = [
				'form_id'    => $submission->form_id,
				'field_name' => $move['field_name'],
				'params'     => $params
			];

			

			// Give a chance to manipulate the file with a hook, exactly like the pre-save upload pass.
			$app->triggerEvent('onConvertFormsFileUpload', [&$destination, $tmpData]);

			// Always save a reference the reader can confine: site-relative for files
			// inside the site, the full path for files in the external base.
			$params[$move['field_name']][$move['key']] = FileStorage::toStoredValue($destination);
		}

		self::$moves = [];
	}
}
