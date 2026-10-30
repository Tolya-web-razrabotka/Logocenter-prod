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

namespace ConvertForms\Validation\Rules;

use Joomla\CMS\Language\Text;

defined('_JEXEC') or die('Restricted access');

/**
 * Validation Rule - Time To Submit
 * 
 * This rule enforces a minimum time requirement between form display and submission
 * to help prevent automated bot submissions. If a form is submitted too quickly,
 * it's likely from a bot rather than a human user.
 */
class TimeToSubmit extends \ConvertForms\Validation\Rule
{
    protected $alias = 'tts';

    /**
     * Validates the time elapsed between form display and submission
     *
     * @return boolean Returns false if submission is too quick, true otherwise
     */
    public function validate()
    {   
        // Get minimum required time in seconds
        $minTime = $this->getMinTimeToSubmit();

        if ($minTime === 0)
        {
            return true;
        }
        
        // Get the timestamp when the form was initially displayed
        $startTime = $this->env->get($this->alias);

        // If it's null, it's likely the script failed to pass the timestamp
        if (is_null($startTime))
        {
            $this->setError(Text::_('COM_CONVERTFORMS_VALIDATION_TIMETOSUBMIT_MISSING'));
            return false;
        }

        // Calculate elapsed time in seconds
        $timeElapsed = time() - $startTime;

        // Check if submission is too quick (likely a bot)
        if ($timeElapsed < $minTime)
        {
            return false;
        }
    }

    /**
     * Checks if the rule is enabled based on the minimum time to submit setting
     *
     * @return boolean Returns true if the rule is enabled, false otherwise
     */
    public function isEnabled()
    {
        return $this->getMinTimeToSubmit() > 0;
    }

    /**
     * Returns the minimum required time in seconds. Defaults to 2 seconds.
     *
     * @return int
     */
    private function getMinTimeToSubmit()
    {
        $enabled = (bool) $this->getFormRegistry()->get('params.enable_min_time_to_submit', false);

        return $enabled ? (int) $this->getFormRegistry()->get('params.min_time_to_submit', 2) : 0;
    }
}