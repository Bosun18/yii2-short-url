<?php

use yii\db\Migration;

/**
 * Миграция для создания таблицы {{%url_visit}}.
 * Хранит логи переходов по коротким ссылкам (IP-адрес, время).
 */
class m240101_000002_create_url_visit_table extends Migration
{
    /**
     * Создаёт таблицу url_visit с полями:
     * - url_id: внешний ключ на таблицу url
     * - ip_address: IP-адрес посетителя (поддерживает IPv6 — до 45 символов)
     * - visited_at: время перехода
     */
    public function safeUp()
    {
        $this->createTable('{{%url_visit}}', [
            'id' => $this->primaryKey(),
            // Ссылка на запись в таблице url
            'url_id' => $this->integer()->notNull(),
            // IP-адрес посетителя. VARCHAR(45) — максимальная длина IPv6-адреса
            'ip_address' => $this->string(45)->notNull(),
            // Время перехода по короткой ссылке
            'visited_at' => $this->dateTime()->notNull(),
        ]);

        // Внешний ключ: при удалении url — каскадно удаляем все логи переходов
        $this->addForeignKey(
            'fk-url_visit-url_id',
            '{{%url_visit}}',
            'url_id',
            '{{%url}}',
            'id',
            'CASCADE'
        );

        // Индекс по url_id для быстрой выборки логов по конкретной ссылке
        $this->createIndex('idx-url_visit-url_id', '{{%url_visit}}', 'url_id');
    }

    /**
     * Откат миграции — удаляет таблицу url_visit
     */
    public function safeDown()
    {
        $this->dropTable('{{%url_visit}}');
    }
}
