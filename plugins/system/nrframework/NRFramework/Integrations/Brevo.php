<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework\Integrations;

// No direct access
defined('_JEXEC') or die;

class Brevo extends Integration
{
	/**
	 * Create a new instance
	 * @param array $options The service's required options
	 * @throws \Exception
	 */
	public function __construct($options)
	{
		parent::__construct();
		$this->setKey($options);
		$this->setEndpoint('https://api.brevo.com/v3');
		$this->options->set('headers.api-key', $this->key);
	}

	/**
	 *  Subscribes a user to a Brevo Account
	 *
	 *  API Reference v3:
	 *  https://developers.brevo.com/reference/createcontact
	 *
	 *  @param   string   $email   The user's email
	 *  @param   array    $params  All the form fields
	 *  @param   string   $listid  The List ID
	 *  @param   boolean  $update_existing  Whether to update the existing contact (Only in v3)
	 *
	 *  @return  boolean
	 */
	public function subscribe($email, $params, $listid = false, $update_existing = true)
	{
		$data = [
			'email'      => $email,
			'attributes' => (object) $params,
			'updateEnabled' => $update_existing
		];

		if ($listid)
		{
			$data['listIds'] = [(int) $listid];
		}

		$this->post('contacts', $data);

		return true;
	}

	/**
	 * Subscribe a contact through Brevo's Double Opt-In flow.
	 * A confirmation email (templateId) is sent; the contact is added to
	 * includeListIds only after they click the confirmation link.
	 *
	 * API Reference: https://developers.brevo.com/reference/createdoicontact
	 *
	 * @param  string  $email
	 * @param  array   $params          Form-field merge values
	 * @param  array   $includeListIds  List IDs to add the contact to after confirmation
	 * @param  int     $templateId      The Brevo DOI email template ID
	 * @param  string  $redirectionUrl  Where the user is redirected after confirming
	 *
	 * @return boolean
	 */
	public function subscribeDOI($email, $params, $includeListIds, $templateId, $redirectionUrl)
	{
		$data = [
			'email'          => $email,
			'attributes'     => (object) $params,
			'includeListIds' => array_values(array_map('intval', (array) $includeListIds)),
			'templateId'     => (int) $templateId,
			'redirectionUrl' => $redirectionUrl,
		];

		$this->post('contacts/doubleOptinConfirmation', $data);

		return true;
	}

	/**
	 *  Returns all Campaign  lists
	 *
	 *  API Reference v3:
	 *  https://developers.brevo.com/reference/getlists-1
	 *
	 *  @return  array
	 */
	public function getLists()
	{
		$data = [
			'offset' => 0,
			'limit' => 50
		];

		$lists = [];

		$data = $this->get('contacts/lists', $data);

		// sanity check
		if (!isset($data['lists']) || !is_array($data['lists']) || $data['count'] == 0)
		{
			return $lists;
		}

		foreach ($data['lists'] as $key => $list)
		{
			$lists[] = [
				'id'   => $list['id'],
				'name' => $list['name']
			];
		}

		return $lists;
		
	}

	/**
	 *  Get the last error returned by either the network transport, or by the API.
	 *
	 *  API Reference:
	 *  https://developers.brevo.com/docs/how-it-works#error-codes
	 *
	 *  @return  string
	 */
	public function getLastError()
	{
		$body    = $this->last_response->body;
		$message = '';

		if (!isset($body['code']))
		{
			return $message;
		}

		return $body['message'];
	}
}