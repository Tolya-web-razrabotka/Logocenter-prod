<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\String\StringHelper;
use Tassos\Framework\HTML;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;

// Initialize Tassos Library
require_once __DIR__ . '/autoload.php';

class plgSystemNRFramework extends CMSPlugin
{
	/**
	 *  Auto load plugin language 
	 *
	 *  @var  boolean
	 */
	protected $autoloadLanguage = true;
	
	/**
	 *  The Joomla Application object
	 *
	 *  @var  object
	 */
	protected $app;

    /**
     *  Update UpdateSites after the user has entered a Download Key
     *
     *  @param   string  $context  The component context
     *  @param   string  $table    
     *  @param   boolean $isNew    
     *
     *  @return  void
     */
	public function onExtensionAfterSave($context, $table, $isNew)
	{
		// Run only on Tassos Framework edit form
		if (
			$this->app->isClient('site')
			|| $context != 'com_plugins.plugin'
			|| $table->element != 'nrframework'
			|| !isset($table->params)
		)
		{
			return;
		}

		// Set Download Key & fix Update Sites
		$upds = new Tassos\Framework\Updatesites();
		$upds->update();
	}

	/**
	 *  Handling of PRO for extensions
	 *  Throws a notice message if the Download Key is missing before downloading the package
	 *
	 *  @param   string  &$url      Update Site URL
	 *  @param   array   &$headers  
	 */
	public function onInstallerBeforePackageDownload(&$url, &$headers)
	{
		$uri  = Uri::getInstance($url);
		$host = $uri->getHost();

		// This is not a Tassos.gr extension
		if (strpos($host, 'tassos.gr') === false)
		{
			return true;
		}

		// If it's a Free version. No need to check for the Download Key. 
		if (strpos($url, 'free') !== false)
		{
			return true;
		}

		// This is a Pro version. Let's validate the Download Key.
		$download_id = $this->params->get('key', '');
		
		// Append it to the URL
		if (!empty($download_id))
		{
			$uri->setVar('dlid', $download_id);
			$url = $uri->toString();
			return true;
		} 
	
		$this->app->enqueueMessage('To be able to update the Pro version of this extension via the Joomla updater, you will need enter your Download Key in the settings of the <a href="' . Uri::base() . 'index.php?option=com_plugins&view=plugins&filter_search=tassos">Tassos Framework System Plugin</a>');
		return true;
	}

    /**
     * Unified AJAX endpoint for all Tassos Framework AJAX requests.
     * 
     * This method serves as the central router for all AJAX handlers in the framework.
     * It validates CSRF tokens, identifies the requested handler, and delegates execution
     * to the appropriate handler class in the Ajax/Handlers/ directory.
     * 
     * URL Structure: ?option=com_ajax&format=raw&plugin=nrframework&handler={handler_name}
     * 
     * @return void
     */
    public function onAjaxNrframework()
    {
		Session::checkToken('request') or jexit(Text::_('JINVALID_TOKEN'));

		$handler_name = $this->app->input->getCmd('handler');

		// Validate input
		if (empty($handler_name))
		{
			http_response_code(400);
			die('MISSING_HANDLER');
		}

		try
		{
			\Tassos\Framework\Ajax\AjaxHandlerRegistry::executeHandler($handler_name);
		}
		catch (\Exception $e)
		{
			http_response_code(404);
			die($e->getMessage());
		}
	}
}