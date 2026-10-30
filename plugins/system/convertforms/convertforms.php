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

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Log\Log;
use ConvertForms\Helper;
use Joomla\Registry\Registry;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarButton;
use ConvertForms\Tasks\MigrationPrompt;

class PlgSystemConvertForms extends CMSPlugin
{
    /**
     *  Application Object
     *
     *  @var  object
     */
    protected $app;

    /**
     *  Component's param object
     *
     *  @var  Registry
     */
    private $param;

    /**
     *  The loaded indicator of helper
     *
     *  @var  boolean
     */
    private $init;

    /**
     *  Log Object
     *
     *  @var  Object
     */ 
    private $log;

    /**
     *  AJAX Response
     *
     *  @var  stdClass
     */
    private $response;

    /**
     *  Plugin constructor
     *
     *  @param  mixed   &$subject
     *  @param  array   $config
     */
    public function __construct(&$subject, $config = array())
    {
        $component = \Joomla\CMS\Component\ComponentHelper::getComponent('com_convertforms', true);

        /**
         * Εxecute parent constructor early as $app is not available when
         * we uninstall the administrator component.
         */
        parent::__construct($subject, $config);

        if (!$component->enabled)
        {   
            return;
        }

        // Load required classes
        if (!$this->loadClasses())
        {
            return;
        }

        // Declare extension logger
        Log::addLogger(
            array('text_file' => 'com_convertforms.php'),
            Log::ALL, 
            array('com_convertforms')
        );
    }

    /**
     * Replace shortcodes in the component's buffer without the need of the "Content Prepare" option. 
     * 
     * Note: This does not parse modules output. The "Custom Module" still requires the Content Prepare option to be enabled.
     * 
     * Note: The replacement that's performed here is causing issues when editing Joomla! articles (replaces the shortcode with actual content).
     * This hook may be removed in the future.
     *
     * @return void
     */
    public function onBeforeRender()
    {
        // Inject the Campaigns to Tasks migration UI on Convert Forms backend pages. Runs before
        // the frontend shortcode logic below, which bails out on the administrator client.
        $this->injectCampaignsMigrationUi();

        if (!$this->getHelper())
        {
            return;
        }

        if (!$this->canReplaceBeforeRender())
        {
            return;
        }

        $doc = $this->app->getDocument();

        $buffer = $doc->getBuffer('component');

        Helper::doShortcodeReplacements($buffer);

        $doc->setBuffer($buffer, 'component');
    }

    /**
     * Server-side guard that disables creating new legacy campaigns once the migration is done.
     *
     * Runs before Joomla loads the (stale) legacy controller, so no legacy code path is executed
     * for blocked tasks. Editing/publishing/trashing existing campaigns stays allowed.
     *
     * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
     *
     * @return  void
     */
    public function onAfterRoute()
    {
        $this->guardCampaignCreation();
    }

    /**
     * Injects the Campaigns to Tasks notice + modal into the component buffer, replaces the stale
     * deprecation warning on the campaigns pages, and applies the post-migration lockdown.
     *
     * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
     *
     * @return  void
     */
    private function injectCampaignsMigrationUi()
    {
        if (!$this->app->isClient('administrator'))
        {
            return;
        }

        if ($this->app->input->get('option') !== 'com_convertforms')
        {
            return;
        }

        $view = $this->app->input->get('view', 'convertforms');

        if (!in_array($view, ['convertforms', 'forms', 'conversions', 'campaigns', 'campaign', 'addons']))
        {
            return;
        }

        if ($this->app->input->get('layout') === 'modal' || $this->app->input->get('tmpl') === 'component')
        {
            return;
        }

        // The administrator path skips getHelper(), so load the language file defensively.
        NRFramework\Functions::loadLanguage('com_convertforms');

        $isCampaignsView = in_array($view, ['campaigns', 'campaign']);

        // On the campaigns pages the "please migrate" warning is wrong both before (the notice
        // replaces it) and after (creation is disabled) migration, so always drop it.
        if ($isCampaignsView)
        {
            $this->filterCampaignsDeprecationMessage();
        }

        $completed = Helper::campaignsMigrationCompleted();
        $html      = '';

        if (MigrationPrompt::shouldShow())
        {
            $html = (new MigrationPrompt())->render();
        }

        // Post-migration lockdown: hide the "New"/"Duplicate" campaign entry points.
        if ($view === 'campaigns' && $completed)
        {
            $this->hideCampaignCreationButtons();
        }

        if ($html === '')
        {
            return;
        }

        $doc    = $this->app->getDocument();
        $buffer = $doc->getBuffer('component');

        $doc->setBuffer($html . $buffer, 'component');
    }

