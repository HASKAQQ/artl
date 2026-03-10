<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказчикам и художникам — ARTlance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/main.js" defer></script>
</head>

<body>
    <?php include 'header.php'; ?>

    <main class="qna-section" style="background:#416d92; min-height:calc(100vh - 80px);">
        <div class="container py-4 py-md-5">
            <h1 class="qna-section-title text-white">Заказчикам и художникам</h1>

            <section class="bg-white rounded-3 p-4 p-md-5 mb-4">
                <h2 class="fw-black mb-3">Информация для заказчиков</h2>
                <ul class="mb-0" style="line-height:1.65;">
                    <li>Перейдите в «Категории» или «Художники» и выберите подходящую услугу.</li>
                    <li>На странице услуги проверьте описание, стоимость и примеры работ.</li>
                    <li>Нажмите «Купить» для оформления — после этого заказ появится у художника.</li>
                    <li>В профиле отслеживайте статус заказа: «Оплачен», «В работе», «Завершено».</li>
                    <li>После покупки можно оставить отзыв — он отображается у художника в профиле.</li>
                </ul>
            </section>

            <section class="bg-white rounded-3 p-4 p-md-5 mb-4">
                <h2 class="fw-black mb-3">Информация для художников</h2>
                <ul class="mb-0" style="line-height:1.65;">
                    <li>Заполните профиль: аватар, описание, соцсети и категории специализации.</li>
                    <li>Добавляйте услуги с понятным названием, стоимостью и сроками.</li>
                    <li>Новые заказы появляются в профиле художника автоматически.</li>
                    <li>Обновляйте статусы заказа по этапам: «Оплачен», «В работе», «Завершено».</li>
                    <li>Следите за отзывами от заказчиков для повышения доверия к профилю.</li>
                </ul>
            </section>

            <section class="bg-white rounded-3 p-4 p-md-5">
                <h2 class="fw-black mb-3">Конфиденциальность</h2>
                <p class="mb-2" style="line-height:1.65;">Мы обрабатываем данные профиля (имя, контакты, аватар и информацию аккаунта) только для работы платформы: авторизации, оформления заказов, отображения профилей и отзывов.</p>
                <p class="mb-2" style="line-height:1.65;">Контактные данные используются исключительно для взаимодействия между заказчиком и художником внутри сценариев сайта.</p>
                <p class="mb-0" style="line-height:1.65;">Рекомендуем не публиковать лишние персональные данные в описании профиля и отзывах, если это не требуется для выполнения заказа.</p>
            </section>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
