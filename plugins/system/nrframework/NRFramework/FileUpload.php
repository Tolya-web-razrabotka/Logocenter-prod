<?php

/**
 * @author          Tassos.gr <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;
use Tassos\Framework\File;
use Tassos\Framework\Security\FileReference;
use Tassos\Framework\Security\FileToken;
use Tassos\Framework\Security\SafePath;

/**
 * Service/facade over the file-upload security primitives (FileToken / FileReference)
 * for upload and delete AJAX flows.
 */
class FileUpload
{
	/**
	 * Build an absolute temp directory under Joomla's configured temp folder:
	 * <tmp_path>/tassos/<…segments>
	 *
	 * Base resolved via File::getTempFolder() (not raw tmp_path): callers
	 * round-trip files as JPATH_ROOT-relative paths, so the temp tree must stay
	 * under JPATH_ROOT — getTempFolder() maps the default '/tmp' to JPATH_SITE/tmp.
	 *
	 * The tree is not scoped per visitor: correctness is guaranteed by the
	 * encrypted file_token (which binds the exact path and field id, signed with
	 * the site secret), so a shared folder is safe and there is no cookie/session
	 * identity to churn between the upload and the save.
	 *
	 * @param   array  $segments  Path segments appended after 'tassos'.
	 *                            Empty segments are skipped.
	 *
	 * @return  string  Absolute directory path.
	 */
	public static function tempDir(array $segments = [])
	{
		$tmp = rtrim(File::getTempFolder(), '/\\');

		$parts = [$tmp, 'tassos'];

		foreach ($segments as $segment)
		{
			$segment = (string) $segment;

			if ($segment !== '')
			{
				$parts[] = $segment;
			}
		}

		return Path::clean(implode(DIRECTORY_SEPARATOR, $parts));
	}

	/**
	 * Backwards-compatible alias for tempDir().
	 *
	 * The framework is bundled with every Tassos extension, so a site can end up
	 * running this version next to an older extension release that still calls
	 * the pre-6.1.16 name. Keep the alias until every product ships the new one.
	 *
	 * @param   array  $segments  Path segments appended after 'tassos'.
	 *
	 * @return  string  Absolute directory path.
	 *
	 * @deprecated  6.1.16  Use tempDir() instead.
	 */
	public static function visitorTempDir(array $segments = []): string
	{
		return self::tempDir($segments);
	}

	/**
	 * No-op kept for backwards compatibility.
	 *
	 * Upload temp folders are no longer scoped per visitor (the signed file_token
	 * binds the staged path and field id), so there is no token to issue at render
	 * time. Older extension releases still call this from their render path.
	 *
	 * @return  void
	 *
	 * @deprecated  6.1.16  Temp uploads are no longer scoped per visitor.
	 */
	public static function ensureVisitorToken(): void
	{
	}

	/**
	 * rmdir $dir when it holds no real content. An index.html / .DS_Store-only
	 * dir counts as empty and is removed with them; non-empty dirs are left as-is.
	 *
	 * @param   string  $dir
	 *
	 * @return  void
	 */
	public static function pruneEmptyDir($dir)
	{
		if (!is_dir($dir))
		{
			return;
		}

		if (array_diff((array) @scandir($dir), ['.', '..', '.DS_Store', 'index.html']))
		{
			return;
		}

		@unlink($dir . DIRECTORY_SEPARATOR . 'index.html');
		@unlink($dir . DIRECTORY_SEPARATOR . '.DS_Store');
		@rmdir($dir);
	}

	/**
	 * Persist $_FILES['file'] to disk
	 *
	 * Shared by handleUpload() and the framework's gallery widgets. 
	 * 
	 * Registry keys:
	 *  - upload_folder   (string|null) Absolute path; null => File::getTempFolder()
	 *  - upload_types    (string)      Forwarded to File::upload (default '*')
	 *  - allow_unsafe    (bool)        Selects 'raw' vs 'cmd' input filter and is
	 *                                  forwarded to File::upload
	 *  - filename_prefix (bool|string|null) Forwarded as $random_prefix: true =>
	 *                                       random unique prefix; a string is
	 *                                       used as a literal prefix; null => none
	 *  - filename_suffix (bool|string|null) Forwarded as $random_suffix: true =>
	 *                                       random unique suffix; a string is
	 *                                       used as a literal suffix; false => none
	 *
	 * @param   Registry  $options
	 *
	 * @return  string  Absolute path of the uploaded file.
	 *
	 * @throws  \Exception  On request-shape or upload failure.
	 */
	public static function uploadFromRequest(Registry $options)
	{
		$input = Factory::getApplication()->input;

		$allow_unsafe = (bool) $options->get('allow_unsafe', false);

		$file = $input->files->get('file', null, $allow_unsafe ? 'raw' : 'cmd');
		if (!$file)
		{
			throw new \RuntimeException('NR_FILE_UPLOAD_INVALID_FILE');
		}

		// Multi-upload payloads arrive as a 2-level array.
		$first = array_pop($file);
		if (is_array($first))
		{
			$file = $first;
		}

		$upload_folder   = $options->get('upload_folder', null);
		$allowed_types   = $options->get('upload_types', '*');
		$filename_prefix = $options->get('filename_prefix', null);
		$filename_suffix = $options->get('filename_suffix', false);

		// `randomize_filename => true` means "randomize with no literal prefix".
		// We can't express that via filename_prefix => '' because Registry::get()
		// collapses '' to its default
		if ((bool) $options->get('randomize_filename', false))
		{
			$filename_prefix = '';
		}

		return File::upload($file, $upload_folder, $allowed_types, $allow_unsafe, $filename_prefix, $filename_suffix);
	}

