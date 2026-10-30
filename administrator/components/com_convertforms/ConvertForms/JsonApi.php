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

use ConvertForms\Helper;
use ConvertForms\Submission\Submission as SubmissionContext;
use ConvertForms\Submission\Detail\Base as SubmissionDetail;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Date\Date;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 *  ConvertForms API Class
 */
class JsonApi
{
    /**
     *  Joomla Application Object
     *
     *  @var  object
     */
    private $app;

    /**
     *  API Key
     *
     *  @var  string
     */
    private $apikey;

    /**
     *  Class Constructor
     *
     *  @param  string  $key  User API Key
     */
    public function __construct($apikey)
    {
        if (!isset($apikey) || empty($apikey))
        {
            $this->throwError('API Key is missing');
        }

        $this->apikey = $apikey;

        if (!$this->authenticate())
        {
            $this->throwError('Invalid API Key: '. $this->apikey);
        }

        $this->app = Factory::getApplication();
    }

    /**
     *  Throws an exception and logs the error message to the log file
     *
     *  @param   string  $error  The error message
     *
     *  @return  void
     */
    private function throwError($error)
    {
        $error = 'ConvertForms API: ' . $error;
        
        Helper::log($error, 'error');
        throw new \Exception($error);
    }

    /**
     *  Authenticate API Call
     *
     *  @return  bool          
     */
    public function authenticate()
    {
        return $this->getSiteKey() === $this->apikey;
    }

    /**
     *  Returns Domain Key
     *
     *  @return  string
     */
    public static function generateDomainKey()
    {
        $parse  = parse_url(Uri::root());
        $domain = isset($parse['host']) ? $parse['host'] : '-';
        $hash   = md5('CF' . $domain);

        return $hash;
    }

    /**
     *  Returns Domain Key
     *
     *  @return  string
     */
    public static function getSiteKey()
    {
        return Helper::getComponentParams()->get('api_key', null);
    }

    /**
     *  Route API endpoint to the proper method
     *
     *  @param   string  $endpoint  The API Endpoint Name
     *
     *  @return  string             Response Array
     */
    public function route($endpoint)
    {
        $endPointMethod = 'endPoint' . $endpoint;

        if (!method_exists($this, $endPointMethod))
        {
            $this->throwError('Endpoint not found:' . $endpoint);
        }

        return $this->$endPointMethod();
    }

    /**
     *  Loads and populates model
     *
     *  @param   string  $model  
     *
     *  @return  Model           
     */
    private function getModel($modelName)
    {
        if (!$modelName)
        {
            return;
        }

        $model = BaseDatabaseModel::getInstance($modelName, 'ConvertFormsModel', array('ignore_request' => true));

        $model->setState('list.limit',     $this->app->input->get('limit', 100));
        $model->setState('list.start',     $this->app->input->get('start', 0));
        $model->setState('list.ordering',  $this->app->input->get('order', 'a.id'));
        $model->setState('list.direction', $this->app->input->get('dir', 'desc'));

        $state = $this->app->input->get('state', null);

        if (!is_null($state))
        {
            $model->setState('filter.state', $state);
        }

        return $model;
    }

    /**
     *  Backwards compatibility support for old Leads endpoint
     * 
     *  @deprecated 2.2.0
     * 
     *  @return  array  Database array
     */
    private function endPointLeads()
    {
        return $this->endPointSubmissions();
    }
    
    /**
     *  Submissions Endpoint
     * 
     *  @since 2.2.0
     * 
     *  @return  array  Database array
     */
    private function endPointSubmissions()
    {
        // Load Model
        $model = $this->getModel('Conversions');

        // Apply form filter
        $formID = $this->app->input->get('form', null);
        if (!is_null($formID))
        {
            $model->setState('filter.form_id', $formID);
        }

        // Apply campaign filter
        $campaignID = $this->app->input->get('campaign', null);
        if (!is_null($campaignID))
        {
            $model->setState('filter.campaign_id', $campaignID);
        }

        // Get the submissions data without preparing dates, as we need the raw date for ISO8601 conversion
        $leads = $model->getItems(false);
        $data  = array();

        // Submission detail columns to expose, resolved once and reused for every row.
        $detailColumns = SubmissionDetail::discoverColumns();

        foreach ($leads as $key => $lead)
        {
            // Wrap each submission in the Submission context so every property resolves
            // through its Detail class — the same mechanism the Export tool uses. The API
            // context makes date columns (created, modified) resolve as ISO8601.
            $context = new SubmissionContext($lead, SubmissionContext::CONSUMER_API);

            $data_ = array();

            // Output every discovered submission detail column (id, status, created,
            // country, device, utm, notes, etc.) through its Detail class.
            foreach ($detailColumns as $column)
            {
                $data_[$column] = $context->getProp($column)->getValueRaw();
            }

            // Legacy keys with no dedicated Detail class (so absent from the loop above),
            // kept for backwards compatibility with existing API consumers.
            $data_['state']       = $context->getProp('state')->getValueRaw();
            $data_['campaign_id'] = $context->getProp('campaign_id')->getValueRaw();

            // Include custom fields as well
            if (isset($lead->prepared_fields))
            {
                foreach ($lead->prepared_fields as $key => $field)
                {
                    // File Upload has always come out of this endpoint as an array of
                    // absolute URLs, and that conversion can only happen here: exports,
                    // webhooks and the {field.X:raw} Smart Tag share value_raw and depend
                    // on in-site values staying site-relative as stored. External files
                    // are already full download URLs; empty entries (an external file
                    // whose link could not be minted) are dropped, as the old explode
                    // workaround dropped them.
                    if ($field->options->get('type') == 'fileupload')
                    {
                        $urls = array_filter((array) $field->value_raw);

                        foreach ($urls as $index => $url)
                        {
                            if (!FileStorage::hasScheme($url))
                            {
                                $urls[$index] = Helper::absURL($url);
                            }
                        }

                        $field->value_raw = array_values($urls);
                    }

                    $data_['field_' . $key] = $field->value_raw;

                    if ($field->value_raw !== $field->value_html)
                    {
                        $data_['field_' . $key . '.html'] = $field->value_html;
                    }
                }
            }

            $data[] = $data_;
        }

        return $data;
    }

    /**
     *  Forms Endpoint
     *
     *  @return  array  Database array
     */
    private function endPointForms()
    {
        return $this->endPointDefault('Forms');
    }

    /**
     *  Campaigns Endpoint
     *
     *  @return  array  Database array
     */
    private function endPointCampaigns()
    {
        if (!Helper::legacyCampaignsEnabled())
        {
            return [];
        }

        return $this->endPointDefault('Campaigns');
    }

    /**
     *  Common Default Endpoint
     *
     *  @param   string  $model  ConvertForms model name
     *
     *  @return  array           
     */
    private function endPointDefault($model)
    {
        // Load Model
        $model = $this->getModel($model);

        if (!$model)
        {
            return;
        }

        // Get Data
        $items = $model->getItems();

        if (!$items)
        {
            return;
        }

        $data  = array();

        foreach ($items as $key => $item)
        {
            $data[] = array(
                'id'      => $item->id,
                'name'    => $item->name,
                'created' => $item->created,
                'state'   => $item->state
            );
        }

        return $data;
    }
}