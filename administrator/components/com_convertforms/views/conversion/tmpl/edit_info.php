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

// A curated, human-readable subset of the Context section, in a sensible reading order:
// who (account, visitor), where (country + flag, IP), device, how they arrived
// (landing/referrer) and campaign. Unlike {submission.context} (which dumps every column),
// the card drops redundant derivations — Source Path/Domain, Referrer Domain, Country Code —
// and the extra account fields. The account row is editable (the Joomla User picker), so the
// submission can be re-assigned right here; Country Name renders with its flag.
$columns = [
    'visitor_id',
    'user_id',
    'country_name',
    'ip',
    'device',
    'os',
    'browser',
    'page_title',
    'source_url',
    'referrer_url',
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_term',
    'utm_content',
    'user_agent'
];

// card) so the submission can be re-assigned in place. The field lives in the form's
// "account" fieldset; we render just its input here, inside the shared adminForm.
$rowOverrides = [
    'user_id' => [
        'icon'   => $submission->getProp('user_id')->getIcon(),
        'label'  => Text::_('COM_CONVERTFORMS_DETAIL_GROUP_ACCOUNT'),
        'value'  => $this->form->getInput('user_id'),
        'noCopy' => true,
    ],
];

// Skip any detail that carries no value — an empty row adds nothing but noise.
// Override rows (e.g. the editable account picker) always render, since their
// value is an input rather than a plain detail.
$columns = array_values(array_filter($columns, function ($column) use ($rowOverrides, $submission) {
    if (isset($rowOverrides[$column]))
    {
        return true;
    }

    return trim(strip_tags((string) $submission->getProp($column)->getValueHTML())) !== '';
}));
?>
<div class="card submission-info-card">
    <h3 class="card-title">
        <?= Text::_('COM_CONVERTFORMS_DETAIL_SECTION_CONTEXT') ?>
        <a class="cf-doc-readmore" href="https://www.tassos.gr/docs/convert-forms/submission-management/context-tracking" target="_blank" rel="noopener"><span class="cf-icon-info" aria-hidden="true"></span><span><?= Text::_('JHELP') ?></span></a>
    </h3>
    <div class="card-body">
        <?php include __DIR__ . '/_detail_rows.php'; ?>
        
        <?php
        // Free edition only — this whole block is stripped from the Pro build. Show users
        // what visitor tracking unlocks, with a handful of concrete examples pulled from the
        // Pro-only detail labels.
        $ctaExamples = [];
        foreach (['ip', 'country_name', 'device', 'browser'] as $exampleColumn)
        {
            $ctaExamples[] = $submission->getProp($exampleColumn)->getLabel();
        }

        // "Upgrade to Pro" is a link that opens the framework upgrade modal (via
        // data-pro-only); render that modal once on the page.
        NRFramework\HTML::renderProOnlyModal('com_convertforms');
        $ctaLink = '<a href="#" class="cf-context-upgrade__link" data-pro-only="' . htmlspecialchars(Text::_('COM_CONVERTFORMS_DETAIL_SECTION_CONTEXT'), ENT_QUOTES, 'UTF-8') . '">' . Text::_('NR_UPGRADE_TO_PRO') . '</a>';
        ?>
        <div class="cf-context-upgrade">
            <span class="icon-star cf-context-upgrade__icon" aria-hidden="true"></span>
            <p class="cf-context-upgrade__text"><?= Text::sprintf('COM_CONVERTFORMS_CONTEXT_PRO_CTA', $ctaLink, implode(', ', $ctaExamples)) ?></p>
        </div>
        
        <script>
        (function () {
            document.querySelectorAll('.cf-copy-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var valueEl = btn.closest('.cf-detail-row').querySelector('.cf-detail-value');
                    var text = valueEl ? valueEl.innerText.trim() : '';
                    if (!text) return;
                    navigator.clipboard.writeText(text).then(function () {
                        btn.classList.add('cf-copied');
                        setTimeout(function () { btn.classList.remove('cf-copied'); }, 1500);
                    });
                });
            });
        })();
        </script>
    </div>
</div>
