<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Factory;

// Registers framework's namespace
JLoader::registerNamespace('Tassos\\Framework', __DIR__ . '/NRFramework/', false, false, 'psr4');

$autoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload))
{
	require_once $autoload;

} elseif (Factory::getApplication()->isClient('administrator'))
{
	Factory::getApplication()->enqueueMessage('Tassos Framework Vendor Autoload Failed. File <b>' . $autoload . '</b> not found.', 'error');
}

// Assignment related class aliases
JLoader::registerAlias('NRFrameworkFunctions',               '\\Tassos\\Framework\\Functions');
JLoader::registerAlias('NRSmartTags', 					     '\\Tassos\\Framework\\SmartTags\\SmartTags');
JLoader::registerAlias('NRFramework\\SmartTags',			 '\\Tassos\\Framework\\SmartTags\\SmartTags');
JLoader::registerAlias('Tassos\\Framework\\SmartTags',	     '\\Tassos\\Framework\\SmartTags\\SmartTags');
JLoader::registerAlias('NRFonts', 					         '\\Tassos\\Framework\\Fonts');
JLoader::registerAlias('NR_activecampaign', 				 '\\Tassos\\Framework\\Integrations\\ActiveCampaign');
JLoader::registerAlias('NR_campaignmonitor', 				 '\\Tassos\\Framework\\Integrations\\CampaignMonitor');
JLoader::registerAlias('NR_convertkit', 				 	 '\\Tassos\\Framework\\Integrations\\ConvertKit');
JLoader::registerAlias('NR_drip', 				 			 '\\Tassos\\Framework\\Integrations\\Drip');
JLoader::registerAlias('NR_elasticemail', 					 '\\Tassos\\Framework\\Integrations\\ElasticEmail');
JLoader::registerAlias('NR_getresponse', 					 '\\Tassos\\Framework\\Integrations\\GetResponse');
JLoader::registerAlias('NR_hubspot', 						 '\\Tassos\\Framework\\Integrations\\HubSpot');
JLoader::registerAlias('NR_icontact', 						 '\\Tassos\\Framework\\Integrations\\IContact');
JLoader::registerAlias('NR_mailchimp', 						 '\\Tassos\\Framework\\Integrations\\MailChimp');
JLoader::registerAlias('NR_recaptcha', 						 '\\Tassos\\Framework\\Integrations\\ReCaptcha');
JLoader::registerAlias('NR_salesforce', 					 '\\Tassos\\Framework\\Integrations\\Salesforce');
JLoader::registerAlias('NR_sendinblue', 					 '\\Tassos\\Framework\\Integrations\\SendInBlue');
JLoader::registerAlias('NR_zoho', 							 '\\Tassos\\Framework\\Integrations\\Zoho');
JLoader::registerAlias('NR_zohocrm', 						 '\\Tassos\\Framework\\Integrations\\ZohoCRM');

// Define a helper constant to indicate whether we are on a Joomla 4 installation
if (version_compare(JVERSION, '4.0', 'ge') && !defined('nrJ4'))
{
	define('nrJ4', true);
}

// Indicates a Joomla 3 installation
if (version_compare(JVERSION, '4.0', 'lt') && !defined('t_isJ3'))
{
	define('t_isJ3', true);
}

// Indicates a Joomla 4 installation
if (version_compare(JVERSION, '4.0', 'ge') && version_compare(JVERSION, '5.0', 'lt') && !defined('t_isJ4'))
{
	define('t_isJ4', true);
}

// Indicates a Joomla 5 installation
if (version_compare(JVERSION, '5.0', 'ge') && version_compare(JVERSION, '6.0', 'lt') && !defined('t_isJ5'))
{
	define('t_isJ5', true);
}

// The Tassos.gr Site URL
if (!defined('TF_TEMPLATES_SITE_URL'))
{
	define('TF_TEMPLATES_SITE_URL', 'https://templates.tassos.gr/');
}

// URL to retrieve templates
if (!defined('TF_TEMPLATES_GET_URL'))
{
	define('TF_TEMPLATES_GET_URL', TF_TEMPLATES_SITE_URL . '{{PROJECT}}/list.doc');
}

// URL to retrieve a template
if (!defined('TF_TEMPLATE_GET_URL'))
{
	define('TF_TEMPLATE_GET_URL', TF_TEMPLATES_SITE_URL . 'tower/template/{{PROJECT}}/{{TEMPLATE}}/{{DOWNLOAD_KEY}}');
}

// URL to check the license
if (!defined('TF_CHECK_LICENSE'))
{
	define('TF_CHECK_LICENSE', 'https://www.tassos.gr/tower/license/{{DOWNLOAD_KEY}}.doc');
}

// Portable Encryption Secret for cross-site data portability
// WARNING: Changing this secret will invalidate all previously encrypted portable data
if (!defined('TF_PORTABLE_SECRET'))
{
	define('TF_PORTABLE_SECRET', 'e275e3425cc0d39f54eabe5c3795957c4410a631165245ae41b2bfc389cd4e43');
}

// Auto-register AJAX handlers using HandlerAutoLoader
\Tassos\Framework\Ajax\HandlerAutoLoader::register();

/**
 * Joomla 3 backward compatibility aliases.
 * 
 * TODO: Remove this file when Joomla 3 support is dropped.
 */
if (version_compare(JVERSION, 4, '<'))
{
	// Fields Aliases
	$tf_aliases = [
		'Text',
		'Textarea',
		'GroupedList',
		'Media',
		'List',
		'Hidden',
		'Number',
		'Checkbox',
		'Password',
		'Note',
		'Subform'
	];
	foreach ($tf_aliases as $name)
	{
		if (class_exists('\\Joomla\\CMS\\Form\\Field\\' . $name . 'Field', true))
		{
			continue;
		}
	
		FormHelper::loadFieldClass(strtolower($name));
		class_alias('JFormField' . $name, '\\Joomla\\CMS\\Form\\Field\\' . $name . 'Field');
	}

	// Extra Aliases
	$extra_aliases = [
		'JHtmlSidebar' => '\\Joomla\\CMS\\HTML\\Helpers\\Sidebar'
	];
	foreach ($extra_aliases as $alias => $class)
	{
		if (class_exists($class, true))
		{
			continue;
		}

		class_alias($alias, $class);
	}

	JLoader::import('components.com_fields.libraries.fieldslistplugin', JPATH_ADMINISTRATOR);
	JLoader::import('components.com_fields.libraries.fieldsplugin', JPATH_ADMINISTRATOR);
	FormHelper::loadFieldClass('Checkboxes');
}
else
{
	// Once Joomla 3 support is dropped, find where the following classes are used and load them using "use" statements.
	JLoader::registerAlias('FieldsPlugin', '\\Joomla\\Component\\Fields\\Administrator\\Plugin\\FieldsPlugin');
	JLoader::registerAlias('FieldsListPlugin', '\\Joomla\\Component\\Fields\\Administrator\\Plugin\\FieldsListPlugin');
	JLoader::registerAlias('JFormFieldCheckboxes', '\\Joomla\\CMS\\Form\\Field\\CheckboxesField');
}

spl_autoload_register(function ($class)
{
    // Only handle NRFramework\* classes
    if (strpos($class, 'NRFramework\\') === 0)
	{
        // Convert NRFramework\ClassName to Tassos\Framework\ClassName
        $newClass = 'Tassos\\Framework\\' . substr($class, strlen('NRFramework\\'));

        if (class_exists($newClass))
		{
            class_alias($newClass, $class);
        }
    }
});