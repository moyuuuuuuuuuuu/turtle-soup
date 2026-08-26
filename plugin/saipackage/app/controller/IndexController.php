<?php

namespace plugin\saipackage\app\controller;

use plugin\saiadmin\basic\OpenController;
use Saithink\Saipackage\service\Terminal;
use support\Request;
use support\Response;
use Throwable;
use Workerman\Protocols\Http\ServerSentEvents;

class IndexController extends OpenController
{
    /**
     * 执行终端
     * @param Request $request
     * @return void
     * @throws Throwable
     */
    public function terminal(Request $request): void
    {
        // SSE 消息
        $connection = $request->connection;
        $connection->send(new Response(200, [
            'Content-Type'                     => 'text/event-stream',
            'Cache-Control'                    => 'no-cache',
            'Connection'                       => 'keep-alive',
            'X-Accel-Buffering'                => 'no',
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Expose-Headers'    => 'Content-Type',
        ], "\r\n"));

        // 消息开始
        $connection->send(new ServerSentEvents([
            'event' => 'message', 'data' => 'start'
        ]));

        // 生成器
        $generator = (new Terminal())->exec();
        foreach ($generator as $chunk) {
            $connection->send(new ServerSentEvents([
                'event' => 'message', 'data' => $chunk
            ]));
        }

        // 关闭链接
        $connection->close();
    }

}