    /**
     * Drops the stale "Campaigns are deprecated" system message enqueued by the legacy views.
     *
     * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
     *
     * @return  void
     */
    private function filterCampaignsDeprecationMessage()
    {
        $target = Text::_('COM_CONVERTFORMS_CAMPAIGNS_LIST_DEPRECATION_MSG');
        $queue  = $this->app->getMessageQueue(true);

        foreach ($queue as $message)
        {
            if (isset($message['message']) && $message['message'] === $target)
            {
                continue;
            }

            $this->app->enqueueMessage($message['message'], $message['type']);
        }
    }

    /**
     * Hides the campaign-creation controls on the campaigns list toolbar (New + Duplicate).
     *
     * Belt and braces: the parent-toolbar items are filtered where the API allows it, and a small
     * CSS rule hides the remaining entry points (child-toolbar Duplicate, empty-state link). The
     * actual enforcement is the onAfterRoute() guard.
     *
     * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
     *
     * @return  void
     */
    private function hideCampaignCreationButtons()
    {
        try
        {
            $toolbar = Toolbar::getInstance('toolbar');

            if (method_exists($toolbar, 'getItems') && method_exists($toolbar, 'setItems'))
            {
                $items = array_filter($toolbar->getItems(), function ($button)
                {
                    return !($button instanceof ToolbarButton && $button->getName() === 'new');
                });

                $toolbar->setItems(array_values($items));
            }
        }
        catch (\Throwable $e)
        {
            // Fall through to the CSS hide + route guard.
        }

        $this->app->getDocument()->addStyleDeclaration(
            '#toolbar joomla-toolbar-button[task="campaign.add"],'
            . ' #toolbar .button-new,'
            . ' #toolbar [task="campaigns.duplicate"],'
            . ' .cf-campaigns-empty [onclick*="campaign.add"],'
            . ' [onclick*="campaign.add"] { display: none !important; }'
        );
    }

    /**
     * Blocks the tasks/routes that create a new legacy campaign once the migration is complete.
     *
     * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
     *
     * @return  void
     */
    private function guardCampaignCreation()
    {
        if (!$this->app->isClient('administrator'))
        {
            return;
        }

        if ($this->app->input->get('option') !== 'com_convertforms')
        {
            return;
        }

        if (!Helper::campaignsMigrationCompleted())
        {
            return;
        }

        $task = $this->app->input->get('task', '');
        $view = $this->app->input->get('view', '');

        $blocked = in_array($task, ['campaign.add', 'campaign.save2new', 'campaign.save2copy', 'campaigns.duplicate'], true);

        // The "new campaign" edit screen (view=campaign with no id and no acting task).
        if (!$blocked && $view === 'campaign' && $task === '' && !$this->app->input->getInt('id', 0))
        {
            $blocked = true;
        }

        // A forged save/apply POST that would create a new row (empty jform id).
        if (!$blocked && in_array($task, ['campaign.save', 'campaign.apply'], true))
        {
            $jform = $this->app->input->get('jform', [], 'array');

            if (empty($jform['id']))
            {
                $blocked = true;
            }
        }

        if (!$blocked)
        {
            return;
        }

        NRFramework\Functions::loadLanguage('com_convertforms');

        $this->app->enqueueMessage(Text::_('COM_CONVERTFORMS_CAMPAIGN_CREATION_DISABLED'), 'warning');
        $this->app->redirect(Uri::base() . 'index.php?option=com_convertforms&view=campaigns');
    }

    /**
     * Runs after the application sends the response to the client.
     *
     * @return  void
     */
    public function onAfterDispatch()
    {
        // Only run in the backend
        $option = $this->app->input->get('option');
        if ($this->app->isClient('administrator') && in_array($option, ['com_cpanel', 'com_convertforms', 'com_config']))
        {
            if (!Factory::getConfig()->get('mailonline') && \ConvertForms\Helper::getTotalFormsWithEmailsEnabled() > 0)
            {
                NRFramework\Functions::loadLanguage('com_convertforms');
                
                $send_email_settings_url = Uri::base() . 'index.php?option=com_config';
                $this->app->enqueueMessage(Text::sprintf('COM_CONVERTFORMS_SEND_EMAIL_DISABLED', $send_email_settings_url), 'warning');
            }
        }
    }

