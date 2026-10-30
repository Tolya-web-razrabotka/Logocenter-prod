<?php
defined('_JEXEC') or die;
return array (
  'PLG_TASK_UPDATENOTIFICATION' => 'Задача - Отправка письма о новой версии Joomla',
  'PLG_TASK_UPDATENOTIFICATION_END' => 'Письмо для администратора отправлено',
  'PLG_TASK_UPDATENOTIFICATION_EMAIL_DESC' => 'Введите один или несколько адресов электронной почты для отправки письма о новой версии Joomla. Если ни один из адресов не будет принадлежать группе суперпользователей или поле оставить пустым, письмо будет отправлено всем суперпользователям сайта.',
  'PLG_TASK_UPDATENOTIFICATION_EMAIL_LBL' => 'E-mail администратора',
  'PLG_TASK_UPDATENOTIFICATION_SEND_TITLE' => 'Отправка письма о новой версии Joomla',
  'PLG_TASK_UPDATENOTIFICATION_SEND_DESC' => 'Задача для отправки письма по электронной почте о новой версии Joomla.',
  'PLG_TASK_UPDATENOTIFICATION_LANGUAGE_OVERRIDE_DESC' => 'Выберите язык письма о новой версии Joomla. Если установлено <strong>Автоматически</strong>, письмо будет отправлено на языке сайта.',
  'PLG_TASK_UPDATENOTIFICATION_LANGUAGE_OVERRIDE_LBL' => 'Язык письма',
  'PLG_TASK_UPDATENOTIFICATION_LANGUAGE_OVERRIDE_NONE' => 'Автоматически',
  'PLG_TASK_UPDATENOTIFICATION_EMAIL_BODY' => 'Здравствуйте!\\n\\nДля сайта {SITENAME} по адресу {URL} доступна новая версия Joomla.\\n\\nИспользуемая версия: {CURVERSION}\\nНовая версия: {NEWVERSION}\\n\\nИнформация о релизе:\\n{RELEASENEWS}\\n\\nДля установки новой версии перейдите по ссылке:\\n{LINK}\\n\\nПисьмо автоматически отправлено задачей "Отправка письма о новой версии Joomla". Если вы не хотите получать письма, отключите плагин "Задача - Отправка письма о новой версии Joomla".\\n\\nЕсли вы не понимаете, что такое Joomla и что необходимо сделать, пожалуйста, обратитесь к администратору, который создал или поддерживает сайт. Если вы являетесь администратором сайта, обратите внимание, что плагин может быть включен автоматически при следующем обновлении Joomla.',
  'PLG_TASK_UPDATENOTIFICATION_EMAIL_SUBJECT' => 'Новая версия Joomla для {SITENAME}',
  'PLG_TASK_UPDATENOTIFICATION_MAIL_MAIL_DESC' => 'Письмо для администратора сайта о доступности новой версии Joomla',
  'PLG_TASK_UPDATENOTIFICATION_MAIL_MAIL_TITLE' => 'Новая версия Joomla',
  'PLG_TASK_UPDATENOTIFICATION_XML_DESCRIPTION' => 'Плагин для отправки письма по электронной почте о доступности новой версии Joomla.',
);
