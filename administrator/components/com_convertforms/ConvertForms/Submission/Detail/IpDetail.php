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

/**
 * Detail class for the IP Address column.
 */
class IpDetail extends Base
{
	protected static int $listOrder = 70;

	protected static string $group = 'location';

	protected static string $icon = 'icon-location';

	// Pro-only: visitor tracking is a Pro feature (see Base::$proOnly).
	protected static bool $proOnly = true;

	public function getLangKey(): string
	{
		return 'NR_IPADDRESS';
	}

	public function getValueHTML(): string
	{
		$ip = parent::getValueHTML();

		if (empty($ip))
		{
			return '';
		}

		return '<code>' . htmlspecialchars($ip) . '</code>';
	}
}
