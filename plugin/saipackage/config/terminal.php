<?php

return [
    // 允许执行的命令
    'commands' => [
        // 查看版本的命令
        'version' => [
            'npm' => 'npm -v',
            'yarn' => 'yarn -v',
            'pnpm' => 'pnpm -v',
            'node' => 'node -v',
        ],
        // 测试命令
        'test' => [
            'npm' => [
                'cwd' => public_path() . DIRECTORY_SEPARATOR . 'npm-install-test',
                'command' => 'npm install',
            ],
            'yarn' => [
                'cwd' => public_path() . DIRECTORY_SEPARATOR . 'npm-install-test',
                'command' => 'yarn install',
            ],
            'pnpm' => [
                'cwd' => public_path() . DIRECTORY_SEPARATOR . 'npm-install-test',
                'command' => 'pnpm install',
            ],
        ],
        // 安装 WEB 依赖包
        'web-install' => [
            'npm' => [
                'cwd' => dirname(base_path()) . DIRECTORY_SEPARATOR . env('FRONTEND_DIR', 'saiadmin-artd'),
                'command' => 'npm install',
            ],
            'yarn' => [
                'cwd' => dirname(base_path()) . DIRECTORY_SEPARATOR . env('FRONTEND_DIR', 'saiadmin-artd'),
                'command' => 'yarn install',
            ],
            'pnpm' => [
                'cwd' => dirname(base_path()) . DIRECTORY_SEPARATOR . env('FRONTEND_DIR', 'saiadmin-artd'),
                'command' => 'pnpm install',
            ],
        ],
        // 构建 WEB 端
        'web-build' => [
            'npm' => [
                'cwd' => dirname(base_path()) . DIRECTORY_SEPARATOR . env('FRONTEND_DIR', 'saiadmin-artd'),
                'command' => 'npm run build',
            ],
            'yarn' => [
                'cwd' => dirname(base_path()) . DIRECTORY_SEPARATOR . env('FRONTEND_DIR', 'saiadmin-artd'),
                'command' => 'yarn run build',
            ],
            'pnpm' => [
                'cwd' => dirname(base_path()) . DIRECTORY_SEPARATOR . env('FRONTEND_DIR', 'saiadmin-artd'),
                'command' => 'pnpm run build',
            ],
        ],
        // 设置 NPM 源
        'set-npm-registry' => [
            'npm' => 'npm config set registry https://registry.npmjs.org/ && npm config get registry',
            'taobao' => 'npm config set registry https://registry.npmmirror.com/ && npm config get registry',
            'tencent' => 'npm config set registry https://mirrors.cloud.tencent.com/npm/ && npm config get registry'
        ],
        // 设置 composer 源
        'set-composer-registry' => [
            'composer' => 'composer config --unset repos.packagist',
            'tencent' => 'composer config -g repos.packagist composer https://mirrors.cloud.tencent.com/composer/',
            'huawei' => 'composer config -g repos.packagist composer https://mirrors.huaweicloud.com/repository/php/',
            'kkame' => 'composer config -g repos.packagist composer https://packagist.kr',
        ],
        // 安装 composer 包
        'composer' => [
            'update' => [
                'cwd' => base_path(),
                'command' => 'composer update --no-interaction',
            ],
        ]
    ],
];