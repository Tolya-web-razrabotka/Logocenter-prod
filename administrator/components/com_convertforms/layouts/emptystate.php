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

extract($displayData);

$icon       = $icon ?? 'icon-list';
$title      = $title ?? '';
$content    = $content ?? '';
$button     = $button ?? '';
$url        = $url ?? '';
$buttonIcon = $buttonIcon ?? 'icon-plus';
?>
<div class="cf-empty-state">
    <div class="cf-empty-state__icon"><span class="<?php echo $icon; ?>" aria-hidden="true"></span></div>
    <h2 class="cf-empty-state__title"><?php echo htmlspecialchars($title); ?></h2>
    <p class="cf-empty-state__text"><?php echo htmlspecialchars($content); ?></p>
    <?php if ($button && $url) : ?>
        <a href="<?php echo $url; ?>" class="btn btn-primary cf-empty-state__btn">
            <span class="<?php echo $buttonIcon; ?>" aria-hidden="true"></span>
            <?php echo htmlspecialchars($button); ?>
        </a>
    <?php endif; ?>
</div>
