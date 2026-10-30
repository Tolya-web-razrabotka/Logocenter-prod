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
 * Detail class for the submission form name column.
 * Populated as a query alias (c.name as form_name), so it is not sortable in the list.
 */
class FormNameDetail extends Base
{
	protected static int $listOrder = 60;

	protected static string $group = 'identity';

	protected static string $icon = 'icon-list-2';

	/**
	 * The form name comes from the `c.name as form_name` alias in the submissions-list
	 * query. On a single-item load (the edit screen) that alias is absent, but the full
	 * form object is available as $item->form — fall back to its name so the detail
	 * renders everywhere without the model having to set a scalar form_name.
	 *
	 * @return  string
	 */
	public function getValueRaw(): string
	{
		if (isset($this->item->form_name) && $this->item->form_name !== '')
		{
			return (string) $this->item->form_name;
		}

		return isset($this->item->form->name) ? (string) $this->item->form->name : '';
	}

	/**
	 * Renders the form name as a link to the form builder when the current user may
	 * edit that form; otherwise the plain name.
	 *
	 * @return  string
	 */
	public function getValueHTML(): string
	{
		$formId = (int) ($this->item->form_id ?? $this->item->form->id ?? 0);

		if ($formId <= 0 || !Factory::getUser()->authorise('core.edit', 'com_convertforms.form.' . $formId))
		{
			return $this->getValue();
		}

		$url = Route::_('index.php?option=com_convertforms&task=form.edit&id=' . $formId);

		return '<a href="' . $url . '">' . $this->getValue() . '</a>';
	}
}
 