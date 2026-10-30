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

namespace ConvertForms;

defined('_JEXEC') or die('Restricted access');

use Tassos\Framework\Cache;
use Tassos\Framework\Functions;
use Tassos\Framework\File;
use ConvertForms\Form;
use Joomla\Registry\Registry;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
use Joomla\String\StringHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\Filesystem\Path;

class Helper
{
    /**
     * Returns the dedicated, protected temp directory used for all Convert Forms
     * temp files (file-upload temp files and submission exports).
     *
     * @return  string  Absolute path with a trailing directory separator.
     */
    public static function getTempPath()
    {
        $path = File::getTempFolder() . 'tassos' . DIRECTORY_SEPARATOR . 'cf';

        File::createDirs($path, true);

        return \Joomla\Filesystem\Path::clean($path . DIRECTORY_SEPARATOR);
    }

    /**
     * Builds a component AJAX endpoint URL with no trailing slash on its path.
     *
     * Route::_() routes a component that has no menu item to "component/convertforms/". A server
     * that redirects a path ending in a slash builds that redirect with the http scheme when it
     * sits behind a TLS terminating proxy, and the browser blocks it as mixed content, so the
     * request dies with no response at all.
     *
     * The query goes through Route::_() rather than being appended afterwards: with SEF off the
     * routed URL already carries a "?", and a second one makes everything after it unreadable.
     *
     * Returns raw ampersands, so callers rendering it into markup must escape it themselves.
     *
     * @param   array  $query  Query parameters to add, as name => value.
     *
     * @return  string  Routed URL, ready to use as an AJAX endpoint.
     */
    public static function getAjaxUrl(array $query = [])
    {
        $url = 'index.php?option=com_convertforms';

        foreach ($query as $name => $value)
        {
            $url .= '&' . $name . '=' . urlencode((string) $value);
        }

        $uri  = new Uri(Route::_($url, false));
        $path = (string) $uri->getPath();

        // Only a trailing slash is ever touched, so no other routing is second guessed.
        if (substr($path, -1) !== '/')
        {
            return $uri->toString();
        }

        // A slash the site asked for stays. Removing it only earns a redirect putting it back.
        if (self::sefPluginAddsTrailingSlash())
        {
            return $uri->toString();
        }

        $path = StringHelper::rtrim($path, '/');

        // An empty path would leave a bare query string, pointing at the current page.
        if ($path === '')
        {
            return $uri->toString();
        }

        $app = Factory::getApplication();

        // Joomla skips the suffix on a path ending in a slash, then redirects to the suffixed URL.
        // Add what it would have built.
        if ($app->get('sef', 1) && $app->get('sef_suffix'))
        {
            $path .= '.' . $uri->getVar('format', 'html');
            $uri->delVar('format');
        }

        $uri->setPath($path);

        return $uri->toString();
    }

    /**
     * Whether the site is configured to end every URL with a trailing slash.
     *
     * Mirrors the conditions the System - SEF plugin applies before attaching the build rule that
     * adds it, so a slash counts as deliberate only when it really is.
     *
     * @return  bool
     */
    private static function sefPluginAddsTrailingSlash()
    {
        $app = Factory::getApplication();

        if (!$app->get('sef', 1) || $app->get('sef_suffix'))
        {
            return false;
        }

        // An empty array when the plugin is disabled or missing.
        $plugin = PluginHelper::getPlugin('system', 'sef');

        if (!is_object($plugin))
        {
            return false;
        }

        return (int) (new Registry($plugin->params))->get('trailingslash', -1) === 1;
    }

    /**
     * Check whether the connected database supports JSON_SEARCH (MySQL 5.7.8+/MariaDB 10.2.3+).
     *
     * Probes the DB with a harmless SELECT JSON_SEARCH(...) and caches the boolean result.
     *
     * @return  bool
     */
    public static function supportsJsonSearch()
    {
        $hash = 'cf_supports_json_search';

        if (Cache::has($hash))
        {
            return Cache::get($hash);
        }

        $result = false;

        $db = Factory::getDbo();

        try
        {
            // Safe probe: simple JSON_SEARCH call on a tiny JSON document.
            $probe = 'SELECT JSON_SEARCH(' . $db->q('[{"k":"v"}]') . ", 'one', " . $db->q('v') . ')';
            $db->setQuery($probe)->loadResult();

            $result = true;
        }
        catch (Exception $e)
        {
        }

        return Cache::set($hash, $result);
    }

