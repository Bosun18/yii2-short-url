<?php

namespace app\models;

use Random\RandomException;
use Yii;
use yii\db\ActiveRecord;

/**
 * Модель для таблицы url.
 * Хранит оригинальные URL и соответствующие им короткие коды.
 *
 * @property int $id
 * @property string $original_url Оригинальная ссылка
 * @property string $short_code Короткий код (6 символов)
 * @property int $clicks_count Количество переходов
 * @property string $created_at Дата создания
 * @property string $updated_at Дата обновления
 *
 * @property-read string $shortUrl
 * @property UrlVisit[] $visits Логи переходов
 */
class Url extends ActiveRecord
{
    /**
     * Символы для генерации короткого кода.
     * a-z, A-Z, 0-9 = 62 символа. При длине кода 6 символов = 62^6 ≈ 56 млрд комбинаций.
     */
    private const CODE_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /** Длина генерируемого короткого кода */
    private const CODE_LENGTH = 6;

    /**
     * {@inheritdoc}
     * Название таблицы в БД
     */
    public static function tableName(): string
    {
        return '{{%url}}';
    }

    /**
     * Правила валидации.
     *
     * - original_url обязателен и должен быть валидным URL с протоколом http или https
     * - short_code уникален в таблице
     * - clicks_count — целое число
     */
    public function rules(): array
    {
        return [
            // Оригинальный URL — обязательное поле
            [['original_url'], 'required', 'message' => 'Введите URL'],
            // Проверка формата URL: только http и https
            [['original_url'], 'url', 'defaultScheme' => 'https', 'message' => 'Введите корректный URL (http или https)'],
            // Короткий код должен быть уникальным
            [['short_code'], 'unique'],
            // Счётчик кликов — целое число
            [['clicks_count'], 'integer'],
        ];
    }

    /**
     * Названия полей (для отображения в формах и ошибках)
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'original_url' => 'URL',
            'short_code' => 'Короткий код',
            'clicks_count' => 'Переходы',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    /**
     * Автоматическая установка created_at и updated_at перед сохранением.
     *
     * beforeSave() вызывается Yii2 автоматически перед каждым save().
     * - При создании новой записи ($this->isNewRecord) — заполняем оба поля
     * - При обновлении — только updated_at
     */
    public function beforeSave($insert): bool
    {
        if (parent::beforeSave($insert)) {
            $now = date('Y-m-d H:i:s');
            if ($this->isNewRecord) {
                $this->created_at = $now;
            }
            $this->updated_at = $now;
            return true;
        }
        return false;
    }

    /**
     * Связь: у одного URL может быть много переходов (логов).
     * Используется для получения всех визитов: $url->visits
     */
    public function getVisits(): \yii\db\ActiveQuery
    {
        return $this->hasMany(UrlVisit::class, ['url_id' => 'id']);
    }

    /**
     * Генерирует уникальный короткий код.
     *
     * Алгоритм:
     * 1. Генерируем случайную строку из CODE_CHARS длиной CODE_LENGTH
     * 2. Проверяем, нет ли такого кода в БД (уникальность)
     * 3. Если код уже занят — генерируем заново (вероятность коллизии крайне мала)
     *
     * Используем random_int() вместо rand() — криптографически безопасный генератор.
     * @throws RandomException
     */
    public function generateShortCode(): void
    {
        $chars = self::CODE_CHARS;
        $length = self::CODE_LENGTH;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (self::find()->where(['short_code' => $code])->exists());

        $this->short_code = $code;
    }

    /**
     * Формирует полную короткую ссылку (с доменом).
     * Например: http://localhost:8080/AbCdEf
     */
    public function getShortUrl(): string
    {
        return Yii::$app->request->hostInfo . '/' . $this->short_code;
    }
}
