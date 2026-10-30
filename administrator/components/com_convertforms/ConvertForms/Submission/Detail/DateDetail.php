<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 *
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace ConvertForms\Submission\Detail;

defined('_JEXEC') or die('Restricted access');

use ConvertForms\Helper;
use ConvertForms\Submission\Submission;
use Tassos\Framework\Date;

/**
 * Shared base for submission datetime columns (created, modified).
 *
 * Operates generically on $this->column, so the concrete subclasses only set their
 * $listOrder. Abstract: it is never a column of its own, so column discovery skips it.
 */
abstract class DateDetail extends Base
{
	/**
	 * Group (see Base::$group). Date columns belong to the Identity group.
	 *
	 * @var  string
	 */
	protected static string $group = 'identity';

	/**
	 * Icon (see Base::$icon). Shared by every date column (created, modified).
	 *
	 * @var  string
	 */
	protected static string $icon = 'icon-calendar';

	/**
	 * Returns the raw datetime, converted to ISO8601 under the JSON API context.
	 *
	 * Every other context keeps the raw SQL datetime exactly as stored (Smart Tags,
	 * CSV/JSON export, etc.). Empty and zero dates are passed through untouched.
	 *
	 * @return  string
	 */
	public function getValueRaw(): string
	{
		$date = parent::getValueRaw();

		if ($this->consumer !== Submission::CONSUMER_API || $date === '' || $date === '0000-00-00 00:00:00')
		{
			return $date;
		}

		return Date::toISO8601($date);
	}

	/**
	 * Returns the date as a human-friendly, timezone-aware string.
	 *
	 * The edit view supplies raw SQL datetimes (date pickers need them raw), so
	 * those are formatted here through the app's shared formatter, matching the
	 * submissions list. Values already formatted upstream by the list / export
	 * model are passed through untouched to avoid a double timezone offset.
	 *
	 * @return  string
	 */
	public function getValueHTML(): string
	{
		$date = isset($this->item->{$this->column}) ? (string) $this->item->{$this->column} : '';

		if ($date === '' || $date === '0000-00-00 00:00:00')
		{
			return '';
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date))
		{
			return Helper::formatDate($date);
		}

		return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
	}
}
