<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Conditions\Conditions\Component;

use Joomla\CMS\Helper\TagsHelper;

defined('_JEXEC') or die;

class ContentTag extends ContentBase
{
    /**
     * Pass check for Joomla! Article Tags
     *
     * @return bool
     */
    public function pass()
    {
        return $this->passSinglePage();
    }

    /**
     * Returns the assignment's value (the current article's tag IDs).
     *
     * @return array
     */
    public function value()
    {
        return $this->getArticleTagIds();
    }

    /**
     * Returns the tag IDs assigned to the given article.
     *
     * @param   int  $articleId  The article ID.
     *
     * @return  array
     */
    protected function getArticleTagIds()
    {
        $articleId = $this->request->id;

        $hash = md5('contentTagIds' . $articleId);

        $cache = $this->factory->getCache();

        if ($cache->has($hash))
        {
            return $cache->get($hash);
        }

        $tags = new TagsHelper;
        $tags = $tags->getItemTags('com_content.article', $articleId, false);

        $tagIds = [];

        foreach ($tags as $tag)
        {
            $tagIds[] = $tag->tag_id;
        }

        return $cache->set($hash, $tagIds ?: []);
    }
}