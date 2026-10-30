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

use Joomla\CMS\Form\Field\MediaField;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

class JFormFieldSignature extends MediaField
{
    /**
     * Allow editing the signature field on the backend
     *
     * @return  string
     */
    protected function getInput()
    {
        Factory::getDocument()->addStyleDeclaration('
            .previewSignature {
                max-width:600px;
                position:relative;
            }
            .previewSignature .btn-download {
                position:absolute;
                right:10px;
                top:10px;
                display:none;
            }
            .previewSignature:hover .btn-download {
                display:block;
            }
        ');

        $this->class = '';

        $parent = parent::getInput();

        return '
            <div class="previewSignature">' . 
                $parent . '
                <a href="' . Uri::root() . '/' . $this->value . '" title="' . Text::_('COM_CONVERTFORMS_SIGNATURE_DOWNLOAD') . '" class="btn btn-small btn-primary btn-sm btn-download" download>
                    <span class="icon-download"></span>
                </a>
            </div>
        ';
    }
}