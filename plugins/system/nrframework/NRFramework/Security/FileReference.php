<?php

/**
 * @author          Tassos.gr <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Security;

defined('_JEXEC') or die('Restricted access');

/**
 * Resolves a client-submitted file reference to a validated absolute path.
 *
 * Two input formats:
 *   1. FileToken — encrypted, self-contained (carries its own base).
 *   2. Legacy path — base64-encoded or plain text, validated against the
 *      caller-supplied $allowedBases.
 *
 * NOTE: $allowedBases is enforced ONLY for the legacy path-resolution branch.
 * A valid FileToken returns its embedded path without consulting $allowedBases —
 * authority comes from the encrypted payload itself.
 */
class FileReference
{
	/**
	 * Resolve a client-submitted file reference to a validated absolute path.
	 *
	 * @param   string  $input         The client-submitted value (token, base64 path, or plain path)
	 * @param   array   $allowedBases  Absolute paths of directories allowed for legacy path resolution
	 *
	 * @return  string  The resolved absolute file path
	 *
	 * @throws  \RuntimeException  If the reference cannot be resolved safely
	 */
	public static function resolve($input, array $allowedBases)
	{
		if (empty($input) || !is_string($input))
		{
			throw new \RuntimeException('Empty file reference');
		}

		$input = trim($input);

		// 1. Try to resolve as a FileToken
		try
		{
			$fileToken = new FileToken();
			return $fileToken->read($input);
		}
		catch (\Throwable $e)
		{
			// Not a valid token — fall through to legacy resolution
		}

		// 2. Legacy resolution: try base64 decode, then plain text
		$path = self::decodeLegacyInput($input);

		// 3. Validate the decoded path against each allowed base
		return self::resolveAgainstBases($path, $allowedBases);
	}

	/**
	 * Lexically confine a client reference (token-less base64 or plain path) to
	 * $allowedBases without filesystem access, for targets that may not exist on
	 * disk. Returns the cleaned absolute path, or false when out of bounds.
	 *
	 * @param   string  $reference      The client-submitted value (base64 or plain path)
	 * @param   array   $allowedBases   Absolute base directories it must sit within
	 * @param   bool    $allowAbsolute  Whether an absolute reference is accepted
	 *
	 * @return  string|false  The cleaned absolute path, or false when out of bounds
	 */
	public static function landsInside($reference, array $allowedBases, $allowAbsolute = true)
	{
		if (!is_string($reference) || $reference === '')
		{
			return false;
		}

		$path = self::decodeLegacyInput(trim($reference));

		if (SafePath::hasTraversal($path))
		{
			return false;
		}

		$candidates = [];
		if (SafePath::isAbsolute($path))
		{
			if (!$allowAbsolute)
			{
				return false;
			}

			$candidates[] = $path;
		}
		// JPATH_ROOT-relative fallback for extensions that strip JPATH_ROOT,
		// producing values like "/tmp/file.png" really at JPATH_ROOT . "/tmp/file.png".
		$candidates[] = JPATH_ROOT . DIRECTORY_SEPARATOR . ltrim($path, '/\\');

		foreach ($allowedBases as $base)
		{
			foreach ($candidates as $candidate)
			{
				if (SafePath::isWithinLexical($candidate, $base))
				{
					return \Joomla\Filesystem\Path::clean($candidate);
				}
			}
		}

		return false;
	}

	/**
	 * Decode base64 if the input round-trips cleanly; otherwise treat as plain.
	 *
	 * @param   string  $input
	 *
	 * @return  string  The decoded path (relative or absolute)
	 */
	public static function decodeLegacyInput($input)
	{
		// Only accept the decoded value when it round-trips back to the input
		$decoded = base64_decode($input, true);

		if ($decoded !== false && $decoded !== $input && base64_encode($decoded) === $input)
		{
			return $decoded;
		}

		return $input;
	}

	/**
	 * Validate $path against $allowedBases.
	 *
	 * For absolute paths: verify the path is within one of the allowed bases using realpath.
	 * For relative paths: try SafePath::absoluteWithin() against each base.
	 * As a fallback, treat the path as JPATH_ROOT-relative (old extensions strip JPATH_ROOT
	 * from paths, producing values like "/tmp/file.jpg" or "media/dir/file.jpg").
	 *
	 * @param   string  $path          The decoded path (may be absolute or relative)
	 * @param   array   $allowedBases  Allowed base directories (absolute paths)
	 *
	 * @return  string  The validated absolute path
	 *
	 * @throws  \RuntimeException  If the path doesn't resolve within any allowed base
	 */
	private static function resolveAgainstBases($path, array $allowedBases)
	{
		// Reject null bytes and traversal sequences early
		if (SafePath::hasTraversal($path))
		{
			throw new \RuntimeException('Invalid path');
		}

		// Handle absolute paths (from legacy CF which stores absolute temp paths)
		if (SafePath::isAbsolute($path))
		{
			foreach ($allowedBases as $base)
			{
				if (SafePath::isWithin($path, $base))
				{
					$realPath = realpath($path);

					if ($realPath === false)
					{
						throw new \RuntimeException('Path does not exist');
					}

					return $realPath;
				}
			}

			// Don't throw yet — fall through to JPATH_ROOT-relative fallback
		}
		else
		{
			// Handle relative paths
			foreach ($allowedBases as $base)
			{
				try
				{
					return SafePath::absoluteWithin($path, $base);
				}
				catch (\Throwable $e)
				{
					// Not within this base — try next
					continue;
				}
			}
		}

		return self::resolveAsJrootRelative($path, $allowedBases);
	}

	/**
	 * Reattach JPATH_ROOT to the input and revalidate against $allowedBases.
	 * Covers old extensions that strip JPATH_ROOT, producing values like
	 * "/tmp/file.jpg" or "media/acfupload/tmp/file.jpg".
	 *
	 * @param   string  $path          The decoded path
	 * @param   array   $allowedBases  Allowed base directories (absolute paths)
	 *
	 * @return  string  The validated absolute path
	 *
	 * @throws  \RuntimeException  If the path doesn't resolve within any allowed base
	 */
	private static function resolveAsJrootRelative($path, array $allowedBases)
	{
		$stripped = ltrim($path, '/\\');

		if (empty($stripped))
		{
			throw new \RuntimeException('Path is outside all allowed directories');
		}

		$jrootPath = \Joomla\Filesystem\Path::clean(JPATH_ROOT . DIRECTORY_SEPARATOR . $stripped);

		foreach ($allowedBases as $base)
		{
			if (SafePath::isWithin($jrootPath, $base))
			{
				$realPath = realpath($jrootPath);

				if ($realPath === false)
				{
					throw new \RuntimeException('Path does not exist');
				}

				return $realPath;
			}
		}

		throw new \RuntimeException('Path is outside all allowed directories');
	}
}
