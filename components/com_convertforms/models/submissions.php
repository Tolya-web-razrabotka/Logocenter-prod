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

use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

require_once JPATH_ADMINISTRATOR . '/components/com_convertforms/models/conversions.php';

class ConvertFormsModelSubmissions extends ConvertFormsModelConversions
{
	/**
	 * Application Object
	 *
	 * @var object
	 */
	private $app;

	/**
	 * Submissions filter options and params;
	 *
	 * @var object
	 */
	public $options;

	/**
	 * Class constructor
	 */
	public function __construct($config = array())
	{
		$this->app  = Factory::getApplication();
		$this->options = isset($config['options']) ? new Registry($config['options']) : $this->app->getMenu()->getActive()->getParams();

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * This method should only be called once per instantiation and is designed
	 * to be called on the first call to the getState() method unless the model
	 * configuration flag to ignore the request is set.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   3.0.1
	 */
	protected function populateState($ordering = 'ordering', $direction = 'ASC')
	{
		$options = $this->options;

        $this->setState('filter.form_id', $options->get('form_id', 0));

        // Set page limit / limit start
        $this->setState('list.limit', $options->get('list_limit', 20));

		$limitstart = $this->app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);

		// Filter State
		$filter_confirmed_only = $options->get('confirmed_only', false);
		$this->setState('filter.state', $filter_confirmed_only ? '1' : '0,1');

		// Set search query
		$query = Factory::getApplication()->input->get('q', '', 'string');
		if ($query)
		{
			$this->setState('filter.search', $query);
		}

        // Set ordering
        $ordering = $options->get('ordering', 'recent');

		switch ($ordering)
		{
			case 'oldest':
				$this->setState('list.ordering', 'created');
				$this->setState('list.direction', 'asc');
				break;		

			case 'random':
				$this->setState('list.ordering', 'rand()');
				break;

			default: // recent
				$this->setState('list.ordering', 'created');
				$this->setState('list.direction', 'desc');
				break;
        }
                
        // Default 'current' so an absent param scopes to the viewer, not everyone.
        $filter_user   = $options->get('filter_user', 'current');
        $view_own_only = $filter_user === 'current' ? true : (bool) $options->get('view_own_only', true);
        $current_user  = (int) Factory::getApplication()->getIdentity()->id;

        if ($view_own_only)
        {
            // Scope to the current user; -1 yields an empty set for guests.
            $this->setState('filter.user_id', $current_user ?: -1);
        }
        elseif ($filter_user === 'specific')
        {
            $this->setState('filter.user_id', $options->get('user_ids', -1));
        }
        // 'all' + view_own_only off: public list, no user scoping.
	}
	
    /**
     *  [getItems description]
     *
     *  @return  object
     */
    public function getItems($prepareDates = true)
    {
		if (!$items = parent::getItems($prepareDates))
		{
			return $items;
		}
		
        foreach ($items as &$item)
        {
			// View submission link
			$item->link = Route::link('site', 'index.php?option=com_convertforms&view=submission&id=' . $item->id . '&Itemid=' . Factory::getApplication()->input->get('Itemid'));
        }

        return $items;
	}

	public function authorize()
	{
		$filter_user   = $this->options->get('filter_user', 'current');
		$view_own_only = $filter_user === 'current' ? true : (bool) $this->options->get('view_own_only', true);

		// Public list only when view_own_only is off; otherwise require a logged-in user.
		if (!$view_own_only)
		{
			return true;
		}

		return (bool) Factory::getUser()->id;
	}
}