<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\SmartTags;

defined('_JEXEC') or die('Restricted access');

use Joomla\Registry\Registry;

abstract class SmartTag
{
	/**
	 * Factory Class
	 *
	 * @var object
	 */
    protected $factory;

    /**
	 * Joomla Application object
	 *
	 * @var object
     */
    protected $app;

    /**
	 * Joomla Document
	 *
	 * @var object
     */
    protected $doc;

    /**
     * Useful data used by a Smart Tag
     * 
     * @var  array
     */
    protected $data;

    /**
     * Parsed Options
     * 
     * @var  array
     */
    protected $parsedOptions;

    /**
     * Smart Tags Configuration Options
     * 
     * @var  array
     */
    protected $options;

    /**
     * Indicates whether this Smart Tag is a Pro-only feature
     *
     * @var boolean
     */
    public $proOnly = false;

    public function __construct($factory = null, $options = null)
    {
        if (!$factory)
        {
            $factory = new \Tassos\Framework\Factory();
        }
        $this->factory = $factory;
        
		$this->app = $this->factory->getApplication();
        $this->doc = $this->factory->getDocument();

        $this->parsedOptions = isset($options['options']) ? $options['options'] : new Registry();

        $this->options = $options;
    }

    /**
     * Set the data
     * 
     * @param   array  $data
     * 
     * @return  void
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * This method runs before replacements and determines whether the class can be executed and do replacements or not.
     * 
     * THE PROBLEM: 
     * 
     * Let's say we have a bunch of Smart Tags in a namespaced folder and we register them using the register() method. 
     * The Smart Tags include, Foo and Bar. Let's say our replacement subject is: 'lorem {foo.x} ipsum {foo.y} lorem ipsum {bar.x}' 
     * and we'd like to replace {foo.x} and {foo.y} and leave {bar.x} untouched. Right now this is not possible. 
     * All 3 Smart Tags will be replaced in the subject because all classes are already registered.
     * 
     * This problem occurs also in Convert Forms during form rendering. When a form is using Calculations, it's very likely 
     * a calculation formula in the form {field.XXX} + {field.YYY} is included in the form's HTML layout. 
     * In Convert Forms, Smart Tag replacements run during page load. Since we have a Smart Tag for Fields {field.XXX} already registered, 
     * the Smart Tags found in the Calculations formula will be replaced by empty space (there's no submitted data yet) breaking Calculation. 
     * 
     * We need a way to determine during runtime whether a Smart Tag can run or not.
     * 
     * We could write a new method so 3rd party extension can register individual classes conditionally but this would add more work on the extension's side.
     * 
     * @return boolean 
     */
    public function canRun()
    {
        return true;
    }

    /**
     * This helps us easily call a Smart Tag inside another Smart Tag. 
     * The best example is the Article Smart Tag where we want to retrieve information about the author. 
     * Instead of re-writing the logic to read author's data, we call the User Smart Tag.
     *
     * @param   string  $smartTagName   The Smart Tag name. eg: User
     * @param   string  $ke             The property we want to get. Eg: 'firstname'
     * @param   array   $options        The options to construct the class
     *
     * @return  mixed   String on success, null on failure
     */
    public function redirect($smartTagName, $key, $options)
    {
		$smartTagClass = '\Tassos\\Framework\\SmartTags\\' . $smartTagName;
		
		$class = new $smartTagClass($this->factory, $options);

        if (method_exists($class, 'get' . $key))
        {
            return $class->{'get' . $key}();
        }

        if (method_exists($class, 'fetchValue'))
        {
            return $class->fetchValue($key);
        }
    }
}