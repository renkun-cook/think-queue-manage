# think-queue-manage 

#### 介绍
用于管理think-queue

#### 安装教程

```shell
composer require renkun-cook/think-queue-manage
```
#### 使用说明

配置

```
项目根目录/config/queue.php
<?php

return [
    'default'     => 'database',
    'connections' => [
        'sync'     => [
            'driver' => 'sync',
        ],
        'database' => [
            'type' => 'database',
            'queue'  => 'default',
            'table'  => 'jobs',
            'queues'   => [
                'helloJobQueue' => [
                    'delay'   => 0,
                    'sleep'   => 3,
                    'tries'   => 1,
                    'memory'  => 128,
                    'timeout' => 60,
                    'processNum' => 1
                ],
                'testJob' => [
                    'delay'   => 0,
                    'sleep'   => 3,
                    'tries'   => 3,
                    'memory'  => 128,
                    'timeout' => 60,
                    'processNum' => 1
                ],
            ]
        ],
        'redis'    => [
            'driver'     => 'redis',
            'queue'      => 'default',
            'host'       => '127.0.0.1',
            'port'       => 6379,
            'password'   => '',
            'select'     => 0,
            'timeout'    => 0,
            'persistent' => false,
        ],
    ],
    'failed'      => [
        'type'  => 'database',
        'table' => 'failed_jobs',
    ],
];
```

在系统的计划任务里添加
~~~
* * * * * php /path/to/think think-queue-manage:handle >> /dev/null 2>&1
~~~

