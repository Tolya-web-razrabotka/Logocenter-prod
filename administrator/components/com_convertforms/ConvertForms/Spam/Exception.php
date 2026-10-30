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

namespace ConvertForms\Spam;

defined('_JEXEC') or die('Restricted access');

class Exception extends \Exception
{
    protected $thrownBy;
    
    public function __construct($message, $thrownBy = '')
    {
        parent::__construct($message, 100);

        $this->thrownBy = $thrownBy;
    }
    
    public function getThrownBy()
    {
        return $this->thrownBy;
    }
}