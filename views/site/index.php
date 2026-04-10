<?php

/**
 * Главная страница сервиса коротких ссылок.
 *
 * Содержит:
 * - Форму ввода URL (input + кнопка «OK»)
 * - Блок вывода результата (короткая ссылка + QR-код)
 * - Блок вывода ошибок
 *
 * Всё работает через AJAX (jQuery) — без перезагрузки страницы.
 *
 * @var yii\web\View $this
 */

$this->title = 'Сервис коротких ссылок';
?>

<div class="site-index">
    <div class="row justify-content-center mt-5">
        <div class="col-lg-8">

            <!-- Заголовок и описание сервиса -->
            <div class="text-center mb-4">
                <h1 class="display-5">Сервис коротких ссылок</h1>
                <p class="text-muted">Вставьте длинную ссылку и получите короткую + QR-код</p>
            </div>

            <!--
                Форма ввода URL.
                id="shorten-form" — для привязки jQuery-обработчика.
                Форма НЕ отправляется стандартным способом — перехватываем submit через JS.
            -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <form id="shorten-form">
                        <!--
                            CSRF-токен.
                            Yii2 по умолчанию требует CSRF-токен для POST-запросов (защита от подделки запросов).
                            Передаём его в скрытом поле, а jQuery подхватит при отправке AJAX.
                        -->
                        <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">

                        <div class="input-group input-group-lg">
                            <!--
                                Поле ввода URL.
                                type="url" — браузер подсказывает, что нужен URL (но основная валидация — на сервере).
                                placeholder — подсказка для пользователя.
                            -->
                            <input type="url"
                                   id="url-input"
                                   name="url"
                                   class="form-control"
                                   placeholder="Вставьте ссылку, например https://example.com"
                                   required>
                            <!-- Кнопка отправки -->
                            <button type="submit" class="btn btn-primary" id="btn-shorten">OK</button>
                        </div>
                    </form>
                </div>
            </div>

            <!--
                Блок ошибок.
                Скрыт по умолчанию (d-none). Показывается через jQuery при ошибке.
                alert-danger — красный фон (Bootstrap).
            -->
            <div id="error-block" class="alert alert-danger mt-3 d-none" role="alert">
                <span id="error-message"></span>
            </div>

            <!--
                Блок результата: QR-код + короткая ссылка.
                Скрыт по умолчанию. Показывается после успешного создания ссылки.
            -->
            <div id="result-block" class="card mt-3 d-none shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <!-- QR-код: изображение в формате base64 (data URI) -->
                        <div class="col-md-4 text-center">
                            <img id="qr-code" src="" alt="QR-код" class="img-fluid" style="max-width: 200px;">
                        </div>
                        <!-- Короткая ссылка: кликабельная, открывается в новой вкладке -->
                        <div class="col-md-8">
                            <h5>Ваша короткая ссылка:</h5>
                            <a id="short-url" href="#" target="_blank" class="fs-4 text-break"></a>
                            <div class="mt-2">
                                <!-- Кнопка копирования ссылки в буфер обмена -->
                                <button id="btn-copy" class="btn btn-outline-secondary btn-sm">
                                    Копировать ссылку
                                </button>
                                <span id="copy-success" class="text-success ms-2 d-none">Скопировано!</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
/**
 * JavaScript-блок (jQuery).
 *
 * Почему registerJs, а не просто <script>?
 * Yii2 рекомендует использовать registerJs() — он гарантирует, что скрипт
 * будет добавлен в нужное место страницы и не продублируется при AJAX-навигации.
 *
 * POS_READY — скрипт выполнится после загрузки DOM (аналог $(document).ready()).
 */
$this->registerJs(<<<JS

    // Обработчик отправки формы
    $('#shorten-form').on('submit', function(e) {
        // Отменяем стандартную отправку формы (перезагрузку страницы)
        e.preventDefault();

        var url = $('#url-input').val().trim();

        // Простая проверка на пустое поле (на стороне клиента)
        if (!url) {
            showError('Введите URL');
            return;
        }

        // Блокируем кнопку на время запроса (защита от повторных кликов)
        var btn = $('#btn-shorten');
        btn.prop('disabled', true).text('Проверяем...');

        // Скрываем предыдущие результаты и ошибки
        $('#result-block').addClass('d-none');
        $('#error-block').addClass('d-none');

        // AJAX POST-запрос к /url/shorten
        $.ajax({
            url: '/url/shorten',
            method: 'POST',
            data: $(this).serialize(),  // Включает CSRF-токен и URL
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Успех: показываем QR-код и короткую ссылку
                    $('#qr-code').attr('src', response.qr_code);
                    $('#short-url').attr('href', response.short_url).text(response.short_url);
                    $('#result-block').removeClass('d-none');
                    // Скрываем ошибку, если была
                    $('#error-block').addClass('d-none');
                } else {
                    // Ошибка от сервера (невалидный URL, недоступен и т.д.)
                    showError(response.message);
                }
            },
            error: function() {
                // Ошибка сети / сервер не ответил
                showError('Произошла ошибка. Попробуйте позже.');
            },
            complete: function() {
                // Разблокируем кнопку в любом случае (успех или ошибка)
                btn.prop('disabled', false).text('OK');
            }
        });
    });

    // Копирование ссылки в буфер обмена
    $('#btn-copy').on('click', function() {
        var shortUrl = $('#short-url').text();
        // navigator.clipboard — современный API для работы с буфером обмена
        navigator.clipboard.writeText(shortUrl).then(function() {
            $('#copy-success').removeClass('d-none');
            // Скрываем надпись «Скопировано!» через 2 секунды
            setTimeout(function() {
                $('#copy-success').addClass('d-none');
            }, 2000);
        });
    });

    /**
     * Показывает блок с ошибкой.
     * @param {string} message Текст ошибки
     */
    function showError(message) {
        $('#error-message').text(message);
        $('#error-block').removeClass('d-none');
        $('#result-block').addClass('d-none');
    }

JS, \yii\web\View::POS_READY);
?>
