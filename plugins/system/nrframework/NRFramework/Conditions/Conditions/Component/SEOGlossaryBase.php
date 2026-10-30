<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Conditions\Conditions\Component;

defined('_JEXEC') or die;

class SEOGlossaryBase extends ComponentBase
{
    /**
     * The component's Category Page view name
     *
     * @var string
     */
    protected $viewCategory = 'glossaries';

    /**
     * The component's Single Page view name
     *
     * @var string
     */
    protected $viewSingle = 'glossary';

    /**
     * The component's option name
     *
     * @var string
     */
    protected $component_option = 'com_seoglossary';

    /**
     * Returns the glossary IDs of the current page.
     *
     * @return  array
     */
    protected function getCategoryIDs()
    {
        // Glossary pages carry the glossary ID in catid, not in id.
        if ($this->isCategoryPage())
        {
            $catid = $this->app->input->getInt('catid');

            return $catid ? [$catid] : [];
        }

        return parent::getCategoryIDs();
    }

    /**
     * Get single page's assosiated categories
     *
     * @param   Integer  The Single Page id
     * 
     * @return  array
     */
    protected function getSinglePageCategories($id)
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__seoglossary'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);

        $db->setQuery($query);

        return $db->loadColumn();
    }
}
