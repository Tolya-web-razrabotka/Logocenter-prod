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
 * Detail class for the submission User Agent column.
 */
class UserAgentDetail extends Base
{
	protected static int $listOrder = 130;

	protected static string $group = 'device';

	protected static string $icon = 'icon-info-circle';

	// Pro-only: visitor tracking is a Pro feature (see Base::$proOnly).
	protected static bool $proOnly = true;
}
