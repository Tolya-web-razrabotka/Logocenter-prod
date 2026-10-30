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

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Detail class for the submitter's Joomla account login name (the username).
 * Derived from user_id (no DB column), so it is not sortable in the list.
 */
class UserLoginNameDetail extends Base
{
	protected static int $listOrder = 40;

	protected static string $group = 'account';

	protected static string $icon = 'icon-user';

	/**
	 * The column key derived from this class is `user_login_name`, but the label
	 * keeps the original `COM_CONVERTFORMS_USER_USERNAME` key so the existing
	 * translations across all language files continue to apply.
	 *
	 * @return  string
	 */
	public function getLangKey(): string
	{
		return 'COM_CONVERTFORMS_USER_USERNAME';
	}

	/**
	 * Resolves the login name from the submission's user_id.
	 * Empty for guest submissions.
	 *
	 * @return  string
	 */
	public function getValueRaw(): string
	{
		$userId = (int) ($this->item->user_id ?? 0);

		return $userId > 0 ? (string) Factory::getUser($userId)->username : '';
	}

	/**
	 * Renders the username as a link to the Joomla user edit screen.
	 *
	 * @return  string
	 */
	public function getValueHTML(): string
	{
		$userId = (int) ($this->item->user_id ?? 0);

		if ($userId <= 0)
		{
			return '';
		}

		$url = Uri::base() . '/index.php?option=com_users&task=user.edit&id=' . $userId;

		return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $this->getValue() . '</a>';
	}
}
