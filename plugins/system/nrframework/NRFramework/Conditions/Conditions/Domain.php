<?php

/**
 * @author          Tassos.gr <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Conditions\Conditions;

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Tassos\Framework\Conditions\Condition;
use Tassos\Framework\Functions;

class Domain extends Condition
{
    /**
     * Normalise the repeater selection into a flat array of lowercase domain strings
     * with the www. prefix stripped.
     *
     * @return array
     */
    public function prepareSelection()
    {
        $selection = Functions::makeArray($this->getSelection());

        return array_values(
            array_filter(
                array_map([$this, 'normalizeDomain'], $selection)
            )
        );
    }

    /**
     * Returns the current website domain, lowercased and without a www. prefix.
     *
     * @return string
     */
    public function value()
    {
        return $this->stripWww(strtolower(Uri::getInstance()->getHost()));
    }

    /**
     * Normalizes a domain or URL string into a bare lowercase hostname without
     * scheme, path, query string, fragment, or www. prefix.
     *
     * Examples:
     *   https://www.tassos.gr/foo?bar=1  → tassos.gr
     *   www.tassos.gr                    → tassos.gr
     *   tassos.gr                        → tassos.gr
     *
     * @param   string  $domain
     *
     * @return  string
     */
    private function normalizeDomain($domain)
    {
        $domain = strtolower(trim($domain));

        // If a scheme is present parse_url can extract the host directly.
        // If not, prepend a dummy scheme so parse_url works reliably.
        if (strpos($domain, '://') === false)
        {
            $domain = 'https://' . $domain;
        }

        $host = parse_url($domain, PHP_URL_HOST);

        // Fall back to the raw value if parse_url fails.
        if (empty($host))
        {
            return '';
        }

        return $this->stripWww($host);
    }

    /**
     * Strips a leading www. prefix from a (already lowercased) hostname.
     *
     * @param   string  $host
     *
     * @return  string
     */
    private function stripWww($host)
    {
        if (strncmp($host, 'www.', 4) === 0)
        {
            $host = substr($host, 4);
        }

        return $host;
    }
}