    /**
     * Prevent the replacement when editing specific components
     * on the front-end of the website.
     */
    private function canReplaceBeforeRender()
    {
        $pass = true;

        switch ($this->app->input->get('option'))
        {
            case 'com_content':
            case 'com_dpcalendar':
                $pass = $this->app->input->get('view') !== 'form' && $this->app->input->get('layout') !== 'edit';
                break;
            case 'com_jevents':
                $pass = $this->app->input->get('task') !== 'icalevent.edit';
                break;
        }

        return $pass;
    }

    /**
     *  Handles the content preparation event fired by Joomla!
     *
     *  @param   mixed     $context     Unused in this plugin.
     *  @param   stdClass  $article     An object containing the article being processed.
     *  @param   mixed     $params      Unused in this plugin.
     *  @param   int       $limitstart  Unused in this plugin.
     *
     *  @return  bool
     */
    public function onContentPrepare($context, &$article)
    {
        if (!isset($article->text))
        {
            return true;
        }
        
        // Get Helper
        if (!$this->getHelper())
        {
            return true;
        }

        Helper::doShortcodeReplacements($article->text, 'onContentPrepare');
    }

    /**
     * Special integration for EngageBox that ensures Convert Forms shortcodes are parsed even if the EngageBox's Content Prepare option is disabled or not supported like in EngageBox Free.
     * 
     * If one day we decide to offer Content Prepare in EngageBox Free, we can get rid of this hook.
     * 
     * @param   string    $html   The campaign's final HTML
     * 
     * @return  void
     */
    public function onEngageBoxAfterRender(&$html)
    {
        if (!$this->getHelper())
        {
            return true;
        }

        Helper::doShortcodeReplacements($html);
    }

    /**
     * Improve performance and prevent conflicts by not allowing modules to be rendered in the form builder.
     * 
     * Don't use the onPrepareModuleList event as it does not work in Joomla 5.
     *
     * @param  array $modules The list of modules to be rendered in the page
     * 
     * @return void
     */
    public function onAfterModuleList(&$modules)
    {
        if ($this->app->isClient('administrator') && $this->app->input->get('option') == 'com_convertforms' && $this->app->input->get('view') == 'form')
        {
            $modules = [];
        }
    }

    /**
     *  Listens to AJAX requests on ?option=com_ajax&format=raw&plugin=convertforms
     *  Method aborts on invalid token or task
     *
     *  @return void
     */
    public function onAjaxConvertForms()
    {
        // Disable all PHP reporting to ensure a success AJAX response.
        $debug = ConvertForms\Helper::getComponentParams()->get('debug', false);
        if (!$debug)
        {
            error_reporting(E_ALL & ~E_NOTICE);
        }

        $input = $this->app->input;
        $form_id = isset($input->getArray()['cf']) ? $input->getArray()['cf']['form_id'] : 0;

        // Check if we have a valid task
        $task = $input->get('task', null);

        if (is_null($task))
        {
            die('Invalid task');
        }

        // An access token is required on all requests except on the API task which 
        // has a native authentication method through an API Key
        if (!in_array($task, ['api']) && !Session::checkToken('request'))
        {
            ConvertForms\Helper::triggerError(Text::_('JINVALID_TOKEN'), $task, $form_id, $input->request->getArray());
            jexit(Text::_('JINVALID_TOKEN'));
        }

        // Cool access granted.
        $componentPath = JPATH_ADMINISTRATOR . '/components/com_convertforms/';
        BaseDatabaseModel::addIncludePath($componentPath . 'models');
        Table::addIncludePath($componentPath . 'tables');

        // Load component language file
        NRFramework\Functions::loadLanguage('com_convertforms');

        // Check if we have a valid method task
        $taskMethod = 'ajaxTask' . $task;

        if (!method_exists($this, $taskMethod))
        {
            die('Task not found');
        }

        // Success! Let's call the method.
        $this->response = new stdClass();

        try
        {
           $this->$taskMethod();
        }
        catch (Exception $e)
        {
            ConvertForms\Helper::triggerError($e->getMessage(), $task, $form_id, $input->request->getArray());
            $this->response->error = $e->getMessage();
        }

        echo json_encode($this->response);

        // Stop execution
        jexit();
    }

    
    
