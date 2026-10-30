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

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Email Cloak Protection Plugin for Convert Forms
 *
 * Joomla's core Email Cloak plugin rewrites any email address it finds in content,
 * including bare addresses that live inside form controls (input/option/textarea
 * values and text). When that happens on a Convert Forms form it breaks the field
 * value: the address is replaced by a JavaScript cloak snippet, so the control no
 * longer submits or displays the real address.
 *
 * How it works:
 * 1. Every email address inside a form control has its "@" rewritten to the "&#64;"
 *    HTML entity. Core Email Cloak keys on a literal "@", so an encoded address no
 *    longer matches any of its patterns and is left alone.
 * 2. The browser decodes "&#64;" back to "@" while parsing, so the control still
 *    displays and submits the real address. Nothing has to be restored server side.
 *
 * Because the encoded markup is the final markup, there is no render time revert to
 * run, which means the result is correct whether the page is freshly rendered or
 * served from Joomla's module/page cache. Emails outside form controls (author
 * placed mailto links) are untouched and are still cloaked by the core plugin.
 */
class PlgConvertFormsToolsEmailCloak extends CMSPlugin
{
    /**
     * Special marker used to disable Joomla's Email Cloak plugin processing.
     * This is appended to form content to prevent unwanted email cloaking of
     * addresses introduced by other content plugins before Email Cloak runs.
     */
    private const EMAIL_CLOAK_OFF = '{emailcloak=off}';

    /**
     * Pattern that matches an email address inside the form markup.
     */
    private const EMAIL_PATTERN = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';

    /**
     * Stand in for "@" while the DOM is serialized, swapped for the "&#64;" entity
     * afterwards. DOMDocument would escape a literal "&#64;" into "&amp;#64;", so the
     * entity is inserted only on the serialized string. The placeholder holds no
     * HTML special characters (so DOMDocument leaves it verbatim) and is unique enough
     * not to collide with real content.
     */
    private const AT_PLACEHOLDER = 'cf_emailcloak_at_9f3c1b7e';

    /**
     * Application object
     *
     * @var    \Joomla\CMS\Application\CMSApplication
     */
    protected $app;

    /**
     * Protects email addresses found in the form HTML by encoding them.
     * This prevents the core emailcloak plugin from processing these emails.
     *
     * @param   string  &$html  The form HTML content
     *
     * @return  void
     */
    public function onConvertFormsFormAfterRender(&$html)
    {
        if (!$this->shouldIrun())
        {
            return;
        }

        // Let's protect the emails in the form HTML
        // This is the first pass, where we protect the emails before any content plugins are applied.
        $html = $this->protectEmails($html);

        // After this event, Convert Forms triggers Joomla's onContentPrepare event, which activates content plugins, including EmailCloak.
        // If a content plugin runs before EmailCloak and introduces additional email addresses into the form, those emails will be cloaked.
        // To prevent this, we append {emailcloak=off} to disable the EmailCloak plugin for the form content.
        $html .= self::EMAIL_CLOAK_OFF;
    }

    /**
     * Additional protection pass after content plugins have run.
     * This is needed because content plugins might introduce new email addresses.
     *
     * @param   string  &$html  The form HTML content
     *
     * @return  void
     */
    public function onConvertFormsFormAfterContentPrepare(&$html)
    {
        if (!$this->shouldIrun())
        {
            return;
        }

        $html = $this->protectEmails($html);

        // Finally, give the chance to Email Cloak plugin to cloak remaining (unprotected) email addresses.
        // We skip the Joomla Articles view, as the Content Prepare event is triggered by default.
        $dontRun = $this->app->input->get('option') == 'com_content' && $this->app->input->get('view') == 'article';

        if ($dontRun)
        {
            return;
        }

        $html = HTMLHelper::_('content.prepare', $html, null, 'convertforms-emailcloak');
    }

    /**
     * Checks if the plugin should run based on various conditions:
     * - Must be in frontend
     * - Email cloak plugin must be enabled
     * - Convert Forms component must be installed
     *
     * @return  boolean
     */
    private function shouldIrun()
    {
        if (!$this->app->isClient('site'))
        {
            return;
        }

        if (!PluginHelper::isEnabled('content', 'emailcloak'))
        {
            return;
        }

        // Initialize Convert Forms Library
        if (!@include_once(JPATH_ADMINISTRATOR . '/components/com_convertforms/autoload.php'))
        {
            return;
        }

        return true;
    }

    /**
     * Encodes email addresses so the core emailcloak plugin leaves them alone.
     * Only processes emails found in specific form elements and their attributes.
     * Uses DOM parsing for reliable HTML manipulation.
     *
     * @param   string  $html  The HTML content to process
     *
     * @return  string  The processed HTML with encoded email addresses
     */
    private function protectEmails($html)
    {
        // Quick check for @ character before expensive DOM parsing
        if (strpos($html, '@') === false)
        {
            return $html;
        }

        try
        {
            // Use DOMDocument to parse HTML
            $dom = new \DOMDocument();

            $html = iconv('UTF-8', 'UTF-8', $html);
            $html = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

            // Prevent HTML5 errors
            libxml_use_internal_errors(true);
            $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            // Define elements to process
            $elements = [
                'input',
                'textarea',
                'option',
            ];

            foreach ($elements as $tag)
            {
                $nodes = $dom->getElementsByTagName($tag);

                foreach ($nodes as $node)
                {
                    // Process all attributes dynamically
                    if ($node->hasAttributes())
                    {
                        foreach ($node->attributes as $attribute)
                        {
                            $value = $attribute->value;
                            $newValue = $this->encodeEmails($value);

                            if ($value !== $newValue)
                            {
                                $attribute->value = $newValue;
                            }
                        }
                    }

                    // Process text content for textarea and option elements
                    if (in_array($tag, ['textarea', 'option']))
                    {
                        $value = $node->nodeValue;
                        $newValue = $this->encodeEmails($value);

                        if ($value !== $newValue)
                        {
                            $node->nodeValue = $newValue;
                        }
                    }
                }
            }

            $html = $dom->saveHTML();

            // Swap the placeholder for the real HTML entity now that DOMDocument is done.
            // The result is the final markup: the browser turns "&#64;" back into "@", so
            // the control submits and displays the real address, while the core emailcloak
            // plugin (which needs a literal "@") no longer matches it. No later restore step
            // is required, so this stays correct even when the page is served from cache.
            return str_replace(self::AT_PLACEHOLDER, '&#64;', $html);

        } catch (\Throwable $th)
        {
            // Fallback to return original content if DOM parsing fails
            return $html;
        }
    }

    /**
     * Within every email address in the given text, replaces the "@" with a
     * serialization safe placeholder (later swapped for the "&#64;" entity). Only the
     * "@" is touched, so the rest of the address round trips unchanged.
     *
     * @param   string  $text  The text content to process
     *
     * @return  string  The processed text with the "@" of each address encoded
     */
    private function encodeEmails($text)
    {
        return preg_replace_callback(self::EMAIL_PATTERN, function ($match)
        {
            return str_replace('@', self::AT_PLACEHOLDER, $match[0]);
        }, $text);
    }
}
