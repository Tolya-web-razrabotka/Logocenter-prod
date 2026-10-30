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

use Tassos\Framework\Security\FileToken;
use Tassos\Framework\Security\SafePath;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Filesystem\Path;

/**
 * Where an uploaded file lives, and how that location is written to and read back
 * from a submission.
 *
 * Neither form of stored value holds a server path:
 *
 *   in-site    /media/com_convertforms/uploads/6a1f_invoice.pdf
 *   external   cf-external://applications/6a1f_invoice.pdf
 *
 * The marker is what makes the base a setting rather than data: move the folder,
 * repoint the setting, and every stored value, link and cleanup job still works.
 * Each value carries its own resolution rule, so switching a field between the secure
 * destination and the legacy ones breaks nothing either.
 *
 * A marked value with no base configured resolves to nothing and logs. It must never
 * fall back to JPATH_ROOT: the file is not there.
 *
 * The marker never leaves the database. prepareRawValue() and toOutwardValue() swap
 * marked values for a download URL first.
 */
class FileStorage
{
	/**
	 * Prefix marking a value stored in the external base. Scheme-style so it cannot
	 * collide with a real filename, which a bare 'external:' could.
	 *
	 * @var string
	 */
	const MARKER = 'cf-external://';

	/**
	 * Sealing purpose for download links. The key is derived from it, so a token from
	 * another flow (the framework's upload tokens) cannot be opened here at all.
	 *
	 * @var string
	 */
	const TOKEN_PURPOSE = 'cf.file.download';

	/**
	 * Context keys inside a download token: form, field. One character each because they
	 * are sealed into every link, and they are never shown.
	 *
	 * Download tokens only. The upload flow's keys are framework API with tokens already
	 * issued against them.
	 *
	 * @var string
	 */
	const CTX_FORM  = 'f';
	const CTX_FIELD = 'k';

	/**
	 * Default download link lifetime in seconds (7 days).
	 *
	 * @var int
	 */
	const DEFAULT_TTL = 604800;

	/**
	 * The resolved external base, or '' when the feature is off. Null until read.
	 *
	 * @var string|null
	 */
	private static $external_root = null;

	/**
	 * Messages already written this request, keyed by message. See logOnce().
	 *
	 * @var array
	 */
	private static $logged = [];

	/**
	 * The configured external storage base, canonicalised.
	 *
	 * realpath()'d so the containment check on write and the comparison in
	 * toStoredValue() speak about the same string when the base is a symlink.
	 *
	 * Validated with externalRootProblem(), the same rule the options screen applies.
	 *
	 * @return  string  The absolute base path, or '' when not configured or unusable.
	 */
	public static function getExternalRoot()
	{
		if (!is_null(self::$external_root))
		{
			return self::$external_root;
		}

		self::$external_root = '';

		// Without a new enough framework File::move() rejects every external destination
		// and FileToken has no sealing purpose, which reads as a broken feature rather
		// than a stale dependency.
		if (!defined('Tassos\\Framework\\Security\\FileToken::PURPOSE_UPLOAD'))
		{
			return self::$external_root;
		}

		$configured = trim((string) Helper::getComponentParams()->get('external_upload_path', ''));

		// The save-time rule, run again here: a value written straight to the database
		// never passed through it, and the folder may have changed underneath it since.
		if ($configured === '' || self::externalRootProblem($configured) !== '')
		{
			return self::$external_root;
		}

		$real = realpath($configured);

		if ($real === false || !is_dir($real))
		{
			return self::$external_root;
		}

		// Again on the resolved path: the checks that are lexical only would otherwise
		// miss a base that reaches the site, or the filesystem root, through a symlink.
		if (self::externalRootProblem($real) !== '')
		{
			return self::$external_root;
		}

		self::$external_root = rtrim(Path::clean($real), DIRECTORY_SEPARATOR);

		return self::$external_root;
	}

