<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    // Название приложения — отображается в навбаре
    'name' => 'Short URL',
    // Язык приложения — русский (для сообщений об ошибках и т.д.)
    'language' => 'ru-RU',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'fYqA9WHfoLZUnFlNJD3V4komdKRAyu-6',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        /**
         * Менеджер URL — включаем ЧПУ (человекопонятные URL).
         *
         * enablePrettyUrl: /url/shorten вместо /index.php?r=url/shorten
         * showScriptName: убираем index.php из URL
         *
         * Правила маршрутизации (rules) обрабатываются сверху вниз — порядок важен!
         * Правило '<code:\w{6}>' должно быть ПОСЛЕДНИМ, иначе оно перехватит
         * все 6-символьные пути (например, /gii/mo и т.д.)
         */
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                // AJAX-эндпоинт для создания короткой ссылки
                'POST url/shorten' => 'url/shorten',
                // Редирект по короткому коду (ровно 6 символов: буквы + цифры)
                '<code:\w{6}>' => 'url/redirect',
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
