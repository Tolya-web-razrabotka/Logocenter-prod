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

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

/**
 * Detail class for the submission ID column.
 */
class IdDetail extends Base
{
	protected static int $listOrder = 10;

	protected static string $group = 'identity';

	protected static string $icon = 'icon-key';

	/**
	 * Renders the ID as a link to the submission edit screen when the current
	 * user may edit it; otherwise the plain ID.
	 *
	 * @return  string
	 */
	public function getValueHTML(): string
	{
		$id = (int) $this->getValueRaw();

		if (!Factory::getUser()->authorise('core.edit', 'com_convertforms.conversion.' . $id))
		{
			return (string) $id;
		}

		$url = Route::_('index.php?option=com_convertforms&task=conversion.edit&id=' . $id);

		return '<a href="' . $url . '">' . $id . '</a>';
	}
}
