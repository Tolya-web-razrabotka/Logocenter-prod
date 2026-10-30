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

use Joomla\CMS\Language\Text;

class ElasticEmail extends Integration
{
	protected $endpoint = 'https://api.elasticemail.com/v2';

	/**
	 * Create a new instance
	 * 
	 * @param array $options The service's required options
	 * @throws \Exception
	 */
	public function __construct($options)
	{
		parent::__construct();
		$this->setKey($options);
	}

	/**
	 *  Subscribe user to ElasticEmail
	 *
	 *  API References:
	 *  http://api.elasticemail.com/public/help/legacy#Contact_Add
	 *  http://api.elasticemail.com/public/help/legacy#Contact_Update
	 *
	 *  @param   string   $email         	  User's email address
	 *  @param   string   $list          	  The ElasticEmail List unique ID
	 *  @param   string   $publicAccountID    The ElasticEmail PublicAccountID
	 *  @param   array    $params        	  The form's parameters
	 *  @param   boolean  $update_existing	  Update existing user
	 *  @param   boolean  $double_optin  	  Send ElasticEmail confirmation email?
	 *
	 *  @return  void
	 */
	public function subscribe($email, $list, $params = [], $update_existing = true, $double_optin = false)
	{
		$data = [
			'apikey'			=> $this->key,
			'email' 			=> $email,
			'firstName'			=> isset($params['firstName']) ? $params['firstName'] : '',
			'lastName'			=> isset($params['lastName']) ? $params['lastName'] : '',
			'publicAccountID'	=> $this->getPublicAccountID(),
			'publicListID'		=> $list,
			'sendActivation' 	=> $double_optin ? 'true' : 'false',
			'consentIP'			=> \Tassos\Framework\User::getIP()
		];

		if (is_array($params) && count($params))
		{
			foreach ($params as $param_key => $param_value) 
			{
				$data[$param_key] = $param_key !== 'field' && is_array($param_value) ? implode(',', $param_value) : $param_value;
			}
		}

		if (!$update_existing)
		{
			if ($this->contactExists($email))
			{
				throw new \Exception(Text::_('NR_CONTACT_ALREADY_EXISTS'));
			}
			
			return $this->get('/contact/add', $data);
		}

		if ($this->getContact($email)) 
		{
			$data['clearRestOfFields'] = 'false';
			$this->get('/contact/update', $data);
		}
		else
		{
			$this->get('/contact/add', $data);
		}

		return true;
	}

	private function contactExists($email = '')
	{
		$contact = $this->get('/contact/loadcontact', ['apikey' => $this->key, 'email' => $email]);
		
		return (bool) $contact['success'];
	}

	/**
	 *  Returns all available ElasticEmail lists
	 *
	 *  http://api.elasticemail.com/public/help/legacy#List_list
	 *
	 *  @return  array
	 */
	public function getLists()
	{
		$data = $this->get('/list/list', ['apikey' => $this->key]);

		if (!$this->success())
		{
			return;
		}

		$lists = [];

		if (!isset($data['data']) || !is_array($data['data']))
		{
			return $lists;
		}

		foreach ($data['data'] as $key => $list)
		{
			$lists[] = [
				'id'   => $list['publiclistid'],
				'name' => $list['listname']
			];
		}

		return $lists;
	}

	/**
	 *  Check to see if a contact exists
	 *
	 *  @param   string  $email  The contact's email
	 *
	 *  @return  boolean
	 */
	public function getContact($email)
	{
		$contact = $this->get('/contact/loadcontact', ['apikey' => $this->key, 'email' => $email]);
		
		return (bool) $contact['success'];
	}

	/**
	 *  Get the Elastic Email Public Account ID
	 *
	 *  @return  string 
	 */
	public function getPublicAccountID()
	{
		$data = $this->get('/account/load', ['apikey' => $this->key]);

		if (isset($data['data']['publicaccountid'])) 
		{
			return $data['data']['publicaccountid'];
		}

		throw new \Exception(Text::_('NR_ELASTICEMAIL_UNRETRIEVABLE_PUBLICACCOUNTID'), 1);
	}
	
	/**
	 *  Get the last error returned by either the network transport, or by the API.
	 *
	 *  @return  string
	 */
	public function getLastError()
	{
		$body = $this->last_response->body;

		if (isset($body['error']))
		{
			return $body['error'];
		}
	}

	/**
	 * Check if the response was successful or a failure. If it failed, store the error.
	 * 
	 * @return bool     If the request was successful
	 */
	protected function determineSuccess()
	{
		$code = $this->last_response->code;
		$body = $this->last_response->body;

		if ($code >= 200 && $code <= 299 && !isset($body['error']))
		{
			return ($this->request_successful = true);
		}

		$this->last_error = 'Unknown error, call getLastResponse() to find out what happened.';
		return false;
	}
}