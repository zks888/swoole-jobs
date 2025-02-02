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
use Kcloze\Jobs\Logs;
use Kcloze\Jobs\Utils;
use yii\console\Application;

class YiiAction implements ActionInterface
{
    private $logger = null;

    private static $application = null;

    public function init()
    {
        $this->logger = Logs::getLogger(Config::getConfig()['logPath'] ?? '', Config::getConfig()['logSaveFileApp'] ?? '');
    }

    /**
     * @param JobObject $JobObject
     */
    public function start(JobObject $JobObject)
    {
        $this->init();
        $application = self::getApplication();
        $route = strtolower($JobObject->class) . '/' . $JobObject->method;
        $params = $JobObject->params;
        try {
            $application->runAction($route, $params);
            \Yii::getLogger()->flush(true);
            $this->logger->log('Action has been done, action content: ' . json_encode($JobObject));
        } catch (\Throwable $e) {
            Utils::catchError($this->logger, $e);
        } catch (\Exception $e) {
            Utils::catchError($this->logger, $e);
        }
        unset($application, $JobObject);
    }

    private static function getApplication()
    {
        if (self::$application === null) {
            $config = Config::getConfig()['framework']['config'] ?? [];
            self::$application = new Application($config);
        }

        return self::$application;
    }
}
