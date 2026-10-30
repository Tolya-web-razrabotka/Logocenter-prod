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

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use ConvertForms\Submission\Submission as SubmissionContext;

$submission = new SubmissionContext($this->item, SubmissionContext::CONSUMER_EDIT);

// The Identity block shown inside the Status card, in a fixed reading order. All rows are
// read-only Details except Status, which renders the editable state dropdown in place (the
// Form can't be reassigned, so form_name stays read-only). Notes and the Joomla account
// live elsewhere (their own card / the Context card).
$columns = ['id', 'form_name', 'status', 'created', 'modified'];

// A submission that was never edited has no modified date — drop the row instead of
// showing an empty one.
if (trim(strip_tags((string) $submission->getProp('modified')->getValueHTML())) === '')
{
    $columns = array_values(array_filter($columns, function ($column) { return $column !== 'modified'; }));
}

$rowOverrides = [
    'status' => [
        'icon'   => $submission->getProp('status')->getIcon(),
        'label'  => Text::_('JSTATUS'),
        'value'  => $this->form->getInput('state'),
        'noCopy' => true,
    ],
];
?>
<div class="submission-info-card cf-identity-details">
    <?php include __DIR__ . '/_detail_rows.php'; ?>
</div>