	/**
	 * The first condition that disqualifies a path as the external base, as a
	 * language key.
	 *
	 * Read-only, so the same list runs at save time and on every render of the options
	 * screen. Creating the folder is the caller's job, so a folder that does not exist
	 * yet is not a problem here.
	 *
	 * @param   string  $path  The value as typed, before cleaning
	 *
	 * @return  string  A language key, or '' when nothing disqualifies the path
	 */
	public static function externalRootProblem($path)
	{
		$path = trim((string) $path);

		if (SafePath::hasTraversal($path) || !SafePath::isAbsolute($path))
		{
			return 'COM_CONVERTFORMS_EXTERNAL_UPLOAD_PATH_MUST_BE_ABSOLUTE';
		}

		$path = rtrim(Path::clean($path), DIRECTORY_SEPARATOR);

		// A path that cleans down to the filesystem root would trust everything.
		if ($path === '')
		{
			return 'COM_CONVERTFORMS_EXTERNAL_UPLOAD_PATH_INVALID';
		}

		// Before is_dir() and is_writable(), which both return false under a restrictive
		// open_basedir and would report "folder does not exist" for one out of reach.
		if (!self::withinOpenBasedir($path))
		{
			return 'COM_CONVERTFORMS_EXTERNAL_UPLOAD_PATH_OPEN_BASEDIR';
		}

		// Inside the site defeats the point. The realpath variant catches a path that
		// only reaches the site through a symlink.
		if (SafePath::isWithinLexical($path, JPATH_ROOT) || SafePath::isWithin($path, JPATH_ROOT))
		{
			return 'COM_CONVERTFORMS_EXTERNAL_UPLOAD_PATH_MUST_BE_OUTSIDE_WEB_ROOT';
		}

		// The inverse matters as much: a base containing the site would put every site
		// file, configuration.php included, one download token away.
		if (SafePath::isWithinLexical(JPATH_ROOT, $path))
		{
			return 'COM_CONVERTFORMS_EXTERNAL_UPLOAD_PATH_CONTAINS_SITE';
		}

		// Same for Joomla's temp and log folders when they sit outside the site.
		foreach (['tmp_path', 'log_path'] as $config_key)
		{
			$joomla_path = (string) Factory::getApplication()->getConfig()->get($config_key);

			if ($joomla_path !== '' && SafePath::isWithinLexical(rtrim(Path::clean($joomla_path), DIRECTORY_SEPARATOR), $path))
			{
				return 'COM_CONVERTFORMS_EXTERNAL_UPLOAD_PATH_CONTAINS_JOOMLA_FOLDER';
			}
		}

		// Only meaningful once the folder is there.
		if (is_dir($path) && !is_writable($path))
		{
			return 'COM_CONVERTFORMS_EXTERNAL_UPLOAD_PATH_NOT_WRITABLE';
		}

		return '';
	}

