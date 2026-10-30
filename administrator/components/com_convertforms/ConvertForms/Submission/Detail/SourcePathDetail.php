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
 * Detail class for the source path — the path parsed out of the submission's
 * source_url at read time, without the domain or query string
 * (e.g. https://www.tassos.gr/landing/page?x=1 → /landing/page). Not stored
 * as its own column.
 */
class SourcePathDetail extends Base
{
	protected static int $listOrder = 152;

	protected static string $group = 'source';

	protected static string $icon = 'icon-link';

	// Pro-only: visitor tracking is a Pro feature (see Base::$proOnly).
	protected static bool $proOnly = true;

	public function getValueRaw(): string
	{
		$url = (string) ($this->item->source_url ?? '');

		if ($url === '')
		{
			return '';
		}

		$path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

		// Drop Joomla's front controller from SEF-off URLs: /index.php/foo → /foo.
		$path = preg_replace('#^/index\.php(?=/|$)#i', '', $path);

		// Homepage normalises to "/" whether SEF is on (/) or off (/index.php).
		return $path === '' ? '/' : $path;
	}
}
