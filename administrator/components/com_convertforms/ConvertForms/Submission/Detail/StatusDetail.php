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
 * Detail class for the submission status, rendered as the raw integer state value.
 * See StatusLabelDetail for the human-friendly "Confirmed/Unconfirmed" label.
 */
class StatusDetail extends Base
{
	protected static int $listOrder = 26;

	protected static string $group = 'identity';

	protected static string $icon = 'icon-check';

	/**
	 * @inheritDoc
	 */
	public function getLangKey(): string
	{
		return 'JSTATUS';
	}

	/**
	 * @inheritDoc
	 */
	public function getValueRaw(): string
	{
		return (string) (isset($this->item->state) ? (int) $this->item->state : 0);
	}
}
