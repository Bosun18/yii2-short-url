<?php

namespace app\models;

use yii\base\Model;

/**
 * Форма для валидации входного URL при создании короткой ссылки.
 */
class ShortenForm extends Model
{
    /** @var string URL, введённый пользователем */
    public string $url;

    /**
     * Правила валидации:
     * 1. url обязателен — нельзя отправить пустую форму
     * 2. url должен быть валидным URL с протоколом http или https
     */
    public function rules(): array
    {
        return [
            [['url'], 'required', 'message' => 'Введите URL'],
            [['url'], 'url', 'defaultScheme' => 'https', 'message' => 'Введите корректный URL'],
        ];
    }

    /**
     * Названия полей для отображения в ошибках
     */
    public function attributeLabels(): array
    {
        return [
            'url' => 'URL',
        ];
    }

    /**
     * Проверяет доступность URL через cURL.
     *
     * Как работает:
     * 1. Отправляем HEAD-запрос (без загрузки тела страницы — экономим трафик и время)
     * 2. Ждём ответ максимум 5 секунд (CURLOPT_TIMEOUT)
     * 3. Следуем за редиректами (CURLOPT_FOLLOWLOCATION)
     * 4. Проверяем HTTP-код ответа: 2xx и 3xx = доступен
     *
     * Подводные камни:
     * - Некоторые сайты блокируют HEAD-запросы — для них делаем fallback на GET
     * - Без таймаута можно зависнуть на недоступном сервере
     * - CURLOPT_NOBODY = true — не загружаем тело ответа
     *
     * @return bool true если ресурс доступен
     */
    public function checkUrlAvailability(): bool
    {
        $ch = curl_init($this->url);

        // Не загружаем тело ответа — только заголовки (HEAD-запрос)
        curl_setopt($ch, CURLOPT_NOBODY, true);
        // Возвращаем результат, а не выводим в stdout
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Следуем за редиректами (301, 302 и т.д.)
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // Таймаут 5 секунд — не ждём вечно
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        // Таймаут на подключение — 5 секунд
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        // User-Agent — некоторые сайты блокируют запросы без него
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; LinkChecker/1.0)');
        // Не проверяем SSL-сертификат (для упрощения; в продакшне нужно проверять)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch);
        // curl_close() не вызываем — с PHP 8.0+ ресурс освобождается автоматически

        // Если cURL вернул ошибку (таймаут, DNS не найден и т.д.) — URL недоступен
        if ($error) {
            return false;
        }

        // HTTP-коды: 200-399 считаем доступными (успех + редиректы)
        return $httpCode >= 200 && $httpCode < 400;
    }
}
