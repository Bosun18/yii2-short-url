<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use app\models\Url;
use app\models\UrlVisit;
use app\models\ShortenForm;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;

/**
 * UrlController — обрабатывает создание коротких ссылок и редиректы.
 *
 * Два основных действия:
 * - actionShorten: AJAX-эндпоинт для создания короткой ссылки + QR
 * - actionRedirect: переход по короткой ссылке → редирект на оригинальный URL
 */
class UrlController extends Controller
{
    /**
     * Создание короткой ссылки (AJAX-запрос).
     *
     * Алгоритм:
     * 1. Принимаем URL из POST-запроса
     * 2. Валидируем формат URL (http/https)
     * 3. Проверяем доступность ресурса через cURL
     * 4. Проверяем, не сокращали ли мы этот URL ранее (дедупликация)
     * 5. Если нет — создаём запись в БД, генерируем короткий код
     * 6. Генерируем QR-код на стороне сервера (без внешних API)
     * 7. Возвращаем JSON: короткая ссылка + QR в base64
     *
     * @return array JSON-ответ
     */
    public function actionShorten()
    {
        // Указываем Yii2, что ответ — JSON
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Создаём и заполняем форму данными из POST-запроса
        $form = new ShortenForm();
        $form->url = Yii::$app->request->post('url');

        // Шаг 1: Валидация формата URL
        if (!$form->validate()) {
            return [
                'success' => false,
                'message' => $form->getFirstError('url'),
            ];
        }

        // Шаг 2: Проверка доступности ресурса
        // HEAD-запрос с таймаутом 5 секунд
        if (!$form->checkUrlAvailability()) {
            return [
                'success' => false,
                'message' => 'Данный URL не доступен',
            ];
        }

        // Шаг 3: Дедупликация — проверяем, не сокращали ли мы этот URL ранее
        // Если URL уже есть в базе — возвращаем существующую короткую ссылку
        $url = Url::findOne(['original_url' => $form->url]);

        if (!$url) {
            // Создаём новую запись
            $url = new Url();
            $url->original_url = $form->url;
            $url->generateShortCode();

            if (!$url->save()) {
                return [
                    'success' => false,
                    'message' => 'Ошибка при сохранении ссылки',
                ];
            }
        }

        // Шаг 4: Генерация QR-кода
        $shortUrl = $url->getShortUrl();
        $qrBase64 = $this->generateQrCode($shortUrl);

        return [
            'success' => true,
            'short_url' => $shortUrl,
            'qr_code' => $qrBase64,
        ];
    }

    /**
     * Редирект по короткой ссылке.
     *
     * Когда пользователь переходит по адресу /AbCdEf:
     * 1. Ищем запись в БД по короткому коду
     * 2. Если не найдена — 404
     * 3. Записываем лог визита (IP + время)
     * 4. Инкрементируем счётчик кликов (атомарная операция)
     * 5. Делаем 302-редирект на оригинальный URL
     *
     * Почему 302, а не 301?
     * - 301 (постоянный) — браузер закеширует и больше не будет обращаться к нашему серверу
     * - 302 (временный) — каждый переход будет проходить через наш сервер, что позволяет считать клики
     *
     * @param string $code Короткий код из URL
     * @return Response
     * @throws NotFoundHttpException если код не найден
     */
    public function actionRedirect($code)
    {
        // Ищем URL по короткому коду
        $url = Url::findOne(['short_code' => $code]);

        if (!$url) {
            throw new NotFoundHttpException('Ссылка не найдена');
        }

        // Записываем лог визита
        $visit = new UrlVisit();
        $visit->url_id = $url->id;
        // Получаем реальный IP пользователя (учитываем прокси)
        $visit->ip_address = Yii::$app->request->userIP;
        $visit->visited_at = date('Y-m-d H:i:s');
        $visit->save();

        // Атомарный инкремент счётчика.
        // updateCounters() генерирует SQL: UPDATE url SET clicks_count = clicks_count + 1
        // Это безопасно при параллельных запросах (нет race condition)
        $url->updateCounters(['clicks_count' => 1]);

        // 302-редирект на оригинальный URL
        return $this->redirect($url->original_url);
    }

    /**
     * Генерирует QR-код и возвращает его как data:image/png;base64 строку.
     *
     * Используем библиотеку chillerlan/php-qrcode — работает локально,
     * без обращения к внешним API (требование задания).
     *
     * @param string $data Данные для кодирования в QR (короткая ссылка)
     * @return string QR-код в формате base64 data URI
     */
    private function generateQrCode($data)
    {
        $options = new QROptions([
            // Размер одного модуля (пикселя) QR-кода
            'scale' => 10,
            // Формат вывода — PNG через GD (класс вместо константы в v6)
            'outputInterface' => QRGdImagePNG::class,
            // Уровень коррекции ошибок: L (7%), M (15%), Q (25%), H (30%)
            // Q — хороший баланс между размером и устойчивостью к повреждениям
            'eccLevel' => EccLevel::Q,
            // Отступ вокруг QR-кода (в модулях)
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]);

        // render() возвращает data URI: data:image/png;base64,...
        return (new QRCode($options))->render($data);
    }
}
