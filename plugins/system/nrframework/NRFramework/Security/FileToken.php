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
 * Authenticated, opaque file reference token, safe to send to the client. Only
 * the server can open it (AES-256-GCM under a key derived from the Joomla site
 * secret and the token's purpose).
 *
 * Plaintext layout, all integers big endian:
 *
 *   flags    C            bit 0 = base present, other bits reserved
 *   expiry   N            unix seconds, 0 = never
 *   path     n + bytes
 *   base     n + bytes    only when bit 0 is set
 *   context  C count, then per pair: C + key bytes, C + value bytes
 *
 * The base is optional: the path is relative to it, so read() can resolve against
 * a base other than the one the token was issued with, and a caller that always
 * supplies one can leave it out. That is what keeps links working when the base is
 * an admin setting the admin then changes.
 *
 * The purpose scopes the key, so a token issued for one flow cannot be opened in
 * another.
 */
class FileToken
{
	/**
	 * Default time-to-live in seconds (24 hours).
	 *
	 * @var int
	 */
	const DEFAULT_TTL = 86400;

	/**
	 * Expiry value meaning the token never expires.
	 *
	 * @var int
	 */
	const NEVER_EXPIRES = 0;

	/**
	 * Purpose of the framework's own upload and delete flow. Also the default, so
	 * extension releases predating the purpose argument keep working.
	 *
	 * @var string
	 */
	const PURPOSE_UPLOAD = 'tf.file.upload';

	/**
	 * Flag bit marking a payload that carries its base.
	 *
	 * @var int
	 */
	const FLAG_BASE = 1;

	/**
	 * @var Encryptor
	 */
	private $encryptor;

	/**
	 * @var string
	 */
	private $purpose;

	/**
	 * @param   string  $purpose  Scopes the sealing key to one flow
	 */
	public function __construct($purpose = self::PURPOSE_UPLOAD)
	{
		$this->encryptor = new Encryptor();
		$this->purpose   = $purpose;
	}

	/**
	 * Issue a token binding $absolutePath to $baseDir, plus optional context.
	 *
	 * @param   string  $absolutePath  The absolute path to the file
	 * @param   string  $baseDir       The allowed base directory (absolute)
	 * @param   array   $context       Optional context metadata (e.g. ['field_id' => 123])
	 * @param   int     $ttl           Time-to-live in seconds. Exactly zero never expires.
	 * @param   bool    $embedBase     Carry the base in the payload. Pass false when the
	 *                                 caller owns the base and will supply it to read(),
	 *                                 which keeps it out of the token entirely.
	 *
	 * @return  string  The sealed token string
	 *
	 * @throws  \RuntimeException  If the path is not within $baseDir
	 */
	public function issue($absolutePath, $baseDir, $context = [], $ttl = self::DEFAULT_TTL, $embedBase = true)
	{
		$realBase = realpath($baseDir);

		if ($realBase === false || !is_dir($realBase))
		{
			throw new \RuntimeException('Base directory does not exist');
		}

		$relativePath = SafePath::relativeTo($absolutePath, $baseDir);

		return $this->encryptor->seal(
			$this->purpose,
			$this->packPayload(
				$relativePath,
				$embedBase ? $realBase : '',
				(array) $context,
				$this->expiryFor($ttl)
			)
		);
	}

	/**
	 * Resolve a token to its absolute file path. Validates integrity, expiry
	 * and that the path is still inside its base.
	 *
	 * By default the embedded base confines the path. A caller whose base is a site
	 * setting passes the one in force as $baseDir instead, so moving the storage folder
	 * does not kill links already sent; callers with no setting to consult leave it null.
	 *
	 * Either way the path is confined, and an override is a base the caller owns, never
	 * one that arrives with the request.
	 *
	 * @param   string       $token    The sealed token string
	 * @param   string|null  $baseDir  Confine to this base instead of the embedded one
	 *
	 * @return  string  The resolved absolute file path
	 *
	 * @throws  \RuntimeException  On an unreadable token, expiry, or path violation
	 */
	public function read($token, $baseDir = null)
	{
		$payload = $this->openPayload($token);

		$this->validateExpiry($payload);

		$baseDir = is_string($baseDir) && $baseDir !== '' ? $baseDir : $payload['base'];

		// With neither an embedded base nor a supplied one there is nothing to confine
		// the path to, and guessing is the silent fallback this class exists to avoid.
		if ($baseDir === '')
		{
			throw new \RuntimeException('No base directory to resolve the token against');
		}

		if (!is_dir($baseDir))
		{
			throw new \RuntimeException('Token base directory no longer exists');
		}

		return SafePath::absoluteWithin($payload['path'], $baseDir);
	}

	/**
	 * Open the token and return its context array (without resolving the path).
	 *
	 * @param   string  $token  The sealed token string
	 *
	 * @return  array  The context array
	 *
	 * @throws  \RuntimeException  When the token cannot be opened
	 */
	public function context($token)
	{
		return $this->openPayload($token)['context'];
	}

	/**
	 * Compute the expiry stamp for a TTL.
	 *
	 * @param   int  $ttl  Registry params arrive as strings, so this is cast
	 *
	 * @return  int
	 */
	private function expiryFor($ttl)
	{
		$ttl = (int) $ttl;

		if ($ttl === 0)
		{
			return self::NEVER_EXPIRES;
		}

		// A negative TTL yields an already-expired token, so a caller computing
		// `$expiry - time()` that goes negative fails closed. Floored at 1 because
		// 0 is taken, and because the field is unsigned on the wire.
		return max(1, time() + $ttl);
	}

	/**
	 * Serialize the payload to its wire bytes.
	 *
	 * @param   string  $path     Relative path
	 * @param   string  $base     Absolute base, or '' to leave it out
	 * @param   array   $context  String-keyed metadata
	 * @param   int     $expiry   Unix seconds, or 0 for never
	 *
	 * @return  string
	 *
	 * @throws  \RuntimeException  When a field is too long for its length prefix
	 */
	private function packPayload($path, $base, array $context, $expiry)
	{
		$flags = $base === '' ? 0 : self::FLAG_BASE;

		$out = pack('C', $flags) . pack('N', $expiry) . $this->packField($path, 'n', 65535);

		if ($flags & self::FLAG_BASE)
		{
			$out .= $this->packField($base, 'n', 65535);
		}

		if (count($context) > 255)
		{
			throw new \RuntimeException('Token context is too large');
		}

		$out .= pack('C', count($context));

		foreach ($context as $key => $value)
		{
			$out .= $this->packField((string) $key, 'C', 255) . $this->packField((string) $value, 'C', 255);
		}

		return $out;
	}

	/**
	 * Length-prefix one field.
	 *
	 * @param   string  $value
	 * @param   string  $format  pack() format of the length prefix
	 * @param   int     $max     Largest length that prefix can express
	 *
	 * @return  string
	 *
	 * @throws  \RuntimeException
	 */
	private function packField($value, $format, $max)
	{
		if (strlen($value) > $max)
		{
			throw new \RuntimeException('Token field is too long');
		}

		return pack($format, strlen($value)) . $value;
	}

	/**
	 * Open the token and parse its payload.
	 *
	 * @param   string  $token
	 *
	 * @return  array  ['path' => .., 'base' => .., 'context' => .., 'expiry' => ..]
	 *
	 * @throws  \RuntimeException
	 */
	private function openPayload($token)
	{
		if (empty($token) || !is_string($token))
		{
			throw new \RuntimeException('Invalid token');
		}

		$raw = $this->encryptor->open($this->purpose, $token);

		if ($raw === null)
		{
			throw new \RuntimeException('Invalid token');
		}

		$cursor  = 0;
		$flags   = ord($this->take($raw, $cursor, 1));

		// An unknown bit means a payload shape this reader cannot account for.
		if ($flags & ~self::FLAG_BASE)
		{
			throw new \RuntimeException('Invalid token payload');
		}

		$expiry  = unpack('N', $this->take($raw, $cursor, 4))[1];
		$path    = $this->takeField($raw, $cursor, 'n', 2);
		$base    = $flags & self::FLAG_BASE ? $this->takeField($raw, $cursor, 'n', 2) : '';
		$context = [];

		for ($i = ord($this->take($raw, $cursor, 1)); $i > 0; $i--)
		{
			$key = $this->takeField($raw, $cursor, 'C', 1);

			$context[$key] = $this->takeField($raw, $cursor, 'C', 1);
		}

		if ($cursor !== strlen($raw))
		{
			throw new \RuntimeException('Invalid token payload');
		}

		return [
			'path'    => $path,
			'base'    => $base,
			'context' => $context,
			'expiry'  => $expiry
		];
	}

	/**
	 * Read $length bytes and advance the cursor.
	 *
	 * @param   string  $raw
	 * @param   int     $cursor
	 * @param   int     $length
	 *
	 * @return  string
	 *
	 * @throws  \RuntimeException  When the payload is shorter than it claims
	 */
	private function take($raw, &$cursor, $length)
	{
		if ($cursor + $length > strlen($raw))
		{
			throw new \RuntimeException('Invalid token payload');
		}

		$bytes   = substr($raw, $cursor, $length);
		$cursor += $length;

		return $bytes;
	}

	/**
	 * Read one length-prefixed field and advance the cursor.
	 *
	 * @param   string  $raw
	 * @param   int     $cursor
	 * @param   string  $format       unpack() format of the length prefix
	 * @param   int     $prefixBytes  Width of that prefix
	 *
	 * @return  string
	 *
	 * @throws  \RuntimeException
	 */
	private function takeField($raw, &$cursor, $format, $prefixBytes)
	{
		$length = unpack($format, $this->take($raw, $cursor, $prefixBytes))[1];

		return $length === 0 ? '' : $this->take($raw, $cursor, $length);
	}

	/**
	 * Validate that the token has not expired.
	 *
	 * @param   array  $payload
	 *
	 * @throws  \RuntimeException
	 */
	private function validateExpiry($payload)
	{
		if ($payload['expiry'] === self::NEVER_EXPIRES)
		{
			return;
		}

		if (time() > $payload['expiry'])
		{
			throw new \RuntimeException('Token has expired');
		}
	}
}