    public static function parseIfShortcode(&$subject, $submission = null)
    {
        
    }

    /**
     * Ensure same script are added once on the page
     *
     * @param  string $styles   The script code
     * 
     * @return null on failure.
     */
    public static function addScriptDeclarationOnce($script)
    {
        $hash = 'cf_script_' . md5($script);

        if (Cache::has($hash))
        {
            return;
        }

        Factory::getDocument()->addScriptDeclaration($script);

        Cache::set($hash, true);
    }

    /**
     * Ensure same styles are added once on the page
     *
     * @param  string $styles   The CSS code
     * 
     * @return null on failure.
     */
    public static function addStyleDeclarationOnce($styles)
    {
        $hash = 'cf_styles_' . md5((string) $styles);

        if (Cache::has($hash))
        {
            return;
        }

        Factory::getDocument()->addStyleDeclaration($styles);

        Cache::set($hash, true);
    }

    /**
     * Check if current logged in user is authorised to view a resource.
     *
     * @param  string $action
     *
     * @return void
     */
    public static function authorise($action, $throw_exception = false)
    {
        $authorised = Factory::getUser()->authorise($action, 'com_convertforms');

        if (!$authorised && $throw_exception)
        {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return $authorised;
    }

    /**
     * Trigger Error Event
     *
     * @param string    $error         The error message
     * @param string    $category      The error category
     * @param integer   $form_id       The form ID assosiated with the error
     * @param mixed     $data          Extra data related to the error occured
     *
     * @return void
     */
    public static function triggerError($error, $category, $form_id, $data = null)
    {
        PluginHelper::importPlugin('convertforms');
        Factory::getApplication()->triggerEvent('onConvertFormsError', [$error, $category, $form_id, $data]);
    }

    /**
     * Convert all applicable characters to HTML entities
     *
     * @param  string $input The input string.
     *
     * @return string
     */
    public static function escape($input)
    {
        if (!is_string($input))
        {
            return $input;
        }

        // Convert all HTML tags to HTML entities.
        $input = htmlspecialchars($input, ENT_NOQUOTES, 'UTF-8');

        // We do not need any Smart Tag replacements take place here, so we need to escape curly brackets too.
        $input = str_replace(['{', '}'], ['&#123;', '&#125;'], $input);

        // Respect newline characters, by converting them to <br> tags.
        $input = nl2br($input);

        return $input;
    }

    public static function getComponentParams()
    {
        $hash = 'cfComponentParams';

        if (Cache::has($hash))
        {
            return Cache::get($hash);
        }

        return Cache::set($hash, \Joomla\CMS\Component\ComponentHelper::getParams('com_convertforms'));
    }

    /**
     * Checks whether the legacy campaign MVC files are still present on disk.
     *
     * This keeps upgraded installs working while fresh installs ship without the
     * retired campaign entry points.
     *
     * @deprecated
     *
     * @return  bool
     */
    public static function legacyCampaignsEnabled()
    {
        return file_exists(JPATH_ADMINISTRATOR . '/components/com_convertforms/models/campaign.php');
    }

    /**
     * Whether the Campaigns to Tasks migration is complete on this site.
     *
     * Derived from the data every time — no persisted flag, no params, no tables. The migration
     * is done when no form is still assigned to a migratable legacy campaign (never-migratable
     * services and default/no-sync campaigns are excluded from the count). Cached per request as
     * it runs at most once per admin page load.
     *
     * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
     *
     * @return  bool
     */
    public static function campaignsMigrationCompleted()
    {
        if (!self::legacyCampaignsEnabled())
        {
            return true;
        }

        $hash = 'campaignsMigrationCompleted';

        if (Cache::has($hash))
        {
            return Cache::get($hash);
        }

        $pending = (new \ConvertForms\Tasks\CampaignsToTasksMigrator())->getPendingMigratableCount();

        return Cache::set($hash, $pending === 0);
    }

    public static function getFormLeadsCount($form)
    {
        $hash = 'formLeadsCount' . $form;

        if (Cache::has($hash))
        {
            return Cache::get($hash);
        }

        if (!$form || $form == 0)
        {
            return 0;
        }

        $db = Factory::getDBO();
        $query = $db->getQuery(true);

        $query
            ->select('count(*)')
            ->from('#__convertforms_conversions')
            ->where('form_id = ' . $form);

        $db->setQuery($query);

        return Cache::set($hash, $db->loadResult());
    }

    /**
     *  Writes the Not Found Items message
     *
     *  @param   string  $view 
     *
     *  @return  string
     */
    public static function noItemsFound($view = 'submissions')
    {
        $html[] = '<span style="font-size:16px; position:relative; top:2px;" class="icon-smiley-sad-2"></span>';
        $html[] = Text::sprintf('COM_CONVERTFORMS_NO_RESULTS_FOUND', strtolower(Text::_('COM_CONVERTFORMS_' . $view)));

        return implode(' ', $html);
    }

    /**
     *  Get Visitor ID
     *
     *  @return  string
     */
    public static function getVisitorID()
    {
        return \NRFramework\VisitorToken::getInstance()->get();
    }

    /**
     * Returns visitors submitted forms.
     * If the user is logged in, we try to get the form by user's ID.
     * Otherwise, the visitor cookie ID will be used instead.
     *
     * @return  array
     */
    public static function getVisitorSubmittedForms()
    {
        $visitorID = self::getVisitorID();
        $user = Factory::getUser();

        // Sanity check. If we can't determine the user uniquely, abort.
        if ($user->id == 0 AND empty($visitorID))
        {
            return;
        }

        $db = Factory::getDBO();

        $query = $db->getQuery(true)
            ->select('form_id')
            ->from('#__convertforms_conversions')
            ->where('state = 1')
            ->group('form_id');

        if ($user->id)
        {
            // This may seem unorthodox, but according to Joomla's docs, there's no way to group conditions with OR and wrap them with parentheses.
            // https://docs.joomla.org/Selecting_data_using_JDatabase#Using_OR_in_queries
            // https://joomla.stackexchange.com/questions/5612/how-to-combine-ands-and-ors-in-the-where-clause-using-a-query-object
            $query->where('( ' . $db->quoteName('user_id') . ' = ' . (int) $user->id . ' OR ' . $db->quoteName('visitor_id') . ' = ' . $db->q($visitorID) . ')');
        } else 
        {
            $query->where('( ' . $db->quoteName('visitor_id') . ' = ' . $db->q($visitorID) . ')');
        }

        $db->setQuery($query);

        return $db->loadColumn();
    }

    /**
     *  Returns campaigns list visitor is subscribed to
     *  If the user is logged in, we try to get the campaigns by user's ID
     *  Otherwise, the visitor cookie ID will be used instead
     *
     *  @return  array  List of campaign IDs
     */
    public static function getVisitorCampaigns()
    {
        $visitorID = self::getVisitorID();
        $user = Factory::getUser();

        // Sanity check. If we can't determine the user uniquely, abort.
        if ($user->id == 0 AND empty($visitorID))
        {
            return;
        }

        $db = Factory::getDBO();

        $query = $db->getQuery(true)
            ->select('campaign_id')
            ->from('#__convertforms_conversions')
            ->where('state = 1')
            ->group('campaign_id');

        if ($user->id)
        {
            // This may seem unorthodox, but according to Joomla's docs, there's no way to group conditions with OR and wrap them with parentheses.
            // https://docs.joomla.org/Selecting_data_using_JDatabase#Using_OR_in_queries
            // https://joomla.stackexchange.com/questions/5612/how-to-combine-ands-and-ors-in-the-where-clause-using-a-query-object
            $query->where('( ' . $db->quoteName('user_id') . ' = ' . (int) $user->id . ' OR ' . $db->quoteName('visitor_id') . ' = ' . $db->q($visitorID) . ')');
        } else 
        {
            $query->where('( ' . $db->quoteName('visitor_id') . ' = ' . $db->q($visitorID) . ')');
        }

        $db->setQuery($query);
        return $db->loadColumn();
    }

    /**
     *  Returns an array with all available campaigns
     *
     *  @return  array
     */
    public static function getCampaigns()
    {
        if (!self::legacyCampaignsEnabled())
        {
            return [];
        }

        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_convertforms/models');

        try
        {
            $model = BaseDatabaseModel::getInstance('Campaigns', 'ConvertFormsModel', array('ignore_request' => true));
            $model->setState('filter.state', 1);

            return $model->getItems();
        }
        catch (\Throwable $th)
        {
            return [];
        }
    }

    /**
     *  Logs messages to log file
     *
     *  @param   string  $msg   The message
     *  @param   object  $type  The log type
     *
     *  @return  void
     */
    public static function log($msg, $type = 'debug')
    {
        $debugIsEnabled = self::getComponentParams()->get('debug', false);

        if ($type == 'debug' && !$debugIsEnabled)
        {
            return;
        }

        $type = ($type == 'debug') ? Log::DEBUG : Log::ERROR;

        try {
            Log::add($msg, $type, 'com_convertforms');
        } catch (\Throwable $th){
        }
    }

    /**
     *  Prepares a form template to be binded to the form builder.
     *
     *  @param   object  $template  The template.
     *
     *  @return  object         
     */
    public static function prepareFormTemplate($template = null)
    {
        if (!$template)
        {
            return;
        }

        $data = (object) array_merge((array) $template, (array) $template->params);

        $data->id = 0;
        $data->campaign = null;
        $data->fields = (array) $data->fields;

        return $data;
    }

    /**
     * Configure the Linkbar.
     *
     * @param   string  $vName  The name of the active view.
     *
     * @return  void
     */
    public static function addSubmenu($vName)
    {
        $items = [
            [
                'label' => 'NR_DASHBOARD',
                'view'  => 'convertforms',
                'skip_auth' => true
            ],
            [
                'label' => 'COM_CONVERTFORMS_FORMS',
                'view'  => 'forms',
            ],
            [
                'label' => 'COM_CONVERTFORMS_CAMPAIGNS',
                'view'  => 'campaigns',
                'requires' => [__CLASS__, 'legacyCampaignsEnabled'],
            ],
            [
                'label' => 'COM_CONVERTFORMS_SUBMISSIONS',
                'view'  => 'conversions',
                'view_rule' => 'submissions'
            ],
            [
                'label' => 'COM_CONVERTFORMS_ADDONS',
                'view'  => 'addons',
            ]
        ];

        foreach ($items as $item)
        {
            if (isset($item['requires']) && !call_user_func($item['requires']))
            {
                continue;
            }

            if (!isset($item['skip_auth']) && !self::authorise('convertforms.' . (isset($item['view_rule']) ? $item['view_rule'] : $item['view']) . '.manage'))
            {
                continue;
            }

            Sidebar::addEntry(Text::_($item['label']), 'index.php?option=com_convertforms&view=' . $item['view'], $item['view']);   
        }
    }

    /**
     *  Returns permissions
     *
     *  @return  object
     */
    public static function getActions()
    {
        $user = Factory::getUser();
        $result = new CMSObject;
        $assetName = 'com_convertforms';

        $actions = array(
            'core.admin', 'core.manage', 'core.create', 'core.edit', 'core.edit.state', 'core.delete'
        );

        foreach ($actions as $action)
        {
            $result->set($action, $user->authorise($action, $assetName));
        }

        return $result;
    }
  
    /**
     *  Prepares a form object for rendering
     *
     *  @param   Object  $form  The form object
     *
     *  @return  array          The prepared form array
     */
    public static function prepareForm($item)
    {
        $item['id'] = isset($item['id']) ? $item['id'] : 0;
        $classPrefix = 'cf';

        // Replace variables in the $item object. Pass $item as the form object too. This helps in {form} Smart Tags.
        $item = \ConvertForms\SmartTags::replace($item, null, true, $item);

        $params = new Registry($item['params']);
        $item['params'] = $params;

        /* Box Classes */
        $boxClasses = array(
            "convertforms",
            $classPrefix,
            $classPrefix . "-" . $params->get("imgposition"),
            $classPrefix . "-" . $params->get("formposition"),
            $params->get("hideform", true) ? $classPrefix . "-success-hideform" : null,
            $params->get("hidetext", false) ? $classPrefix . "-success-hidetext" : null,
            !$params->get("hidelabels", false) ? $classPrefix . "-hasLabels" : null,
            $params->get("centerform", false) ? $classPrefix . "-isCentered" : null,
            $params->get("classsuffix", null),
            $classPrefix . '-labelpos-' . $params->get('labelposition', 'top'),
        );

        /* Box Styles */
        $font = trim($params->get('font'));

        // Form HTML Attributes
        $newBoxAtts = [
            'id' => $classPrefix . '_' . $item['id'],
            'class' => implode(" ", $boxClasses),
            'data-id' => $item['id']
        ];

        $existingBoxAtts = isset($item['containerAtts']) ? $item['containerAtts'] : [];
        $boxAtts = array_merge($newBoxAtts, $existingBoxAtts);

       // Create HTML attributes string safely
        $item['containerAtts'] = implode(' ', array_map(function($key, $value)
        {
            // Escape values to prevent XSS
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            return sprintf('%s="%s"', $key, $value);
        }, array_keys($boxAtts), $boxAtts));

        // Keep old attribute to prevent breaking template layout overrides.
        $item['boxattributes'] = $item['containerAtts'];

        // Main Image Checks
        $imageOption = $params->get("image");
        if ($imageOption == '1')
        {
            $imageFile = Helper::cleanLocalImage($params->get('imagefile', ''));

            if (file_exists(JPATH_SITE . '/' . $imageFile))
            {
                $item['image'] = Uri::root() . $imageFile;
            }
        }
        if ($imageOption == '2')
        {
            $item['image'] = $params->get("imageurl");
        }

        // Image Container
        $item['imagecontainerclasses'] = implode(" ", array(
            (in_array($params->get("imgposition"), array("img-right", "img-left")) ? $classPrefix . "-col-medium-" . $params->get("imagesize", 6) : null),
        ));

        // Image
        $item['imagestyles'] = array(
            "width:" . ($params->get("imageautowidth", "auto") == "auto" ? "auto" : (int) $params->get("imagewidth", "500") . "px"),
            "left:". (int) $params->get("imagehposition", "0") . "px ",
            "top:". (int) $params->get("imagevposition", "0") . "px"
        );
        $item['imageclasses'] = array(
            ($params->get("hideimageonmobile", false) ? "cf-hide-mobile" : "")
        );

        // Form Container
        $item['formclasses'] = array(
            (in_array($params->get("formposition"), array("form-left", "form-right")) ? $classPrefix . "-col-large-" . $params->get("formsize", 6) : null),
        );
        $item['formstyles'] = array(
            "background-color:" . $params->get("formbgcolor", "none")
        );

        // Content
        $item['contentclasses'] = implode(" ", array(
            (in_array($params->get("formposition"), array("form-left", "form-right")) ? $classPrefix . "-col-large-" . (16 - $params->get("formsize", 6)) : null),
        ));

        // Text Container
        $item['textcontainerclasses'] = implode(" ", array(
            (in_array($params->get("imgposition"), array("img-right", "img-left")) ? $classPrefix . "-col-medium-" . (16 - $params->get("imagesize", 6)) : null),
        ));

        $textContent = trim($params->get("text", ''));
        $footerContent = trim($params->get("footer", ''));

        $item['textIsEmpty']   = empty($textContent);
        $item['footerIsEmpty'] = empty($footerContent);
        $item['hascontent']    = !$item['textIsEmpty'] || (bool) isset($item['image']) ?  1 : 0;

        // Prepare Fields
        $item['fields_prepare'] = \ConvertForms\FieldsHelper::prepare($item);

        // Load custom fonts into the document
        \NRFramework\Fonts::loadFont($font);

        return $item;
    }

    /**
     *  Renders form by ID
     *
     *  @param   integer  $id  The form ID
     *
     *  @return  string        The form HTML
     */
    public static function renderFormById($id)
    {
        if (!$data = Form::load($id))
        {
            return '';
        }

        self::loadassets(true);

        return self::renderForm($data);
    }

    /**
     *  Renders Form
     *
     *  @param   integer  $formid  The form id
     *
     *  @return  string            The form HTML
     */
    public static function renderForm($data)
    {
        $app = Factory::getApplication();

        PluginHelper::importPlugin('convertforms');
        PluginHelper::importPlugin('convertformstools');

        // load translation strings
        self::loadTranslations();

        // Ensure extension's language file is loaded. 
        Functions::loadLanguage('com_convertforms');

        \ConvertForms\Validation\Helper::formBeforeRender();

        // @todo - Move PHP Scripts logic into a separate plugin
        // Let user manipulate the form's settings by running their own PHP script
        $payload_1 = ['form' => &$data];
        Form::runPHPScript($data['id'], 'formprepare', $payload_1);

        $app->triggerEvent('onConvertFormsFormBeforeRender', [&$data]);

        // Prepare form and fields
        $data = self::prepareForm($data);
        $html = self::layoutRender('form', $data);

        // Let user manipulate the form's HTML by running their own PHP script
        $payload_2 = [
            'formLayout' => &$html,
            'form'       => $data
        ];
        Form::runPHPScript($data['id'], 'formdisplay', $payload_2);

        $app->triggerEvent('onConvertFormsFormAfterRender', [&$html, $data]);

        if ($app->isClient('site'))
        {
            // Support conditional shortcode (Only on the front-end)
            self::parseIfShortcode($html);

            // Add Custom JS to <head>
            if ($customJS = $data['params']->get('customcode'))
            {
                // Remove the <script> tags from the code
                $customJS = preg_replace('/<.*script.*>/m', '', $customJS);
                Factory::getDocument()->addScriptDeclaration($customJS);
            }
        }

        // Prevent user frustration by fixing broken images in the backend. 
        // This is required since v2.8.0 where we no longer forces absolute URLs in the text editors.
        if ($app->isClient('administrator'))
        {
            $html = \NRFramework\URLHelper::relativePathsToAbsoluteURLs($html, null, false);
        }

        if ($app->isClient('site'))
        {
            $html = HTMLHelper::_('content.prepare', $html, null, 'convertforms');
        }

        $app->triggerEvent('onConvertFormsFormAfterContentPrepare', [&$html, $data]);

        return $html;
    }
    
    /**
     * Enqueues translations for the front-end
     * 
     * @return  void
     */
    private static function loadTranslations()
    {
        Text::script('COM_CONVERTFORMS_INVALID_RESPONSE');
        Text::script('COM_CONVERTFORMS_INVALID_TASK');
    }

    /**
     * Render HTML overridable layout
     *
     * @param  string $layout   The layout name
     * @param  object $data     The data passed to layout
     *
     * @return string   The rendered HTML layout
     */
    public static function layoutRender($layout, $data)
    {
        return LayoutHelper::render($layout, $data, null, ['debug' => false, 'client' => 1, 'component' => 'com_convertforms']);
    }

    /**
     *  Loads components media files
     *
     *  @param   boolean  $front
     *
     *  @return  void
     */
    public static function loadassets($frontend = false)
    {
        static $run;

        if ($run)
        {
            return;
        }

        $doc = Factory::getDocument();
        $app = Factory::getApplication();
        $input = $app->input;

        $run = true;

        // Front-end media files
        if ($frontend)
        {
            // Load core.js needed by keepalive script. 
            HTMLHelper::_('behavior.core');
            HTMLHelper::_('behavior.keepalive');

			HTMLHelper::script('com_convertforms/site.js', ['relative' => true, 'version' => 'auto']);

            $params = self::getComponentParams();

            if ($params->get("loadCSS", true))
            {
                HTMLHelper::stylesheet('com_convertforms/convertforms.css', ['relative' => true, 'version' => 'auto']);
            }

            $options = $doc->getScriptOptions('com_convertforms');
            $options = is_array($options) ? $options : [];

            $options = [
                // The endpoint every AJAX request in site.js is built from, form submission included.
                // Same helper as the File Upload and ALTCHA fields, so all three agree.
                'baseURL' => self::getAjaxUrl(),
                'debug' => (bool) $params->get('debug', false),
                'forward_context' => [
                    'request' => [
                        'view' => $input->get('view'),
                        'task' => $input->get('task'),
                        'option' => $input->get('option'),
                        'layout' => $input->get('layout'),
                        'id' => $input->getInt('id')
                    ]
                ]
            ];

            $doc->addScriptOptions('com_convertforms', $options);

            return;
        }

        HTMLHelper::_('jquery.framework');

        HTMLHelper::stylesheet('com_convertforms/convertforms.sys.css', ['relative' => true, 'version' => 'auto']);
    }

    /**
     *  Get Campaign Item by ID
     *
     *  @param   integer  $id  The campaign ID
     *
     *  @return  object
     */
    public static function getCampaign($id)
    {
        if (!self::legacyCampaignsEnabled() || !$id)
        {
            return null;
        }

        try
        {
            $model = BaseDatabaseModel::getInstance('Campaign', 'ConvertFormsModel', array('ignore_request' => true));
            return $model->getItem($id);
        }
        catch (\Throwable $th)
        {
            return null;
        }
    }

    /**
     * Write the given error message to log file. 
     *
     * @param  string $error_message    The error message
     *
     * @return void
     */
    public static function logError($error_message)
    {
        try {
            Log::add($error_message, Log::ERROR, 'convertforms_errors');
        } catch (\Throwable $th) {
        }
    }

    /**
     * Format given date based on the DATE_FORMAT_LC5 global format
     *
     * @param  string $date
     *
     * @return string
     */
    public static function formatDate($date)
    {
        if (!$date || $date == '0000-00-00 00:00:00')
        {
            return $date;
        }

        return HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC5'));
    }

    public static function getColumns($form_id)
    {
        if (!$form_id)
        {
            return [];
        }

        $fields = Form::load($form_id, true, true);

        if (!is_array($fields) || !is_array($fields['fields']))
        {
            return [];
        }

        // Form Fields
        $form_fields = array_map(function($value)
        {
            return 'param_' . $value;
        }, array_keys($fields['fields']));

        // The submission-metadata columns are the single source of truth held by the
        // Detail classes (see Submission\Detail\Base::discoverColumns()). Adding a new
        // *Detail.php file makes its column appear here automatically. Submission Notes
        // is NotesDetail and sorts last via its high $listOrder.
        $detail_columns = \ConvertForms\Submission\Detail\Base::discoverColumns();

        // Keep ID and Date Submitted as the first 2 columns, then the form fields,
        // then the remaining metadata columns (with Submission Notes last).
        $columns = array_merge(
            array_slice($detail_columns, 0, 2),
            $form_fields,
            array_slice($detail_columns, 2)
        );

        return $columns;
    }

    /**
     * Whether the Pro edition of Convert Forms is installed. Visitor tracking
     * (IP, geolocation, device, source/campaign) is a Pro-only feature; in the
     * Free edition those columns are neither collected nor exposed.
     *
     * @return  bool
     */
    public static function isPro()
    {
        static $isPro = null;

        if ($isPro === null)
        {
            $isPro = \NRFramework\Extension::isPro('com_convertforms');
        }

        return $isPro;
    }

    /**
     * Returns the real column names of the submissions table.
     * Used to decide whether a list column is sortable (derived/alias columns are not).
     *
     * @return  string[]
     */
    public static function getSubmissionTableColumns()
    {
        static $cols = null;

        if ($cols === null)
        {
            $cols = array_keys(Factory::getDbo()->getTableColumns('#__convertforms_conversions'));
        }

        return $cols;
    }

	/**
	 * Return absolute full URL of a path
	 *
	 * @param	string	$path
	 *
	 * @return	string
	 */
	public static function pathTorelative($path)
	{
		return str_replace([JPATH_SITE, JPATH_ROOT], '', $path);
	}

	/**
	 * Return absolute full URL of a path
	 *
	 * @param	string	$path
	 *
	 * @return	string
	 */
	public static function absURL($path)
	{
		$path = str_replace([JPATH_SITE, JPATH_ROOT, Uri::root()], '', $path);
		$path = Path::clean($path);

		// Convert Windows Path to Unix
		$path = str_replace('\\','/',$path);

		$path = ltrim($path, '/');
		$path = Uri::root() . $path;

		return $path;
    }

    /**
     * This is a joke. Joomla 4's media field started including width and height information in the path. 
     * So, we need to clean the path before we can use it. 
     * 
     * images/headers/blue-flower.jpg#joomlaImage://local-images/headers/blue-flower.jpg?width=700&height=180)
     * 
     * @param  string $path
     * 
     * @return string
     */
    public static function cleanLocalImage($path)
    {
        return \Joomla\CMS\Helper\MediaHelper::getCleanMediaFieldValue($path);
    }

    /**
     * Remove special charactes from string in order to be used in a HTML attribute without issues.
     *
     * @param  string $string   The text to clean
     *  
     * @return string  The cleaned text
     */
    public static function makeHTMLAttributeSafe($string)
    {
        // Replaces all spaces with hyphens.
        $string = str_replace(' ', '-', $string);

        // Removes special characters
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);

        // Replaces multiple hyphens with single one.
        $string = preg_replace('/-+/', '-', $string);
        
        return strtolower($string);
    }

