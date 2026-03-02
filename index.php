<?php
$installLockFile = __DIR__ . '/install/install.lock';

if (!file_exists($installLockFile)) {
     $isInstallPage = strpos($_SERVER['PHP_SELF'], 'install/') !== false;
    
    if (!$isInstallPage) {
      $installUrl = 'install/';
        header("Location: $installUrl");
        exit;
    }
}

require_once 'config.php';
require_once 'common/TemplateManager.php';

// 渲染用户中心模板
TemplateManager::renderHome('index.php');