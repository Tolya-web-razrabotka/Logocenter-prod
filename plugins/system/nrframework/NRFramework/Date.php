<?php
/**
 *  @author          Tassos.gr <info@tassos.gr>
 *  @link            https://www.tassos.gr
 *  @copyright       Copyright © 2026 Tassos All Rights Reserved
 *  @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework;

use Joomla\CMS\Factory;
use Joomla\CMS\Date\Date as JoomlaDate;

defined('_JEXEC') or die('Restricted access');

/**
 * Helper class for date manipulation and formatting.
 */
class Date
{
    /**
     * Convert a UTC date string to ISO 8601 format, expressed in the site's local timezone.
     *
     * Dates stored in the database are always in UTC. This method shifts the date
     * to the site's configured timezone and formats it as ISO 8601 with the offset
     * included, so the output correctly represents the local moment in time.
     *
     * Example: DB value "2026-03-17 07:31:00" on a UTC+2 site → "2026-03-17T09:31:00+02:00"
     *
     * @param   string  $date  UTC date string (e.g. "2026-03-17 07:31:00")
     *
     * @return  string|null  ISO 8601 date string, or null if the input is empty or unparseable.
     */
	public static function toISO8601($date)
	{
		$date = is_string($date) ? trim($date) : $date;

		if (empty($date) || is_null($date) || $date == '0000-00-00 00:00:00')
		{
			return null;
		}

		// Already in ISO 8601 format — nothing to do.
		if (strpos($date, 'T') !== false)
		{
			return $date;
		}

        $tz = new \DateTimeZone(Factory::getApplication()->getCfg('offset', 'UTC'));

		try
        {
            // Construct as UTC (the DB default), then shift to the site timezone for output.
            // Passing $tz to the constructor would wrongly re-interpret the UTC input as local time.
            $date = new JoomlaDate($date);
            $date->setTimezone($tz);

			return $date->toISO8601(true);

		} catch (\Exception $e)
        {
		}
	}
}