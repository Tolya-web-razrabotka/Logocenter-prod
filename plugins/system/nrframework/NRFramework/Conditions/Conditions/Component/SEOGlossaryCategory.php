<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Conditions\Conditions\Component;

defined('_JEXEC') or die;

class SEOGlossaryCategory extends SEOGlossaryBase
{
    /**
     * Shortcode aliases for this Condition
     */
    public static $shortcode_aliases = ['seoglossary.glossary'];

    /**
     *  Pass check
     *
     *  @return bool
     */
    public function pass()
    {
        // Glossaries are flat, so there is no parent column to walk.
        return $this->passCategories('seoglossaries', 'id');
    }
}
