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
 * Shared base for domain columns (source_domain, referrer_domain). The host is
 * parsed out of the matching *_url column at read time, with the leading "www."
 * stripped (e.g. https://www.tassos.gr/page → tassos.gr). Domains are stored as
 * their own columns nowhere — this derives them.
 *
 * Abstract: never a column of its own, so column discovery skips it.
 */
abstract class DomainDetail extends Base
{
	/**
	 * Group (see Base::$group). Domain columns belong to the Page & Source group.
	 *
	 * @var  string
	 */
	protected static string $group = 'source';

	protected static string $icon = 'icon-globe';

	// Pro-only: visitor tracking is a Pro feature (see Base::$proOnly).
	protected static bool $proOnly = true;

	public function getValueRaw(): string
	{
		// source_domain → source_url, referrer_domain → referrer_url.
		$urlColumn = preg_replace('/_domain$/', '_url', $this->column);
		$url       = (string) ($this->item->{$urlColumn} ?? '');

		if ($url === '')
		{
			return '';
		}

		$host = parse_url($url, PHP_URL_HOST);

		if (empty($host))
		{
			return '';
		}

		return preg_replace('/^www\./i', '', $host);
	}
}
