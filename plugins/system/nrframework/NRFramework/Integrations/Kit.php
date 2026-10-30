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

use Joomla\String\StringHelper;

class Kit extends Integration
{
	/**
	 * Create a new instance
	 * 
	 * @param string $api_key Your Kit API Key
	 */
	public function __construct($api_key)
	{
		parent::__construct();

		$this->setKey($api_key);
		$this->setEndpoint('https://api.kit.com/v4');
		$this->options->set('headers.X-Kit-Api-Key', $this->key);
	}

	/**
	 *  Subscribe a user to a ConvertKit Form
	 *  
	 *  API Reference:
	 *  https://developers.kit.com/v4#create-a-subscriber
	 *
	 *  @param   string  $email				The subscriber's email
	 *  @param   string  $first_name		The subscriber's first name
	 *  @param   array   $tags				The tags that will be associated with the subscriber
	 *  @param   array   $custom_fields		The custom fields that will be associated with the subscriber
	 *
	 *  @return  boolean
	 */
	public function subscribe($email, $first_name = '', $tags = [], $custom_fields = [])
	{
		$data = [
			'email_address' => $email,
			'fields'     	=> $custom_fields
		];

		if ($first_name)
		{
			$data['first_name'] = $first_name;
		}
		
		$subscriber = $this->post('subscribers', $data);
		$subscriber_id = isset($subscriber['subscriber']['id']) ? $subscriber['subscriber']['id'] : null;
		
		// Add tags to subscriber
		$this->addTagsToSubscriber($subscriber_id, $tags);

		return true;
	}

	/**
	 *  Adds tags to a subscriber.
	 *
	 *  @param   string  $subscriber_id  Subscriber ID
	 *  @param   string  $tags  		 An array of tag IDs
	 *
	 *  @return  void
	 */
	public function addTagsToSubscriber($subscriber_id, $tags = [])
	{
		if (!$subscriber_id || !$tags)
		{
			return;
		}

		$accountTags = $this->get('tags');

		if (empty($accountTags) || !$this->request_successful)
		{
			return;
		}

		// Find the valid tags
		$validTags = [];
		foreach ($accountTags['tags'] as $tag)
		{
			foreach ($tags as $tagname)
			{
				if (StringHelper::strcasecmp($tag['name'], $tagname) == 0) 
				{
					$validTags[] = $tag['id'];
					break;
				}
			}
		}

		if (empty($validTags))
		{
			return;
		}

		// Add each tag to subscriber (Bulk API needs OAuth2)
		foreach ($validTags as $tagId)
		{
			$this->post("tags/{$tagId}/subscribers/{$subscriber_id}");
		}
	}

	/**
	 *  Get the last error returned by either the network transport, or by the API.
	 *
	 *  @return  string
	 */
	public function getLastError()
	{
		$body    = $this->last_response->body;
		$message = '';

		if (isset($body['errors']) && !empty($body['errors']))
		{
			$message = implode(', ', $body['errors']);
		}

		if (isset($body['message']) && !empty($body['message']))
		{
			$message .= ' - ' . $body['message'];
		}

		return $message;
	}
}