    /**
     *  Map onContentAfterSave event to onConvertFormsConversionAfterSave
     *  
     *  Content is passed by reference, but after the save, so no changes will be saved.
     *  Method is called right after the content is saved.
     *
     *  @param   string  $context  The context of the content passed to the plugin (added in 1.6)
     *  @param   object  $article  A JTableContent object
     *  @param   bool    $isNew    If the content has just been created
     *
     *  @return  void
     * 
     *  @deprecated Remove this code block and update the onConvertFormsConversionAfterSave() method to use onConvertFormsSubmissionAfterSave().
     */
    public function onContentAfterSave($context, $article, $isNew)
    {
        if ($context != 'com_convertforms.conversion' || $this->app->isClient('administrator'))
        {
            return;
        }

        PluginHelper::importPlugin('convertforms');
        PluginHelper::importPlugin('convertformstools');

        // Load item row
        $model = BaseDatabaseModel::getInstance('Conversion', 'ConvertFormsModel', array('ignore_request' => true));
        if (!$conversion = $model->getItem($article->id))
        {
            return;
        }

        Factory::getApplication()->triggerEvent('onConvertFormsConversionAfterSave', array($conversion, $model, $isNew));
    }

     /**
     *  Prepare form.
     *
     *  @param   Form  $form  The form to be altered.
     *  @param   mixed  $data  The associated data for the form.
     *
     *  @return  boolean
     */
    public function onContentPrepareForm($form, $data)
    {
        // Return if we are in frontend
        if ($this->app->isClient('site'))
        {
            return true;
        }

        // Check we have a form
        if (!($form instanceof Joomla\CMS\Form\Form))
        {
            return false;
        }

        // Check we have a valid form context
        $validForms = array(
            "com_convertforms.campaign",
            "com_convertforms.form"
        );

        if (!in_array($form->getName(), $validForms))
        {
            return true;
        }

        // Load ConvertForms plugins
        PluginHelper::importPlugin('convertforms');
        PluginHelper::importPlugin('convertformstools');

        // Campaign Forms
        if ($form->getName() == 'com_convertforms.campaign')
        {
            if (!isset($data->service) || !$service = $data->service)
            {
                return true;
            }
            
            $result = Factory::getApplication()->triggerEvent('onConvertFormsCampaignPrepareForm', [$form, $data, $service]);
        }

        // Form Editing Page
        if ($form->getName() == 'com_convertforms.form')
        {
            $result = Factory::getApplication()->triggerEvent('onConvertFormsFormPrepareForm', [$form, $data]);
        }

        return true;
    }

    /**
     *  Silent load of Convert Forms and Framework classes
     *
     *  @return  boolean
     */
    private function loadClasses()
    {
        // Initialize Convert Forms Library
        if (!@include_once(JPATH_ADMINISTRATOR . '/components/com_convertforms/autoload.php'))
        {
            return false;
        }

        // Load Framework
        if (!@include_once(JPATH_PLUGINS . '/system/nrframework/autoload.php'))
        {
            return false;
        }

        // Declare extension's error log file
        Joomla\CMS\Log\Log::addLogger(
            [
                'text_file' => 'convertforms_errors.php',
                'text_entry_format' => '{MESSAGE}'
            ], 
            Joomla\CMS\Log\Log::ERROR, 
            ['convertforms_errors']
        );

        return true;
    }

    /**
     *  Loads the helper classes of plugin
     *
     *  @return  bool
     */
    private function getHelper()
    {
        // Return if is helper is already loaded
        if ($this->init)    
        {
            return true;
        }

        // Return if we are not in frontend
        if (!$this->app->isClient('site'))
        {
            return false;
        }

        // Return if document type is Feed
        if (NRFramework\Functions::isFeed())
        {
            return false;
        }

        // Load language
        Factory::getLanguage()->load('com_convertforms', JPATH_ADMINISTRATOR . '/components/com_convertforms');

        return ($this->init = true);
    }
}
