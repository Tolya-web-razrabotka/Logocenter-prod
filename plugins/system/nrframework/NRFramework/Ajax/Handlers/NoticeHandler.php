<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Ajax\Handlers;

use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

/**
 * Handler for backend notices and download key management.
 * 
 * URL: ?option=com_ajax&format=raw&plugin=nrframework&handler=notice&task={task}
 * 
 * Available tasks:
 * - downloadkey: Update the framework download key
 * - ajaxnotices: Retrieve extension notices
 */
class NoticeHandler extends BaseHandler
{
    public function init()
    {
        $this->requireAdmin();

        $task = $this->input->getCmd('action', '');

        $allowed_actions = [
            'downloadkey',
            'ajaxnotices'
        ];

        if (!in_array($task, $allowed_actions))
        {
            $this->jsonError('Invalid action.');
        }

        switch ($task)
        {
            case 'downloadkey':
                $this->handleDownloadKey();
                break;

            case 'ajaxnotices':
                $this->handleAjaxNotices();
        }
    }

    private function handleDownloadKey()
    {
        // Get Download Key
        if (!$download_key = $this->input->get('download_key', null, 'string'))
        {
            $this->jsonError('Missing download key.');
        }
        
        // Try and update the Download Key
        if (!\Tassos\Framework\Functions::updateDownloadKey($download_key))
        {
            $this->jsonError('Cannot update download key.');
        }

        $this->jsonResponse([
            'error' => false,
            'response' => Text::_('NR_DOWNLOAD_KEY_UPDATED')
        ]);
    }

    private function handleAjaxNotices()
    {
        // Get element
        if (!$ext_element = $this->input->get('ext_element', null, 'string'))
        {
            $this->jsonError('Missing extension element.');
        }

        // Get xml
        if (!$ext_xml = $this->input->get('ext_xml', null, 'string'))
        {
            $this->jsonError('Missing extension xml.');
        }

        // Get type
        if (!$ext_type = $this->input->get('ext_type', null, 'string'))
        {
            $this->jsonError('Missing extension type.');
        }

        // Current URL
        if (!$current_url = $this->input->get('current_url', null, 'string'))
        {
            $this->jsonError('Missing current URL.');
        }
        
        // Get excluded notices
        $exclude = $this->input->get('exclude', null, 'string');
        $exclude = array_filter(explode(',', $exclude));

        $notices = \Tassos\Framework\Notices\Notices::getInstance([
            'ext_element' => $ext_element,
            'ext_xml' => $ext_xml,
            'ext_type' => $ext_type,
            'exclude' => $exclude,
            'current_url' => $current_url
        ])->getNotices();

        $this->jsonResponse([
            'error' => false,
            'notices' => $notices
        ]);
    }
}