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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
?>
<script type="text/javascript">
    Joomla.submitbutton = function(task)
    {
        if (task == 'conversion.cancel' || document.formvalidator.isValid(document.getElementById('adminForm')))
        {
            Joomla.submitform(task, document.getElementById('adminForm'));
        }
    }
</script>

<div class="form-horizontal">
    <form action="<?= Route::_('index.php?option=com_convertforms&view=conversion&layout=edit&id='.(int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm">
        <div class="rowx">
            <span class="col-7 d-flex flex-column gap-4">
                <div class="card">
                    <h3 class="card-title">
                        <?= Text::_('COM_CONVERTFORMS_LEAD_USER_SUBMITTED_DATA') ?>
                    </h3>
                    <div class="card-body">
                        <?= $this->form->renderFieldset('params') ?>
                    </div>
                </div>
                <?= $this->loadTemplate('notes') ?>
            </span>
            <span class="col-5 d-flex flex-column gap-4">
                <div class="card">
                    <h3 class="card-title">
                        <?= Text::_('COM_CONVERTFORMS_DETAIL_SECTION_IDENTITY') ?>
                    </h3>
                    <div class="card-body">
                        <?= $this->loadTemplate('identity') ?>
                        <?= $this->form->getInput('id') ?>
                    </div>
                </div>
                <?php echo $this->loadTemplate('info') ?>

                <?php $pdfHtml = trim($this->form->renderFieldset('pdf_document') ?? ''); ?>
                <?php if ($pdfHtml): ?>
                <div class="card">
                    <h3 class="card-title">
                        <?= Text::_('PLG_CONVERTFORMSTOOLS_PDF_DOCUMENT') ?>
                        <a class="cf-doc-readmore" href="https://www.tassos.gr/docs/convert-forms/features/pdf-form-submissions" target="_blank" rel="noopener"><span class="cf-icon-info" aria-hidden="true"></span><span><?= Text::_('JHELP') ?></span></a>
                    </h3>
                    <div class="card-body">
                        <?= $pdfHtml ?>
                    </div>
                </div>
                <?php endif ?>
            </span>
        </div>
        <?= HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="task" value="conversion.edit" />
    </form>
</div>