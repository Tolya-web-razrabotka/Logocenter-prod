<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\CMS\Document\ErrorDocument $this */

$app       = Factory::getApplication();
$tpl       = Uri::root(true) . '/media/templates/site/' . $this->template;
$errorCode = (int) $this->error->getCode() ?: 500;
$is404     = $errorCode === 404;

// Модули нельзя выводить, если ошибка случилась до полной загрузки Joomla
$renderModules = $app->getIdentity() && $app->getLanguage();

$this->setTitle(($is404 ? 'Страница не найдена' : 'Ошибка ' . $errorCode) . ' — Логоцентр');
$this->setMetaData('viewport', 'width=device-width, initial-scale=1');
$this->setMetaData('robots', 'noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>">
<head>
	<jdoc:include type="metas" />
	<link rel="icon" href="<?php echo $tpl; ?>/images/favicon/favicon.svg" type="image/svg+xml">
	<link rel="icon" href="<?php echo $tpl; ?>/images/favicon/favicon-32.png" type="image/png" sizes="32x32">
	<link rel="apple-touch-icon" href="<?php echo $tpl; ?>/images/favicon/apple-touch-icon.png">
	<meta name="theme-color" content="#10293C">
	<jdoc:include type="styles" />
	<link rel="stylesheet" href="<?php echo $tpl; ?>/css/bootstrap.css">
	<link rel="stylesheet" href="<?php echo $tpl; ?>/css/normalize.css">
	<link rel="stylesheet" href="<?php echo $tpl; ?>/css/slick.css">
	<link rel="stylesheet" href="<?php echo $tpl; ?>/css/style.css">
	<jdoc:include type="scripts" />
</head>
<body class="innerPage">
	<div class="wrapper">
		<div class="all_cnt">
			<?php if ($renderModules) : ?>
				<jdoc:include type="modules" name="header" style="none" />
			<?php endif; ?>

			<main class="pageMain">
				<div class="container">
					<div class="notFound">
						<div class="notFoundInfo">
							<div class="notFoundCode"><?php echo $errorCode; ?></div>
							<?php if ($is404) : ?>
								<h1 class="notFoundTitle">Страница не найдена</h1>
								<div class="notFoundText">Возможно, она была удалена или в адресе допущена ошибка. <br>Перейдите на главную или выберите нужный раздел каталога.</div>
							<?php else : ?>
								<h1 class="notFoundTitle">Что-то пошло не так</h1>
								<div class="notFoundText">На сайте произошла ошибка. Попробуйте обновить страницу <br>или вернитесь на главную.</div>
							<?php endif; ?>
							<div class="notFoundBtns">
								<a href="<?php echo Uri::root(true); ?>/" class="btnOrange notFoundBtn">На главную<span class="btnArrow"><img src="<?php echo $tpl; ?>/images/svg/arrow_white_long.svg" alt=""></span></a>
								<a href="<?php echo Uri::root(true); ?>/katalog/arenda-spectehniki" class="sectionLink">Аренда спецтехники<img src="<?php echo $tpl; ?>/images/svg/arrow_orange_long.svg" alt=""></a>
							</div>
						</div>
						<img class="notFoundImg" src="<?php echo $tpl; ?>/images/consult/excavator.png" alt="">
					</div>
				</div>
			</main>

			<?php if ($renderModules) : ?>
				<jdoc:include type="modules" name="float" style="none" />
			<?php endif; ?>
		</div>
		<?php if ($renderModules) : ?>
			<jdoc:include type="modules" name="footer" style="none" />
			<jdoc:include type="modules" name="modal" style="none" />
		<?php endif; ?>
	</div>
	<script src="<?php echo $tpl; ?>/js/jquery-3.7.1.min.js"></script>
	<script src="<?php echo $tpl; ?>/js/slick.min.js"></script>
	<script src="<?php echo $tpl; ?>/js/main.js"></script>
</body>
</html>