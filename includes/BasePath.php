<?php
class BasePath {
    private static $instance = null;
    private $basePath;
    private $adminPath;
    private $webRoot;
    
    private function __construct() {
        $this->basePath = dirname(__DIR__);
        $this->adminPath = $this->basePath . DS . 'admin';
        $this->webRoot = $this->getWebRoot();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function getWebRoot() {
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        return dirname($scriptPath) . '/';
    }
    
    public function getBasePath() {
        return $this->basePath;
    }
    
    public function getAdminPath() {
        return $this->adminPath;
    }
    
    public function getWebRoot() {
        return $this->webRoot;
    }
    
    public function getAdminWebPath() {
        return $this->webRoot . 'admin/';
    }
    
    public function getPath($path) {
        // Clean the path
        $path = preg_replace('#admin/admin/#', 'admin/', $path);
        return $this->basePath . DS . trim($path, '/');
    }
    
    public function getUrl($path) {
        $path = preg_replace('#admin/admin/#', 'admin/', $path);
        return $this->webRoot . trim($path, '/');
    }
}

// Usage
$base = BasePath::getInstance();

// In dashboard.php
require_once($base->getPath('includes/config.php'));
require_once($base->getPath('includes/functions.php'));

// For links
<a href="<?php echo $base->getUrl('admin/dashboard'); ?>">Dashboard</a>