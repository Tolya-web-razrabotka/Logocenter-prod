<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 * 
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace ConvertForms\Tasks;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die('Restricted access');

class Apps
{	
	/**
	 * Check if app plugin exists
	 *
	 * @param   string  $appName  The app name
	 *
	 * @return  boolean
	 */
	public static function exists($appName)
	{
		return PluginHelper::getPlugin('convertformsapps', $appName);
	}

	public static function getApp($name, $data = null)
	{
		if (!$plugin = self::exists($name))
		{
			throw new \RuntimeException(Text::sprintf('PLUGIN_NOT_FOUND', $name));
		}

		$app = Factory::getApplication()->bootPlugin($plugin->name, $plugin->type);
		
		// Provide data options
		$app->setParams($data);

		return $app;
	}

	public static function getList($tasks = null)
	{
		PluginHelper::importPlugin('convertformsapps');

		$apps = \method_exists(static::class, 'getProApps') ? self::getProApps() : [];

		if ($result = Factory::getApplication()->triggerEvent('onConvertFormsAppInfo', [$tasks]))
		{
			// Return an assosiative array for faster manipulation in JS.
			foreach ($result as $app)
			{
				// $app['error'] = [
				// 	'type' => 'proOnly',
				// 	'text' => strip_tags(Text::sprintf('NR_PROFEATURE_DESC', $app['label'])),
				// ];

				$apps[$app['value']] = $app;
			}
		}

		ksort($apps);

		return $apps;
	}

	
	private static function getProApps()
	{
		$apps = [
			'mailchimp'   	  		=> 'MailChimp',
			'brevo'   	  	  		=> 'Brevo',
			'mailerlite'  	  		=> 'MailerLite',
			'activecampaign'  		=> 'ActiveCampaign',
			'salesforcewebtolead'  	=> 'Salesforce Web-To-Lead',
			'kit'  			  		=> 'Kit',
			'drip'  			  	=> 'Drip',
			'elasticemail'		  	=> 'Elastic Email',
			'icontact'		  		=> 'iContact',
			'campaignmonitor'  		=> 'Campaign Monitor',
			'getresponse' 	  		=> 'GetResponse',
			'useraccount' 	  		=> 'User Account',
			'content'	  	  		=> 'Content',
			'webhooks' 	  	  		=> 'Webhooks',
			'hubspot' 	  	  		=> 'HubSpot',
			'php'	 	  	  		=> 'PHP'
		];

		$apps_ = [];

		foreach ($apps as $appAlias => $appLabel)
		{
			$apps_[$appAlias] = [
				'value' => $appAlias,
				'label' => $appLabel,
				'logo'  => 'https://www.tassos.gr/images/apps/' . $appAlias . '.png',
				'error' => [
					'type' => 'proOnly',
					'text' => strip_tags(Text::sprintf('NR_PROFEATURE_DESC', $appLabel)),
				]
			];
		}

		return $apps_;
	}
	
}