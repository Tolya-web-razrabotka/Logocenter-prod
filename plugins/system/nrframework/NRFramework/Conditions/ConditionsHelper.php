<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework\Conditions;

use Tassos\Framework\Factory;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

/**
 *  Conditions Helper Class
 * 
 *  Singleton
 */
class ConditionsHelper
{
    /**
     *  Factory object 
     * 
     *  @var \Tassos\Framework\Factory
     */
    protected $factory;

    /**
     *  Class constructor
     */
    public function __construct($factory = null)
    {
        $this->factory = is_null($factory) ? new Factory() : $factory;
    }

    /**
     * Get only one instance of the class
     *
     * @return object
     */
    static public function getInstance($factory = null)
    {
        static $instance = null;

		if ($instance === null)
		{
            $instance = new ConditionsHelper($factory);
		}
		
        return $instance;
    }

    /**
     * Passes a set of groups which are connected with OR comparison operator.
     * 
     * Expected object:
     * 
     * $groups = [
     *   [
     *      mathing_method => string (all|any),
     *      rules          => array
     *   ],
     *   [
     *      mathing_method => string (all|any),
     *      rules          => array
     *   ]
     *   ...
     * ];
     * 
     * @param   array  $groups
     * 
     * @return  mixed  On validation error return null, if validation runs return bool
     */
    public function passSets($groups)
    {
        $pass = null;

        // Validations
        if (!is_array($groups) OR (is_array($groups) AND empty($groups)))
        {
            return $pass;
        }

        foreach ($groups as $group)
        {
            // Skip invalid groups
            if (!isset($group['rules']) OR !is_array($group['rules']) OR (is_array($group['rules']) AND empty($group['rules'])))
            {
                continue;
            }

            $matching_method = isset($group['matching_method']) ? $group['matching_method'] : 'all';

            // If a group meets the condition, pass the check and abort so no further tests are executed.
            if ($pass = $this->passSet($group['rules'], $matching_method))
            {
                break;
            }
        }

        return $pass;
    }

    /**
     * Passes a set of rules.
     * 
     * Expected object for rules:
     * 
     * $rules = [
     *   [
     *      name     => string,
     *      value    => mixed,
     *      operator => string,
     *      params   => array
     *   ],
     *   [
     *      name     => string,
     *      value    => mixed,
     *      operator => string,
     *      params   => array
     *   ]
     *   ...
     * ];
     * 
     * @param   array   $rules
     * @param   string  $matchingMethod
     * 
     * @return  bool
     */
    public function passSet($rules, $matchingMethod)
    {
        $pass = null;

        // Validations
        if (!is_array($rules) OR (is_array($rules) AND empty($rules)))
        {
            return $pass;
        }

        foreach ($rules as $rule)
        {
            // Skip unknown rules
            if (!isset($rule['name']))
            {
                continue;
            }

            // Validate rule
            $params   = isset($rule['params'])   ? $rule['params'] : null;
            $value    = isset($rule['value'])    ? $rule['value'] : '';
            $operator = isset($rule['operator']) ? $rule['operator'] : '';

            // Run checks
            $pass = $this->passOne($rule['name'], $value, $operator, $params);

            // Check no further the Ruleset when any of the following happens:
            // 1. We expect ALL Rules to pass but one fails.
            // 2. We expect ANY Rule to pass and one does so.
            if ((!$pass AND $matchingMethod == 'all') OR ($pass AND $matchingMethod == 'any'))
            {
                break;
            }
        }

        return $pass;
    }

    /**
    * Execute given rnule
    *
    * @param  string  $name       The name of the rule. Case-sensitive.
    * @param  mixed   $selection  The value to compare with the value returned by the rule.
    * @param  string  $operator   The operator to use to do the comparison
    * @param  array   $params     Optional rule parameters

    * @return mixed   Null when the validation doesn't run properly, bool otherwize
    */
    public function passOne($name, $selection, $operator, $params = [])
    {
        // Convert deprecated operators to new operators
        $migrationMap = [
            'is'     => 'not_empty',
            'is_not' => 'empty',
        ];

        if (array_key_exists($operator, $migrationMap))
        {
            $operator = $migrationMap[$operator];
        }

        if (!$rule = $this->getCondition($name, $selection, str_replace('not_', '', $operator ?? ''), $params))
        {
            return;
        }

        $pass = $rule->pass();

        if (is_null($pass))
        {
            return $pass;
        }
        
		return strpos($operator ?? '', 'not_') !== false ? !$pass : $pass;
    }