	/**
	 * Issue a token for the given absolute path and emit the upload response, then exit.
	 *
	 * @param   string  $absolutePath  Absolute path of the persisted file.
	 * @param   string  $base          Token base directory.
	 * @param   array   $context       Token context for cross-request binding.
	 * @param   bool    $exposeUrl     Include the public URL when under JPATH_ROOT.
	 * @param   bool    $emitLegacy    Include the legacy `file` and `file_encode`
	 *                                 base64-path keys. Defaults to true so older
	 *                                 Dropzone clients keep working. Migrated
	 *                                 callers should pass false to get a
	 *                                 token-only response.
	 *
	 * @return  void  Always exits.
	 */
	public static function emitUploadResponse($absolutePath, $base, array $context = [], $exposeUrl = true, $emitLegacy = true)
	{
		$token = '';
		try
		{
			$token = (new FileToken())->issue($absolutePath, $base, $context);
		}
		catch (\Throwable $e) {}

		$response = ['file_token' => $token];

		// Back-compat: only emitted for clients whose JS doesn't read file_token.
		if ($emitLegacy)
		{
			$response['file']        = base64_encode($absolutePath);
			$response['file_encode'] = base64_encode(str_replace([JPATH_SITE, JPATH_ROOT], '', $absolutePath));
		}

		if ($exposeUrl)
		{
			if ($url = self::computePublicUrl($absolutePath))
			{
				$response['url'] = $url;
			}
		}

		header('Content-Type: application/json');
		echo json_encode($response);
		Factory::getApplication()->close();
	}

	/**
	 * Handle an upload request end-to-end.
	 *
	 * Composes uploadFromRequest() and emitUploadResponse().
	 *
	 * Registry keys:
	 *  - upload_folder   (string|null) Absolute path; null => File::getTempFolder()
	 *  - upload_types    (string)      Forwarded to File::upload (default '*')
	 *  - allow_unsafe    (bool)        Selects 'raw' vs 'cmd' input filter and is
	 *                                  forwarded to File::upload
	 *  - filename_prefix (string|null) Forwarded as $random_prefix
	 *  - filename_suffix (bool)        Forwarded as $random_suffix (default false)
	 *  - randomize_filename (bool)     Randomize the filename
	 *  - token_context  (array)  		Stored inside the token for cross-request binding
	 *  - expose_url     (bool)   		Include a public URL when under JPATH_ROOT (default true)
	 *  - emit_legacy    (bool)   		Include the legacy `file`/`file_encode` keys in
	 *                            		the response (default true). Migrated callers
	 *                            		should set this to false for a token-only payload.
	 *
	 * @param   Registry  $options
	 *
	 * @return  void  Always exits.
	 */
	public static function handleUpload(Registry $options)
	{
		try
		{
			$uploaded = self::uploadFromRequest($options);

			$upload_folder = $options->get('upload_folder', null);
			$base = is_null($upload_folder) ? File::getTempFolder() : $upload_folder;

			self::emitUploadResponse(
				$uploaded,
				$base,
				(array) $options->get('token_context', []),
				(bool) $options->get('expose_url', true),
				(bool) $options->get('emit_legacy', true)
			);
		}
		catch (\Throwable $th)
		{
			self::uploadDie($th->getMessage());
		}
	}

