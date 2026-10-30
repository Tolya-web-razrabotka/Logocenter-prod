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

namespace ConvertForms\Helpers;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class AcyMailing
{
    /**
	 * Subscribe method for AcyMailing v6
	 *
	 * @param   string  $email
	 * @param   array   $params
	 * @param   array   $lists
	 * @param   bool 	$doubleOptin
	 * @param   bool 	$triggerAcymNotifications   This triggers the Acym Configuration > Subscription > Advanced Configuration notifications
	 *
	 * @return  void
	 */
	public static function subscribe($email, $params, $lists, $doubleOptin = true, $triggerAcymNotifications = false)
	{
		return \NRFramework\Helpers\AcyMailingHelper::subscribe($email, $params, $lists, $doubleOptin, $triggerAcymNotifications);
    }
    
    /**
	 * Subscribe method for AcyMailing v5
	 *
	 * @param  array $lists
	 *
	 * @return void
	 */
	public static function subscribe_v5($email, $params, $lists, $doubleOptin = true)
	{
        return \NRFramework\Helpers\AcyMailingHelper::subscribe_v5($email, $params, $lists, $doubleOptin);
	}
}