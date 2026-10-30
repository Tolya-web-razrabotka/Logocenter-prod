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

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use ConvertForms\Helper;

/**
 *  Conversions Class
 */
class ConvertFormsModelConversions extends ListModel
{
    /**
     * Constructor.
     *
     * @param    array    An optional associative array of configuration settings.
     *
     * @see        JController
     */
    public function __construct($config = array())
    {
        if (empty($config['filter_fields']))
        {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'state', 'a.state',
                'created', 'a.created',
                'search',
                'campaign_id', 'a.campaign_id',
                'form_id', 'a.form_id',
                'created_from', 'created_to',
                'columns', 'a.columns',
                'period', 'a.period'
            );
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function populateState($ordering = 'a.id', $direction = 'desc')
    {
        // List state information.
        parent::populateState($ordering, $direction);

        $state = $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state');
        $this->setState('filter.state', $state);
        
        $formID = $this->getUserStateFromRequest($this->context . '.filter.form_id', 'filter_form_id', $this->getLastFormID());
        $this->setState('filter.form_id', $formID);
        
        $campaignID = $this->getUserStateFromRequest($this->context . '.filter.campaign_id', 'filter_campaign_id');
        $this->setState('filter.campaign_id', $campaignID);

        $period = $this->getUserStateFromRequest($this->context . '.filter.period', 'filter_period');
        $this->setState('filter.period', $period);

        $formColumns = \ConvertForms\Helper::getColumns($formID);

        // The column selection is remembered per form, per back-end user, in #__user_profiles.
        // The picker lives in the search-tools filter form, so it submits as filter[columns][];
        // fall back to the flat filter_columns key just in case.
        $input  = Factory::getApplication()->getInput();
        $filter = (array) $input->get('filter', [], 'array');

        if (array_key_exists('columns', $filter))
        {
            $requestColumns = (array) $filter['columns'];
        }
        else
        {
            $requestColumns = $input->get('filter_columns', null, 'array');
        }

        // The picker reports the form its checkboxes were rendered for (filter[columns_form]).
        // Only persist when that matches the current form. This rejects a stale submission
        // replayed by a browser refresh after switching forms — it still carries the previous
        // form's columns AND its marker, so it can't overwrite the new form's saved columns.
        $columnsForm      = $filter['columns_form'] ?? null;
        $columnsMatchForm = $columnsForm !== null && (string) $columnsForm === (string) $formID;

        if ($requestColumns !== null && $columnsMatchForm)
        {
            // Keep the chosen order, drop anything not in the form (XSS guard), persist per form.
            $userColumns = array_values(array_intersect((array) $requestColumns, $formColumns));
            $this->saveUserColumns($formID, $userColumns);
        }
        else
        {
            // Restore this form's remembered selection.
            $userColumns = array_values(array_intersect($this->getUserColumns($formID), $formColumns));
        }

        // Fall back to the first 8 columns when nothing is remembered for this form.
        if (empty($userColumns))
        {
            $userColumns = array_slice($formColumns, 0, 8);
        }

        $this->setState('filter.columns', $userColumns);
    }

    /**
     * Profile key under which the per-form column selections are stored in #__user_profiles.
     */
    private const COLUMNS_PROFILE_KEY = 'com_convertforms.columns';

    /**
     * Returns the current user's saved column map ({formID: [columns]}) from #__user_profiles.
     *
     * @return  array
     */
    private function getStoredColumnsMap(): array
    {
        $userId = (int) Factory::getUser()->id;

        if (!$userId)
        {
            return [];
        }

        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('profile_value'))
            ->from($db->quoteName('#__user_profiles'))
            ->where($db->quoteName('user_id') . ' = ' . $userId)
            ->where($db->quoteName('profile_key') . ' = ' . $db->quote(self::COLUMNS_PROFILE_KEY));

        $value = $db->setQuery($query)->loadResult();
        $map   = $value ? json_decode($value, true) : [];

        return is_array($map) ? $map : [];
    }

    /**
     * Returns the saved columns for a single form.
     *
     * @param   mixed  $formID
     *
     * @return  array
     */
    private function getUserColumns($formID): array
    {
        $map = $this->getStoredColumnsMap();
        $key = (string) $formID;

        return isset($map[$key]) && is_array($map[$key]) ? $map[$key] : [];
    }

