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

defined('_JEXEC') or die('Restricted access');

/**
 * Validation Rule - Honeypot
 * 
 * This rule implements a honeypot field technique to catch automated bot submissions.
 * A hidden field is added to the form that should remain empty; legitimate users
 * won't see or fill it, while bots typically complete all fields automatically.
 * If the honeypot field contains any data, the submission is rejected as spam.
 */
class Honeypot extends \ConvertForms\Validation\Rule
{
    protected $alias = 'hp';

    public function validate()
    {
        if (!$this->isEnabled())
        {
            return;
        }

        $honeypotId = $this->env->get($this->alias);

        // The honeypot field is injected via JavaScript after page load. Bots with limited or no post-load JavaScript execution, 
        // won't execute the script and will not have the honeypot field in the submission data.
        if (is_null($honeypotId))
        {
            $this->setError('Honeypot ID not found');
            return false;
        }

        // The honeypot field should be present in the submission data. Otherwise might be a direct attempt.
        if (!isset($this->submission[$honeypotId]))
        {
            $this->setError('Honeypot not found in submission');
            return false;
        }

        // The honeypot field should be empty. Otherwise it's likely a bot.
        if (!empty($this->submission[$honeypotId]))
        {
            return false;
        }
    }

    /**
     * Returns the minimum required time in seconds. Defaults to 2 seconds.
     *
     * @return int
     */
    public function isEnabled()
    {
        return (int) $this->getFormRegistry()->get('params.honeypot') == 2;
    }
}