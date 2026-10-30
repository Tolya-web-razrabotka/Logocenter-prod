<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

$app  = Factory::getApplication();
$menu = $app->getMenu();

// Главная ли это страница: активный пункт меню совпадает с пунктом «по умолчанию»
$isHome = $menu->getActive() === $menu->getDefault();

// Адрес папки со статикой шаблона: /media/templates/site/logocenter
$tpl = Uri::root(true) . '/media/templates/site/' . $this->template;

$this->setMetaData('viewport', 'width=device-width, initial-scale=1');
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

<body class="<?php echo $isHome ? 'homePage' : 'innerPage'; ?>">
  <div class="wrapper">
    <div class="all_cnt">
      <jdoc:include type="modules" name="header" style="none" />

      <jdoc:include type="modules" name="main-top" style="none" />

      <?php if (!$isHome) : ?>
        <main class="pageMain">
          <div class="container">
            <jdoc:include type="modules" name="breadcrumbs" style="none" />
            <jdoc:include type="message" />
            <jdoc:include type="component" />
          </div>
        </main>
      <?php endif; ?>

      <jdoc:include type="modules" name="main-bottom" style="none" />

      <jdoc:include type="modules" name="float" style="none" />
    </div>

    <jdoc:include type="modules" name="footer" style="none" />

    <jdoc:include type="modules" name="modal" style="none" />
  </div>

  <script src="<?php echo $tpl; ?>/js/jquery-3.7.1.min.js"></script>
  <script src="<?php echo $tpl; ?>/js/slick.min.js"></script>
  <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&amp;apikey=6a0a70e3-b1fe-4c23-ab8b-779377082ed2"></script>
  <script src="<?php echo $tpl; ?>/js/main.js"></script>
</body>

</html>