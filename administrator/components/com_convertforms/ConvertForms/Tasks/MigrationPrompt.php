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

defined('_JEXEC') or die('Restricted access');

use ConvertForms\Helper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Renders the Campaigns to Tasks migration notice + modal on Convert Forms backend pages.
 *
 * The legacy campaigns views no longer ship, so this is injected at runtime from the system
 * plugin instead of living in a form field or a view template.
 *
 * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
 */
class MigrationPrompt
{
    /**
     * The docs URL for the deprecation/migration guide.
     */
    const DOCS_URL = 'https://www.tassos.gr/docs/convert-forms/troubleshoot/campaigns-deprecated';

    /**
     * Whether the migration notice should be shown.
     *
     * Gated on: legacy campaigns present, migration not yet complete, and the current user able
     * to run it (the AJAX controller requires core.admin, so don't advertise a dead button).
     *
     * @return  bool
     */
    public static function shouldShow()
    {
        if (!Helper::legacyCampaignsEnabled())
        {
            return false;
        }

        if (Helper::campaignsMigrationCompleted())
        {
            return false;
        }

        return Factory::getUser()->authorise('core.admin', 'com_convertforms');
    }

    /**
     * Returns the notice + modal HTML.
     *
     * @return  string
     */
    public function render()
    {
        $this->addAssets();

        $notice = new \NRFramework\Notices\Notices\Notice([
            'ext_element' => 'com_convertforms',
            'type'        => 'warning',
            'class'       => 'cf-campaigns-migration-notice',
            'dismissible' => false,
            'title'       => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_NOTICE_TITLE'),
            'description' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_NOTICE_DESC'),
            'actions'     => '<button type="button" class="btn btn-primary" data-cf-campaigns-migrate>'
                . Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_BUTTON') . '</button>'
                . ' <a href="' . self::DOCS_URL . '" target="_blank" rel="noopener">'
                . Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_NOTICE_DOCS') . '</a>'
        ]);

        return '<div class="tf-notices cf-static-notices">' . $notice->render() . '</div>' . $this->renderModal();
    }

    /**
     * Renders the migration modal.
     *
     * @return  string
     */
    private function renderModal()
    {
        return LayoutHelper::render('campaignstotasks.modal', [], JPATH_ADMINISTRATOR . '/components/com_convertforms/layouts');
    }

    /**
     * Loads the framework notices stylesheet (shared by both the migration and migrated notices).
     *
     * @return  void
     */
    private function addNoticeAssets()
    {
        HTMLHelper::stylesheet('plg_system_nrframework/notices.css', ['relative' => true, 'version' => 'auto']);
    }

    /**
     * Loads the assets and script options the notice + modal need.
     *
     * @return  void
     */
    private function addAssets()
    {
        $this->addNoticeAssets();

        HTMLHelper::stylesheet('com_convertforms/campaignstotasks.css', ['relative' => true, 'version' => 'auto']);
        HTMLHelper::script('com_convertforms/campaignstotasks.js', ['relative' => true, 'version' => 'auto']);

        Factory::getDocument()->addScriptOptions('com_convertforms_campaigns_to_tasks', [
            'startUrl' => Route::_('index.php?option=com_convertforms&task=campaignstotasks.start', false),
            'runUrl'   => Route::_('index.php?option=com_convertforms&task=campaignstotasks.run', false),
            'token'    => Session::getFormToken(),
            'text'     => [
                'running' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_RUNNING'),
                'complete' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_COMPLETE'),
                'failed' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_FAILED'),
                'processed' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_PROCESSED'),
                'migrated' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_MIGRATED'),
                'migratedFull' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_MIGRATED_FULL'),
                'migratedPartial' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_MIGRATED_PARTIAL'),
                'tasksCreated' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_TASKS_CREATED'),
                'tasksReused' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_TASKS_REUSED'),
                'connectionsCreated' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_CONNECTIONS_CREATED'),
                'connectionsReused' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_CONNECTIONS_REUSED'),
                'formsUpdated' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_FORMS_UPDATED'),
                'skipped' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIPPED'),
                'ignored' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_IGNORED'),
                'statusMigrated' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_STATUS_MIGRATED'),
                'statusMigratedFull' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_STATUS_MIGRATED_FULL'),
                'statusMigratedPartial' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_STATUS_MIGRATED_PARTIAL'),
                'statusSkipped' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_STATUS_SKIPPED'),
                'statusIgnored' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_STATUS_IGNORED'),
                'campaign' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_CAMPAIGN'),
                'campaignNone' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_CAMPAIGN_NONE'),
                'mappedCount' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_MAPPED_COUNT'),
                'unmappedFields' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_UNMAPPED_FIELDS'),
                'requiredUnmappedFields' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_REQUIRED_UNMAPPED_FIELDS'),
                'requiredUnmappedNotice' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_REQUIRED_UNMAPPED_NOTICE'),
                'filterAll' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_FILTER_ALL'),
                'reportsEmpty' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_REPORTS_EMPTY'),
                'docsLink' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_UNSAFE_DOCS'),
                'start' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_START'),
                'runAgain' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_RUN_AGAIN'),
                'close' => Text::_('JCLOSE')
            ]
        ]);
    }
}
