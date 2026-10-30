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
 * Handler for component items AJAX requests.
 * 
 * URL: ?option=com_ajax&format=raw&plugin=nrframework&handler=componentitems&task={task}
 * 
 * This handler wraps the legacy JFormFieldComponentItems form field class
 * to provide AJAX functionality through our new handler system.
 */
class ComponentItemsHandler extends BaseHandler
{
    public function init()
    {
        $this->requireAdmin();

        $safeInput = $this->gatherSafeInput();

        require_once JPATH_PLUGINS . '/system/nrframework/fields/componentitems.php';

        // Use factory to get appropriate field class based on preset
        $preset = $safeInput['preset'] ?? 'content';
        $field = \JFormFieldComponentItems::createInstance($preset);

        $field->onAjax($safeInput);
    }

    /**
     * Gathers safe input parameters from the request
     */
    protected function gatherSafeInput()
    {
        $inputData = $this->input->getArray();

        $safeInput = [];

        if (isset($inputData['preset']))
        {
            $safeInput['preset'] = $inputData['preset'];
        }

        if (isset($inputData['term']))
        {
            $safeInput['term'] = $inputData['term'];
        }

        if (isset($inputData['page']))
        {
            $safeInput['page'] = $inputData['page'];
        }

        if (isset($inputData['limit']))
        {
            $safeInput['limit'] = $inputData['limit'];
        }

        if (isset($inputData['value']))
        {
            $safeInput['value'] = $inputData['value'];
        }

        return $safeInput;
    }
}