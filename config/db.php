<?php

/**
 * Настройки подключения к базе данных.
 * При развёртывании проекта измените параметры подключения на свои.
 */
return [
    'class' => 'yii\db\Connection',
    // Подключение к MySQL: хост, имя базы данных
    'dsn' => 'mysql:host=localhost;dbname=short_url',
    'username' => 'root',
    'password' => '',
    // utf8mb4 — поддержка полного Unicode (включая эмодзи)
    'charset' => 'utf8mb4',
];
