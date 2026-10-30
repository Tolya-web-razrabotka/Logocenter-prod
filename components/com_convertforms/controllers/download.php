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

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * The original download endpoint, kept so links minted before the view existed keep
 * working. New links route through view=download instead, which can carry a menu item.
 */
class ConvertFormsControllerDownload extends BaseController
{
	/**
	 * @return  void
	 */
	public function download()
	{
		\ConvertForms\Download::serve();
	}
}
