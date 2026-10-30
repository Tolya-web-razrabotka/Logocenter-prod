<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Conditions\Conditions\Component;

defined('_JEXEC') or die;

class SEOGlossarySingle extends SEOGlossaryBase
{
    /**
     * Shortcode aliases for this Condition
     */
    public static $shortcode_aliases = ['seoglossary.term'];

    /**
     *  Pass check
     *
     *  @return  bool
     */
    public function pass()
    {
        return $this->passSinglePage();
    }

    /**
     *  Returns the assignment's value
     * 
     *  @return int
     */
    public function value()
    {
        return $this->request->id;
    }
}
