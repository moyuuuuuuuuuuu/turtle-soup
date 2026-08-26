<?php

namespace plugin\saipackage\app\logic;

use Throwable;
use Saithink\Saipackage\service\Server;
use Saithink\Saipackage\service\Version;
use Saithink\Saipackage\service\Filesystem;
use Saithink\Saipackage\service\Depends;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\app\cache\UserMenuCache;

class InstallLogic
{
    public const UNINSTALLED = 0;
    public const INSTALLED = 1;
    public const WAIT_INSTALL = 2;
    public const CONFLICT_PENDING = 3;
    public const DEPENDENT_WAIT_INSTALL = 4;
    public const DIRECTORY_OCCUPIED = 5;

    /**
     * @var string 安装目录
     */
    protected string $installDir;

    /**
     * @var string 备份目录
     */
    protected string $backupsDir;

    /**
     * @var string 插件名称
     */
    protected string $appName;

    /**
     * @var string 插件根目录
     */
    protected string $appDir;

    public function __construct(string $appName = '')
    {
        $this->installDir = runtime_path() . DIRECTORY_SEPARATOR . 'saipackage' . DIRECTORY_SEPARATOR;
        $this->backupsDir = $this->installDir . 'backups' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->installDir)) {
            mkdir($this->installDir, 0755, true);
        }
        if (!is_dir($this->backupsDir)) {
            mkdir($this->backupsDir, 0755, true);
        }

        if ($appName) {
            $this->appName = $appName;
            $this->appDir = $this->installDir . $appName . DIRECTORY_SEPARATOR;
        }
    }

    public function getInstallState()
    {
        if (!is_dir($this->appDir)) {
            return self::UNINSTALLED;
        }
        $info = $this->getInfo();
        if ($info && isset($info['state'])) {
            return $info['state'];
        }

        // 目录已存在，但非正常的模块
        return Filesystem::dirIsEmpty($this->appDir) ? self::UNINSTALLED : self::DIRECTORY_OCCUPIED;
    }

    /**
     * 获取允许覆盖的目录
     * @return string[]
     */
    public function getAllowedPath(): array
    {
        $backend = 'plugin' . DIRECTORY_SEPARATOR . $this->appName;
        $frontend = env('FRONTEND_DIR', 'saiadmin-artd') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . $this->appName;
        return [
            $this->appDir . $backend => base_path() . DIRECTORY_SEPARATOR . $backend,
            $this->appDir . $frontend => dirname(base_path()) . DIRECTORY_SEPARATOR . $frontend
        ];
    }

    /**
     * 上传安装
     * @param mixed $file
     * @return array 模块的基本信息
     * @throws Throwable
     */
    public function upload(mixed $file): array
    {
        $copyTo = $this->installDir . 'uploadTemp' . date('YmdHis') . '.zip';
        $file->move($copyTo);

        // 解压
        $copyToDir = Filesystem::unzip($copyTo);
        $copyToDir .= DIRECTORY_SEPARATOR;

        // 删除zip
        @unlink($file);
        @unlink($copyTo);

        // 读取ini
        $info = Server::getIni($copyToDir);
        if (empty($info['app'])) {
            Filesystem::delDir($copyToDir);
            // 基本配置不完整
            throw new ApiException('插件的基础配置信息错误');
        }


        $this->appName = $info['app'];
        $this->appDir = $this->installDir . $info['app'] . DIRECTORY_SEPARATOR;

        $upgrade = false;
        if (is_dir($this->appDir)) {
            $oldInfo = $this->getInfo();
            if ($oldInfo && !empty($oldInfo['app'])) {
                $versions = explode('.', $oldInfo['version']);
                if (isset($versions[2])) {
                    $versions[2]++;
                }
                $nextVersion = implode('.', $versions);
                $upgrade = Version::compare($nextVersion, $info['version']);
                if (!$upgrade) {
                    Filesystem::delDir($copyToDir);
                    throw new ApiException('插件已经存在');
                }
            }

            if (Filesystem::dirIsEmpty($this->appDir) || (!Filesystem::dirIsEmpty($this->appDir) && !$upgrade)) {
                Filesystem::delDir($copyToDir);
                // 模块目录被占
                throw new ApiException('该插件的安装目录已经被占用');
            }
        }

        $newInfo = ['state' => self::WAIT_INSTALL];
        if ($upgrade) {
            $newInfo['update'] = 1;

            // 清理旧版本代码
            Filesystem::delDir($this->appDir);
        }

        // 放置新模块
        rename($copyToDir, $this->appDir);

        // 检查新包是否完整
        $this->checkPackage();

        // 设置为待安装状态
        $this->setInfo($newInfo);

        return $info;
    }

    /**
     * 从本地 zip 文件路径安装（用于在线下载后安装）
     * @param string $zipPath zip 文件完整路径
     * @return array 模块的基本信息
     * @throws Throwable
     */
    public function uploadFromPath(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            throw new ApiException('文件不存在');
        }

        // 解压
        $copyToDir = Filesystem::unzip($zipPath);
        $copyToDir .= DIRECTORY_SEPARATOR;

        // 删除 zip
        @unlink($zipPath);

        // 读取 ini
        $info = Server::getIni($copyToDir);
        if (empty($info['app'])) {
            Filesystem::delDir($copyToDir);
            throw new ApiException('插件的基础配置信息错误');
        }

        $this->appName = $info['app'];
        $this->appDir = $this->installDir . $info['app'] . DIRECTORY_SEPARATOR;

        $upgrade = false;
        if (is_dir($this->appDir)) {
            $oldInfo = $this->getInfo();
            if ($oldInfo && !empty($oldInfo['app'])) {
                $versions = explode('.', $oldInfo['version']);
                if (isset($versions[2])) {
                    $versions[2]++;
                }
                $nextVersion = implode('.', $versions);
                $upgrade = Version::compare($nextVersion, $info['version']);
                if (!$upgrade) {
                    Filesystem::delDir($copyToDir);
                    throw new ApiException('插件已经存在');
                }
            }

            if (Filesystem::dirIsEmpty($this->appDir) || (!Filesystem::dirIsEmpty($this->appDir) && !$upgrade)) {
                Filesystem::delDir($copyToDir);
                throw new ApiException('该插件的安装目录已经被占用');
            }
        }

        $newInfo = ['state' => self::WAIT_INSTALL];
        if ($upgrade) {
            $newInfo['update'] = 1;
            Filesystem::delDir($this->appDir);
        }

        // 放置新模块
        rename($copyToDir, $this->appDir);

        // 检查新包是否完整
        $this->checkPackage();

        // 设置为待安装状态
        $this->setInfo($newInfo);

        return $info;
    }


    /**
     * 安装或更新
     * @return array
     * @throws Throwable
     */
    public function install(): array
    {
        $state = $this->getInstallState();
        if ($state == self::INSTALLED || $state == self::DIRECTORY_OCCUPIED) {
            throw new ApiException('插件已经存在');
        }

        if ($state == self::DEPENDENT_WAIT_INSTALL) {
            throw new ApiException('等待依赖安装');
        }

        echo '开始安装[' . $this->appName . ']' . PHP_EOL;

        $info = $this->getInfo();

        if ($state == self::WAIT_INSTALL) {
            echo '安装数据库' . PHP_EOL;
            $sql = $this->appDir . 'install.sql';
            Server::importSql($sql);
        }

        if (isset($info['update']) && $info['update'] == 1) {
            echo '更新数据库' . PHP_EOL;
            $sql = $this->appDir . 'update.sql';
            Server::importSql($sql);

            unset($info['update']);
            $this->setInfo([], $info);
        }

        // 依赖检查
        $this->dependConflictHandle();

        // 执行安装脚本
        echo '安装文件' . PHP_EOL;
        $pathRelation = $this->getAllowedPath();
        Server::installByRelation($pathRelation);

        // 依赖更新
        echo '依赖更新' . PHP_EOL;
        $this->dependUpdateHandle();

        // 清理菜单缓存
        UserMenuCache::clearMenuCache();

        // 重启后端
        Server::restart();

        return $info;
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function uninstall(): void
    {
        $state = $this->getInstallState();
        if ($state != self::INSTALLED) {
            echo PHP_EOL . '删除插件[' . $this->appName . ']' . PHP_EOL;
            $pathRelation = $this->getAllowedPath();
            foreach ($pathRelation as $key => $value) {
                if (is_dir($value)) {
                    Filesystem::delDir($value);
                }
            }

            // 删除临时目录
            Filesystem::delDir($this->appDir);

            return;
        }

        echo '开始卸载[' . $this->appName . ']' . PHP_EOL;

        echo '卸载数据库' . PHP_EOL;
        $sql = $this->appDir . 'uninstall.sql';
        Server::importSql($sql);

        echo '备份文件' . PHP_EOL;
        $backFiles = [];
        $pathRelation = $this->getAllowedPath();
        $index = 1;
        foreach ($pathRelation as $key => $value) {
            if (is_dir($value)) {
                $backFiles[$this->appName . '-' . $index] = $value;
                $index++;
            }
        }
        $backupsZip = $this->backupsDir . $this->appName . '-uninstall-' . date('YmdHis') . '.zip';
        Filesystem::zipDir($backFiles, $backupsZip);

        echo '卸载文件' . PHP_EOL;
        $pathRelation = $this->getAllowedPath();
        foreach ($pathRelation as $key => $value) {
            if (is_dir($value)) {
                Filesystem::delDir($value);
            }
        }

        // 删除临时目录
        Filesystem::delDir($this->appDir);

        // 清理菜单缓存
        UserMenuCache::clearMenuCache();

        // 重启后端
        Server::restart();

    }

    /**
     * 检查包是否完整
     * @throws Throwable
     */
    public function checkPackage(): bool
    {
        if (!is_dir($this->appDir)) {
            throw new ApiException('插件目录不存在');
        }
        $info = $this->getInfo();
        $infoKeys = ['app', 'title', 'about', 'author', 'version', 'state'];
        foreach ($infoKeys as $value) {
            if (!array_key_exists($value, $info)) {
                Filesystem::delDir($this->appDir);
                throw new ApiException('该插件的基础配置信息不完善');
            }
        }
        return true;
    }

    /**
     * 依赖安装完成标记
     * @throws Throwable
     */
    public function dependentInstallComplete(string $type): void
    {
        $info = $this->getInfo();
        if ($info['state'] == self::DEPENDENT_WAIT_INSTALL) {
            if ($type == 'npm') {
                unset($info['npm_dependent_wait_install']);
            }
            if ($type == 'composer') {
                unset($info['composer_dependent_wait_install']);
            }
            if ($type == 'all') {
                unset($info['npm_dependent_wait_install'], $info['composer_dependent_wait_install']);
            }
            if (!isset($info['npm_dependent_wait_install']) && !isset($info['composer_dependent_wait_install'])) {
                $info['state'] = self::INSTALLED;
            }
            $this->setInfo([], $info);
        }
    }

    /**
     * 依赖冲突检查
     * @return bool
     * @throws Throwable
     */
    public function dependConflictHandle(): bool
    {
        $info = $this->getInfo();
        if ($info['state'] != self::WAIT_INSTALL && $info['state'] != self::CONFLICT_PENDING) {
            return false;
        }

        $coverFiles = [];// 要覆盖的文件-备份
        $depends = Server::getDepend($this->appDir);

        $serverDep = new Depends(base_path() . DIRECTORY_SEPARATOR . 'composer.json', 'composer');
        $webDep = new Depends(dirname(base_path()) . DIRECTORY_SEPARATOR . env('FRONTEND_DIR', 'saiadmin-vue') . DIRECTORY_SEPARATOR . 'package.json');

        // 如果有依赖更新，增加要备份的文件
        if ($depends) {
            foreach ($depends as $key => $item) {
                if (!$item) {
                    continue;
                }
                if ($key == 'require' || $key == 'require-dev') {
                    $coverFiles[] = base_path() . DIRECTORY_SEPARATOR . 'composer.json';
                    continue;
                }
                if ($key == 'dependencies' || $key == 'devDependencies') {
                    $coverFiles[] = dirname(base_path()) . DIRECTORY_SEPARATOR . env('FRONTEND_DIR', 'saiadmin-vue') . DIRECTORY_SEPARATOR . 'package.json';
                }
            }
        }

        // 备份将被覆盖的文件
        if ($coverFiles) {
            $backupsZip = $this->backupsDir . $this->appName . '-cover-' . date('YmdHis') . '.zip';
            Filesystem::zip($coverFiles, $backupsZip);
        }

        if ($depends) {
            $npm = false;
            $composer = false;

            // composer config 更新
            $composerConfig = Server::getConfig($this->appDir, 'composerConfig');
            if ($composerConfig) {
                $serverDep->setComposerConfig($composerConfig);
            }

            foreach ($depends as $key => $item) {
                if (!$item) {
                    continue;
                }
                if ($key == 'require') {
                    $composer = true;
                    $serverDep->addDepends($item, false, true);
                } elseif ($key == 'require-dev') {
                    $composer = true;
                    $serverDep->addDepends($item, true, true);
                } elseif ($key == 'dependencies') {
                    $npm = true;
                    $webDep->addDepends($item, false, true);
                } elseif ($key == 'devDependencies') {
                    $npm = true;
                    $webDep->addDepends($item, true, true);
                }
            }
            if ($npm) {
                $info['npm_dependent_wait_install'] = 1;
                $info['state'] = self::DEPENDENT_WAIT_INSTALL;
            }
            if ($composer) {
                $info['composer_dependent_wait_install'] = 1;
                $info['state'] = self::DEPENDENT_WAIT_INSTALL;
            }
            if ($info['state'] != self::DEPENDENT_WAIT_INSTALL) {
                // 无冲突
                $this->setInfo([
                    'state' => self::INSTALLED,
                ]);
            } else {
                $this->setInfo([], $info);
            }
        } else {
            // 无冲突
            $this->setInfo([
                'state' => self::INSTALLED,
            ]);
        }
        return true;
    }

    /**
     * 依赖升级处理
     * @throws Throwable
     */
    public function dependUpdateHandle(): void
    {
        $info = $this->getInfo();
        if ($info['state'] == self::DEPENDENT_WAIT_INSTALL) {
            $waitInstall = [];
            if (isset($info['composer_dependent_wait_install'])) {
                $waitInstall[] = 'composer_dependent_wait_install';
            }
            if (isset($info['npm_dependent_wait_install'])) {
                $waitInstall[] = 'npm_dependent_wait_install';
            }
            if (empty($waitInstall)) {
                $this->setInfo([
                    'state' => self::INSTALLED,
                ]);
            }
        }
    }

    /**
     * 获取模块基本信息
     */
    public function getInfo(): array
    {
        return Server::getIni($this->appDir);
    }

    /**
     * 设置模块基本信息
     * @throws Throwable
     */
    public function setInfo(array $kv = [], array $arr = []): bool
    {
        if ($kv) {
            $info = $this->getInfo();
            foreach ($kv as $k => $v) {
                $info[$k] = $v;
            }
            return Server::setIni($this->appDir, $info);
        } elseif ($arr) {
            return Server::setIni($this->appDir, $arr);
        }
        throw new ApiException('参数错误');
    }
}
