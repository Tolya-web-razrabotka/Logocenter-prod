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

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;

/**
 * Serves a file stored outside the site.
 *
 * A view rather than a task so the component router can name it as a route segment,
 * which is what lets a download link sit under the menu item that publishes its form.
 * It streams and exits, so the layout under tmpl/ is only a safety net.
 */
class ConvertFormsViewDownload extends HtmlView
{
	/**
	 * @param   string  $tpl  Unused: nothing is rendered.
	 *
	 * @return  void
	 */
	public function display($tpl = null)
	{
		\ConvertForms\Download::serve();
	}
}
