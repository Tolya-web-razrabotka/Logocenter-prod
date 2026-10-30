<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

// No direct access to this file
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

require_once __DIR__ . '/ajaxify.php';

/*
 * Creates an AJAX-based dropdown
 * https://select2.org/
 */
class JFormFieldComponentItems extends JFormFieldAjaxify
{
    /**
     * List of allowed query presets
     *
     * @var array
     */
    protected static $queryPresets = null;

    /**
     * Single items table name
     *
     * @var string
     */
    protected $table;

    /**
     * Primary key column of the single items table
     *
     * @var string
     */
    protected $column_id = 'id';

    /**
     * The title column of the single items table
     *
     * @var string
     */
    protected $column_title = 'title';

    /**
     * The state column of the single items table
     *
     * @var string
     */
    protected $column_state = 'state';

    /**
     * Pass extra where SQL statement
     *
     * @var string
     */
    protected $where;

    /**
     * Pass extra join SQL statement
     *
     * @var string
     */
    protected $join;

    /**
     * Pass extra group SQL statement
     *
     * @var string
     */
    protected $query_group;

    /**
     * The Joomla database object
     *
     * @var object
     */
    protected $db;

    /**
     * Query preset identifier
     */
    protected $preset;

	/**
	 * Method to attach a JForm object to the field.
	 *
	 * @param   SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
	 * @param   mixed             $value    The form field value to validate.
	 * @param   string            $group    The field name group control value. This acts as an array container for the field.
	 *                                      For example if the field has name="foo" and the group value is set to "bar" then the
	 *                                      full field name would end up being "bar[foo]".
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.2
	 */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
		if ($return = parent::setup($element, $value, $group))
		{
            $this->init();
        }
        
