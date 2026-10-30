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

namespace ConvertForms\Submission\Detail;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Uri\Uri;

/**
 * Detail class for the submission User ID column.
 */
class UserIdDetail extends Base
{
	protected static int $listOrder = 30;

	protected static string $group = 'account';

	protected static string $icon = 'icon-key';

	/**
	 * Renders the user ID as a link to the Joomla user edit screen.
	 * Empty for guest submissions (no user_id).
	 *
	 * @return  string
	 */
	public function getValueHTML(): string
	{
		$userId = (int) $this->getValueRaw();

		if ($userId <= 0)
		{
			return '';
		}

		$url = Uri::base() . '/index.php?option=com_users&task=user.edit&id=' . $userId;

		return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $userId . '</a>';
	}
}
