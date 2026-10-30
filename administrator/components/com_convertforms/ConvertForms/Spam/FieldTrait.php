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

use Joomla\CMS\Language\Text;

/**
 * Trait for fields that handle spam detection
 */
trait FieldTrait
{
    /**
     * Override the throwError method to throw ConvertForms\Spam\Exception instead of regular Exception
     *
     * @param   string  $message  The error message
     *
     * @return  void
     * 
     * @throws  ConvertForms\Spam\Exception
     */
    public function spamError($message)
    {
        if (!$label = $this->getLabel())
        {
            $label = $this->field->get('name', ucfirst($this->field->get('type')));
        }

        $message = htmlspecialchars($label . ': ' . Text::_($message), ENT_QUOTES, 'UTF-8');

        throw new \ConvertForms\Spam\Exception($message, 'field.' . $this->field->get('type'));
    }
}