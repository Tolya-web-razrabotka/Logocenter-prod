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

defined('_JEXEC') or die('Restricted access');

// Initialize Convert Forms Library
if (!@include_once(JPATH_ADMINISTRATOR . '/components/com_convertforms/autoload.php'))
{
    return;
}

use Joomla\CMS\Helper\ModuleHelper;
use ConvertForms\Helper;

$form = Helper::renderFormById($params->get('form'));

require ModuleHelper::getLayoutPath('mod_convertforms', $params->get('layout', 'default'));