    /**
    * Initialize the condition class object
    *
    * @param  string  $name       The name of the rule. Case-sensitive.
    * @param  mixed   $selection  The value to compare with the value returned by the rule.
    * @param  string  $operator   The operator to use to do the comparison
    * @param  array   $params     Optional rule parameters

    * @return mixed   Null on failure, object on success
    */
    public function getCondition($name, $selection = null, $operator = '', $params = null)
    {
        if (!$name)
        {
            return;
        }

        $class = __NAMESPACE__ . '\\Conditions\\' . $name;

        if (!class_exists($class))
        {
            return;
        }

        // Prepare rule options
        $options = [
            'selection' => $selection,
            'operator'  => str_replace('not_', '', $operator),
            'params'    => $params
        ];

        $rule = new $class($options, $this->factory);

        return $rule;
    }

    /**
     * Validate and manipulate rules before they are get stored into the database.
     *
     * @param  array $rules
     * 
     * @return void
     */
    public function onBeforeSave(&$rules)
    {
        // If its a string, transform it into an array, otherwise, use the actual value (array)
        $rules = is_string($rules) ? json_decode($rules, true) : $rules;

        if (!is_array($rules))
        {
            return;
        }
        
        foreach ($rules as &$group)
        {
            if (!isset($group['rules']))
            {
                continue;
            }

            foreach ($group['rules'] as &$rule)
            {
                if (!$condition = $this->getCondition($rule['name']))
                {
                    continue;
                }

                if (!\method_exists($condition, 'onBeforeSave'))
                {
                    continue;
                }

                $condition->onBeforeSave($rule);
            }
        }
    }