	/**
	 * Whether the path is reachable under the current open_basedir restriction.
	 *
	 * @param   string  $path  The cleaned absolute path
	 *
	 * @return  bool
	 */
	private static function withinOpenBasedir($path)
	{
		$open_basedir = trim((string) ini_get('open_basedir'));

		if ($open_basedir === '')
		{
			return true;
		}

		foreach (explode(PATH_SEPARATOR, $open_basedir) as $allowed)
		{
			$allowed = rtrim(Path::clean(trim($allowed)), DIRECTORY_SEPARATOR);

			if ($allowed !== '' && SafePath::isWithinLexical($path, $allowed))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether uploads may be stored outside the site.
	 *
	 * @return  bool
	 */
	public static function externalStorageEnabled()
	{
		return self::getExternalRoot() !== '';
	}

	/**
	 * Lifetime in seconds for a download link, or FileToken::NEVER_EXPIRES.
	 *
	 * @return  int
	 */
	public static function getTokenTTL()
	{
		$ttl = Helper::getComponentParams()->get('download_token_ttl', self::DEFAULT_TTL);

		// An unset param must not silently mean "never expires".
		return $ttl === '' || is_null($ttl) ? self::DEFAULT_TTL : (int) $ttl;
	}

	/**
	 * Whether a stored submission value points outside the site.
	 *
	 * A property of the value itself, deliberately independent of whether a base is
	 * configured right now. Were it to consult the setting, clearing the setting would
	 * reclassify every marked value as site-relative and send it off to resolve under
	 * JPATH_ROOT, which is exactly the silent fallback the marker exists to prevent.
	 *
	 * @param   string  $stored_value  The value as held in the submission params
	 *
	 * @return  bool
	 */
	public static function isExternal($stored_value)
	{
		return is_string($stored_value) && strpos($stored_value, self::MARKER) === 0;
	}

	/**
	 * Convert an absolute path into the value stored in the submission params.
	 *
	 * The relative part is written with forward slashes whatever the platform, so a
	 * value stays readable, greppable and portable between installations.
	 *
	 * @param   string  $absolute_path  The absolute path the file was moved to
	 *
	 * @return  string  The marked relative path for external files, otherwise the JPATH_ROOT-relative path
	 */
	public static function toStoredValue($absolute_path)
	{
		$root = self::getExternalRoot();

		if ($root !== '' && SafePath::isWithinLexical($absolute_path, $root))
		{
			// Derived lexically rather than with SafePath::relativeTo(), which throws
			// and resolves symlinks. isWithinLexical() has already settled containment
			// on exactly these strings, and a save must not fail because the two
			// disagree about a symlinked folder.
			$relative = ltrim(substr(Path::clean($absolute_path), strlen($root)), '/\\');

			return self::MARKER . str_replace('\\', '/', $relative);
		}

		return Helper::pathTorelative($absolute_path);
	}

	/**
	 * The path a stored value holds, relative to whichever base confines it.
	 *
	 * @param   string  $stored_value  The value as held in the submission params
	 *
	 * @return  string  The relative path, with the marker stripped when there is one
	 */
	public static function toRelativePath($stored_value)
	{
		if (!is_string($stored_value))
		{
			return '';
		}

		if (self::isExternal($stored_value))
		{
			return ltrim(substr($stored_value, strlen(self::MARKER)), '/\\');
		}

		return ltrim($stored_value, '/\\');
	}

	/**
	 * Resolve a stored submission value back to an absolute path on disk.
	 *
	 * Never throws: this is called from display code on every render. The stored value
	 * is not necessarily one we wrote (a submission can be edited), so it is confined
	 * rather than simply joined onto its base.
	 *
	 * Containment is lexical, not realpath-based. sanitize() has already rejected
	 * traversal, which is the attack; insisting the resolved path also realpaths inside
	 * the base would reject a site whose media folder is a symlink to another volume.
	 * That is a supported layout, and these paths were built by plain concatenation
	 * before this method existed, so rejecting it would break working installations.
	 *
	 * @param   string  $stored_value  The value as held in the submission params
	 *
	 * @return  string  The absolute path, or '' when it cannot be resolved safely
	 */
	public static function toAbsolutePath($stored_value)
	{
		if (!is_string($stored_value) || $stored_value === '')
		{
			return '';
		}

		$relative = self::toRelativePath($stored_value);

		if (self::isExternal($stored_value))
		{
			$base = self::getExternalRoot();

			// Fail closed: resolving under JPATH_ROOT would point at something else
			// entirely. Logged because nothing else on the site says uploads are being
			// refused and downloads failing until an admin restores the setting.
			if ($base === '')
			{
				self::logOnce('Convert Forms: a submission holds a file stored outside the site, but no Uploads Base Path is set in Options, under Security. The file cannot be reached until one is.');

				return '';
			}

			// The base itself is a folder, not a file.
			if ($relative === '')
			{
				return '';
			}
		}
		else
		{
			$base = JPATH_ROOT;
		}

		try
		{
			SafePath::sanitize($relative);
		}
		catch (\Throwable $e)
		{
			return '';
		}

		$absolute = Path::clean($base . DIRECTORY_SEPARATOR . $relative);

		return SafePath::isWithinLexical($absolute, $base) ? $absolute : '';
	}

	/**
	 * The base directory a stored value must resolve inside.
	 *
	 * @param   string  $stored_value  The value as held in the submission params
	 *
	 * @return  string  The external base for external values, otherwise JPATH_ROOT.
	 *                  Empty for an external value with no base configured, which every
	 *                  caller has to treat as a failure rather than as a default.
	 */
	public static function getBaseFor($stored_value)
	{
		return self::isExternal($stored_value) ? self::getExternalRoot() : JPATH_ROOT;
	}

	/**
	 * Build a front-end download URL for an external file.
	 *
	 * Absolute, since these end up in notification emails.
	 *
	 * The URL is a bearer credential: whoever holds it can fetch the file until the
	 * token expires, unless Download Access says otherwise. It is what the File Upload
	 * Smart Tag resolves to, so it reaches webhooks, API responses and exports too.
	 *
	 * The site URL is only derivable from a real request. A site sending notifications
	 * from CLI cron must set Global Configuration → Site URL, or these come out
	 * malformed.
	 *
	 * @param   string  $stored_value  The value as held in the submission params
	 * @param   array   $context       Extra token context, typically form_id and field_key
	 *
	 * @return  string  The download URL, or '' when a token cannot be issued
	 */
	public static function downloadUrl($stored_value, $context = [])
	{
		if (!self::isExternal($stored_value))
		{
			return '';
		}

		// toAbsolutePath() has already logged the unconfigured case. The rest are a
		// missing file or an unusable base.
		$path = self::toAbsolutePath($stored_value);

		if ($path === '')
		{
			return '';
		}

		try
		{
			$token = (new FileToken(self::TOKEN_PURPOSE))->issue(
				$path,
				self::getExternalRoot(),
				$context,
				self::getTokenTTL(),
				// Both readers pass the base in force instead, which is what lets an admin
				// move the storage folder. It is also the largest thing in the payload.
				false
			);
		}
		catch (\Throwable $e)
		{
			// An export, webhook or notification is about to go out with an empty value
			// where a link should be, and nothing else records why.
			self::logOnce('Convert Forms: could not issue a download link for a file stored outside the site. ' . $e->getMessage());

			return '';
		}

		// Routed through the site router explicitly, so an export or a task running in the
		// administrator still mints the link the recipient can open. form_id names the form
		// for the router, which routes the link under that form's menu item and drops the
		// argument again, so it never reaches the address.
		$form_id = isset($context[self::CTX_FORM]) ? (int) $context[self::CTX_FORM] : 0;

		return Route::link(
			'site',
			'index.php?option=com_convertforms&view=download&token=' . rawurlencode($token)
				. ($form_id ? '&form_id=' . $form_id : ''),
			false,
			Route::TLS_IGNORE,
			true
		);
	}

	/**
	 * What a stored value looks like to anything outside the database.
	 *
	 * Exports, webhooks, the JSON API and the developer API all hand submission data to
	 * someone who cannot use a server path and must not see the marker. An external file
	 * becomes a download link; an in-site file passes through unchanged.
	 *
	 * @param   string  $stored_value  The value as held in the submission params
	 * @param   array   $context       Extra token context, typically form_id and field_key
	 *
	 * @return  string  The download URL, or the value unchanged, or '' when no link could be minted
	 */
	public static function toOutwardValue($stored_value, $context = [])
	{
		return self::isExternal($stored_value) ? self::downloadUrl($stored_value, $context) : $stored_value;
	}

	/**
	 * Build a back-end download URL, authorised by ACL rather than by a token.
	 *
	 * No path component comes from the request: the submission is looked up by id and
	 * the field by name, so there is nothing to traverse with.
	 *
	 * @param   int     $submission_id  The submission holding the file
	 * @param   string  $field_name     The File Upload field's name
	 * @param   int     $index          Which file, for multi-file fields
	 *
	 * @return  string
	 */
	public static function adminDownloadUrl($submission_id, $field_name, $index = 0)
	{
		// Unescaped on purpose: every caller runs this through htmlspecialchars(), and
		// Route::_()'s own XML escaping would double-encode the ampersands.
		return Route::_('index.php?option=com_convertforms&task=conversion.download'
			. '&id=' . (int) $submission_id
			. '&field=' . urlencode($field_name)
			. '&index=' . (int) $index
			. '&' . Session::getFormToken() . '=1', false);
	}

	/**
	 * Resolve and filter the references handed to the mailer as attachments.
	 *
	 * NRFramework\Email attaches whatever names a file, so the whitelist has to be here.
	 * A reference reaches the mailer only when it resolves into the external base or into
	 * the site; anything else is dropped.
	 *
	 * The Attachments box takes Smart Tags, so a reference can carry a submitted value.
	 * Reaching the external base therefore requires decoding one of our own signed
	 * download URLs, not merely naming a path under the base.
	 *
	 * @param   mixed  $attachments  Comma-separated list or array of references
	 *
	 * @return  array  Download URLs replaced by absolute paths, in-site references
	 *                 unchanged, everything else removed
	 */
	public static function resolveAttachments($attachments)
	{
		if (empty($attachments))
		{
			return [];
		}

		$items = is_array($attachments) ? $attachments : explode(',', (string) $attachments);
		$resolved = [];

		foreach ($items as $item)
		{
			$item = trim((string) $item);

			if ($item === '')
			{
				continue;
			}

			if ($path = self::pathFromDownloadUrl($item))
			{
				$resolved[] = $path;

				continue;
			}

			// Passed through as given: the mailer has always resolved in-site references
			// itself.
			if (self::isSiteFile($item))
			{
				$resolved[] = $item;

				continue;
			}

			self::logOnce('Convert Forms: an email attachment was skipped because it does not name a file inside the site or a valid download link.');
		}

		return $resolved;
	}

	/**
	 * Whether a reference carries a URL scheme (https://, cf-external://, ...), as
	 * opposed to being a bare path.
	 *
	 * @param   mixed  $reference  The reference to test
	 *
	 * @return  bool
	 */
	public static function hasScheme($reference)
	{
		return is_string($reference) && preg_match('#^[a-z][a-z0-9+.\-]*://#i', $reference) === 1;
	}

	/**
	 * Whether a reference names an existing file inside the site.
	 *
	 * A leading slash is tried both ways, site-relative first: that is how an in-site
	 * upload is stored, and it is indistinguishable from a real absolute path.
	 *
	 * @param   string  $reference  A path or a URL
	 *
	 * @return  bool
	 */
	private static function isSiteFile($reference)
	{
		$root = rtrim(Uri::root(), '/');

		if ($root !== '' && strpos($reference, $root) === 0)
		{
			$reference = substr($reference, strlen($root));
		}
		elseif (self::hasScheme($reference))
		{
			// Another host, or a marked value that never had a link minted for it.
			return false;
		}

		if ($reference === '')
		{
			return false;
		}

		$reference = Path::clean($reference);
		$candidates = [Path::clean(JPATH_ROOT . DIRECTORY_SEPARATOR . ltrim($reference, '/\\'))];

		if (SafePath::isAbsolute($reference))
		{
			$candidates[] = $reference;
		}

		foreach ($candidates as $candidate)
		{
			if (SafePath::isWithinLexical($candidate, JPATH_ROOT) && is_file($candidate))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract the file a CF download URL points at.
	 *
	 * @param   string  $url  A candidate URL
	 *
	 * @return  string  The absolute path, or '' when this is not one of our URLs
	 */
	private static function pathFromDownloadUrl($url)
	{
		// Identified by the token, not by the query shape: a Downloads menu item routes
		// the link to a SEF URL that carries neither the view nor the component.
		$query = parse_url($url, PHP_URL_QUERY);

		if (!$query)
		{
			return '';
		}

		parse_str($query, $params);

		if (empty($params['token']) || !is_string($params['token']))
		{
			return '';
		}

		$root = self::getExternalRoot();

		if ($root === '')
		{
			return '';
		}

		try
		{
			$fileToken = new FileToken(self::TOKEN_PURPOSE);

			$context = $fileToken->context($params['token']);

			// Resolved against the base in force, not the one the token was issued with,
			// so a link minted before the admin moved their storage still finds the file.
			$path = $fileToken->read($params['token'], $root);
		}
		catch (\Throwable $e)
		{
			return '';
		}

		// The same field lookup the download endpoint does. This URL came from a Smart
		// Tag whose value can be a submitted field, so it is no more trusted than the
		// endpoint's own input, and a token naming a field that is gone or is no longer
		// a File Upload field must not resolve to a file.
		if (!self::downloadFieldSettings($context))
		{
			return '';
		}

		return SafePath::isWithin($path, $root) ? $path : '';
	}

	/**
	 * The settings of the File Upload field a download token names.
	 *
	 * @param   array  $context  The token's context claims
	 *
	 * @return  \Joomla\Registry\Registry|null  Null when the field is gone or is no
	 *                                          longer a File Upload field
	 */
	public static function downloadFieldSettings($context)
	{
		$settings = Form::getFieldSettingsByKey(
			isset($context[self::CTX_FORM]) ? $context[self::CTX_FORM] : 0,
			isset($context[self::CTX_FIELD]) ? $context[self::CTX_FIELD] : '',
			// Unpublishing a form must not silently kill download links that were already sent.
			true
		);

		return $settings && $settings->get('type') === 'fileupload' ? $settings : null;
	}

	/**
	 * How many submissions hold a file stored outside the site.
	 *
	 * Counted so the options screen can tell an admin what changing the base path will
	 * cost them. Convert Forms does not move the files.
	 *
	 * @return  int  The count, or 0 when it cannot be determined
	 */
	public static function countExternalSubmissions()
	{
		try
		{
			$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

			// json_encode() escapes forward slashes, so the column holds the marker as
			// 'cf-external:\/\/'. Match what is actually stored, not what it decodes to.
			$needle = str_replace('/', '\\\\/', self::MARKER);

			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__convertforms_conversions'))
				->where($db->quoteName('params') . ' LIKE ' . $db->quote('%' . $needle . '%'));

			return (int) $db->setQuery($query)->loadResult();
		}
		catch (\Throwable $e)
		{
			// A warning that cannot be counted is not a reason to break the screen.
			return 0;
		}
	}

	/**
	 * Log a message, at most once per request.
	 *
	 * A misconfigured base is discovered while rendering, so a submissions list of twenty
	 * files would otherwise log the same line twenty times per page load.
	 *
	 * 'error' rather than the 'debug' default because debug is gated on a component
	 * param, and a misconfiguration that silently empties download links has to be
	 * recorded whether or not anyone turned it on.
	 *
	 * @param   string  $message  The message to log
	 *
	 * @return  void
	 */
	private static function logOnce($message)
	{
		if (isset(self::$logged[$message]))
		{
			return;
		}

		self::$logged[$message] = true;

		Helper::log($message, 'error');
	}
}
