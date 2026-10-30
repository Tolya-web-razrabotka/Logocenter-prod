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

/**
 * Detail class for the submitter's Joomla account email address.
 * Derived from user_id (no DB column), so it is not sortable in the list.
 */
class UserEmailDetail extends Base
{
	protected static int $listOrder = 60;

	protected static string $group = 'account';

	protected static string $icon = 'icon-mail';

	/**
	 * Resolves the email address from the submission's user_id.
	 * Empty for guest submissions.
	 *
	 * @return  string
	 */
	public function getValueRaw(): string
	{
		$userId = (int) ($this->item->user_id ?? 0);

		return $userId > 0 ? (string) Factory::getUser($userId)->email : '';
	}

	/**
	 * Renders the email address as a mailto link.
	 *
	 * @return  string
	 */
	public function getValueHTML(): string
	{
		$email = $this->getValueRaw();

		if (empty($email))
		{
			return '';
		}

		$escaped = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

		return '<a href="mailto:' . $escaped . '">' . $escaped . '</a>';
	}
}
