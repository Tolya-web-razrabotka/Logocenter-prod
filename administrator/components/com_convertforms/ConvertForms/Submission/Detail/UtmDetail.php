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

/**
 * Shared base for UTM columns (utm_source, utm_medium, utm_campaign, ...).
 *
 * UTM parameters are not stored as their own columns; the value is parsed out of
 * the submission's source_url query string at read time. The column key (e.g.
 * 'utm_source') is exactly the query-param name, so this one method serves every
 * concrete subclass.
 *
 * Abstract: it is never a column of its own, so column discovery skips it.
 */
abstract class UtmDetail extends Base
{
	/**
	 * Group (see Base::$group). All UTM columns belong to the Source & Campaign group.
	 *
	 * @var  string
	 */
	protected static string $group = 'source';

	protected static string $icon = 'icon-link';

	// Pro-only: visitor tracking is a Pro feature (see Base::$proOnly).
	protected static bool $proOnly = true;

	/**
	 * @inheritDoc
	 */
	public function getValueRaw(): string
	{
		$url = (string) ($this->item->source_url ?? '');

		if ($url === '')
		{
			return '';
		}

		$query = parse_url($url, PHP_URL_QUERY);

		if (empty($query))
		{
			return '';
		}

		parse_str($query, $params);

		$value = $params[$this->column] ?? '';

		// utm_x=a&utm_x=b or utm_x[]=a yields an array; ignore that edge case.
		return is_array($value) ? '' : (string) $value;
	}
}
