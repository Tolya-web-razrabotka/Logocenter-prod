<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 * 
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class JFormFieldTextList extends TextField
{
    /**
     * Method to get a list of options for a list input.
     *
     * @return      array           An array of options.
     */
    protected function getInput()
    {
        $html = '<div data-id="' . $this->id . '" class="input_item_list "><div class="input_items">';

        if (!empty($this->value))
        {
            // Keep the original keys: they are the index the download task uses to
            // pick the file out of the stored array.
            $values = array_filter((array) $this->value);

            foreach ($values as $index => $value)
            {
                $html .= '<div>' . $this->getInputHTML($value, (int) $index) . '</div>';
            }
        }

        $html .= '</div>';

        // Read-only lists render no add/remove controls at all: an input the save
        // ignores must not look editable.
        if (!$this->isReadonly())
        {
            $html .= '<div id="' . $this->id . '_tmpl" class="input_item_list_tmpl"> ' . $this->getInputHTML('') . '</div>';
            $html .= '<button class="btn btn-success add_item"><span class="icon-plus"></span></button>';
        }

        $html .= '</div>';

        static $run;

        if (!$run)
        {
            $this->addMedia();
            $run = true;
        }

        return $html;
    }

    private function isReadonly()
    {
        return !empty($this->element['readonly']);
    }

    private function getInputHTML($value = '', $index = 0)
    {
        $viewUrlEnabled = !empty($this->element['view_url']);

        $html = '
        <input type="text" name="' . $this->name . '[]" id="' . $this->id . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '" class="form-control input-xlarge" placeholder="' . $this->hint . '"' . ($this->isReadonly() ? ' readonly' : '') . '>';

        if (!$this->isReadonly())
        {
            $html .= '
        <button class="btn btn-sm btn-danger remove_item"><span class="icon-minus"></span></button>';
        }

        if ($viewUrlEnabled && !empty($value))
        {
            if ($fileUrl = $this->getViewURL($value, $index))
            {
                $html .= '
                    <a href="' . htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm btn-info" style="margin-left:5px;">
                        <span class="icon-eye"></span>
                    </a>';
            }
        }

        return $html;
    }

    /**
     * Build the URL that opens a stored file.
     *
     * Files inside the site keep their direct URL. Files stored outside it have none,
     * so they go through the ACL-checked administrator download task instead.
     *
     * @param   string  $value  The stored submission value
     * @param   int     $index  Which file within the field
     *
     * @return  string  The URL, or '' when the file cannot be resolved
     */
    private function getViewURL($value, $index)
    {
        if (!class_exists('ConvertForms\\FileStorage'))
        {
            return '';
        }

        if (\ConvertForms\FileStorage::isExternal($value))
        {
            $submission_id = (int) $this->element['submission_id'];

            if (!$submission_id || \ConvertForms\FileStorage::toAbsolutePath($value) === '')
            {
                return '';
            }

            return \ConvertForms\FileStorage::adminDownloadUrl($submission_id, $this->fieldname, $index);
        }

        $ds = DIRECTORY_SEPARATOR;

        if (!is_file(implode($ds, [JPATH_ROOT, ltrim($value, $ds)])))
        {
            return '';
        }

        return implode($ds, [rtrim(Uri::root(), $ds), ltrim($value, $ds)]);
    }

    private function addMedia()
    {
        // Add CSS
        Factory::getDocument()->addStyleDeclaration('
            .input_item_list_tmpl {
                display:none;
            }
            .input_item_list div div {
                margin-bottom:5px;
                display:flex;
                display:-webkit-flex;
                align-items:center;
                -webkit-align-items:center;
            }
            .input_item_list input {
                margin-right:5px;
            }
            .input_item_list *[class^="icon"] {
                margin:0;
                pointer-events: none;
            }
        ');

        // Add Script
        Factory::getDocument()->addScriptDeclaration('
            document.addEventListener("DOMContentLoaded", function(e) {
                var els = document.querySelectorAll(".input_item_list");

                els.forEach(function(el) {

                    // A read-only list renders no template and no add/remove buttons.
                    var el_tmpl = el.querySelector(".input_item_list_tmpl");

                    if (!el_tmpl) {
                        return;
                    }

                    el_tmpl.querySelector("input").removeAttribute("value");
                    document.body.appendChild(el_tmpl);

                    el.addEventListener("click", function(e) {
                        
                        // Remove item action
                        if (e.target.classList.contains("remove_item")) {
                            e.preventDefault();

                            var button = e.target;
                            var container = button.closest(".input_items");

                            container.removeChild(button.parentNode);
                        }

                        // Add new item action
                        if (e.target.classList.contains("add_item")) {
                            e.preventDefault();

                            var el_tmpl = document.getElementById(el.dataset.id + "_tmpl");

                            var cln = el_tmpl.cloneNode(true);

                            cln.removeAttribute("id");

                            cln.classList.remove("input_item_list_tmpl");
                            
                            el.querySelector(".input_items").appendChild(cln);
                        }
                    });
                });
            });
        ');
    }
}