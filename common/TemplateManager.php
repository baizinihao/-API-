<?php
class TemplateManager {
    private static $pdo = null;
    
    private static function getDb() {
        if (self::$pdo === null) {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
        return self::$pdo;
    }
    
    // 首页模板管理
    public static function getActiveHomeTemplate() {
        try {
            $stmt = self::getDb()->prepare("SELECT folder FROM site_home_templates WHERE is_active = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            return $result ? $result['folder'] : 'default';
        } catch (Exception $e) {
            return 'default';
        }
    }
    
    public static function getAllHomeTemplates() {
        try {
            $stmt = self::getDb()->query("SELECT * FROM site_home_templates ORDER BY is_active DESC, name ASC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    public static function setActiveHomeTemplate($id) {
        try {
            self::getDb()->beginTransaction();
            
            // 重置所有激活状态
            $stmt = self::getDb()->prepare("UPDATE site_home_templates SET is_active = 0");
            $stmt->execute();
            
            // 设置新的激活模板
            $stmt = self::getDb()->prepare("UPDATE site_home_templates SET is_active = 1 WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            self::getDb()->commit();
            return $success;
        } catch (Exception $e) {
            if (self::$pdo->inTransaction()) {
                self::$pdo->rollBack();
            }
            return false;
        }
    }
    
    public static function addHomeTemplate($name, $folder, $description = '') {
        try {
            $stmt = self::getDb()->prepare("
                INSERT INTO site_home_templates 
                (name, folder, description) 
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$name, $folder, $description]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public static function deleteHomeTemplate($id) {
        try {
            $stmt = self::getDb()->prepare("DELETE FROM site_home_templates WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    // 用户中心模板管理
    public static function getActiveUserTemplate() {
        try {
            $stmt = self::getDb()->prepare("SELECT folder FROM site_user_templates WHERE is_active = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            return $result ? $result['folder'] : 'default';
        } catch (Exception $e) {
            return 'default';
        }
    }
    
    public static function getAllUserTemplates() {
        try {
            $stmt = self::getDb()->query("SELECT * FROM site_user_templates ORDER BY is_active DESC, name ASC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    public static function setActiveUserTemplate($id) {
        try {
            self::getDb()->beginTransaction();
            
            // 重置所有激活状态
            $stmt = self::getDb()->prepare("UPDATE site_user_templates SET is_active = 0");
            $stmt->execute();
            
            // 设置新的激活模板
            $stmt = self::getDb()->prepare("UPDATE site_user_templates SET is_active = 1 WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            self::getDb()->commit();
            return $success;
        } catch (Exception $e) {
            if (self::$pdo->inTransaction()) {
                self::$pdo->rollBack();
            }
            return false;
        }
    }
    
    public static function addUserTemplate($name, $folder, $description = '') {
        try {
            $stmt = self::getDb()->prepare("
                INSERT INTO site_user_templates 
                (name, folder, description) 
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$name, $folder, $description]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public static function deleteUserTemplate($id) {
        try {
            $stmt = self::getDb()->prepare("DELETE FROM site_user_templates WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    // 模板路径和URL相关方法
    public static function getHomeTemplatePath() {
        return __DIR__ . '/../../template/home/' . self::getActiveHomeTemplate() . '/';
    }
    
    public static function getUserTemplatePath() {
        return __DIR__ . '/../../template/user/' . self::getActiveUserTemplate() . '/';
    }
    
    public static function getHomeTemplateUrl() {
        return '/template/home/' . self::getActiveHomeTemplate() . '/';
    }
    
    public static function getUserTemplateUrl() {
        return '/template/user/' . self::getActiveUserTemplate() . '/';
    }
    
    public static function renderUser($templateFile) {
        // 先执行模板访问检查
        self::enforceActiveTemplate('user');
        
        // 获取网站真实根目录
        $baseDir = realpath(__DIR__ . '/..');
        
        $activeTemplate = self::getActiveUserTemplate();
        $templatePath = $baseDir . '/template/user/' . $activeTemplate . '/' . $templateFile;
        $defaultPath = $baseDir . '/template/user/default/' . $templateFile;
        
        // 优先使用激活模板
        if (file_exists($templatePath)) {
            include $templatePath;
            return;
        }
        
        // 回退到默认模板
        if (file_exists($defaultPath)) {
            include $defaultPath;
            return;
        }
        
        // 获取所有可用模板用于错误提示
        $availableTemplates = glob($baseDir . '/template/user/*', GLOB_ONLYDIR);
        
        $error = "模板加载失败\n\n";
        $error .= "尝试加载: ".htmlspecialchars($templateFile)."\n";
        $error .= "搜索路径:\n";
        $error .= "- 激活模板: ".htmlspecialchars($templatePath)."\n";
        $error .= "- 默认模板: ".htmlspecialchars($defaultPath)."\n\n";
        $error .= "当前存在的模板文件夹:\n";
        $error .= implode("\n", array_map('htmlspecialchars', $availableTemplates))."\n\n";
        $error .= "建议检查:\n";
        $error .= "1. 数据库site_user_templates表中的folder字段值\n";
        $error .= "2. template/user/目录下的文件夹名称\n";
        $error .= "3. 文件权限(确保www-data可读)";
        
        throw new Exception($error);
    }
    
    public static function renderHome($templateFile) {
        // 先执行模板访问检查
        self::enforceActiveTemplate('home');
        
        // 获取网站真实根目录
        $baseDir = realpath(__DIR__ . '/..');
        
        $activeTemplate = self::getActiveHomeTemplate();
        $templatePath = $baseDir . '/template/home/' . $activeTemplate . '/' . $templateFile;
        $defaultPath = $baseDir . '/template/home/default/' . $templateFile;
        
        // 优先使用激活模板
        if (file_exists($templatePath)) {
            include $templatePath;
            return;
        }
        
        // 回退到默认模板
        if (file_exists($defaultPath)) {
            include $defaultPath;
            return;
        }
        
        // 获取所有可用模板用于错误提示
        $availableTemplates = glob($baseDir . '/template/home/*', GLOB_ONLYDIR);
        
        $error = "模板加载失败\n\n";
        $error .= "尝试加载: ".htmlspecialchars($templateFile)."\n";
        $error .= "搜索路径:\n";
        $error .= "- 激活模板: ".htmlspecialchars($templatePath)."\n";
        $error .= "- 默认模板: ".htmlspecialchars($defaultPath)."\n\n";
        $error .= "当前存在的模板文件夹:\n";
        $error .= implode("\n", array_map('htmlspecialchars', $availableTemplates))."\n\n";
        $error .= "建议检查:\n";
        $error .= "1. 数据库site_home_templates表中的folder字段值\n";
        $error .= "2. template/home/目录下的文件夹名称\n";
        $error .= "3. 文件权限(确保www-data可读)";
        
        throw new Exception($error);
    }
    
    /**
     * 强制使用当前激活模板，如果访问的是非激活模板则重定向
     * @param string $templateType 'home' 或 'user'
     */
    public static function enforceActiveTemplate($templateType = 'home') {
        // 只处理GET请求
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return;
        }
        
        $requestUri = $_SERVER['REQUEST_URI'];
        
        // 确定当前激活的模板和基础路径
        if ($templateType === 'user') {
            $activeFolder = self::getActiveUserTemplate();
            $basePath = '/template/user/';
        } else {
            $activeFolder = self::getActiveHomeTemplate();
            $basePath = '/template/home/';
        }
        
        // 检查请求URI是否包含模板路径但不是当前激活模板
        $pattern = '~^' . preg_quote($basePath, '~') . '(?!' . preg_quote($activeFolder, '~') . '/)([^/]+)/~';
        
        if (preg_match($pattern, $requestUri, $matches)) {
            // 获取请求的文件路径
            $requestedFile = preg_replace($pattern, '', $requestUri);
            
            // 构建重定向URL到激活模板
            $redirectUrl = $basePath . $activeFolder . '/' . $requestedFile;
            
            // 检查文件是否存在
            $filePath = realpath(__DIR__ . '/../..' . $redirectUrl);
            if (file_exists($filePath)) {
                // 如果是静态资源(css/js/images等)，直接重定向
                $ext = pathinfo($requestedFile, PATHINFO_EXTENSION);
                $staticExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'];
                
                if (in_array(strtolower($ext), $staticExtensions)) {
                    header("Location: $redirectUrl", true, 301);
                    exit;
                }
                
                header("Location: $redirectUrl", true, 301);
                exit;
            }
        }
    }
}