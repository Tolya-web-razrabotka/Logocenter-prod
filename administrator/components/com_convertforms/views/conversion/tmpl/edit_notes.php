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

use Joomla\CMS\Language\Text;
?>

<div class="card">
    <h3 class="card-title">
        <?= Text::_('COM_CONVERTFORMS_INTERNAL_NOTES') ?>
        <a class="cf-doc-readmore" href="https://www.tassos.gr/docs/convert-forms/submission-management/edit#internal-notes" target="_blank" rel="noopener"><span class="cf-icon-info" aria-hidden="true"></span><span><?= Text::_('JHELP') ?></span></a>
    </h3>
    <div class="card-body">
        <?= $this->form->renderFieldset('notes') ?>
    </div>
</div>
