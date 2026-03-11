<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARTlance — Вопрос-Ответ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/main.js" defer></script>
</head>

<body>

<?php include 'header.php'; ?>

    <section class="qna-section">
        <div class="container-fluid qna-container">
            <h1 class="qna-section-title">Ответы на частые вопросы</h1>

            <div class="qna-line">
                <button class="question" type="button" aria-expanded="false">
                    <span class="question-title">Как оформить заказ у художника?</span>
                    <img src="src/image/icons/Group 26.svg" alt="Открыть ответ" class="question-btn">
                </button>
                <div class="answer" aria-hidden="true">
                    <p>Откройте нужную услугу, нажмите кнопку «Купить» на странице оформления заказа, после чего заказ автоматически появится в профиле художника и в вашем профиле заказчика.</p>
                </div>
            </div>

            <div class="qna-line">
                <button class="question" type="button" aria-expanded="false">
                    <span class="question-title">Кто может менять статус заказа?</span>
                    <img src="src/image/icons/Group 26.svg" alt="Открыть ответ" class="question-btn">
                </button>
                <div class="answer" aria-hidden="true">
                    <p>Статус заказа меняет только художник, у которого заказали услугу. Заказчик видит текущий статус, но не может его редактировать.</p>
                </div>
            </div>

            <div class="qna-line">
                <button class="question" type="button" aria-expanded="false">
                    <span class="question-title">Когда можно оставить отзыв?</span>
                    <img src="src/image/icons/Group 26.svg" alt="Открыть ответ" class="question-btn">
                </button>
                <div class="answer" aria-hidden="true">
                    <p>Отзыв можно оставить после покупки услуги. Отзыв будет отображаться в профиле художника, у которого вы оформили заказ.</p>
                </div>
            </div>

            <div class="qna-line">
                <button class="question" type="button" aria-expanded="false">
                    <span class="question-title">Как связаться с заказчиком или художником?</span>
                    <img src="src/image/icons/Group 26.svg" alt="Открыть ответ" class="question-btn">
                </button>
                <div class="answer" aria-hidden="true">
                    <p>Связь происходит через контакты и соцсети, которые пользователь прикрепил в своём профиле (например, VK и email).</p>
                </div>
            </div>

            <div class="qna-line">
                <button class="question" type="button" aria-expanded="false">
                    <span class="question-title">Почему не отображается аватар?</span>
                    <img src="src/image/icons/Group 26.svg" alt="Открыть ответ" class="question-btn">
                </button>
                <div class="answer" aria-hidden="true">
                    <p>Проверьте, что изображение действительно загружено в профиль и сохранено. Если обновление не видно сразу, попробуйте перезагрузить страницу с очисткой кэша браузера.</p>
                </div>
            </div>

            <div class="qna-line">
                <button class="question" type="button" aria-expanded="false">
                    <span class="question-title">Можно ли переключаться между ролями художника и заказчика?</span>
                    <img src="src/image/icons/Group 26.svg" alt="Открыть ответ" class="question-btn">
                </button>
                <div class="answer" aria-hidden="true">
                    <p>Да, можно переключаться между ролями. Данные профиля (контакты, описание, аватар) сохраняются отдельно для каждой роли.</p>
                </div>
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
