<?php

/**
 * @author          Tassos.gr <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Security;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Encrypt\Aes;
use Joomla\CMS\Factory;

/**
 * Site-bound encryption using Joomla's site secret.
 * Data encrypted with this class can only be decrypted on the same Joomla installation.
 */
class Encryptor
{
	/**
	 * Encryption key size in bits.
	 *
	 * @var int
	 */
	protected $keySize = 256;

	/**
	 * AEAD used by seal()/open().
	 *
	 * @var string
	 */
	const AEAD_CIPHER = 'aes-256-gcm';

	/**
	 * GCM nonce length in bytes.
	 *
	 * @var int
	 */
	const AEAD_NONCE_BYTES = 12;

	/**
	 * GCM authentication tag length in bytes.
	 *
	 * @var int
	 */
	const AEAD_TAG_BYTES = 16;

	/**
	 * Derived keys, by subclass and purpose. The secret does not change within a
	 * request, and HKDF runs on every seal() and open().
	 *
	 * @var array
	 */
	private static $derivedKeys = [];

	/**
	 * Encrypts a string.
	 *
	 * @param   string  $data  Data to encrypt
	 *
	 * @return  string  Encrypted data (URL-safe base64 encoded)
	 *
	 * @throws  \RuntimeException
	 */
	public function encrypt($data)
	{
		if (empty($data))
		{
			return '';
		}

		$aes = $this->createAes();
		$encrypted = $aes->encryptString($data, true);
		// encryptString may also return false on failure
		// so we need to throw an exception manually
		if ($encrypted === false)
		{
			throw new \RuntimeException('Encryption failed');
		}

		return self::toUrlSafe($encrypted);
	}

	/**
	 * Decrypts a string.
	 *
	 * @param   string  $data  Data to decrypt (URL-safe base64 encoded)
	 *
	 * @return  string  Decrypted data
	 *
	 * @throws  \RuntimeException
	 */
	public function decrypt($data)
	{
		if (empty($data))
		{
			return '';
		}

		$data = self::fromUrlSafe($data);

		$aes = $this->createAes();
		$decrypted = $aes->decryptString($data);

		if ($decrypted === false)
		{
			throw new \RuntimeException('Decryption failed');
		}

		return $decrypted;
	}

	/**
	 * Encrypts data and returns it as a JSON string.
	 *
	 * @param   mixed  $data  Data to encrypt (will be JSON encoded)
	 *
	 * @return  string  Encrypted JSON data
	 *
	 * @throws  \RuntimeException
	 */
	public function encryptJson($data)
	{
		$json = json_encode($data);

		if ($json === false)
		{
			throw new \RuntimeException('Failed to JSON encode data: ' . json_last_error_msg());
		}

		return $this->encrypt($json);
	}

	/**
	 * Decrypts a JSON string and returns the decoded data.
	 *
	 * @param   string  $data  Encrypted JSON data
	 *
	 * @return  mixed  Decrypted and JSON decoded data
	 *
	 * @throws  \RuntimeException
	 */
	public function decryptJson($data)
	{
		$decrypted = $this->decrypt($data);
		if (empty($decrypted))
		{
			return null;
		}

		$decoded = json_decode($decrypted, true);

		if ($decoded === null && json_last_error() !== JSON_ERROR_NONE)
		{
			throw new \RuntimeException('Failed to JSON decode data: ' . json_last_error_msg());
		}

		return $decoded;
	}

	/**
	 * Seal a string under a purpose-scoped key.
	 *
	 * The purpose is both the HKDF info and the GCM additional authenticated data,
	 * so a blob sealed for one purpose cannot be opened under another.
	 *
	 * @param   string  $purpose  Purpose label, e.g. 'tf.file.upload'
	 * @param   string  $plain    The bytes to seal
	 *
	 * @return  string  nonce || ciphertext || tag, URL-safe base64
	 *
	 * @throws  \RuntimeException
	 */
	public function seal($purpose, $plain)
	{
		$nonce = random_bytes(self::AEAD_NONCE_BYTES);
		$tag   = '';

		$ciphertext = openssl_encrypt(
			$plain,
			self::AEAD_CIPHER,
			$this->deriveKey($purpose),
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			$purpose,
			self::AEAD_TAG_BYTES
		);

		if ($ciphertext === false)
		{
			throw new \RuntimeException('Encryption failed');
		}

		return self::toUrlSafe(base64_encode($nonce . $ciphertext . $tag));
	}

