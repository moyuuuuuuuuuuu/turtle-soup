<?php

namespace plugin\saipackage\app\controller;

use plugin\saiadmin\app\cache\UserMenuCache;
use plugin\saiadmin\app\middleware\SystemLog;
use plugin\saiadmin\app\middleware\CheckLogin;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\exception\ApiException;
use plugin\saipackage\app\logic\InstallLogic;
use Saithink\Saipackage\service\Server;
use Saithink\Saipackage\service\Version;
use support\annotation\Middleware;
use support\Request;
use support\Response;
use Throwable;

#[Middleware(CheckLogin::class, SystemLog::class)]
class InstallController extends BaseController
{
    /**
     * 构造
     */
    public function __construct()
    {
        parent::__construct();
        if ($this->adminId > 1) {
            throw new ApiException('仅超级管理员能够操作');
        }
    }

    /**
     * 环境检查状态
     */
    static string $ok = 'ok';
    static string $fail = 'fail';
    static string $warn = 'warn';

    static array $needDependentVersion = [
        'php' => '8.1.0',
        'saiadmin' => '6.0.0',
        'saipackage' => '6.0.0',
    ];

    /**
     * 应用列表
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $data = Server::installedList(runtime_path() . DIRECTORY_SEPARATOR . 'saipackage' . DIRECTORY_SEPARATOR);

        $phpVersion = phpversion();
        $phpVersionCompare = Version::compare(self::$needDependentVersion['php'], $phpVersion);
        $phpVersionNotes = '正常';
        if (!$phpVersionCompare) {
            $phpVersionNotes = '需要版本' . ' >= ' . self::$needDependentVersion['php'];
        }

        $saiadminVersion = config('plugin.saiadmin.app.version');
        $saiadminVersionCompare = Version::compare(self::$needDependentVersion['saiadmin'], $saiadminVersion);
        $saiadminVersionNotes = '正常';
        if (!$saiadminVersionCompare) {
            $saiadminVersionNotes = '需要版本' . ' >= ' . self::$needDependentVersion['saiadmin'];
        }

        $saithinkVersion = config('plugin.saipackage.app.version');
        $saithinkVersionCompare = Version::compare(self::$needDependentVersion['saipackage'], $saithinkVersion);
        $saithinkVersionNotes = '正常';
        if (!$saithinkVersionCompare) {
            $saithinkVersionNotes = '需要版本' . ' >= ' . self::$needDependentVersion['saipackage'];
        }


        return $this->success([
            'version' => [
                'php_version' => [
                    'describe' => $phpVersion,
                    'state' => $phpVersionCompare ? self::$ok : self::$fail,
                    'notes' => $phpVersionNotes,
                ],
                'saiadmin_version' => [
                    'describe' => $saiadminVersion,
                    'state' => $saiadminVersionCompare ? self::$ok : self::$fail,
                    'notes' => $saiadminVersionNotes,
                ],
                'saipackage_version' => [
                    'describe' => $saithinkVersion,
                    'state' => $saithinkVersionCompare ? self::$ok : self::$fail,
                    'notes' => $saithinkVersionNotes,
                ],
            ],
            'data' => $data
        ]);
    }

    /**
     * 上传插件
     * @param Request $request
     * @return Response
     * @throws Throwable
     */
    public function upload(Request $request): Response
    {
        $spl_file = current($request->file());
        if (!$spl_file->isValid()) {
            return $this->fail('上传文件校验失败');
        }
        $config = config('plugin.saipackage.upload', [
            'size' => 1024 * 1024 * 5,
            'type' => ['zip']
        ]);
        if (!in_array($spl_file->getUploadExtension(), $config['type'])) {
            return $this->fail('文件格式上传失败,请选择zip格式文件上传');
        }
        if ($spl_file->getSize() > $config['size']) {
            return $this->fail('文件大小不能超过5M');
        }
        $install = new InstallLogic();
        $info = $install->upload($spl_file);
        return $this->success($info);
    }

    /**
     * 安装插件
     * @param Request $request
     * @return Response
     * @throws Throwable
     */
    public function install(Request $request): Response
    {
        $appName = $request->post("appName", '');
        if (empty($appName)) {
            return $this->fail('参数错误');
        }
        $install = new InstallLogic($appName);
        $info = $install->install();
        UserMenuCache::clearMenuCache();
        return $this->success($info);
    }

    /**
     * 卸载插件
     * @param Request $request
     * @return Response
     * @throws Throwable
     */
    public function uninstall(Request $request): Response
    {
        $appName = $request->post("appName", '');
        if (empty($appName)) {
            return $this->fail('参数错误');
        }
        $install = new InstallLogic($appName);
        $install->uninstall();
        UserMenuCache::clearMenuCache();
        return $this->success('卸载插件成功');
    }

