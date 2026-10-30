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

use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\Utilities\IpHelper;
use Tassos\Framework\Helpers\Geo;
use Tassos\Framework\WebClient;

class ConvertFormsTableConversion extends Table
{
    private $app;

    /**
     *  Constructor
     *
     *  @param object Database connector object
     */
    function __construct(&$db) 
    {
    	$this->setColumnAlias('published', 'state');

        $this->app = Factory::getApplication();

        parent::__construct('#__convertforms_conversions', 'id', $db);
    }

    /**
     *  Method to perform sanity checks on the Table instance properties to ensure
     *  they are safe to store in the database.  Child classes should override this
     *  method to make sure the data they are storing in the database is safe and
     *  as expected before storage.
     * 
     *  @return  boolean  True if the instance is sane and able to be stored in the database.
     */
    public function check()
    {
        $date = Factory::getDate();
        $user = Factory::getUser();

        if ($this->id)
        {
            if (is_array($this->params))
            {
                $this->params = json_encode($this->params);
            }

            $this->modified = $date->toSql();
        }
        else
        {
            $this->created = $date->toSql();
            $this->user_id = $user->id;
            $this->visitor_id = ConvertForms\Helper::getVisitorID();

            
        }

        return true;
    }

    
}