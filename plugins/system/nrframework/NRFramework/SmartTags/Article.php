<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\SmartTags;

use Tassos\Framework\Conditions\Conditions\Component\ContentBase;
use Tassos\Framework\Cache;
use Joomla\Registry\Registry;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;

defined('_JEXEC') or die('Restricted access');

/**
 * Use the {article} Smart Tags to retrieve information about a Joomla article. This Smart Tag can return the value of any property from the Joomla Article object as long as you know the name of the property. It can access details from the current browsing article and any article by providing the article's ID using the –id property.
 */
class Article extends SmartTag
{
    /**
     * The data object of the article loaded
     *
     * @var mixed
     */
    protected $article;

    /**
     * Class constructor
     *
     * @param [type] $factory
     * @param [type] $options
     */
    public function __construct($factory = null, $options = null)
    {
        parent::__construct($factory, $options);

        $contentAssignment = new ContentBase();

        $article_id = $this->parsedOptions->get('id', null);

        if (is_null($article_id) && !$contentAssignment->isSinglePage())
        {
            return;
        }
        
        $this->article = $contentAssignment->getItem($article_id);
    }

    /**
     * Fetch a property from the User object
     *
     * @param   string  $key   The name of the property to return
     *
     * @return  mixed   Null if property is not found, mixed if property is found
     */
    public function fetchValue($key)
    {
        if (!$this->article)
        {
            return;
        }

        // Case SEF URL: {article.link}
        if ($key == 'link')
        {
            return Route::_(RouteHelper::getArticleRoute($this->article->id, $this->article->catid, $this->article->language));
        }

        // Support {article.user.USER_PROPERTY}
        if (substr($key, 0, 5) == 'user.')
        {
            $key = str_replace('user.', '', $key);
            $options = array_merge($this->options, ['user' => $this->article->created_by]);

            return $this->redirect('User', $key, $options);
        }

        // Support {article.category.CATEGORY_PROPERTY}. Eg: {article.category.title}
        if (substr($key, 0, 9) == 'category.')
        {
            if (!$category = $this->fetchCategory())
            {
                return;
            }

            $categoryRegistry = new Registry($category);

            return $categoryRegistry->get(substr($key, 9));
        }

        // Case custom fields: {article.field.age}
        if (strpos($key, 'field.') !== false && $this->options['isPro'])
        {
            $fieldParts = explode('.', $key);

            $fieldname = $fieldParts[1];

            // Case {article.field.age.rawvalue}
            $fieldProp = isset($fieldParts[2]) ? implode('.', array_slice($fieldParts, 2)) : 'value';

            if ($fields = $this->fetchCustomFields())
            {
                return $fields->get($fieldname . '.' . $fieldProp);
            }

            return;
        }

        $articleRegistry = new Registry($this->article);

        return $articleRegistry->get($key);
    }

    /**
     * Return the category the article belongs to
     *
     * @return  mixed   Object on success, null on failure
     */
    protected function fetchCategory()
    {
        if (!$this->article || !isset($this->article->catid))
        {
            return;
        }

        $callback = function()
        {
            $db = $this->factory->getDbo();

            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = ' . (int) $this->article->catid);

            $db->setQuery($query);

            return $db->loadObject();
        };

        return Cache::memo('ArticleCategory' . $this->article->catid, $callback);
    }

    /**
     * Return an assosiative array with user custoom fields
     *
     * @return mixed    Array on success, null on failure
     */
    private function fetchCustomFields()
    {
        if (!$this->article)
        {
            return;
        }

        $callback = function()
        {
            \JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

            $prepareCustomFields = $this->parsedOptions->get('preparecustomfields', 'true') === 'true';
    
            if (!$fields = \FieldsHelper::getFields('com_content.article', $this->article, $prepareCustomFields))
            {
                return;
            }

            $fieldsAssoc = [];

            foreach ($fields as $field)
            {
                if ($field->type == 'subform')
                {
                    // Make subform field values accessible via a user-friendly shortcode {article.field.[SUBFORM_FIELD_NAME].rawvalue.[ROW_INDEX].[FIELD_NAME]}
                    // We could just decode the rawvalue property directly but it does make use of the field IDs instead of field names which is not that user-friendly.
                    $rows = [];

                    if (!isset($field->subform_rows))
                    {
                        continue;
                    }

                    foreach ($field->subform_rows as $row)
                    {
                        $row_ = [];

                        foreach ($row as $fieldName => $fieldObj)
                        {
                            $row_[$fieldName] = $fieldObj->value;
                        }

                        $rows[] = $row_;
                    }

                    $field->rawvalue = $rows;
                }

                $fieldsAssoc[$field->name] = $field;
            }

            return new Registry($fieldsAssoc);
        };

        $memoKey = md5('ArticleCustomFields' . $this->article->id . serialize($this->parsedOptions->toArray()));

        return Cache::memo($memoKey, $callback);
    }
}