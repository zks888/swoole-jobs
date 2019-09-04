<?php

/*
 * This file is part of PHP CS Fixer.
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kcloze\Jobs\Action;

use Kcloze\Jobs\Config;
use Kcloze\Jobs\JobObject;
use Kcloze\Jobs\JobApi;
use Kcloze\Jobs\Logs;
use Kcloze\Jobs\Utils;

class DispatchAction implements ActionInterface
{
    /**
     * @var Logs
     */
    private $logger = null;

    public function init()
    {
        $config = Config::getConfig();

        $this->logger = Logs::getLogger($config['logPath'] ?? '', $config['logSaveFileApp'] ?? '', $config['system'] ?? '');
    }

    public function start($JobObject)
    {
        try {
            $this->init();

            $type = $JobObject->type;
            $class = $JobObject->class;
            $method = $JobObject->method;
            $params = $JobObject->params;

            //调用消费者API
            if ($type === 'api') {
                $client = new \GuzzleHttp\Client();
                $res = $client->request('GET', 'https://api.github.com/repos/guzzle/guzzle');
                echo $res->getStatusCode();
                echo $res->getBody();
            }

            //调用本地类库进行处理
            elseif ($type === 'object') {
                $obj = new $class();
                if (is_object($obj) && method_exists($obj, $method)) {
                    call_user_func_array([$obj, $method], $params);
                } else {
                    $this->logger->log('Action obj not find: ' . json_encode($JobObject), 'error');
                }
            }

            //未定义的类型
            else {
                $this->logger->log('Action obj not find: ' . json_encode($JobObject), 'error');
            }
        } catch (\Exception $e) {
            Utils::catchError($this->logger, $e);
        }

        $this->logger->log('Action has been done, action content: ' . json_encode($JobObject));
    }
}
