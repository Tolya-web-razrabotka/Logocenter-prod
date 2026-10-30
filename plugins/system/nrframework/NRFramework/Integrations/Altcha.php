<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework\Integrations;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 *  ALTCHA Proof-of-Work wrapper.
 *
 *  Unlike the reCAPTCHA/hCaptcha/Turnstile wrappers, ALTCHA Proof-of-Work is
 *  fully self-hosted: there is no third-party service and no remote HTTP call.
 *  The server signs a computational challenge with its own secret, the browser
 *  solves it (via the ALTCHA widget/Web Worker), and the server verifies the
 *  solution locally. See https://altcha.org/docs/v2/proof-of-work-captcha
 */
class Altcha extends Integration
{
	/**
	 *  Hashing algorithm. One of: SHA-256, SHA-384, SHA-512.
	 *
	 *  @var  string
	 */
	protected $algorithm = 'SHA-256';

	/**
	 *  The maximum random number the client may have to iterate to. Higher = harder.
	 *
	 *  @var  int
	 */
	protected $maxnumber = 100000;

	/**
	 *  Challenge time-to-live, in seconds.
	 *
	 *  @var  int
	 */
	protected $expires = 300;

	/**
	 * Create a new instance
	 *
	 * @param   array  $options  Requires 'secret'. Optional: 'algorithm', 'maxnumber', 'expires'.
	 *
	 * @throws \Exception
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		if (!array_key_exists('secret', $options))
		{
			$this->setError('NR_ALTCHA_INVALID_SECRET_KEY');
			throw new \Exception($this->getLastError());
		}

		$this->setKey($options['secret']);

		if (!empty($options['algorithm']) && in_array($options['algorithm'], ['SHA-256', 'SHA-384', 'SHA-512']))
		{
			$this->algorithm = $options['algorithm'];
		}

		if (isset($options['maxnumber']))
		{
			$this->maxnumber = (int) $options['maxnumber'];
		}

		if (isset($options['expires']))
		{
			$this->expires = (int) $options['expires'];
		}
	}

	/**
	 *  Build a fresh, signed challenge to hand to the ALTCHA widget.
	 *
	 *  The expiry is embedded in the salt and covered by the HMAC signature, so it
	 *  is tamper-proof without any server-side storage.
	 *
	 *  @return  array  { algorithm, challenge, maxnumber, salt, signature }
	 */
	public function createChallenge()
	{
		$algo   = $this->phpAlgo();
		$salt   = bin2hex(random_bytes(12)) . '?' . http_build_query(['expires' => time() + $this->expires]);
		$number = random_int(0, $this->maxnumber);

		$challenge = hash($algo, $salt . $number);
		$signature = hash_hmac($algo, $challenge, $this->key);

		return [
			'algorithm' => $this->algorithm,
			'challenge' => $challenge,
			'maxnumber' => $this->maxnumber,
			'salt'      => $salt,
			'signature' => $signature,
		];
	}

	/**
	 *  Verify a base64-encoded solution payload returned by the ALTCHA widget.
	 *
	 *  Mirrors the reCAPTCHA/Turnstile contract: call validate(), then success().
	 *
	 *  @param   string  $solution  The base64 solution payload (the hidden input value).
	 *  @param   string  $remoteip  Unused; kept for signature parity with the other wrappers.
	 *
	 *  @return  bool               True if the solution is valid.
	 */
	public function validate($solution, $remoteip = null)
	{
		$this->request_successful = false;

		if (empty($solution) || !is_string($solution))
		{
			return $this->setError('NR_ALTCHA_PLEASE_VALIDATE');
		}

		$decoded = base64_decode($solution, true);
		$data    = $decoded ? json_decode($decoded, true) : null;

		if (!is_array($data)
			|| empty($data['salt'])
			|| !isset($data['number'])
			|| empty($data['challenge'])
			|| empty($data['signature']))
		{
			return $this->setError('NR_ALTCHA_VERIFICATION_FAILED');
		}

		$algo = $this->phpAlgo($data['algorithm'] ?? $this->algorithm);

		if (!$algo)
		{
			return $this->setError('NR_ALTCHA_VERIFICATION_FAILED');
		}

		// Reject expired challenges (expiry is signed into the salt).
		parse_str((string) parse_url($data['salt'], PHP_URL_QUERY), $params);

		if (!empty($params['expires']) && (int) $params['expires'] < time())
		{
			return $this->setError('NR_ALTCHA_EXPIRED');
		}

		// Recompute the challenge from salt + number and confirm it matches.
		$challenge = hash($algo, $data['salt'] . $data['number']);

		if (!hash_equals($challenge, (string) $data['challenge']))
		{
			return $this->setError('NR_ALTCHA_VERIFICATION_FAILED');
		}

		// Confirm the challenge was issued by us (HMAC signature).
		$signature = hash_hmac($algo, $challenge, $this->key);

		if (!hash_equals($signature, (string) $data['signature']))
		{
			return $this->setError('NR_ALTCHA_VERIFICATION_FAILED');
		}

		$this->request_successful = true;

		return true;
	}

	/**
	 *  Map an ALTCHA algorithm name (e.g. "SHA-256") to a PHP hash() algo (e.g. "sha256").
	 *
	 *  @param   string  $algorithm  ALTCHA algorithm name. Defaults to the instance algorithm.
	 *
	 *  @return  string|false        The PHP hash algo name, or false if unsupported.
	 */
	private function phpAlgo($algorithm = null)
	{
		$map = [
			'SHA-256' => 'sha256',
			'SHA-384' => 'sha384',
			'SHA-512' => 'sha512',
		];

		$algorithm = $algorithm ?: $this->algorithm;

		return $map[$algorithm] ?? false;
	}

	/**
	 *  Set wrapper error text
	 *
	 *  @param  String  $error  The error message to display
	 */
	private function setError($error)
	{
		$this->last_error = Text::_($error);

		return false;
	}
}
