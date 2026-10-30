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

defined('_JEXEC') or die;
?>
<div class="convertforms-submissions list">
	<?php if ($this->params->get('show_page_heading')) { ?>
		<h1><?php echo $this->params->get('page_heading', $this->params->get('page_title')) ?></h1>
	<?php } ?>

	<?php
	if ($this->params->get('show_search', true) && $this->searchbar && (count($this->submissions) || (!count($this->submissions) && $this->state->get('filter.search'))))
	{
		echo $this->searchbar;
	}
	?>

	<?php echo $this->loadTemplate(count($this->submissions) ? 'list' : 'noresults'); ?>
</div>