	/**
	 * Handle a delete request end-to-end.
	 *
	 * Reads file_token / filename from input, optionally validates the token's
	 * embedded context against the supplied expectation, resolves the reference
	 * via FileReference, deletes the file and emits {"success": true}.
	 *
	 * The `filename` input is the LEGACY field for clients that still send a
	 * base64-encoded path. Newer clients send `file_token` (which takes
	 * precedence here).
	 *
	 * Registry keys:
	 *  - allowed_bases     (string[])  Required. Absolute paths.
	 *  - expected_context  (array)     Optional. Every key/value must match the token's context.
	 *
	 * @param   Registry  $options
	 *
	 * @return  void  Always exits.
	 */
	public static function handleDelete(Registry $options)
	{
		$input = Factory::getApplication()->input;

		$file_token = $input->getString('file_token', '');
		$filename   = $input->getString('filename', '');

		$reference = $file_token ?: $filename;
		if (!$reference)
		{
			self::uploadDie('NR_FILE_UPLOAD_INVALID_FILE');
		}

		$allowed_bases = (array) $options->get('allowed_bases', []);
		if (empty($allowed_bases))
		{
			self::uploadDie('NR_FILE_UPLOAD_INVALID_FILE');
		}

		try
		{
			$expected = (array) $options->get('expected_context', []);
			if ($file_token && !empty($expected))
			{
				self::assertContextMatches($file_token, $expected);
			}

			$absolutePath = self::resolveDeletable($reference, $allowed_bases, (array) $options->get('allowed_files', []));

			if ($absolutePath !== '' && is_file($absolutePath))
			{
				File::delete($absolutePath);
			}

			header('Content-Type: application/json');
			echo json_encode(['success' => true]);
			Factory::getApplication()->close();
		}
		catch (\Throwable $th)
		{
			self::uploadDie($th->getMessage());
		}
	}

	/**
	 * Resolve a delete reference to the absolute path that may be removed:
	 * confine it to $allowedBases, then require an exact $allowedFiles match
	 * when that list is set.
	 *
	 * @param   string  $reference
	 * @param   array   $allowedBases  Dir-level bases (temp folders)
	 * @param   array   $allowedFiles  Exact deletable files (optional)
	 *
	 * @return  string  Absolute path to delete, or '' for an idempotent no-op
	 *
	 * @throws  \RuntimeException  When the reference is unresolvable or unauthorized
	 */
	private static function resolveDeletable($reference, array $allowedBases, array $allowedFiles)
	{
		$resolveBases = empty($allowedFiles)
			? $allowedBases
			: array_values(array_unique(array_merge($allowedBases, array_map('dirname', $allowedFiles))));

		try
		{
			$absolutePath = FileReference::resolve($reference, $resolveBases);
		}
		catch (\RuntimeException $e)
		{
			// Idempotent no-op: the file was already moved out of an allowed
			// base (e.g. a concurrent CF submit), so realpath() can't validate
			// it. A structural in-base match still counts as success.
			if (FileReference::landsInside($reference, $resolveBases) === false)
			{
				throw $e;
			}

			return '';
		}

		// Exact-file gate: siblings, index.html and .htaccess inside a stored-file
		// directory are not deletable, only listed files and temp-base contents.
		if (!empty($allowedFiles) && !self::authorizeAgainstFiles($absolutePath, $allowedBases, $allowedFiles))
		{
			throw new \RuntimeException('NR_FILE_UPLOAD_INVALID_FILE');
		}

		return $absolutePath;
	}

	/**
	 * Resolve a client-submitted reference (token or legacy path) to a
	 * validated absolute path.
	 *
	 * NOTE: $allowedBases is enforced ONLY for the legacy path-resolution
	 * branch. A valid FileToken returns its embedded path without consulting
	 * $allowedBases — token authority is self-contained.
	 *
	 * @param   string  $reference
	 * @param   array   $allowedBases
	 *
	 * @return  string
	 */
	public static function resolveReference($reference, array $allowedBases)
	{
		return FileReference::resolve($reference, $allowedBases);
	}

	/**
	 * Resolve a client-submitted reference and, when it is a FileToken,
	 * verify its embedded context contains every key/value in $expected.
	 *
	 * Legacy plaintext/base64 paths skip the context check (there is no
	 * context attached to them) and are gated only by $allowedBases.
	 *
	 * @param   string  $reference
	 * @param   array   $allowedBases
	 * @param   array   $expected      Required context keys/values for tokens
	 *
	 * @return  string  The resolved absolute file path
	 *
	 * @throws  \RuntimeException  When the token's context contradicts $expected,
	 *                             or the reference cannot be resolved safely.
	 */
	public static function resolveReferenceWithContext($reference, array $allowedBases, array $expected = [])
	{
		if (!empty($expected) && self::looksLikeToken($reference))
		{
			self::assertContextMatches($reference, $expected);
		}

		return FileReference::resolve($reference, $allowedBases);
	}

