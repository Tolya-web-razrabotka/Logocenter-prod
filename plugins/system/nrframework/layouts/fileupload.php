<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

extract($displayData);
$label = isset($label) ? trim(strip_tags((string) $label)) : '';
?>
<div
    id="<?php echo $id ?>"
    role="group"
    <?php
        if (!empty($hidelabel) && !empty($label))
        {
            echo 'aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"';
        }
        elseif (!empty($label_id))
        {
            echo 'aria-labelledby="' . $label_id . '"';
        }
    ?>
    <?php if (!empty($required)) : ?>aria-required="true"<?php endif; ?>
	data-name="<?php echo $name ?>"
	data-maxfilesize="<?php echo $max_file_size ?>"
	data-maxfiles="<?php echo $limit_files ?>"
	data-upload_types="<?php echo $upload_types ?>"
	data-preview="<?php echo $preview; ?>"
    data-ajax-url="<?php echo $ajax_url; ?>"
	data-value="<?php echo $value ? htmlspecialchars(json_encode($value), ENT_COMPAT, 'UTF-8') : ''; ?>"
	<?php foreach ($dataAttributes as $key => $value): ?>
		data-<?php echo $key; ?>="<?php echo $value; ?>"
	<?php endforeach; ?>
	class="tf-file-upload-editor<?php echo !empty($class) ? ' ' . $class : ''; ?>">
	<div class="dz-message">
		<svg width="44" height="30" viewBox="0 0 44 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.3125 29.875C7.66156 29.875 5.40517 28.9531 3.54331 27.1093C1.6811 25.2659 0.75 23.0125 0.75 20.3492C0.75 17.8321 1.61629 15.6499 3.34887 13.8026C5.08181 11.9556 7.11703 10.9625 9.45453 10.8233C9.93124 7.7286 11.3546 5.17188 13.7247 3.15313C16.0948 1.13438 18.8532 0.125 22 0.125C25.5519 0.125 28.5648 1.3621 31.0387 3.83631C33.5129 6.31017 34.75 9.32306 34.75 12.875V15H36.0579C38.093 15.0655 39.8004 15.8126 41.1802 17.2413C42.5601 18.6704 43.25 20.4025 43.25 22.4375C43.25 24.5218 42.545 26.2823 41.1351 27.7192C39.7252 29.1564 37.9782 29.875 35.8943 29.875H24.3704C23.3922 29.875 22.5755 29.5474 21.9203 28.8922C21.2651 28.237 20.9375 27.4203 20.9375 26.4421V14.1091L16.475 18.5472L14.971 17.0841L22 10.0551L29.029 17.0841L27.525 18.5472L23.0625 14.1091V26.4421C23.0625 26.7693 23.1987 27.0691 23.471 27.3415C23.7434 27.6138 24.0432 27.75 24.3704 27.75H35.8125C37.3 27.75 38.5573 27.2365 39.5844 26.2094C40.6115 25.1823 41.125 23.925 41.125 22.4375C41.125 20.95 40.6115 19.6927 39.5844 18.6656C38.5573 17.6385 37.3 17.125 35.8125 17.125H32.625V12.875C32.625 9.93542 31.5891 7.42969 29.5172 5.35781C27.4453 3.28594 24.9396 2.25 22 2.25C19.0604 2.25 16.5547 3.28594 14.4828 5.35781C12.4109 7.42969 11.375 9.93542 11.375 12.875H10.2307C8.25833 12.875 6.53921 13.601 5.07331 15.0531C3.60777 16.5052 2.875 18.2583 2.875 20.3125C2.875 22.3667 3.60104 24.1198 5.05313 25.5719C6.50521 27.024 8.25833 27.75 10.3125 27.75H15.625V29.875H10.3125Z" fill="currentColor"/></svg>
		<span class="drag-and-drop-text">
			<?= Text::_('NR_DRAG_AND_DROP_FILES_OR_BROWSE') ?>
			<span class="tf-upload-browse">
				<?= Text::_('NR_BROWSE') ?>
			</span>
		</span>
        <?php if ($max_file_size > 0): ?>
		<span class="max-file-size"><?php echo Text::sprintf('NR_MAX_FILE_SIZE', $max_file_size . 'MB') ?></span>
        <?php endif; ?>
	</div>
	<div class="tf-file-upload-editor--items"></div>
</div>
