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

class SalesForce extends Integration
{
	/**
	 *  Service API Endpoint
	 *
	 *  @var  string
	 */
	protected $endpoint = 'https://{{ENV}}.salesforce.com/servlet/servlet.WebToLead?encoding=UTF-8';

	/**
	 *  Encode data before sending the request
	 *
	 *  @var  boolean
	 */
	protected $encode = false;

	/**
	 * Create a new instance
	 * 
	 * @param  array       $options
	 * 
	 * @throws \Exception
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->setKey($options);
		$this->prepareEndpoint($options);

		$this->options->set('headers.Content-Type', 'application/x-www-form-urlencoded');
	}

	private function prepareEndpoint($options = [])
	{
		if (isset($options['test_mode']) && $options['test_mode'])
		{
			$this->setEndpoint(str_replace('{{ENV}}', 'test', $this->endpoint));
		}
		else
		{
			$this->setEndpoint(str_replace('{{ENV}}', 'webto', $this->endpoint));
		}
	}

	/**
	 *  Subscribe user to SalesForce
	 *
	 *  API References:
	 *  https://developer.salesforce.com/page/Wordpress-to-lead
	 *
	 *  @param   string   $email         	  User's email address
	 *  @param   array    $params  			  All the form fields
	 *
	 *  @return  void
	 */
	public function subscribe($email, $params)
	{
		$data = array(
			"email" => $email,
			"oid"   => $this->key
		);

		if (is_array($params) && count($params))
		{
			$data = array_merge($data, $params);
		}

		$this->post('', $data);

		return true;
	}

	/**
	 *  Determine if the Lead has been stored successfully in SalesForce
	 *
	 *  @return  string
	 */
	public function determineSuccess()
	{
		$status = $this->last_response->code;

		if ($status < 200 && $status > 299)
		{
			return false;
		}

		$headers = $this->last_response->headers;

		if (isset($headers['Is-Processed']) && (strpos($headers['Is-Processed'], 'Exception') !== false))
		{
			$this->last_error = Text::_('NR_SALESFORCE_ERROR');
			return false;
		}

		return ($this->request_successful = true);
	}
}