<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Модель для таблицы url_visit.
 * Хранит логи переходов по коротким ссылкам.
 *
 * Каждый раз, когда пользователь переходит по короткой ссылке,
 * создаётся новая запись с его IP-адресом и временем перехода.
 *
 * @property int $id
 * @property int $url_id Внешний ключ на таблицу url
 * @property string $ip_address IP-адрес посетителя
 * @property string $visited_at Время перехода
 *
 * @property Url $url Связанная запись URL
 */
class UrlVisit extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%url_visit}}';
    }

    /**
     * Правила валидации
     */
    public function rules()
    {
        return [
            [['url_id', 'ip_address', 'visited_at'], 'required'],
            [['url_id'], 'integer'],
            // IPv6 адрес может быть до 45 символов
            [['ip_address'], 'string', 'max' => 45],
            // Проверяем, что url_id ссылается на существующую запись
            [['url_id'], 'exist', 'targetClass' => Url::class, 'targetAttribute' => 'id'],
        ];
    }

    /**
     * Названия полей
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'url_id' => 'URL',
            'ip_address' => 'IP адрес',
            'visited_at' => 'Время перехода',
        ];
    }

    /**
     * Связь: каждый лог принадлежит одному URL.
     * Используется для получения оригинального URL: $visit->url
     */
    public function getUrl()
    {
        return $this->hasOne(Url::class, ['id' => 'url_id']);
    }
}
