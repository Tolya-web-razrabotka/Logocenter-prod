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
 * Detail class for the Country Code column.
 * getValue() returns the raw ISO code; getValueHTML() renders a flag image and full country name.
 */
class CountryCodeDetail extends Base
{
	protected static int $listOrder = 80;

	protected static string $group = 'location';

	protected static string $icon = 'icon-flag';

	// Pro-only: visitor tracking is a Pro feature (see Base::$proOnly).
	protected static bool $proOnly = true;

	public function getLangKey(): string
	{
		return 'COM_CONVERTFORMS_COUNTRY_CODE';
	}

	public function getValue(): string
	{
		return htmlspecialchars((string) ($this->item->country_code ?? ''), ENT_QUOTES, 'UTF-8');
	}


}
