<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: huangweijie <1539369355@qq.com>
// +----------------------------------------------------------------------
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