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

use ConvertForms\Tasks\CampaignsToTasksMigrator;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

/**
 * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
 */
class ConvertFormsControllerCampaignsToTasks extends BaseController
{
    public function start()
    {
        $this->respond(function() {
            $migrator = new CampaignsToTasksMigrator();
            return $migrator->start();
        });
    }

    public function run()
    {
        $this->respond(function() {
            $app = Factory::getApplication();
            $migrator = new CampaignsToTasksMigrator();

            $result = $migrator->run(
                $app->input->getInt('cursor', 0),
                $app->input->getInt('batch', CampaignsToTasksMigrator::DEFAULT_BATCH_SIZE)
            );

            // Once the batch loop is done, tell the client whether any migratable form is still
            // pending, so it knows whether the notice must disappear (see the notice's JS).
            if (!empty($result['done']))
            {
                $result['pending'] = $migrator->getPendingMigratableCount();
            }

            return $result;
        });
    }

    private function respond($callback)
    {
        try
        {
            $this->checkRequest();
            echo new JsonResponse($callback());
        }
        catch (\Throwable $e)
        {
            http_response_code(500);
            echo new JsonResponse($e);
        }

        jexit();
    }

    private function checkRequest()
    {
        if (!Session::checkToken('request'))
        {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'));
        }

        if (!Factory::getUser()->authorise('core.admin', 'com_convertforms'))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
        }

        if (!\ConvertForms\Helper::legacyCampaignsEnabled())
        {
            throw new \RuntimeException(Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_DISABLED'));
        }
    }
}
