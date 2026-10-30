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
 * Detail class for the submission notes, stored under the params JSON as `leadnotes`.
 *
 * Surfacing notes as a Detail makes it a selectable column in the submissions list,
 * the export picker's Submission Details group, and a {submission.notes} Smart Tag,
 * all from one place. The high $listOrder keeps notes rendering last in the list.
 */
class NotesDetail extends Base
{
	protected static int $listOrder = 9999;

	protected static string $group = 'identity';

	protected static string $icon = 'icon-pencil-2';

	/**
	 * @inheritDoc
	 */
	public function getLangKey(): string
	{
		return 'COM_CONVERTFORMS_NOTES';
	}

	/**
	 * Notes live in the params JSON (params->leadnotes), not a top-level column.
	 *
	 * @return  string
	 */
	public function getValueRaw(): string
	{
		return (string) ($this->item->params->leadnotes ?? '');
	}
}