    public static function getConditionsList($product = '')
    {
        return [
            'page_targeting' => [
                'title' => Text::_('NR_PAGE_TARGETING'),
                'desc' => Text::sprintf('NR_PAGE_TARGETING_SECTION_DESC', $product),
                'conditions' => [
                    'Homepage' => [
                        'title' => Text::_('NR_HOMEPAGE'),
                        'desc' => Text::sprintf('NR_HOMEPAGE_CONDITION_DESC', $product)
                    ],
                    'Domain' => [
                        'title' => Text::_('NR_DOMAIN'),
                        'desc' => Text::sprintf('NR_DOMAIN_CONDITION_DESC', $product)
                    ],
                    'Joomla\Menu' => [
                        'title' => Text::_('NR_MENU'),
                        'desc' => Text::sprintf('NR_MENU_CONDITION_DESC', $product)
                    ],
                    'URL' => [
                        'title' => Text::_('NR_URL_QUERY_STRING'),
                        'desc' => Text::sprintf('NR_URL_CONDITION_DESC', $product)
                    ],
                    'com_content#Component\ContentArticle' => [
                        'title' => Text::_('NR_CONTENT_ARTICLE'),
                        'desc' => Text::sprintf('NR_CONTENT_ARTICLE_CONDITION_DESC', $product)
                    ],
                    'com_content#Component\ContentCategory' => [
                        'title' => Text::_('NR_CONTENT_CATEGORY'),
                        'desc' => Text::sprintf('NR_CONTENT_CATEGORY_CONDITION_DESC', $product)
                    ],
                    'com_content#Component\ContentView' => [
                        'title' => Text::_('NR_CONTENT_VIEW'),
                        'desc' => Text::sprintf('NR_CONTENT_VIEW_CONDITION_DESC', $product)
                    ],
                    'com_content#Component\ContentTag' => [
                        'title' => Text::_('NR_CONTENT_TAG'),
                        'desc' => Text::sprintf('NR_CONTENT_TAG_CONDITION_DESC', $product)
                    ]
                ]
            ],
            'user_targeting' => [
                'title' => Text::_('NR_USER_TARGETING'),
                'desc' => Text::sprintf('NR_USER_TARGETING_SECTION_DESC', $product),
                'conditions' => [
                    'Joomla\UserID' => [
                        'title' => Text::_('NR_USER'),
                        'desc' => Text::sprintf('NR_USER_CONDITION_DESC', $product)
                    ],
                    'Joomla\UserGroup' => [
                        'title' => Text::_('NR_USERGROUP'),
                        'desc' => Text::sprintf('NR_USER_GROUP_CONDITION_DESC', $product)
                    ],
                    'Joomla\AccessLevel' => [
                        'title' => Text::_('NR_USERACCESSLEVEL'),
                        'desc' => Text::sprintf('NR_USER_ACCESS_LEVEL_CONDITION_DESC', $product)
                    ],
                    'ReturningNewVisitor' => [
                        'title' => Text::_('NR_NEW_VS_RETURNING'),
                        'desc' => Text::sprintf('NR_NEW_VS_RETURNING_VISITOR_CONDITION_DESC', $product)
                    ],
                    'IP' => [
                        'title' => Text::_('NR_IPADDRESS'),
                        'desc' => Text::sprintf('NR_IP_ADDRESS_CONDITION_DESC', $product)
                    ],
                    'Device' => [
                        'title' => Text::_('NR_DEVICE_TYPE'),
                        'desc' => Text::sprintf('NR_DEVICE_CONDITION_DESC', $product)
                    ],
                    'Geo\Country' => [
                        'title' => Text::_('NR_ASSIGN_COUNTRIES'),
                        'desc' => Text::sprintf('NR_COUNTRY_CONDITION_DESC', $product)
                    ],
                    'Geo\City' => [
                        'title' => Text::_('NR_CITY'),
                        'desc' => Text::sprintf('NR_CITY_CONDITION_DESC', $product)
                    ],
                    'Geo\Continent' => [
                        'title' => Text::_('NR_CONTINENT'),
                        'desc' => Text::sprintf('NR_CONTINENT_CONDITION_DESC', $product)
                    ],
                    'Geo\Region' => [
                        'title' => Text::_('NR_REGION'),
                        'desc' => Text::sprintf('NR_REGION_CONDITION_DESC', $product)
                    ],
                    'TimeOnSite' => [
                        'title' => Text::_('NR_ASSIGN_TIMEONSITE'),
                        'desc' => Text::sprintf('NR_TIME_ON_SITE_CONDITION_DESC', $product)
                    ],
                    'Pageviews' => [
                        'title' => Text::_('NR_PAGEVIEWS_COUNT'),
                        'desc' => Text::sprintf('NR_PAGEVIEWS_COUNT_CONDITION_DESC', $product)
                    ],
                    'OS' => [
                        'title' => Text::_('NR_OPERATING_SYSTEM'),
                        'desc' => Text::sprintf('NR_OPERATING_SYSTEM_CONDITION_DESC', $product)
                    ],
                    'Browser' => [
                        'title' => Text::_('NR_ASSIGN_BROWSERS'),
                        'desc' => Text::sprintf('NR_BROWSER_CONDITION_DESC', $product)
                    ],
                ]
            ],
            'date_time' => [
                'title' => Text::_('NR_DATE_AND_TIME'),
                'desc' => Text::sprintf('NR_DATE_AND_TIME_SECTION_DESC', $product),
                'conditions' => [
                    'Date\Date' => [
                        'title' => Text::_('NR_DATE'),
                        'desc' => Text::sprintf('NR_DATE_CONDITION_DESC', $product)
                    ],
                    'Date\Time' => [
                        'title' => Text::_('NR_TIME'),
                        'desc' => Text::sprintf('NR_TIME_CONDITION_DESC', $product)
                    ],
                    'Date\Day' => [
                        'title' => Text::_('NR_WEEKDAY'),
                        'desc' => Text::sprintf('NR_DAY_CONDITION_DESC', $product)
                    ],
                    'Date\Month' => [
                        'title' => Text::_('NR_MONTH'),
                        'desc' => Text::sprintf('NR_MONTH_CONDITION_DESC', $product)
                    ],
                ]
            ],
            'eCommerce' => [
                'title' => Text::_('NR_ECOMMERCE'),
                'desc' => Text::sprintf('NR_ECOMMERCE_SECTION_DESC', $product),
                'conditions' => [
                    'com_virtuemart#Component\VirtueMartCartContainsProducts' => [
                        'title' => Text::_('NR_VM_CART_CONTAINS_PRODUCTS'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CART_CONTAINS_PRODUCTS_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartCartContainsXProducts' => [
                        'title' => Text::_('NR_VM_CART_CONTAINS_X_PRODUCTS'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CART_CONTAINS_X_PRODUCTS_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartCartValue' => [
                        'title' => Text::_('NR_VM_CART_VALUE'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CART_VALUE_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartSingle' => [
                        'title' => Text::_('NR_VM_PRODUCT'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_PRODUCT_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartCategory' => [
                        'title' => Text::_('NR_VM_CURRENT_PRODUCT_CATEGORY'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CURRENT_PRODUCT_CATEGORY_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartCurrentProductPrice' => [
                        'title' => Text::_('NR_VM_CURRENT_PRODUCT_PRICE'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CURRENT_PRODUCT_PRICE_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartCurrentProductStock' => [
                        'title' => Text::_('NR_VM_CURRENT_PRODUCT_STOCK'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CURRENT_PRODUCT_STOCK_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartCategoryView' => [
                        'title' => Text::_('NR_VM_CURRENT_CATEGORY'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CURRENT_CATEGORY_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartPurchasedProduct' => [
                        'title' => Text::_('NR_VM_PURCHASED_PRODUCT'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_PURCHASED_PRODUCT_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartLastPurchasedDate' => [
                        'title' => Text::_('NR_VM_LAST_PURCHASED_DATE'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_LAST_PURCHASED_DATE_CONDITION_DESC', $product)
                    ],
                    'com_virtuemart#Component\VirtueMartTotalSpend' => [
                        'title' => Text::_('NR_VM_TOTAL_SPEND'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_TOTAL_SPEND_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopCartContainsProducts' => [
                        'title' => Text::_('NR_HIKA_CART_CONTAINS_PRODUCTS'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CART_CONTAINS_PRODUCTS_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopCartContainsXProducts' => [
                        'title' => Text::_('NR_HIKA_CART_CONTAINS_X_PRODUCTS'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CART_CONTAINS_X_PRODUCTS_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopCartValue' => [
                        'title' => Text::_('NR_HIKA_CART_VALUE'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CART_VALUE_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopSingle' => [
                        'title' => Text::_('NR_HIKA_PRODUCT'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_PRODUCT_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopCategory' => [
                        'title' => Text::_('NR_HIKA_CURRENT_PRODUCT_CATEGORY'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CURRENT_PRODUCT_CATEGORY_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopCurrentProductPrice' => [
                        'title' => Text::_('NR_HIKA_CURRENT_PRODUCT_PRICE'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CURRENT_PRODUCT_PRICE_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopCurrentProductStock' => [
                        'title' => Text::_('NR_HIKA_CURRENT_PRODUCT_STOCK'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CURRENT_PRODUCT_STOCK_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopCategoryView' => [
                        'title' => Text::_('NR_HIKA_CURRENT_CATEGORY'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_CURRENT_CATEGORY_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopPurchasedProduct' => [
                        'title' => Text::_('NR_HIKA_PURCHASED_PRODUCT'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_PURCHASED_PRODUCT_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopLastPurchasedDate' => [
                        'title' => Text::_('NR_HIKA_LAST_PURCHASED_DATE'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_LAST_PURCHASED_DATE_CONDITION_DESC', $product)
                    ],
                    'com_hikashop#Component\HikashopTotalSpend' => [
                        'title' => Text::_('NR_HIKA_TOTAL_SPEND'),
                        'desc' => Text::sprintf('NR_ECOMMERCE_TOTAL_SPEND_CONDITION_DESC', $product)
                    ]
                ]
            ],
            'integrations' => [
                'title' => Text::_('NR_INTEGRATIONS'),
                'desc' => Text::sprintf('NR_INTEGRATIONS_SECTION_DESC', $product),
                'conditions' => [
                    'com_convertforms#ConvertForms'=> [
                        'title' => Text::_('NR_CONVERT_FORMS_CAMPAIGN'),
                        'desc' => Text::sprintf('NR_CONVERT_FORMS_CAMPAIGN_CONDITION_DESC', $product)
                    ],
                    'com_convertforms#ConvertFormsForm'=> [
                        'title' => Text::_('NR_CONVERT_FORMS_FORM'),
                        'desc' => Text::sprintf('NR_CONVERT_FORMS_FORM_CONDITION_DESC', $product)
                    ],
                    'com_rstbox#EngageBox'=> [
                        'title' => Text::_('NR_ENGAGEBOX'),
                        'desc' => Text::sprintf('NR_ENGAGEBOX_CONDITION_DESC', $product)
                    ],
                    'com_acymailing#AcyMailing|com_acym#AcyMailing' => [
                        'title' => Text::_('NR_ACYMAILING_LIST'),
                        'desc' => Text::sprintf('NR_ACYMAILING_LIST_CONDITION_DESC', $product)
                    ],
                    'com_k2#Component\K2Item' => [
                        'title' => Text::_('NR_K2_ITEM'),
                        'desc' => Text::sprintf('NR_K2_ITEM_CONDITION_DESC', $product)
                    ],
                    'com_k2#Component\K2Category' => [
                        'title' => Text::_('NR_K2_CATEGORY'),
                        'desc' => Text::sprintf('NR_K2_CATEGORY_CONDITION_DESC', $product)
                    ],
                    'com_k2#Component\K2Tag' => [
                        'title' => Text::_('NR_K2_TAG'),
                        'desc' => Text::sprintf('NR_K2_TAG_CONDITION_DESC', $product)
                    ],
                    'com_k2#Component\K2Pagetype' => [
                        'title' => Text::_('NR_K2_PAGE_TYPE'),
                        'desc' => Text::sprintf('NR_K2_PAGE_TYPE_CONDITION_DESC', $product)
                    ],
                    'com_akeebasubs#AkeebaSubs' => [
                        'title' => Text::_('NR_AKEEBASUBS_LEVELS'),
                        'desc' => Text::sprintf('NR_AKEEBASUBS_LEVELS_CONDITION_DESC', $product)
                    ]
                ]
            ],
            'other' => [
                'title' => Text::_('NR_OTHER'),
                'desc' => Text::sprintf('NR_OTHER_SECTION_DESC', $product),
                'conditions' => [
                    'Joomla\Component' => [
                        'title' => Text::_('NR_ASSIGN_COMPONENTS'),
                        'desc' => Text::sprintf('NR_COMPONENT_CONDITION_DESC', $product)
                    ],
                    'Joomla\Language' => [
                        'title' => Text::_('NR_ASSIGN_LANGS'),
                        'desc' => Text::sprintf('NR_LANGUAGE_CONDITION_DESC', $product)
                    ],
                    'Referrer' => [
                        'title' => Text::_('NR_ASSIGN_REFERRER'),
                        'desc' => Text::sprintf('NR_REFERRER_URL_CONDITION_DESC', $product)
                    ],
                    'Cookie' => [
                        'title' => Text::_('NR_COOKIE'),
                        'desc' => Text::sprintf('NR_COOKIE_CONDITION_DESC', $product)
                    ],
                    'PHP' => [
                        'title' => Text::_('NR_ASSIGN_PHP'),
                        'desc' => Text::sprintf('NR_PHP_CONDITION_DESC', $product)
                    ]
                ]
            ]
        ];
    }
}