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

use Joomla\CMS\Language\Text;

/**
 * Detail class for the human-friendly submission status label (Confirmed/Unconfirmed).
 * The raw integer lives in the `state` column; see StatusDetail for the numeric value.
 */
class StatusLabelDetail extends Base
{
	protected static int $listOrder = 27;

	protected static string $group = 'identity';

	protected static string $icon = 'icon-info';

	/**
	 * @inheritDoc
	 */
	public function getValueRaw(): string
	{
		$state = isset($this->item->state) ? (int) $this->item->state : 0;

		return $state === 1
			? Text::_('COM_CONVERTFORMS_SUBMISSION_CONFIRMED')
			: Text::_('COM_CONVERTFORMS_SUBMISSION_UNCONFIRMED');
	}
}
