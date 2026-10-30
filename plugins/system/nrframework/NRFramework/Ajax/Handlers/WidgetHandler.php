<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Ajax\Handlers;

defined('_JEXEC') or die;

/**
 * Handler for widget AJAX requests delegation.
 * 
 * URL: ?option=com_ajax&format=raw&plugin=nrframework&handler=widget&widget={widget_name}&task={task}
 * 
 * This handler delegates AJAX calls to specific widget classes in the Widgets namespace.
 * The widget parameter specifies which widget class to instantiate and call.
 */
class WidgetHandler extends BaseHandler
{
    public function init()
    {
        $task = $this->input->getCmd('task', '');
        $widget = $this->input->get('widget', null);

        if (empty($widget))
        {
            $this->httpError(400, 'MISSING_WIDGET');
        }

        $class = '\Tassos\Framework\Widgets\\' . $widget;

        if (!class_exists($class))
        {
            $this->httpError(404, 'WIDGET_CLASS_NOT_FOUND');
        }

        if (!method_exists($class, 'onAjax'))
        {
            $this->httpError(500, 'WIDGET_METHOD_NOT_FOUND');
        }

        (new $class)->onAjax($task);
    }
}