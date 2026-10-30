<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework;

defined( '_JEXEC' ) or die( 'Restricted access' );

use Tassos\Framework\Mimes;
use Tassos\Framework\Security\SafePath;
use Joomla\Filesystem\File as JoomlaFile;
use Joomla\Filesystem\Path;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class File 
{
	/**
	 * Upload file
	 *
	 * @param	array	$file				The request file as posted by form
	 * @param	string	$upload_folder		The upload folder where the file must be uploaded
	 * @param	string	$allowed_file_types	A comma separated list of allowed file types like: .jpg, .gif, .png
	 * @param	bool	$allow_unsafe		Allow the upload of unsafe files. See JFilterInput::isSafeFile() method.
	 * @param	bool|string	$random_prefix	True => random unique prefix; a string is used as a literal prefix; null/false => none
	 * @param	bool|string	$random_suffix	True => random unique suffix; a string is used as a literal suffix; null/false => none
	 *
	 * @return	mixed	String on success, Null on failure
	 */
	public static function upload($file, $upload_folder = null, $allowed_file_types = [], $allow_unsafe = false, $random_prefix = null, $random_suffix = false)
	{
		// Make sure we have a valid file array
		if (!isset($file['name']) || !isset($file['tmp_name']) || !is_file($file['tmp_name']))
		{
			self::error(Text::sprintf('NR_UPLOAD_ERROR_CANNOT_UPLOAD_FILE', $file['name']));
		}

		// Check file type
		self::checkMimeOrDie($allowed_file_types, $file);

		/**
		 * Try transiterating the file name using the native php function
		 * 
		 * If the given filename is non-latin, then all characters will be removed from the filename via makeSafe and thus
		 * we wont be able to upload the file.
		 * 
		 * @see https://github.com/joomla/joomla-cms/pull/27974
		 */
		if (!defined('t_isJ5') && function_exists('transliterator_transliterate') && function_exists('iconv'))
		{
			// Using iconv to ignore characters that can't be transliterated
			$file['name'] = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", transliterator_transliterate('Any-Latin; Latin-ASCII;', $file['name']));
		}
		
		// Sanitize filename
		$filename = self::makeSafe($file['name']);

		// Documented as a bool toggle (true => random prefix). A string is also
		// accepted as a literal prefix (e.g. Convert Forms' 'cf_'). Bool true
		// must seed uniqid() with an empty string, otherwise (string) true
		// would leak a literal "1" into the name. null/false => no prefix.
		if ($random_prefix !== null && $random_prefix !== false)
		{
			$prefix = $random_prefix === true ? '' : $random_prefix;
			$filename = uniqid($prefix) . '_' . $filename;
		}

		// Mirror $random_prefix: true => random unique suffix (empty uniqid
		// seed); a string is used as a literal suffix; null/false => none.
		if ($random_suffix !== null && $random_suffix !== false)
		{
			$suffix    = $random_suffix === true ? '' : $random_suffix;
			$file_data = File::pathinfo($filename);
			$extension = isset($file_data['extension']) ? '.' . $file_data['extension'] : '';
			$filename  = $file_data['filename'] . '_' . uniqid($suffix) . $extension;
		}

		$filename = str_replace(' ', '_', $filename);

		// Setup the full file name
		$upload_folder = is_null($upload_folder) ? self::getTempFolder() : $upload_folder;
		$destination_file = implode(DIRECTORY_SEPARATOR, [$upload_folder, $filename]);

		// If file exists, rename to copy_X
		self::uniquefy($destination_file);

		$destination_file = Path::clean($destination_file);

		// Joomla\Filesystem\File::upload() only grew its own $allowUnsafe safety check in
		// Joomla 5; on 4 it takes three arguments and the fourth one below goes nowhere.
		// Run under the sanitized name, which is what actually lands on disk.
		$descriptor = array_merge(
			['type' => '', 'error' => 0, 'size' => 0],
			$file,
			['name' => basename($destination_file)]
		);

		if (!$allow_unsafe && !InputFilter::isSafeFile($descriptor))
		{
			self::error(Text::sprintf('NR_UPLOAD_ERROR_UNSAFE_FILE', $file['name']));
		}

		if (!JoomlaFile::upload($file['tmp_name'], $destination_file, false, $allow_unsafe))
		{
			self::error(Text::sprintf('NR_UPLOAD_ERROR_CANNOT_UPLOAD_FILE', $file['name']));
		}

		return $destination_file;
	}

	/**
	 * Moves a file from one directory to another. Destination directories will be created if they are not exist.
	 *
	 * @param	string	$source_file		The source file path
	 * @param	string	$destination_file	The destination file path
	 * @param	bool	$replace_existing	Replace same files names, otherwise create a copy in the format copy_X
	 * @param	bool	$hash				Whether to md5 hash the filename
	 * @param	array	$extra_roots		Additional absolute roots to trust, beyond JPATH_ROOT and tmp_path
	 *
	 * @return	mixed	String on success
	 */
	public static function move($source_file, $destination_file, $replace_existing = false, $hash = false, array $extra_roots = [])
	{
		// Reject sources or destinations outside JPATH_ROOT, the configured tmp_path
		// or a caller-supplied extra root.
		$destination_root = self::matchingAllowedRoot($destination_file, $extra_roots);

		if ($destination_root === '' || !self::isWithinAllowedRoots($source_file, $extra_roots))
		{
			self::error(Text::sprintf('NR_CANNOT_MOVE_FILE', basename($destination_file)));
		}

		$destination_folder = dirname($destination_file);

		// Before the folders are created, so a refused write leaves nothing behind.
		self::assertResolvedFolderIsContained($destination_folder, $destination_file, $destination_root, $extra_roots);

		// Create destination folders recursively
		if (!self::createDirs($destination_folder))
		{
			self::error(Text::sprintf('NR_CANNOT_CREATE_FOLDER', basename($destination_folder)));
		}

		// Don't replace files with the same name. Instead, append copy_x to this one.
		if (!$replace_existing)
		{
			self::uniquefy($destination_file, $hash);
		}

		// Move file to the destination folder
		if (!JoomlaFile::move($source_file, $destination_file))
		{
			self::error(Text::sprintf('NR_CANNOT_MOVE_FILE', basename($destination_file)));
		}

		return Path::clean($destination_file);
	}

	/**
	 * Copies a file from one directory to another.
	 * 
	 * @param	string	$source_file		The source file path
	 * @param	string	$destination_file	The destination file path
	 * @param	bool	$replace_existing	Replace same files names, otherwise create a copy in the format copy_X
	 * @param	bool	$hash				Whether to md5 hash the filename
	 * @param	array	$extra_roots		Additional absolute roots to trust, beyond JPATH_ROOT and tmp_path
	 *
	 * @return	mixed	String on success
	 */
	public static function copy($source_file, $destination_file, $replace_existing = false, $hash = false, array $extra_roots = [])
	{
		// Reject sources or destinations outside JPATH_ROOT, the configured tmp_path
		// or a caller-supplied extra root.
		$destination_root = self::matchingAllowedRoot($destination_file, $extra_roots);

		if ($destination_root === '' || !self::isWithinAllowedRoots($source_file, $extra_roots))
		{
			self::error(Text::sprintf('NR_CANNOT_MOVE_FILE', basename($destination_file)));
		}

		$destination_folder = dirname($destination_file);

		// Before the folders are created, so a refused write leaves nothing behind.
		self::assertResolvedFolderIsContained($destination_folder, $destination_file, $destination_root, $extra_roots);

		// Create destination folders recursively
		if (!self::createDirs($destination_folder))
		{
			self::error(Text::sprintf('NR_CANNOT_CREATE_FOLDER', basename($destination_folder)));
		}

		// Don't replace files with the same name. Instead, append copy_x to this one.
		if (!$replace_existing)
		{
			self::uniquefy($destination_file, $hash);
		}

		// Copy file to the destination folder
		if (!JoomlaFile::copy($source_file, $destination_file))
		{
			self::error(Text::sprintf('NR_CANNOT_MOVE_FILE', basename($destination_file)));
		}

		return Path::clean($destination_file);
	}

	/**
	 * Reads (and checks) the temp Joomla folder
	 *
	 * @return string
	 */
	public static function getTempFolder()
	{
		$ds = DIRECTORY_SEPARATOR;

		$tmpdir = (string) Factory::getConfig()->get('tmp_path');

		if ($tmpdir !== '' && realpath($tmpdir) == $ds . 'tmp')
		{
			$tmpdir = JPATH_SITE . $ds . 'tmp';
		}
		
		elseif (!is_dir($tmpdir))
		{
			$tmpdir = JPATH_SITE . $ds . 'tmp';
		}

		return Path::clean(trim($tmpdir) . $ds);
	}

	/**
	 * Checks if the path exists. If not creates the folders as well as subfolders.
	 * 
	 * @param   string  $path	 The folder path
	 * @param   string  $protect If set to true, each folder will be protected by disabling PHP engine and preventing folder browsing
	 * 
	 * @return  bool
	 */
	public static function createDirs($path, $protect = true)
	{
		if (!is_dir($path))
		{
			mkdir($path, 0755, true);

			// New folder created. Let's protect it.
			if ($protect)
			{
				self::writeHtaccessFile($path);
				self::writeIndexHtmlFile($path);
			}
		}

		// Make sure the folder is writable
		return @is_writable($path);
	}

	/**
	 * Checks whether a file type is in an allowed list
	 *
	 * @param	mixed	$allowed_types	Array or a comma separated list of allowed file extensions or mime types. Eg: .jpg, .png, applicaton/pdf
	 * @param	string	$file_object	The uploaded file as appears in the $_FILES array
	 *
	 * @return	bool
	 */
	public static function checkMimeOrDie($allowed_types, $file_object)
	{
		$file_path = $file_object['tmp_name'];
		$file_name = isset($file_object['name']) ? $file_object['name'] : basename($file_path);
		$safeFilename = strip_tags($file_name);
		$fileExtension = pathinfo($file_name, PATHINFO_EXTENSION);

		// Check if the file exists
		if (!is_file($file_path))
		{
			self::error(Text::sprintf('NR_UPLOAD_ERROR_CANNOT_UPLOAD_FILE', $safeFilename));
		}
		
		// First, validate file by its extension
		if (!Mimes::validateFileExtension($fileExtension, $allowed_types))
		{
			self::error(Text::sprintf('NR_UPLOAD_INVALID_FILE_EXT', $safeFilename, $fileExtension, $allowed_types));
		}

		// Do we have a mime type detected?
		if (!$mime_type = Mimes::detectFileType($file_path))
		{
			self::error(Text::sprintf('NR_UPLOAD_NO_MIME_TYPE', $safeFilename));
		}

		if (!Mimes::check($allowed_types, $mime_type))
		{
			self::error(Text::sprintf('NR_UPLOAD_INVALID_FILE_TYPE', $safeFilename, $mime_type, $allowed_types));
		}
	}

	/**
	 * Add an .htaccess file to the folder in order to disable PHP engine entirely 
	 *
	 * @param  string $path	The path where to write the file
	 *
	 * @return void
	 */
	public static function writeHtaccessFile($path)
	{
		$content = '
			# Block direct access to any PHP executable file type.
			# Matches php, php3..php9, phps, pht, phtml, phtm, phar and inc case-insensitively.
			# The trailing (\.|$) also catches the token in any position (shell.php.jpg,
			# shell.php.), closing the AddHandler multi-extension bypass, not just names ending in it.
			<FilesMatch "(?i)\.(php|php[0-9]|phps|pht|phtml|phtm|phar|inc)(\.|$)">
				<IfModule !mod_authz_core.c>
					Deny from all
				</IfModule>
				<IfModule mod_authz_core.c>
					Require all denied
				</IfModule>
			</FilesMatch>
		';

		JoomlaFile::write($path . '/.htaccess', $content);
	}

	/**
	 * Creates an empty index.html file to prevent directory listing 
	 *
	 * @param  string $path	The path where to write the file
	 *
	 * @return void
	 */
	public static function writeIndexHtmlFile($path)
	{
		$content = '<!DOCTYPE html><title></title>';
		
		JoomlaFile::write($path . '/index.html', $content);	
	}

	/**
	 * Generates a unique filename in case the give name already exists by appending copy_X suffix to filename.
	 *
	 * @param   string  $path	The path to the file.
	 * @param   bool	$hash	MD5 hashes the file name.
	 *
	 * @return  void
	 */
	public static function uniquefy(&$path, $hash = false)
	{
		$path_parts = self::pathinfo($path);

		$dir = $path_parts['dirname'];
		$ext = $path_parts['extension'];
		$actual_name = $path_parts['filename'];
		
		$original_name = $actual_name;

		// md5 hash the file name
		if ($hash)
		{
			$actual_name = md5($actual_name);

			// Initialize again the path due to md5 hash
			$path = $dir . '/' . $actual_name . '.' . $ext;
		}

		$i = 1;

		while(file_exists($dir . '/' . $actual_name . '.' . $ext))
		{           
			$actual_name = (string) $original_name . '_copy_' . $i;

			// md5 hash the file name
			if ($hash)
			{
				$actual_name = md5($actual_name);
			}
			
			$path = $dir . '/' . $actual_name . '.' . $ext;
			$i++;
		}
	}

	/**
	 * Sanitizes a file name, keeping its extension intact.
	 *
	 * Joomla's File::makeSafe() deletes every run of two or more dots instead of
	 * collapsing it (#(\.){2,}# => ''), so a name whose base part ends with a dot
	 * loses the separator in front of its extension. The German abbreviation "i.V."
	 * is the common case: "Vollmacht i.V..pdf" comes back as "Vollmacht i.Vpdf",
	 * which reads as a file of type "Vpdf". It is then stored without a usable
	 * extension, and every later extension check, such as the one Convert Forms
	 * runs on submit against the staged file, rejects it.
	 *
	 * The extension is therefore split off before sanitizing and appended again
	 * afterwards, reduced to alphanumerics of its own. Only the last segment counts
	 * as the extension, so "archive.tar.gz" and multi extension names such as
	 * "invoice.php.pdf" keep the exact shape they already had.
	 *
	 * @param   string  $name  The file name to sanitize. A directory part is not expected.
	 *
	 * @return  string
	 */
	public static function makeSafe($name)
	{
		$name = (string) $name;
		$dot  = strrpos($name, '.');

		// A leading dot marks a hidden file rather than an extension, and
		// JoomlaFile::makeSafe() strips it on purpose. Leave that case to it.
		if ($dot === false || $dot === 0)
		{
			return JoomlaFile::makeSafe($name);
		}

		$extension = preg_replace('#[^A-Za-z0-9]#', '', substr($name, $dot + 1));
		$basename  = JoomlaFile::makeSafe(substr($name, 0, $dot));

		if ($extension === '')
		{
			return $basename;
		}

		// Nothing survived sanitization, for instance a name made up entirely of
		// characters that could not be transliterated. Fall back to a placeholder
		// so the result stays a regular file instead of turning into a hidden one.
		if ($basename === '')
		{
			$basename = 'file';
		}

		return $basename . '.' . $extension;
	}

	/**
	 * Returns information about a file path with multi-byte support
	 *
	 * @param  string $path   The path to be parsed.
	 *
	 * @return array 
	 */
	public static function pathinfo($path)
	{
		// Store temporary the currenty locale
		$currentLocale = setlocale(LC_ALL, 0);

		setlocale(LC_ALL, 'C.UTF-8');
		$pathinfo = pathinfo($path);

		// Set back to previus value
		setlocale(LC_ALL, $currentLocale);

		return $pathinfo;
	}

	/**
	 * Force download of the exported file
	 *
	 * @return void
	 */
	public static function download($filename, $path = null)
	{
        $base = is_null($path) ? self::getTempFolder() : $path;

		// Confine the requested filename inside $base. absoluteWithin() rejects
		// null bytes, traversal sequences and absolute paths, then re-validates
		// the resolved path with realpath() on both sides and returns the validated path.
		try
		{
			$filename = SafePath::absoluteWithin($filename, $base);
		}
		catch (\Throwable $e)
		{
			self::error('Invalid filename');
		}

		if (!is_file($filename))
		{
			self::error('Invalid filename');
		}

		error_reporting(0);

		// Send the appropriate headers to force the download in the browser
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Cache-Control: public', false);
		header('Pragma: public');
		header('Content-Length: ' . @filesize($filename));

        // Clear the output buffer and disable output buffering
        ob_clean();
        flush();

		// Read exported file to buffer
		readfile($filename);

		// Don't leave any clues on the server. Delete the file.
		JoomlaFile::delete($filename);

		jexit();
	}

	/**
	 * Stream a persistent file to the browser and exit.
	 *
	 * Unlike download(), this does NOT delete the file afterwards. It takes an absolute
	 * path plus the base it has to resolve inside, so it cannot be called without a
	 * containment check.
	 *
	 * @param	string	$absolute_path	The absolute path of the file to stream
	 * @param	string	$base_dir		The directory the file must resolve inside
	 * @param	array	$options		filename: the name sent to the browser
	 *									inline: allow inline delivery, see streamHeaders()
	 *									chunk_size: read buffer in bytes, default 1MB
	 *
	 * @return	void	Always exits.
	 */
	public static function stream($absolute_path, $base_dir, $options = [])
	{
		if (!self::isStreamable($absolute_path, $base_dir))
		{
			self::error('Invalid file');
		}

		$chunk_size = isset($options['chunk_size']) ? (int) $options['chunk_size'] : 1048576;

		foreach (self::streamHeaders($absolute_path, $options) as $header)
		{
			header($header);
		}

		// Joomla nests output buffers, so a single ob_clean() would leave the file
		// prepended with buffered page output.
		while (ob_get_level())
		{
			ob_end_clean();
		}

		// A large file must not be truncated by the time limit, nor keep reading once the
		// visitor has gone away.
		ignore_user_abort(true);
		@set_time_limit(0);

		$handle = @fopen($absolute_path, 'rb');

		if ($handle === false)
		{
			self::error('Invalid file');
		}

		// Chunked rather than readfile(), which would hold the whole file in memory.
		while (!feof($handle))
		{
			echo fread($handle, $chunk_size > 0 ? $chunk_size : 1048576);
			flush();

			if (connection_aborted())
			{
				break;
			}
		}

		fclose($handle);

		jexit();
	}

	/**
	 * Whether stream() may serve this file.
	 *
	 * Containment is realpath-based on both ends: a symlink inside the base that points
	 * out of it must not be followed. Callers reach here with paths that were only ever
	 * confined lexically, so this is the check that decides.
	 *
	 * Split out from stream() because stream() exits and so cannot be unit tested.
	 *
	 * @param	string	$absolute_path	The absolute path of the file to stream
	 * @param	string	$base_dir		The directory the file must resolve inside
	 *
	 * @return	bool
	 */
	public static function isStreamable($absolute_path, $base_dir)
	{
		return SafePath::isWithin($absolute_path, $base_dir) && is_file($absolute_path);
	}

	/**
	 * Build the response headers for stream().
	 *
	 * Split out from stream() because stream() exits and so cannot be unit tested.
	 *
	 * @param	string	$absolute_path	The absolute path of the file being streamed
	 * @param	array	$options		filename: the name sent to the browser
	 *
	 * @return	array	The headers, ready to pass to header()
	 */
	public static function streamHeaders($absolute_path, $options = [])
	{
		$filename = isset($options['filename']) && $options['filename'] !== ''
			? $options['filename']
			: basename($absolute_path);

		// Strip anything that could break out of the header value or forge a second header.
		$filename = str_replace(["\r", "\n", "\0", '"'], '', $filename);

		// ASCII fallback for old clients, plus RFC 5987 for everything else. Substituted
		// per character, not per byte, so one multi-byte character does not become a run
		// of underscores; the byte-wise pass covers names that are not valid UTF-8.
		$ascii_filename = preg_replace('/[^\x20-\x7E]/u', '_', $filename);

		if ($ascii_filename === null)
		{
			$ascii_filename = preg_replace('/[^\x20-\x7E]/', '_', $filename);
		}

		$type        = self::inlineImageType($absolute_path, !empty($options['inline']));
		$disposition = $type === '' ? 'attachment' : 'inline';

		return [
			'Content-Description: File Transfer',
			'Content-Type: ' . ($type === '' ? 'application/octet-stream' : $type),
			'Content-Disposition: ' . $disposition . '; filename="' . $ascii_filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
			'Content-Length: ' . @filesize($absolute_path),
			// Uploads can be private, so no shared cache may keep a copy.
			'Cache-Control: private, no-store, must-revalidate',
			'Pragma: no-cache',
			'Expires: 0',
			// Costs nothing alongside inline delivery: the declared type is read from the
			// file itself, so there is nothing to sniff towards.
			'X-Content-Type-Options: nosniff',
			'Accept-Ranges: none'
		];
	}

	/**
	 * The content type to serve a file inline as, or '' to send it as a download.
	 *
	 * Limited to raster images, typed from the file's own bytes rather than its name:
	 * anything a browser could execute in the site's origin (SVG, HTML) stays a download,
	 * whatever it is called.
	 *
	 * @param   string  $absolute_path  The file to inspect
	 * @param   bool    $allowed        Whether the caller permits inline at all
	 *
	 * @return  string
	 */
	private static function inlineImageType($absolute_path, $allowed)
	{
		if (!$allowed)
		{
			return '';
		}

		$type = strtolower(trim((string) Mimes::detectFileType($absolute_path)));

		// Detection can return the type with charset parameters attached.
		if (($semicolon = strpos($type, ';')) !== false)
		{
			$type = trim(substr($type, 0, $semicolon));
		}

		return in_array($type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif']) ? $type : '';
	}

	/**
	 * Delete a file.
	 *
	 * Accepts either an absolute path or a path relative to JPATH_ROOT. The
	 * resolved path must sit inside an allowed root — see isWithinAllowedRoots().
	 *
	 * @param   string  $filename		The absolute or JPATH_ROOT-relative file path
	 * @param   array   $extra_roots	Additional absolute roots to trust, beyond JPATH_ROOT and tmp_path
	 *
	 * @return  bool
	 */
	public static function delete($filename, array $extra_roots = [])
	{
		if (empty($filename) || !is_string($filename))
		{
			return;
		}

		if (\Tassos\Framework\Security\SafePath::isAbsolute($filename))
		{
			$file = Path::clean($filename);
		}
		else
		{
			// Defense-in-depth: reject traversal sequences on relative input
			try
			{
				\Tassos\Framework\Security\SafePath::sanitize($filename);
			}
			catch (\Throwable $e)
			{
				return false;
			}

			$file = Path::clean(implode(DIRECTORY_SEPARATOR, [JPATH_ROOT, $filename]));
		}

		if (!self::isWithinAllowedRoots($file, $extra_roots))
		{
			return false;
		}

		if (!is_file($file))
		{
			return;
		}

		return JoomlaFile::delete($file);
	}

	/**
	 * True if $file sits inside JPATH_ROOT, the configured tmp_path, or one of the
	 * caller-supplied extra roots.
	 *
	 * Extra roots let a caller opt into a location outside the site, such as an
	 * admin-configured upload folder above the web root. They are passed per call
	 * on purpose: a process-wide allowlist would leak one extension's trusted root
	 * into every other caller for the rest of the request.
	 *
	 * @param	string	$file			The file path to validate
	 * @param	array	$extra_roots	Additional absolute roots to trust, for this call only
	 *
	 * @return	bool
	 */
	private static function isWithinAllowedRoots($file, array $extra_roots = [])
	{
		return self::matchingAllowedRoot($file, $extra_roots) !== '';
	}

	/**
	 * The first allowed root $file sits inside, or '' when there is none.
	 *
	 * The caller needs to know which root matched, not merely that one did: a later
	 * re-check against the union would accept a different root than the one that
	 * authorised the write.
	 *
	 * @param	string	$file			The file path to validate
	 * @param	array	$extra_roots	Additional absolute roots to trust, for this call only
	 *
	 * @return	string
	 */
	private static function matchingAllowedRoot($file, array $extra_roots = [])
	{
		$tmp_path = (string) Factory::getConfig()->get('tmp_path');

		foreach ([JPATH_ROOT, $tmp_path] as $root)
		{
			if ($root !== '' && SafePath::isWithinLexical($file, $root))
			{
				return $root;
			}
		}

		foreach ($extra_roots as $root)
		{
			if (self::isTrustableRoot($root) && SafePath::isWithinLexical($file, $root))
			{
				return $root;
			}
		}

		return '';
	}

	/**
	 * True if $root is safe to use as an additional trusted root.
	 *
	 * Extra roots originate from extension configuration, so they are validated
	 * rather than trusted. An empty, relative or filesystem-root value would widen
	 * the containment check to the entire filesystem and turn the guard into a no-op.
	 *
	 * @param	mixed	$root	The candidate root
	 *
	 * @return	bool
	 */
	private static function isTrustableRoot($root)
	{
		if (!is_string($root) || $root === '' || SafePath::hasTraversal($root) || !SafePath::isAbsolute($root))
		{
			return false;
		}

		// Path::clean() does not resolve '..', so a root that merely cleans down to
		// the filesystem root is rejected here rather than silently trusted.
		return rtrim(Path::clean($root), DIRECTORY_SEPARATOR) !== '';
	}

	/**
	 * Assert containment on the resolved destination folder.
	 *
	 * matchingAllowedRoot() is lexical and cannot see through symlinks, so a symlink
	 * planted inside a trusted root could redirect a write outside it — while the read
	 * side (SafePath::absoluteWithin) realpaths both ends and would refuse to serve that
	 * same file. This closes that asymmetry.
	 *
	 * Resolves the deepest ancestor that already exists, so it can run before the folders
	 * are created: a write that is going to be refused must not leave real folders on
	 * disk. Checked against the one root that authorised the write, since the union would
	 * still accept an escape landing back inside the site.
	 *
	 * Runs only when that root is a caller-supplied one. In-site writes keep exactly the
	 * behaviour they have always had, including installs that legitimately symlink folders
	 * such as media/ to another volume.
	 *
	 * @param	string	$destination_folder	The destination folder, which may not exist yet
	 * @param	string	$destination_file	The destination file, used for the error message
	 * @param	string	$root				The root that authorised the write
	 * @param	array	$extra_roots		The extra roots the caller opted into
	 *
	 * @return	void
	 */
	private static function assertResolvedFolderIsContained($destination_folder, $destination_file, $root, array $extra_roots)
	{
		if (!in_array($root, $extra_roots, true))
		{
			return;
		}

		$resolved  = realpath(self::deepestExistingAncestor($destination_folder));
		$real_root = realpath($root);

		if ($resolved === false || $real_root === false || !SafePath::isWithinLexical($resolved, $real_root))
		{
			self::error(Text::sprintf('NR_CANNOT_MOVE_FILE', basename($destination_file)));
		}
	}

	/**
	 * The deepest ancestor of $path that exists on disk, or '' when none does.
	 *
	 * @param	string	$path
	 *
	 * @return	string
	 */
	private static function deepestExistingAncestor($path)
	{
		$path = rtrim(Path::clean($path), DIRECTORY_SEPARATOR);

		while ($path !== '' && !is_dir($path))
		{
			$parent = dirname($path);

			if ($parent === $path)
			{
				return '';
			}

			$path = $parent;
		}

		return $path;
	}

	/**
	 * Throw a sanitized exception
	 *
	 * @param	string	$error
	 *
	 * @return	void
	 */
	private static function error($error)
	{
		throw new \Exception(htmlspecialchars($error, ENT_QUOTES, 'UTF-8'));
	}
}