    /**
     * Replace shortcodes anywhere we can write text
     *
     * @param   string  $buffer The text where to seach for shortcodes
     * 
     * @param   boolean $disableEmailCloak
     * 
     * @return void
     */
    public static function doShortcodeReplacements(&$buffer, $context = null)
    {
        // Abort if not a string
        if (!is_string($buffer))
        {
            return true;
        }

        // Check whether the plugin should process or not
        if (StringHelper::strpos($buffer, 'convertforms') === false)
        {
            return true;
        }

        $regex = "#{convertforms(\s)(\d+)(\s?)(.*?)}#s";

        preg_match_all($regex, $buffer, $matches);

        if (empty($matches[4]))
        {
            return;
        }

        foreach ($matches[4] as $index => $task)
        {
            // Experimental workaround: Prevent Joomla from caching a form by skipping shortcode replacement during the onContentPrepare event.
            // Instead, let the onBeforeRender event which runs later to catch and replace the shortcode.
            // To enable this, add the --skipPrepareContentRender attribute to the {convertforms X} shortcode.
            // Example usage: {convertforms ID --skipPrepareContentRender}
            // If this approach proves stable, it may become the default behavior.
            if ($context == 'onContentPrepare' && strpos($task, '--skipPrepareContentRender') !== false)
            {
                continue;
            }

            $formID = $matches[2][$index];
            $result = '';

            switch ($task)
            {
                case 'submissions_total':
                    $result = \ConvertForms\Api::getFormSubmissionsTotal($formID);
                    break;

                default:
                    $result = self::renderFormById($formID);
            }

            $buffer = str_replace($matches[0][$index], $result, $buffer);
        }
    }