		return $return;
    }

    public function init()
    {
        if ($this->element['preset'])
        {
            $this->preset = (string) $this->element['preset'];
        }
        else 
        {
            // Backward compatibility: Support preset detection based on the old field XML elements
            $this->preset = self::findPresetByConfig([
                'table'        => (string) $this->element['table'] ?? 'content',
                'column_id'    => (string) $this->element['column_id'],
                'column_title' => (string) $this->element['column_title'],
                'column_state' => (string) $this->element['column_state'],
                'where'        => (string) $this->element['where'],
                'join'         => (string) $this->element['join'],
                'group'        => (string) $this->element['group']
            ]);
        }

        // Get query configuration from preset
        $queryConfig = $this->getPresetConfig();

        $this->table        = $queryConfig['table'];
        $this->column_id    = $this->prefix($queryConfig['column_id']);
        $this->column_title = $this->prefix($queryConfig['column_title']);
        $this->column_state = $this->prefix($queryConfig['column_state']);
        $this->where        = $queryConfig['where'] ?? null;
        $this->join         = $queryConfig['join'] ?? null;
        $this->query_group  = $queryConfig['group'] ?? null;

        $this->append_id_to_label = isset($this->element['append_id_to_label'])
            && (string) $this->element['append_id_to_label'] === 'true';

        if (!isset($this->element['placeholder']) && isset($this->element['description']))
        {
            $this->placeholder = (string) $this->element['description'];
        }

        // Initialize database Object
        $this->db = Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class);
    }

    /**
     * Get ComponentItems AJAX endpoint URL
     */
    protected function getAjaxEndpoint()
    {
        $payload = $this->createAjaxHandlerPayload();
        return URI::base() . '?option=com_ajax&format=raw&plugin=nrframework&' . http_build_query($payload);
    }

    /**
     * Create the payload for the AJAX request handler
     */
    protected function createAjaxHandlerPayload()
    {
        return [
            'handler' => 'ComponentItems',
            Session::getFormToken()  => 1,
            'preset' => $this->preset,
            'append_id_to_label' => $this->append_id_to_label ? 1 : 0
        ];
    }

    private function prefix($string)
    {
        if (strpos($string, '.') === false)
        {
            $string = 'i.' . $string;
        }

        return $string;
    }

    protected function getTemplateResult()
    {
        return '<span class="row-text">\' + state.text + \'</span><span style="float:right; opacity:.7">\' + state.id + \'</span>';
    }

    protected function getItemsQuery()
    {
        $db = $this->db;

        $query = $this->getQuery()
            ->order($db->quoteName($this->column_id) . ' DESC');

        if ($this->limit > 0)
        {
            // Joomla uses offset
            $page = $this->page - 1;

            $query->setLimit($this->limit, $page * $this->limit);
        }

        return $query;
    }

    protected function getItems()
    {
        $db = $this->db;

        $db->setQuery($this->getItemsQuery());
        
		$results = $db->loadObjectList();

        if ($this->append_id_to_label && !empty($results))
        {
            foreach ($results as &$item)
            {
                $item->text = $item->text . ' (' . $item->id . ')';
            }
        }

        return $results;
    }

    protected function getItemsTotal()
    {
        $db = $this->db;

        $subQuery = $this->getQuery()
            ->clear('select')
            ->select($this->column_id);

        if ($this->query_group)
        {
            $subQuery = $this->getQuery()
                ->clear('group')
                ->group($this->column_id);
        }
        
        $subQuery = (string) $subQuery;

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from("($subQuery) AS x");

        $db->setQuery($query);
        return (int) $db->loadResult();
    }

    protected function getQuery()
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName($this->column_id, 'id'),
                $db->quoteName($this->column_title, 'text'),
                $db->quoteName($this->column_state, 'state')
            ])
            ->from($db->quoteName('#__' . $this->table, 'i'));

        if (!empty($this->search_term))
        {
            $query->where($db->quoteName($this->column_title) . ' LIKE ' . $db->quote('%' . $this->search_term . '%'));
        }

        if ($this->join)
        {
            $query->join('INNER', $this->join);
        }

        if ($this->where)
        {
            $query->where($this->where);
        }

        if ($this->query_group)
        {
            $query->group($this->query_group);
        }

        return $query;
    }

    protected function validateOptions($options)
    {
        $db = $this->db;

        // Cast all options to integers before imploding
        $safeOptions = array_map('intval', $options);

        $query = $this->getQuery()
            ->where($db->quoteName($this->column_id) . ' IN (' . implode(',', $safeOptions) . ')');

        $db->setQuery($query);

        $results = $db->loadAssocList('id', 'text');

        if ($this->append_id_to_label && !empty($results))
        {
            foreach ($results as $id => &$text)
            {
                $text = $text . ' (' . $id . ')';
            }
        }

        return $results;
    }

    /**
     * Factory method to create the appropriate ComponentItems field instance based on query preset
     *
     * @param string $preset Preset identifier (e.g., 'jshopping', 'virtuemart', 'content')
     * @return JFormFieldComponentItems|JFormFieldJShoppingComponentItems|JFormFieldVirtueMartComponentItems
     */
    public static function createInstance($preset = 'content')
    {
        // Map of presets that point to subclasses of ComponentItems
        static $specialHandlers = [
            'jshopping' => [
                'class' => 'JFormFieldJShoppingComponentItems',
                'file' => 'jshoppingcomponentitems.php'
            ],
            'virtuemart' => [
                'class' => 'JFormFieldVirtueMartComponentItems',
                'file' => 'virtuemartcomponentitems.php'
            ]
        ];

        if (isset($specialHandlers[$preset]))
        {
            $handler = $specialHandlers[$preset];
            require_once __DIR__ . '/' . $handler['file'];
            return new $handler['class']();
        }

        // Default to standard ComponentItems
        return new self();
    }

    /**
     * Initializes query presets map
     *
     * @return array
     */
    protected static function getQueryPresetsMap()
    {
        if (self::$queryPresets !== null)
        {
            return self::$queryPresets;
        }

        self::$queryPresets = [
            'content' => [
                'default' => [
                    'table' => 'content',
                    'column_id' => 'id',
                    'column_title' => 'title',
                    'column_state' => 'state'
                ],
                'published' => [
                    'table' => 'content',
                    'column_id' => 'id',
                    'column_title' => 'title',
                    'column_state' => 'state',
                    'where' => 'i.state >= 0'
                ]
            ],

            'tags' => [
                'default' => [
                    'table' => 'tags',
                    'column_id' => 'id',
                    'column_title' => 'title',
                    'column_state' => 'published',
                    'where' => 'i.published = 1 AND i.alias != \'root\' AND i.level > 0'
                ],
                'acf' => [
                    'table' => 'tags',
                    'column_id' => 'id',
                    'column_title' => 'title',
                    'column_state' => 'published',
                    'where' => 'i.published = 1 AND i.alias != \'root\''
                ]
            ],

            'users' => [
                'default' => [
                    'table' => 'users',
                    'column_id' => 'id',
                    'column_title' => 'name',
                    'column_state' => 'block',
                    'where' => 'i.block = 0'
                ],
                'authors' => [
                    'table' => 'users',
                    'column_id' => 'id',
                    'column_title' => 'i.name',
                    'column_state' => 'i.block',
                    'join' => '#__content as c ON c.created_by = i.id',
                    'group' => 'i.id'
                ]
            ],
            'hikashop' => [
                'table' => 'hikashop_product',
                'column_id' => 'product_id',
                'column_title' => 'product_name',
                'column_state' => 'product_published'
            ],

            'k2items' => [
                'table' => 'k2_items',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'published',
                'where' => 'i.trash = 0'
            ],

            'rseventspro' => [
                'table' => 'rseventspro_events',
                'column_id' => 'id',
                'column_title' => 'name',
                'column_state' => 'published'
            ],

            'jreviews' => [
                'table' => 'jreviews_content',
                'column_id' => 'c.id',
                'column_title' => 'c.title',
                'column_state' => 'c.state',
                'join' => '#__content as c ON c.id = i.contentid',
                'where' => 'c.state >= 0'
            ],

            'dpcalendar' => [
                'table' => 'dpcalendar_events',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'state'
            ],

            'j2store' => [
                'table' => 'j2store_products',
                'column_id' => 'j2store_product_id',
                'column_title' => 'c.title',
                'column_state' => 'c.state',
                'join' => '#__content as c ON i.product_source_id = c.id',
                'where' => "i.product_source='com_content' AND c.state >= 0"
            ],

            'zoo' => [
                'table' => 'zoo_item',
                'column_id' => 'id',
                'column_title' => 'name',
                'column_state' => 'state'
            ],

            'icagenda' => [
                'table' => 'icagenda_events',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'state'
            ],

            'sppagebuilder' => [
                'table' => 'sppagebuilder',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'published',
                'where' => 'i.published >= 0'
            ],
            'jbusinessdirectory' => [
                'default' => [
                    'table' => 'jbusinessdirectory_companies',
                    'column_id' => 'id',
                    'column_title' => 'name',
                    'column_state' => 'state'
                ],
                'offer' => [
                    'table' => 'jbusinessdirectory_company_offers',
                    'column_id' => 'id',
                    'column_title' => 'subject',
                    'column_state' => 'state'
                ],
                'event' => [
                    'table' => 'jbusinessdirectory_company_events',
                    'column_id' => 'id',
                    'column_title' => 'name',
                    'column_state' => 'state'
                ]
            ],

            'djcatalog2' => [
                'table' => 'djc2_items',
                'column_id' => 'id',
                'column_title' => 'name',
                'column_state' => 'published'
            ],

            'djclassifieds' => [
                'table' => 'djcf_items',
                'column_id' => 'id',
                'column_title' => 'name',
                'column_state' => 'published'
            ],

            'djevents' => [
                'table' => 'djev_events',
                'column_id' => 'id',
                'column_title' => 'name',
                'column_state' => 'published'
            ],

            'easyblog' => [
                'table' => 'easyblog_post',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'published',
                'where' => 'i.state = 0'
            ],

            'eventbooking' => [
                'table' => 'eb_events',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'published'
            ],

            'eshop' => [
                'table' => 'eshop_productdetails',
                'column_id' => 'product_id',
                'column_title' => 'product_name',
                'column_state' => 'p.published',
                'join' => '#__eshop_products as p ON i.product_id = p.id'
            ],

            'gridbox' => [
                'table' => 'gridbox_pages',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'published',
                'where' => 'i.published = 1'
            ],

            'quix' => [
                'table' => 'quix',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'state',
                'where' => 'i.state > 0'
            ],

            'rsblog' => [
                'table' => 'rsblog_posts',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'published'
            ],

            'seoglossary' => [
                'table' => 'seoglossary',
                'column_id' => 'id',
                'column_title' => 'tterm',
                'column_state' => 'state'
            ],

            'sobipro' => [
                'table' => 'sobipro_object',
                'column_id' => 'i.id',
                'column_title' => 'f.baseData',
                'column_state' => 'i.state',
                'join' => '#__sobipro_field_data as f ON i.id = f.sid',
                'where' => 'f.fid = 1'
            ],

            'jcalpro' => [
                'table' => 'jcalpro_events',
                'column_id' => 'id',
                'column_title' => 'title',
                'column_state' => 'published'
            ],

            'jevents' => [
                'table' => 'jevents_vevent',
                'column_id' => 'ev_id',
                'column_title' => 'summary',
                'column_state' => 'state'
            ]
        ];

        return self::$queryPresets;
    }

    /**
     * Get query preset configuration for this field
     *
     * @return array Query preset configuration
     */
    protected function getPresetConfig()
    {
        // Parse preset:variant syntax
        $parts = explode(':', $this->preset, 2);
        $presetBase = $parts[0];
        $variant = isset($parts[1]) ? $parts[1] : 'default';

        // Get preset map
        $map = self::getQueryPresetsMap();

        // Validate preset exists
        if (!isset($map[$presetBase]))
        {
            throw new \RuntimeException('ComponentItems: Invalid preset "' . $presetBase . '"');
        }

        $presetConfig = $map[$presetBase];

        // Check if preset has variants
        if (isset($presetConfig['table']))
        {
            // single variant preset
            if ($variant !== 'default')
            {
                throw new \RuntimeException('ComponentItems: Preset "' . $presetBase . '" does not support variants');
            }
            return $presetConfig;
        }

        // multi-variant preset
        if (!isset($presetConfig[$variant]))
        {
            throw new \RuntimeException('ComponentItems: Invalid variant "' . $variant . '" for preset "' . $presetBase . '"');
        }

        return $presetConfig[$variant];
    }

    /**
     * 
     * Backward compatibility methods
     * 
     * Find the preset that best matches the given old XML configuration values
     *
     * @param array $queryConfig Configuration to match (table, column_id, column_title, column_state, where, join, group)
     * @return string|null Preset identifier (e.g., 'content:published') or null if no match
     */
    public static function findPresetByConfig(array $queryConfig)
    {
        $map = self::getQueryPresetsMap();
        $fields = ['table', 'column_id', 'column_title', 'column_state', 'where', 'join', 'group'];

        $bestMatch = null;
        $bestMatchCount = 0;

        foreach ($map as $presetBase => $presetConfig)
        {
            if (isset($presetConfig['table']))
            {
                $matchCount = self::countMatchingFields($queryConfig, $presetConfig, $fields);
                if ($matchCount > $bestMatchCount) {
                    $bestMatch = $presetBase;
                    $bestMatchCount = $matchCount;
                }
            }
            else
            {
                foreach ($presetConfig as $variant => $variantConfig)
                {
                    $matchCount = self::countMatchingFields($queryConfig, $variantConfig, $fields);
                    if ($matchCount > $bestMatchCount)
                    {
                        $bestMatch = $presetBase . ':' . $variant;
                        $bestMatchCount = $matchCount;
                    }
                }
            }
        }

        return $bestMatch;
    }

    private static function countMatchingFields(array $input, array $preset, array $fields)
    {
        $count = 0;

        foreach ($fields as $field)
        {
            $inputValue = $input[$field] ?? null;
            $presetValue = $preset[$field] ?? null;

            if ($inputValue !== null && $inputValue === $presetValue)
            {
                $count++;
            }
        }

        return $count;
    }
}