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

namespace ConvertForms\Field;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

/**
 *  ALTCHA Proof-of-Work CAPTCHA field.
 *
 *  Fully self-contained: the ALTCHA widget assets are bundled in Convert Forms media,
 *  the challenge is generated and verified by our own NRFramework integration (no
 *  external service, no third-party keys), and challenges are served from Convert
 *  Forms' own AJAX endpoint (task=field.ajax → onAjax()). No Joomla captcha plugin.
 */
class Altcha extends \ConvertForms\Field
{
	use \ConvertForms\Spam\FieldTrait;

	/**
	 *  Challenge time-to-live, in seconds.
	 *
	 *  @var  int
	 */
	const EXPIRES = 300;

	/**
	 *  Proof-of-Work complexity (ALTCHA "maxnumber"): the upper bound of the random
	 *  number the visitor's browser must find to solve the challenge.
	 *
	 *  100,000 is ALTCHA's documented reference complexity - expensive enough to deter
	 *  automated abuse, yet fast for visitors: sub-second on modern devices and ~2.5s on
	 *  a low-end Android. Higher values mostly punish legitimate visitors on slow phones
	 *  for a negligible spam gain, so this is intentionally fixed (not user-configurable).
	 *
	 *  @var  int
	 */
	const MAXNUMBER = 100000;

	/**
	 *  Maximum number of recently-used challenge salts to remember (replay prevention).
	 *
	 *  @var  int
	 */
	const REPLAY_BUFFER = 50;

	/**
	 *  Exclude all common fields
	 *
	 *  @var  mixed
	 */
	protected $excludeFields = [
		'name',
		'required',
		'size',
		'value',
		'placeholder',
		'browserautocomplete',
		'browserautocompletecustom',
		'inputcssclass'
	];

	/**
	 *  Set field object
	 *
	 *  @param  mixed  $field  Object or Array Field options
	 */
	public function setField($field)
	{
		parent::setField($field);

		$this->field->required = true;

		return $this;
	}

	/**
	 *  The stable HMAC secret used to sign Proof-of-Work challenges.
	 *
	 *  Derived from the Joomla site secret with domain separation. It is stable across
	 *  the challenge and verification requests, unique per site, never exposed to the
	 *  client, and needs no storage - so there is nothing to configure and nothing that
	 *  can fail or get out of sync.
	 *
	 *  @return  string
	 */
	public function getSecret()
	{
		return hash_hmac('sha256', 'com_convertforms.altcha.pow', (string) $this->app->get('secret'));
	}

	/**
	 *  Build the ALTCHA integration wrapper.
	 *
	 *  @return  \NRFramework\Integrations\Altcha
	 */
	private function integration()
	{
		return new \NRFramework\Integrations\Altcha([
			'secret'    => $this->getSecret(),
			'algorithm' => 'SHA-256',
			'maxnumber' => self::MAXNUMBER,
			'expires'   => self::EXPIRES,
		]);
	}

	/**
	 *  The name of the input the ALTCHA widget renders (and we read on validation).
	 *
	 *  @return  string
	 */
	public function getInputName()
	{
		return $this->getName() . '_' . $this->getField()->key;
	}

	/**
	 *  Challenge endpoint.
	 *
	 *  Reached via index.php?option=com_convertforms&task=field.ajax&field_type=altcha&…
	 *  The ALTCHA widget fetches this to obtain a fresh challenge.
	 *
	 *  @param   int     $form_id    The form id
	 *  @param   string  $field_key  The field key
	 *
	 *  @return  array               The challenge: {algorithm, challenge, maxnumber, salt, signature}
	 */
	public function onAjax($form_id, $field_key)
	{
		return $this->integration()->createChallenge();
	}

	/**
	 *  Validate field value
	 *
	 *  @param   mixed  $value           The field's value to validate
	 *
	 *  @return  mixed                   True on success, throws an exception on error
	 */
	public function validate(&$value)
	{
		// In case this is a submission via URL, skip the check.
		if ($this->app->input->get('task') == 'optin')
		{
			return true;
		}

		$name     = $this->getInputName();
		$solution = isset($this->data[$name]) ? (string) $this->data[$name] : '';

		$integration = $this->integration();
		$integration->validate($solution);

		if (!$integration->success())
		{
			$this->spamError($integration->getLastError());
		}

		// Replay prevention: a given challenge (salt) may be solved only once.
		$this->guardReplay($solution);
	}

	/**
	 *  Reject a solution whose challenge salt was already used in this session.
	 *
	 *  @param   string  $solution  The base64 solution payload
	 *
	 *  @return  void
	 */
	private function guardReplay($solution)
	{
		$decoded = base64_decode($solution, true);
		$data    = $decoded ? json_decode($decoded, true) : null;

		if (!is_array($data) || empty($data['salt']))
		{
			return;
		}

		$saltHash = md5($data['salt']);
		$session  = $this->app->getSession();
		$used     = (array) $session->get('cf.altcha.used', []);

		if (in_array($saltHash, $used, true))
		{
			$this->spamError(Text::_('COM_CONVERTFORMS_FIELD_ALTCHA_FAILED'));
		}

		$used[] = $saltHash;

		// Keep the buffer bounded.
		$session->set('cf.altcha.used', array_slice($used, -self::REPLAY_BUFFER));
	}
}