    /**
     * Returns the total number of forms with emails enabled (both old and new email notifications).
     * 
     * @return integer
     */
    public static function getTotalFormsWithEmailsEnabled()
    {
        $db = Factory::getDBO();
        $query = $db->getQuery(true);

        $query
            ->select('id, params')
            ->from('#__convertforms');

        $db->setQuery($query);

        $forms = $db->loadAssocList();

        $total = 0;
        
        foreach ($forms as $form)
        {
            $params = isset($form['params']) ? json_decode($form['params'], true) : [];
            if (!$params)
            {
                continue;
            }

            $oldEmailsEnabled = isset($params['sendnotifications']) && $params['sendnotifications'] == '1';
            $totalOldEmails = $oldEmailsEnabled && isset($params['emails']) && is_array($params['emails']) ? count($params['emails']) : 0;
            $tasks = \ConvertForms\Tasks\ModelTasks::getItems($form['id']);
            $totalNewEmails = count(array_filter($tasks, function($task) {
                return $task['app'] == 'email' && $task['state'];
            }));

            $total += $totalOldEmails + $totalNewEmails;
        }

        return $total;
    }

    public static function icon($icon, $params = [])
    {
        $size = isset($params['size']) ? (int) $params['size'] : 28;

        $iconContent = @file_get_contents(JPATH_SITE . '/media/com_convertforms/icons/' . $icon . '.svg');
        $iconContent = str_replace('<svg width="50" height="50"', '<svg width="' . $size . '" height="' . $size . '"', $iconContent);
        $iconContent = str_replace('<path', '<path style="fill:currentColor;" class="fill-current"', $iconContent);
        $iconContent = str_replace('Icon=', 'Icon', $iconContent);
        $iconContent = str_replace('Bounding box', 'Boundingbox', $iconContent);

        return $iconContent;
    }
}
