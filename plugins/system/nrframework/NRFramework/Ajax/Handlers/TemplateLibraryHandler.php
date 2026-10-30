<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Ajax\Handlers;

use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * Handler for templates library operations.
 * 
 * URL: ?option=com_ajax&format=raw&plugin=nrframework&handler=templatelibrary&action={action}
 * 
 * Available actions:
 * - get_templates: Retrieve available templates
 * - refresh_templates: Refresh template cache
 * - insert_template: Insert a template
 * - favorites_toggle: Toggle template favorite status
 */
class TemplateLibraryHandler extends BaseHandler
{
    public function init()
    {
        $this->requireAdmin();

        $action = $this->input->get('action', null);

		$input_data = new Registry(json_decode(file_get_contents('php://input')));

        $template_id = $input_data->get('template_id', '');

        $allowed_actions = [
            'get_templates',
            'refresh_templates',
            'insert_template',
            'favorites_toggle'
        ];

        if (!in_array($action, $allowed_actions))
        {
            $this->jsonError('Cannot validate request.', 'message');
        }

        if (!$options = json_decode($input_data->get('options', []), true))
        {
            $this->jsonError('Cannot validate request.', 'message');
        }

        $class = '';
        $method = 'tf_library_ajax_' . $action;
        
        switch ($action) {
            case 'get_templates':
            case 'refresh_templates':
            case 'insert_template':
                $class = 'templates';
                
                if ($action === 'insert_template')
                {
                    // Ensure a template ID is given
                    if (empty($template_id))
                    {
                        $this->jsonError('Cannot process request.', 'message');
                    }
        
                    $options['template_id'] = $template_id;
                }
                    
                break;
            case 'favorites_toggle':
                $class = 'favorites';

                // Ensure a template ID is given
                if (empty($template_id))
                {
                    $this->jsonError('Cannot process request.', 'message');
                }

                $options['template_id'] = $template_id;
                
                break;
        }
        
        $library = new \Tassos\Framework\Library\Library($options);

        $this->jsonResponse($library->$class->$method());
    }
}