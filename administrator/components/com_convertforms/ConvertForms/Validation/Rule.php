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

namespace ConvertForms\Validation;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

/**
 * Base Validation Rule Class
 * 
 * This abstract class serves as the foundation for all form validation rules.
 * Each specific validation rule should extend this class and implement its own
 * validate() method with the specific validation logic.
 */
abstract class Rule
{
    /**
     * Stores the validation error message
     *
     * @var string
     */
    protected $error;

    /**
     * The form object being validated
     *
     * @var object
     */
    protected $form;

    /**
     * The submission data to validate
     *
     * @var array
     */
    protected $submission;

    /**
     * Joomla application instance
     *
     * @var object
     */
    protected $app;

    /**
     * The alias of the rule
     *
     * @var string
     */
    protected $env;

    /**
     * Constructor
     *
     * @param  object  $form        The form object
     * @param  array   $submission  The submission data
     */
    public function __construct(&$form, $submission)
    {
        $this->form = &$form;
        $this->submission = $submission;
        $this->env = new Registry(isset($submission['env']) ? $submission['env'] : null);
        $this->app = Factory::getApplication();
    }

    /**
     * Validates and throws an exception if validation fails
     *
     * @throws \Exception
     * @return void
     */
    public function validateOrDie()
    {
        if ($this->validate() === false)
        {
            $this->throwError();
        }
    }

    /**
     * Gets the validation error message
     * If no custom error message is set, uses a language string based on the rule name
     *
     * @return string
     */
    public function getError()
    {
        if (!$this->error)
        {
            $this->setError(Text::_('COM_CONVERTFORMS_VALIDATION_' . strtoupper($this->getName()) . '_ERROR'));
        }

        return $this->error;
    }

    /**
     * Sets a custom error message
     *
     * @param  string  $error  The error message
     * @return void
     */
    public function setError($error)
    {
        $this->error = $error;
    }

    /**
     * Throws an exception with the validation error message
     * The message is HTML escaped to prevent XSS attacks
     *
     * @throws \Exception
     * @return void
     */
    public function throwError()
    {
        $message = htmlspecialchars($this->getError(), ENT_QUOTES, 'UTF-8');

        \ConvertForms\Spam\Helper::error($message, 'validation.' . $this->getName());
    }

    /**
     * Gets the rule name from the class name
     * For example, TimeToSubmit becomes timetosubmit
     *
     * @return string
     */
    public function getName()
    {
        $class_parts = explode('\\', get_called_class());
        return strtolower(end($class_parts));
    }

    protected function getFormRegistry()
    {
        return new Registry($this->form);
    }

    public function onFormBeforeRender()
    {
        if (!$this->isEnabled())
        {
            return;
        }

        $boxAtts = isset($this->form['containerAtts']) ? $this->form['containerAtts'] : [];

        // Let's add some data attributes for the client-side script.
        $this->form['containerAtts'] = array_merge($boxAtts, [
            'data-cf-' . $this->alias => ''
        ]);
    }

    abstract public function isEnabled();
    abstract public function validate();
}
