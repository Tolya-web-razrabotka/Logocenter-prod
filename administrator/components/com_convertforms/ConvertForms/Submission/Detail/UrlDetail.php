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
 * Shared base for plain URL columns (source_url, referrer_url).
 * getValueHTML() wraps the URL in a safe external <a> tag.
 *
 * Abstract: it is never a column of its own, so column discovery skips it.
 */
abstract class UrlDetail extends Base
{
	/**
	 * Group (see Base::$group). URL columns belong to the Page & Source group.
	 *
	 * @var  string
	 */
	protected static string $group = 'source';

	/**
	 * Icon (see Base::$icon). Shared by every URL column (source_url, referrer_url).
	 *
	 * @var  string
	 */
	protected static string $icon = 'icon-link';

	// Pro-only: visitor tracking is a Pro feature (see Base::$proOnly).
	protected static bool $proOnly = true;

	public function getValueHTML(): string
	{
		$url = $this->getValueRaw();

		if (empty($url))
		{
			return '';
		}

		$escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

		return '<a href="' . $escaped . '" target="_blank" rel="noopener noreferrer">' . $escaped . '</a>';
	}
}