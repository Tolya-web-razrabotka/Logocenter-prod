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

namespace ConvertForms;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Tassos\Framework\File;
use Tassos\Framework\Security\FileToken;
use Tassos\Framework\Security\SafePath;

/**
 * Serves a file that is stored outside the site and therefore has no URL of its own.
 *
 * Reached two ways, which is why this is not in the controller: view=download carries a
 * menu item and routes to a SEF URL, task=download is the original endpoint and stays
 * for links already sent. Both land here.
 *
 * The token in the query string is the whole credential. These links are opened from
 * notification emails, in a different browser and session from the one that submitted
 * the form, so there is deliberately no CSRF check. Adding one would break every link
 * the moment it left the originating session.
 *
 * What does the work instead is the token itself (sealed under a key derived from the
 * site secret and this flow's purpose, carrying its own expiry), and a server-side
 * re-check that the resolved file really is under the folder the administrator
 * configured.
 *
 * Note what that makes the link: a bearer credential. Anyone holding it can fetch the
 * file until it expires, unless the field's Download Access level says otherwise. It
 * travels wherever the File Upload Smart Tag is used and, being a query string, into
 * access and proxy logs.
 */
class Download
{
	/**
	 * Stream the file the request's token points at. Does not return.
	 *
	 * @return  void
	 */
	public static function serve()
	{
		$app = Factory::getApplication();
		$app->allowCache(false);

		$token = $app->input->get('token', '', 'RAW');

		// Cheap shape check before any crypto, so junk costs nothing to reject.
		if (!is_string($token) || !preg_match('#^[A-Za-z0-9\-_.]{16,4096}$#', $token))
		{
			self::fail();
		}

		if (!FileStorage::externalStorageEnabled())
		{
			self::fail();
		}

		$fileToken = new FileToken(FileStorage::TOKEN_PURPOSE);

		try
		{
			// Resolved against the base configured right now, not the one the token was
			// issued with, so moving the storage folder does not kill links already sent.
			$context = $fileToken->context($token);
			$path    = $fileToken->read($token, FileStorage::getExternalRoot());
		}
		catch (\Throwable $e)
		{
			// An expired token is the one failure worth naming: the visitor holds a link
			// that really was valid. Only a token carrying our own signature gets this far,
			// so the distinct status reveals nothing an attacker does not already hold.
			if (stripos($e->getMessage(), 'expired') !== false)
			{
				throw new \Exception(Text::_('COM_CONVERTFORMS_DOWNLOAD_LINK_EXPIRED'), 410);
			}

			self::fail();
		}

		// read() already confined the path. Repeated because the base is passed in rather
		// than fixed, and this is the one endpoint an anonymous visitor reaches.
		if (!SafePath::isWithin($path, FileStorage::getExternalRoot()))
		{
			self::fail();
		}

		self::authorise($context);

		if (!is_file($path))
		{
			self::fail();
		}

		// Inline so an uploaded photo works as an <img> source. File::stream() grants it
		// only to raster image types it detects in the file itself; everything else is
		// still handed over as a download.
		File::stream($path, FileStorage::getExternalRoot(), [
			'filename' => basename($path),
			'inline'   => true
		]);
	}

	/**
	 * Apply the owning field's Download Access level.
	 *
	 * Read from the form on every request rather than baked into the token, so
	 * tightening or relaxing it takes effect for links that are already out there.
	 *
	 * @param   array  $context  The token's context claims
	 *
	 * @return  void
	 */
	private static function authorise($context)
	{
		$settings = FileStorage::downloadFieldSettings($context);

		if (!$settings)
		{
			self::fail();
		}

		// Unset means the field was saved before this setting existed. Public either way:
		// the token alone is the credential, which is what most links are.
		$access = (int) $settings->get('download_access', 0);

		if ($access < 1)
		{
			return;
		}

		$user = Factory::getUser();

		// Anyone who can administer submissions can already read the file in the backend.
		if ($user->authorise('core.manage', 'com_convertforms'))
		{
			return;
		}

		if (!in_array($access, $user->getAuthorisedViewLevels()))
		{
			self::denied();
		}
	}

	/**
	 * Refuse the download.
	 *
	 * A guest is sent to the login form, since logging in may well be all that is
	 * missing. Everyone else gets a flat 403.
	 *
	 * @return  void
	 */
	private static function denied()
	{
		$user = Factory::getUser();

		if ($user->guest)
		{
			// base64 produces '+', '/' and '=', all of which change meaning in a query
			// string, so the round trip only survives url-encoded.
			$return = urlencode(base64_encode(Uri::getInstance()->toString()));

			Factory::getApplication()->redirect(
				Route::_('index.php?option=com_users&view=login&return=' . $return, false)
			);
		}

		throw new NotAllowed(Text::_('COM_CONVERTFORMS_NOT_AUTHORIZED'), 403);
	}

	/**
	 * Reject the request.
	 *
	 * Every reason returns the same 404, so the endpoint cannot be used as an oracle for
	 * probing what exists.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 */
	private static function fail()
	{
		throw new \Exception(Text::_('COM_CONVERTFORMS_DOWNLOAD_LINK_INVALID'), 404);
	}
}