    /**
     * 重启
     * @param Request $request
     * @return Response
     */
    public function reload(Request $request): Response
    {
        Server::restart();

        return $this->success('重载成功');
    }

    // ========== 商店代理接口 ==========

    /**
     * 代理请求封装
     */
    protected function proxyRequest(string $url, string $method = 'GET', ?string $token = null, ?array $postData = null, int $timeout = 10): array
    {
        $headers = [];
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }
        if ($postData !== null) {
            $headers[] = "Content-Type: application/json";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $postData ? json_encode($postData) : null,
                'timeout' => $timeout,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return ['success' => false, 'message' => '请求失败'];
        }

        // 尝试解析 JSON
        $data = json_decode($response, true);
        if ($data && isset($data['code'])) {
            if ($data['code'] === 200) {
                return ['success' => true, 'data' => $data['data'] ?? null];
            }
            return ['success' => false, 'message' => $data['message'] ?? '请求失败'];
        }

        // 非 JSON 响应（可能是文件）
        return ['success' => true, 'raw' => $response, 'headers' => $http_response_header ?? []];
    }

    /**
     * 获取应用商店列表
     */
    public function appList(Request $request): Response
    {
        $params = http_build_query([
            'page' => $request->input('page', 1),
            'limit' => $request->input('limit', 16),
            'price' => $request->input('price', 'all'),
            'type' => $request->input('type', ''),
            'keywords' => $request->input('keywords', ''),
        ]);

        $result = $this->proxyRequest("https://saas.saithink.top/dev-api/app/saistore/api/store/appList?{$params}");

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 获取商店验证码
     */
    public function storeCaptcha(): Response
    {
        $result = $this->proxyRequest("https://saas.saithink.top/dev-api/app/saiuser/api/common/index/captcha");

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 商店登录
     */
    public function storeLogin(Request $request): Response
    {
        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saiuser/api/common/index/accountLogin",
            'POST',
            null,
            [
                'username' => $request->input('username'),
                'password' => $request->input('password'),
                'code' => $request->input('code'),
                'uuid' => $request->input('uuid'),
            ]
        );

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 获取商店用户信息
     */
    public function storeUserInfo(Request $request): Response
    {
        $token = $request->input('token');
        if (empty($token)) {
            return $this->fail('未登录');
        }

        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saiuser/api/user/user/userInfo",
            'GET',
            $token
        );

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 获取已购应用列表
     */
    public function storePurchasedApps(Request $request): Response
    {
        $token = $request->input('token');
        if (empty($token)) {
            return $this->fail('未登录');
        }

        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saistore/api/StoreOrder/orderList?saiType=all",
            'GET',
            $token
        );

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 获取应用版本列表
     */
    public function storeAppVersions(Request $request): Response
    {
        $token = $request->input('token');
        $appId = $request->input('app_id');

        if (empty($token)) {
            return $this->fail('未登录');
        }

        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saistore/api/StoreOrder/appVersionList?app_id={$appId}",
            'GET',
            $token
        );

        return $result['success']
            ? $this->success($result['data'])
            : $this->fail($result['message']);
    }

    /**
     * 下载应用 - 下载并调用 InstallLogic 处理
     */
    public function storeDownloadApp(Request $request): Response
    {
        $token = $request->input('token');
        $versionId = $request->input('id');

        if (empty($token)) {
            return $this->fail('未登录');
        }

        if (empty($versionId)) {
            return $this->fail('版本ID不能为空');
        }

        $result = $this->proxyRequest(
            "https://saas.saithink.top/dev-api/app/saistore/api/StoreOrder/downloadVersion",
            'POST',
            $token,
            ['version_id' => (int) $versionId],
            60
        );

        if (!$result['success']) {
            return $this->fail($result['message'] ?? '下载失败');
        }

        if (!isset($result['raw'])) {
            return $this->fail('下载失败');
        }

        // 保存临时 zip 文件
        $tempZip = runtime_path() . DIRECTORY_SEPARATOR . 'saipackage' . DIRECTORY_SEPARATOR . 'downloadTemp' . date('YmdHis') . '.zip';
        if (!is_dir(dirname($tempZip))) {
            mkdir(dirname($tempZip), 0755, true);
        }
        file_put_contents($tempZip, $result['raw']);

        try {
            // 调用 InstallLogic 处理
            $install = new InstallLogic();
            $info = $install->uploadFromPath($tempZip);

            return $this->success($info, '下载成功，请在插件列表中安装');
        } catch (Throwable $e) {
            @unlink($tempZip);
            return $this->fail($e->getMessage());
        }
    }
}
