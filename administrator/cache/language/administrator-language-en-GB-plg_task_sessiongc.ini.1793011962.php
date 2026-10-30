<?php
defined('_JEXEC') or die;
return array (
  'PLG_TASK_SESSIONGC' => 'Task - Session Data Purge',
  'PLG_TASK_SESSIONGC_DESC' => 'Task Plugin that purges expired data and metadata depending on the session handler set in Global Configuration.',
  'PLG_TASK_SESSIONGC_ENABLE_SESSION_GC_DESC' => 'When enabled, this plugin will attempt to purge expired data.',
  'PLG_TASK_SESSIONGC_ENABLE_SESSION_GC_LABEL' => 'Enable Session Data Cleanup',
  'PLG_TASK_SESSIONGC_ENABLE_SESSION_METADATA_GC_DESC' => 'When enabled, this plugin will clean optional session metadata from the database. Note that this operation will not run when the database handler is in use as that data is cleared as part of the Session Data Cleanup operation.',
  'PLG_TASK_SESSIONGC_ENABLE_SESSION_METADATA_GC_LABEL' => 'Enable Session Metadata Cleanup',
  'PLG_TASK_SESSIONGC_TITLE' => 'Session Data Purge',
  'PLG_TASK_SESSIONGC_XML_DESCRIPTION' => 'Task Plugin that purges expired data and metadata depending on the session handler set in Global Configuration.',
);
