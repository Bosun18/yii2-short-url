<?php

use yii\db\Migration;

/**
 * Миграция для создания таблицы {{%url}}.
 * Хранит оригинальные URL и их короткие коды.
 */
class m240101_000001_create_url_table extends Migration
{
    /**
     * Создаёт таблицу url с полями:
     * - original_url: оригинальная ссылка
     * - short_code: уникальный короткий код (6 символов)
     * - clicks_count: счётчик переходов по короткой ссылке
     * - created_at, updated_at: временные метки
     */
    public function safeUp(): void
    {
        $this->createTable('{{%url}}', [
            'id' => $this->primaryKey(),
            // Оригинальная ссылка, которую пользователь хочет сократить
            'original_url' => $this->text()->notNull(),
            // Короткий код для формирования короткой ссылки (6 символов, уникальный)
            'short_code' => $this->string(10)->notNull()->unique(),
            // Счётчик переходов — инкрементируется при каждом редиректе
            'clicks_count' => $this->integer()->notNull()->defaultValue(0),
            // Дата создания записи
            'created_at' => $this->dateTime()->notNull(),
            // Дата последнего обновления (например, при очередном клике)
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        // Индекс по short_code для быстрого поиска при редиректе
        $this->createIndex('idx-url-short_code', '{{%url}}', 'short_code', true);
    }

    /**
     * Откат миграции — удаляет таблицу url
     */
    public function safeDown(): void
    {
        $this->dropTable('{{%url}}');
    }
}
