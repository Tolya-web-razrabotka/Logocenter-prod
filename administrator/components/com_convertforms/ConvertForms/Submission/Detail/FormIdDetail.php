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
 * Detail class for the submission's form ID (a.form_id), a real submission column.
 * See FormNameDetail for the form's name (a query-aliased join column).
 */
class FormIdDetail extends Base
{
	protected static int $listOrder = 50;

	protected static string $group = 'identity';

	protected static string $icon = 'icon-list-2';
}
