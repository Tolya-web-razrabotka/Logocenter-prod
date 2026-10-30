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

use Joomla\CMS\Language\Text;

/**
 * Detail class for the Operating System column.
 * Translates the raw OS slug (e.g. "macos") via the NR_ lang key (e.g. NR_MACOS -> "macOS").
 */
class OsDetail extends Base
{
	protected static int $listOrder = 110;

	protected static string $group = 'device';

	protected static string $icon = 'icon-cog';

	// Pro-only: visitor tracking is a Pro feature (see Base::$proOnly).
	protected static bool $proOnly = true;

	public function getLangKey(): string
	{
		return 'NR_OPERATING_SYSTEM';
	}

	public function getValueHTML(): string
	{
		$raw = $this->getValueRaw();

		if (empty($raw))
		{
			return '';
		}

		return Text::_('NR_' . strtoupper($raw));
	}
}