	/**
	 * Open a sealed string.
	 *
	 * Returns null rather than throwing: callers probe arbitrary submitted values,
	 * so "not ours" is an expected outcome, not a fault.
	 *
	 * @param   string  $purpose  The purpose it was sealed under
	 * @param   string  $token    URL-safe base64 of nonce || ciphertext || tag
	 *
	 * @return  string|null  The plaintext, or null when it is not ours
	 *
	 * @throws  \RuntimeException  When the site secret is unavailable
	 */
	public function open($purpose, $token)
	{
		if (!is_string($token) || $token === '')
		{
			return null;
		}

		$sealed = base64_decode(self::fromUrlSafe($token), true);

		if ($sealed === false || strlen($sealed) <= self::AEAD_NONCE_BYTES + self::AEAD_TAG_BYTES)
		{
			return null;
		}

		$plain = openssl_decrypt(
			substr($sealed, self::AEAD_NONCE_BYTES, -self::AEAD_TAG_BYTES),
			self::AEAD_CIPHER,
			$this->deriveKey($purpose),
			OPENSSL_RAW_DATA,
			substr($sealed, 0, self::AEAD_NONCE_BYTES),
			substr($sealed, -self::AEAD_TAG_BYTES),
			$purpose
		);

		return $plain === false ? null : $plain;
	}

	/**
	 * Derive the sealing key for a purpose from the secret.
	 *
	 * @param   string  $purpose
	 *
	 * @return  string  32 raw bytes
	 *
	 * @throws  \RuntimeException
	 */
	protected function deriveKey($purpose)
	{
		if (!is_string($purpose) || $purpose === '')
		{
			throw new \RuntimeException('A sealing purpose is required');
		}

		// Subclasses answer getSecret() differently, so the cache is per subclass.
		$cacheKey = static::class . "\0" . $purpose;

		if (!isset(self::$derivedKeys[$cacheKey]))
		{
			$secret = $this->getSecret();

			if (empty($secret))
			{
				throw new \RuntimeException('Encryption secret is not available');
			}

			self::$derivedKeys[$cacheKey] = hash_hkdf('sha256', $secret, 32, $purpose);
		}

		return self::$derivedKeys[$cacheKey];
	}

	/**
	 * Standard base64 to the URL-safe, unpadded alphabet.
	 *
	 * @param   string  $base64
	 *
	 * @return  string
	 */
	private static function toUrlSafe($base64)
	{
		return rtrim(strtr($base64, '+/', '-_'), '=');
	}

	/**
	 * The reverse: URL-safe alphabet back to padded standard base64.
	 *
	 * @param   string  $urlSafe
	 *
	 * @return  string
	 */
	private static function fromUrlSafe($urlSafe)
	{
		$length = strlen($urlSafe);

		return str_pad(
			strtr($urlSafe, '-_', '+/'),
			$length % 4 ? $length + 4 - $length % 4 : $length,
			'=',
			STR_PAD_RIGHT
		);
	}

	/**
	 * Creates a Joomla AES encryption object.
	 *
	 * @return  Aes  AES encryption object
	 *
	 * @throws  \RuntimeException
	 */
	protected function createAes()
	{
		$secret = $this->getSecret();

		if (empty($secret))
		{
			throw new \RuntimeException('Encryption secret is not available');
		}

		return new Aes($secret, $this->keySize);
	}

	/**
	 * Gets the encryption secret. (Joomla site secret)
	 *
	 * @return  string  Encryption secret
	 */
	protected function getSecret()
	{
		return Factory::getApplication()->getConfig()->get('secret');
	}
}
