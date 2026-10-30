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

namespace ConvertForms;

defined('_JEXEC') or die('Restricted access');

use ConvertForms\Submission\Submission as SubmissionContext;

/**
 * @deprecated  Use ConvertForms\SmartTags::replace() instead.
 *
 * This class only exists to wrap a raw submission DB row so {submission.*} Smart Tags
 * resolve correctly. That wrapping is already done properly by ConvertForms\SmartTags::replace(),
 * which is where its 2 callers (single submission custom view, list custom rows) should route.
 *
 * We are NOT switching them over yet: SmartTags::replace() fires the
 * onConvertFormsGetSubmissionSmartTags event, and the PDF plugin listens to it on the front end
 * and generates a PDF. Routing the display views through it today would write a PDF to disk on
 * every page view of a custom-layout Submissions page, and hand rows 2..N the first row's PDF URL
 * (the plugin caches the generated PDF per request, not per submission id).
 *
 * To keep this release small and just fix the getProp() error, we keep this class temporarily.
 * Long-term fix:
 *   1. Add a skip-event option to SmartTags::replace().
 *   2. Switch both front-end callers to it and reduce replaceSmartTags() to a one-line wrapper.
 *   3. Fix the PDF plugin cache to key per submission id.
 */
class Submission
{
    /**
     * Replaces the Smart Tags of a submission.
     *
     * @deprecated  Use ConvertForms\SmartTags::replace() instead. See the class docblock.
     *
     * @param   object  $submission
     * @param   string  $layout
     *
     * @return  string
     */
	public static function replaceSmartTags($submission, $layout)
	{
		// Wrap the raw DB row in the domain object so the {submission.*} Smart Tags can
		// resolve values through getProp() (the Detail hierarchy). Without this the
		// front-end Submissions views hand a plain stdClass to tags that call getProp()
		// on it, causing "Call to undefined method stdClass::getProp()". __get()/__isset()
		// keep raw property access ($submission->id, ->created, …) working transparently.
		if (!($submission instanceof SubmissionContext))
		{
			$submission = new SubmissionContext($submission, SubmissionContext::CONSUMER_SMARTTAG);
		}

		$st = new \NRFramework\SmartTags();

		// Register CF Smart Tags
		$st->register(
			'\ConvertForms\SmartTags',
			JPATH_SITE . '/administrator/components/com_convertforms/ConvertForms/SmartTags',
			[
				'submission' => $submission
			]
		);

		return $st->replace($layout);
	}
}