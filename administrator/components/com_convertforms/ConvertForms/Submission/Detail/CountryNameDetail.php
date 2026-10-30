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
use Joomla\CMS\Uri\Uri;

/**
 * Derived detail for the country NAME.
 *
 * There is no `country_name` DB column; the value is resolved from the stored
 * `country_code` via the NR_COUNTRY_<CODE> language strings. Unlike CountryCodeDetail,
 * no flag image is rendered — this is the plain country name ("Greece").
 *
 * getValueRaw() resolves the name from country_code, so all three value tiers
 * (raw, plain, HTML) yield the country name. Being a derived column, it is not
 * sortable in the submissions list.
 */
class CountryNameDetail extends Base
{
	protected static int $listOrder = 90;

	protected static string $group = 'location';

	protected static string $icon = 'icon-flag';

	// Pro-only: visitor tracking is a Pro feature (see Base::$proOnly).
	protected static bool $proOnly = true;

	public function getLangKey(): string
	{
		return 'COM_CONVERTFORMS_COUNTRY_NAME';
	}

	public function getValueRaw(): string
	{
		$code = $this->item->country_code ?? null;

		return empty($code) ? '' : Text::_('NR_COUNTRY_' . strtoupper($code));
	}

	public function getValueHTML($displayFlag = false): string
	{
		$countryName = $this->getValueRaw();

		if (empty($countryName))
		{
			return '';
		}

		if (!$displayFlag)
		{
			return htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8');
		}

		$flagSrc = Uri::root(true) . '/media/plg_system_nrframework/img/flags/' . strtolower($this->item->country_code) . '.png';

		return '<div class="flex align-items-center gap-1">'
			. '<img src="' . htmlspecialchars($flagSrc, ENT_QUOTES, 'UTF-8') . '" width="20" height="auto" loading="lazy"> '
			. htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8')
			. '</div>';
	}
}