    /**
     * Persists the columns chosen for a single form into the current user's profile map.
     *
     * @param   mixed  $formID
     * @param   array  $columns
     *
     * @return  void
     */
    private function saveUserColumns($formID, array $columns): void
    {
        $userId = (int) Factory::getUser()->id;

        if (!$userId || !$formID)
        {
            return;
        }

        // Merge this form's columns into the user's existing map.
        $map                   = $this->getStoredColumnsMap();
        $map[(string) $formID] = array_values($columns);

        $db  = $this->getDbo();
        $row = (object) [
            'user_id'       => $userId,
            'profile_key'   => self::COLUMNS_PROFILE_KEY,
            'profile_value' => json_encode($map),
            'ordering'      => 0,
        ];

        $exists = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__user_profiles'))
                ->where($db->quoteName('user_id') . ' = ' . $userId)
                ->where($db->quoteName('profile_key') . ' = ' . $db->quote(self::COLUMNS_PROFILE_KEY))
        )->loadResult();

        try
        {
            if ($exists)
            {
                $db->updateObject('#__user_profiles', $row, ['user_id', 'profile_key']);
            }
            else
            {
                $db->insertObject('#__user_profiles', $row);
            }
        }
        catch (\Exception $e)
        {
            // Remembering the layout is best-effort; never break the list on a write failure.
        }
    }

    /**
	 * Allows preprocessing of the JForm object.
	 *
	 * @param   JForm   $form   The form object
	 * @param   array   $data   The data to be merged into the form object
	 * @param   string  $group  The plugin group to be executed
	 *
	 * @return  void
	 *
	 * @since    3.6.1
	 */
	protected function preprocessForm(Joomla\CMS\Form\Form $form, $data, $group = 'content')
	{
        if (!isset($data->filter))
        {
            $data->filter = [];
        } else 
        {
            if (is_object($data->filter))
            {
                $data->filter = (array) $data->filter;
            }
        }

        $data->filter['form_id'] = $this->getState('filter.form_id');

        $columns = $this->getState('filter.columns');

        $data->filter['columns'] = $columns;

		parent::preprocessForm($form, $data, $group);
	}

    /**
     * Method to build an SQL query to load the list data.
     *
     * @return      string  An SQL query
     */
    public function getListQuery()
    {
        // Create a new query object.
        $db = Factory::getDBO();
        $query = $db->getQuery(true);

        // Select some fields from the item table
        $query
            ->select('a.*')
            ->from('#__convertforms_conversions a');
        
        // Filter State
        $filter = $this->getState('filter.state');

        if ($filter !== 'skip')
        {
            if (is_numeric($filter))
            {
                $query->where($db->quoteName('a.state') . ' = ' . (int) $filter);
            }

            if (is_null($filter) || $filter == '')
            {
                $filter = [0, 1];
            }

            if (is_array($filter))
            {
                $query->whereIn($db->quoteName('a.state'), $filter);
            }
        }

        // Join Forms Table
        if ($this->getState('filter.join_forms') != 'skip')
        {
            $query->select("c.name as form_name");
            $query->join('LEFT', $db->quoteName('#__convertforms', 'c') . ' ON 
                (' . $db->quoteName('a.form_id') . ' = ' . $db->quoteName('c.id') . ')');
        }

        // Filter the list over the search string if set.
        $search = $this->getState('filter.search');
        if (!empty($search))
        {
            if (stripos($search, 'id:') === 0)
            {
                $ids = str_replace('id:', '', $search);
                $ids = array_map('trim', explode(',', $ids));

                $query->whereIn($db->quoteName('a.id'), $ids);
            } else 
            {
                $supportsJSON = ConvertForms\Helper::supportsJsonSearch();

                $likeQ = $db->q('%' . $search . '%');

                if ($supportsJSON)
                {
                    $jsonSearchExpr = 'JSON_SEARCH(' . $db->quoteName('a.params') . ", 'one', " . $likeQ . ') IS NOT NULL';
                } else 
                {
                    $jsonSearchExpr = $db->quoteName('a.params') . ' LIKE ' . $likeQ;
                }
                
                $query->where($jsonSearchExpr);
            }
        }

        // Filter by ID
        if ($id = $this->getState('filter.id'))
        {
            $query->whereIn($db->quoteName('a.id'), $id);
        }

        // Filter by Campaign ID
        if ($campaign_id = $this->getState('filter.campaign_id'))
        {
            $query->whereIn($db->quoteName('a.campaign_id'), (array) $campaign_id);
        }

        // Filter Form
        if ($form_id = $this->getState('filter.form_id'))
        {
            $query->where('a.form_id = ' . (int) $form_id);
        }

        // Period
        if ($period = $this->getState('filter.period'))
        {
            switch ($period)
            {
                case 'today':
                    $date_from = 'now';
                    $date_to = 'now';
                    break;
                    
                case 'yesterday':
                    $date_from = '-1 day';
                    $date_to = '-1 day';
                    break;

                case 'this_week':
                    $date_from = 'monday this week';
                    $date_to = 'sunday this week';
                    break;

                case 'this_month':
                    $date_from = 'first day of this month';
                    $date_to = 'last day of this month';
                    break;

                case 'this_year':
                    $date_from = 'first day of January';
                    $date_to = 'last day of December';
                    break;

                case 'last_week':
                    $date_from = 'monday previous week';
                    $date_to = 'sunday previous week';
                    break;

                case 'last_month':
                    $date_from = 'first day of previous month';
                    $date_to = 'last day of previous month';
                    break;

                case 'last_year':
                    $date_from = 'first day of January ' . (date('Y') - 1);
                    $date_to = 'last day of December ' . (date('Y') - 1);
                    break;

                default:
                    $date_from = $this->getState('filter.created_from');
                    $date_to = $this->getState('filter.created_to');
                    break;
            }

            // Set the start and end dates with time to 00:00:00 and 23:59:59 respectively, formatting them as Y-m-d H:i:s.
            $date_from_ = HTMLHelper::date($date_from, 'Y-m-d 00:00:00');
            $date_to_   = HTMLHelper::date($date_to, 'Y-m-d 23:59:59');

            // Retrieve the time zone setting from the Joomla configuration.
            $tz = $this->getState('filter.timezone') ? $this->getState('filter.timezone') : Factory::getConfig()->get('offset');

            // Check if the time zone is not already UTC (i.e., if a conversion is needed).
            if (!empty($tz) && strtolower($tz) !== 'utc')
            {
                $utc = new \DateTimeZone('UTC');

                // Convert both $date_from_ and $date_to_ from the current time zone ($tz) to UTC.
                // For instance, a +03:00 date time 2024-10-22 00:00:00 becomes 2024-10-21 21:00:00.
                $date_from_ = Factory::getDate($date_from_, $tz)->setTimezone($utc);
                $date_to_   = Factory::getDate($date_to_, $tz)->setTimezone($utc);
            }

            // MySQL optimizer won't use an index once a column in the WHERE clause is wrapped with a function.
            // So we should never never never use MONTH(), YEAR(), DATE() methods again if we do care about performance.
            $query->where($db->quoteName('a.created') . ' >= ' . $db->q($date_from_));
            $query->where($db->quoteName('a.created') . ' <= ' . $db->q($date_to_));
        }

        // Filter User
        $filter_user = $this->getState('filter.user_id');

        if ($filter_user != '')
        {
            if (is_numeric($filter_user))
            {
                $query->where($db->quoteName('a.user_id') . ' = ' . $filter_user);
            } else 
            {
                $query->where('(' . $db->quoteName('a.state') . ' IN (' . $filter_user . '))');
            }
        }

        // Filter by country code
        if ($country_code = trim((string) $this->getState('filter.country_code')))
        {
            $query->where($db->quoteName('a.country_code') . ' = ' . $db->q(strtoupper($country_code)));
        }

        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'desc');

        $query->order($db->escape($orderCol . ' ' . $orderDirn));

        return $query;
    }
   
    /**
     *  [getItems description]
     *
     *  @return  object
     */
    public function getItems($prepareDates = true)
    {
        if (!$items = parent::getItems())
        {
            return [];
        };

        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_convertforms/models', 'ConvertFormsModel');
        $submission_model = BaseDatabaseModel::getInstance('Conversion', 'ConvertFormsModel', ['ignore_request' => true]);

        foreach ($items as $key => $item)
        {
            $items[$key]->params = json_decode($item->params);

            if ($prepareDates)
            {
                $item->created = Helper::formatDate($item->created);
                $item->modified = Helper::formatDate($item->modified);
            }

            $submission_model->prepare($item);
        }


        return $items;
    }
    
    private function getLastFormID()
    {
        $model = BaseDatabaseModel::getInstance('Forms', 'ConvertFormsModel', ['ignore_request' => true]);
        $model->setState('list.limit', 1);
        $model->setState('list.direction', 'asc');

        $forms = $model->getItems();

        return empty($forms) ? null : $forms[0]->id;
    }
}