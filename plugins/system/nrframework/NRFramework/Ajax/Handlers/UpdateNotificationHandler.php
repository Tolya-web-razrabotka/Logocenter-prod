<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Ajax\Handlers;

use Tassos\Framework\HTML;

defined('_JEXEC') or die;

/**
 * Handler for update notification display (deprecated).
 * 
 * URL: ?option=com_ajax&format=raw&plugin=nrframework&handler=updatenotification
 * 
 * @deprecated 4.9.50 This handler remains for backwards compatibility only.
 */
class UpdateNotificationHandler extends BaseHandler
{
    public function init()
    {
        $this->requireAdmin();

        echo HTML::updateNotification($this->input->get('element'));
    }
}