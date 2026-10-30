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

defined('_JEXEC') or die('Restricted access');

require_once __DIR__ . '/script.install.helper.php';

class PlgConvertFormsToolsEmailCloakInstallerScript extends PlgConvertFormsToolsEmailCloakInstallerScriptHelper
{
	public $name = 'emailcloak';
	public $alias = 'emailcloak';
	public $extension_type = 'plugin';
	public $plugin_folder = 'convertformstools';
	public $show_message = false;
	public $autopublish = true;
}