	/**
	 * Whether $reference is an opaque token rather than a stored path.
	 *
	 * The two outcomes are not symmetric: callers drop what this reports as a token and
	 * persist what it reports as a path. So the question is "is this a token", not "is
	 * this a token I can open". One sealed under another purpose, issued before the site
	 * secret changed, or minted by an older release is still a token, and calling it a
	 * path writes the token string into the caller's data for good.
	 *
	 * Shape answers that without opening anything, and stays true for tokens this release
	 * has never seen. A sealed blob is base64url and its bytes are ciphertext, which is
	 * essentially never valid UTF-8; a stored path is text, and a legacy base64 path
	 * decodes to text, so both stay on the path side.
	 *
	 * @param   string  $reference
	 *
	 * @return  bool
	 */
	public static function looksLikeToken($reference)
	{
		// No separators, and long enough that ordinary values never reach the decode:
		// a stored path carries a directory separator or an extension dot.
		if (!is_string($reference) || !preg_match('#^[A-Za-z0-9\-_]{40,}$#', $reference))
		{
			return false;
		}

		$raw = base64_decode(strtr($reference, '-_', '+/'), true);

		// preg_match('//u') is the cheapest valid-UTF-8 test available.
		return $raw !== false && !preg_match('//u', $raw);
	}

	/**
	 * Issue an encrypted token for an already-persisted file.
	 *
	 * @param   string  $absolutePath
	 * @param   string  $baseDir
	 * @param   array   $context
	 * @param   int     $ttl
	 *
	 * @return  string
	 */
	public static function issueToken($absolutePath, $baseDir, array $context = [], $ttl = FileToken::DEFAULT_TTL)
	{
		return (new FileToken())->issue($absolutePath, $baseDir, $context, $ttl);
	}

	/**
	 * Verify that every key/value in $expected appears in the token's stored
	 * context. Throws when the token cannot be decrypted or when the context
	 * does not match.
	 *
	 * @param   string  $token
	 * @param   array   $expected
	 *
	 * @return  void
	 *
	 * @throws  \RuntimeException  When the token cannot be decrypted, or when
	 *                             its context contradicts $expected.
	 */
	private static function assertContextMatches($token, array $expected)
	{
		try
		{
			$context = (new FileToken())->context($token);
		}
		catch (\Throwable $e)
		{
			throw new \RuntimeException('Token context unverifiable');
		}

		foreach ($expected as $key => $value)
		{
			if (!array_key_exists($key, $context) || (string) $context[$key] !== (string) $value)
			{
				throw new \RuntimeException('Token context mismatch');
			}
		}
	}

	/**
	 * Build a browser-reachable URL for a file stored under JPATH_ROOT.
	 *
	 * Used by emitUploadResponse() to populate the `url` field for clients
	 * (e.g. Dropzone-based fields) that render an inline preview straight
	 * after upload, before any token round-trip.
	 *
	 * Returns an empty string when $absolutePath does not resolve under
	 * JPATH_ROOT — this is a deliberate guard so off-root temp locations
	 * are never advertised to the browser.
	 *
	 * Path segments are not URL-encoded; callers must ensure stored
	 * filenames are already web-safe (the upload pipeline sanitises them).
	 *
	 * @param   string  $absolutePath
	 *
	 * @return  string  Absolute URL, or '' if the file is outside the web root.
	 */
	private static function computePublicUrl($absolutePath)
	{
		$root = Path::clean(JPATH_ROOT);
		$path = Path::clean($absolutePath);

		if (strpos($path, $root) !== 0)
		{
			return '';
		}

		$relative = ltrim(substr($path, strlen($root)), '/\\');
		$relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

		return rtrim(Uri::root(), '/') . '/' . $relative;
	}

	/**
	 * True if $absolutePath sits inside one of $allowedBases (dir-level) or
	 * realpath-matches one of $allowedFiles exactly. Compares realpaths on both
	 * sides, since upload folders may be symlinked outside the web root.
	 *
	 * @param   string  $absolutePath  The resolved (realpath) target
	 * @param   array   $allowedBases  Dir-level bases (e.g. temp folders)
	 * @param   array   $allowedFiles  Exact files that may be deleted
	 *
	 * @return  bool
	 */
	private static function authorizeAgainstFiles($absolutePath, array $allowedBases, array $allowedFiles)
	{
		foreach ($allowedBases as $base)
		{
			if (SafePath::isWithin($absolutePath, $base))
			{
				return true;
			}
		}

		foreach ($allowedFiles as $file)
		{
			$real = realpath($file);

			if ($real !== false && $real === $absolutePath)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Emit a 500 response with a translated message and stop execution.
	 *
	 * @param   string  $message
	 *
	 * @return  void
	 */
	private static function uploadDie($message)
	{
		http_response_code(500);
		die(Text::_($message));
	}
}
