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
 
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
 
class ConvertFormsViewConvertForms extends HtmlView
{
    /**
     * Items view display method
     * 
     * @return void
     */
    function display($tpl = null) 
    {
    	$this->config = \Joomla\CMS\Component\ComponentHelper::getParams('com_convertforms');

        $model = BaseDatabaseModel::getInstance('Conversions', 'ConvertFormsModel', ['ignore_request' => true]);
        $model->setState('list.limit', 10);
        $model->setState('filter.state', 1);

        $this->latestleads = $model->getItems();

        // Load the ConvertForms Templates Library
        new ConvertForms\Library();
        
        HTMLHelper::stylesheet('jui/icomoon.css', array(), true);

        ToolbarHelper::title(Text::_('COM_CONVERTFORMS'));

        // Display the template
        parent::display($tpl);
    }
}