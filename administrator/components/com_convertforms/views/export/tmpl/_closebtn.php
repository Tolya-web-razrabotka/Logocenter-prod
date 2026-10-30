<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 *
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 *
 * Close button shown inside the export dialog header (component/modal layout).
 * The dialog renders inside an iframe, so closing routes through the parent
 * modal's own close control. Included by the export tmpl files.
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

Factory::getDocument()->addScriptDeclaration(<<<JS
    document.addEventListener("DOMContentLoaded", function () {
        var btn = document.querySelector(".export_tool__close");

        if (!btn) {
            return;
        }

        btn.addEventListener("click", function () {
            try {
                var pw = window.parent;

                // Direct (non-iframe) render, e.g. tests: nothing to close.
                if (!pw || pw === window) {
                    return;
                }

                // Reuse the modal's own close control so its teardown runs.
                var x = pw.document.querySelector("#cfExportSubmissions .btn-close, #cfExportSubmissions .close");

                if (x) {
                    x.click();
                    return;
                }

                if (pw.jQuery) {
                    pw.jQuery("#cfExportSubmissions").modal("hide");
                }
            } catch (e) {}
        });
    });
JS);

?>
<a class="cf-doc-icon" href="https://www.tassos.gr/docs/convert-forms/submission-management/export" target="_blank" rel="noopener" aria-label="<?php echo Text::_('NR_READMORE') ?>" title="<?php echo Text::_('NR_READMORE') ?>"><span class="cf-icon-info" aria-hidden="true"></span></a>
<button type="button" class="export_tool__close" aria-label="Close">&times;</button>
