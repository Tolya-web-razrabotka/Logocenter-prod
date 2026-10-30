<?php

/**
 *  @author          Tassos Marinos <info@tassos.gr>
 *  @link            https://www.tassos.gr
 *  @copyright       Copyright © 2024 Tassos All Rights Reserved
 *  @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Conditions\Conditions\Geo;

defined('_JEXEC') or die;

class City extends GeoBase
{
    /**
     * Shortcode aliases for this Condition
     */
    public static $shortcode_aliases = ['geo.city'];
    
    /**
     *  Returns the assignment's value
     * 
     *  @return string City name
     */
	public function value()
	{
        if (!$this->geo)
        {
            return;
        }
        
		return $this->geo->getCity();
	}
}