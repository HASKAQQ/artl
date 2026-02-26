<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Профиль - ARTlance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
</head>

<body>
  <div id="header-placeholder"></div>

  <!-- Профиль -->
  <section class="profile-section">
    <div class="container py-5">
      <!-- Профильная карточка -->
      <div class="profile-card bg-white row">
        <div class="col-4 col-lg-3 profile-col-wrapper">
          <div class="profile-avatar-wrapper position-relative">
            <img src="src/image/Ellipse 2.png" alt="Avatar" class="profile-avatar" id="avatarImage">
            <div class="avatar-overlay position-absolute">
              <span class="avatar-overlay-text">Сменить<br>аватар</span>
            </div>
          </div>
          <div class="profile-balance">
            <span class="balance-label">Баланс, руб</span>
            <div class="balance-amount">
              <img src="src/image/icons/icons8-карточка-в-использовании-100 (1) 1.svg" alt="Wallet">
              <span>0</span>
            </div>
            <div class="balance-buttons d-flex justify-content-between flex-wrap">
              <button class="btn-balance">Вывести</button>
              <button class="btn-balance">Пополнить</button>
            </div>
          </div>
        </div>

        <div class="profile-info col-8 col-lg-9">
          <div class="d-flex align-items-center gap-3 mb-1">
            <input type="text" class="profile-name-input" value="Екатерина Кравчюк" id="profileName">
            <div class="profile-role-toggle">
              <button class="role-btn" data-role="artist">Художник <img
                  src="src/image/icons/icons8-кисть-100 1.svg" alt=""></button>
              <button class="role-btn active" data-role="client">Заказчик <img src="src/image/icons/icons8-заказ-100 1.svg"
                  alt=""></button>
            </div>
          </div>

          <p class="profile-registration">Дата регистрации</p>

          <textarea class="profile-description" placeholder="О себе..."></textarea>
          <button class="btn-save-profile">Сохранить</button>
        </div>

      </div>

      <!-- Заказы -->
      <div class="section-collapsible" id="ordersSection">
        <div class="section-header" onclick="toggleSection('orders')">
          <h2>Заказы</h2>
          <span class="toggle-arrow" id="ordersArrow">▼</span>
        </div>
        <div class="section-content" id="ordersContent">
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <div class="order-card bg-white">
                <img src="src/image/Rectangle 55.png" alt="Service" class="order-image">
                <div class="order-details">
                  <h3 class="order-title">Название услуги</h3>
                  <p class="order-category">3D-моделирование</p>
                  <select class="order-status">
                    <option class="orders-status-option" value="paid">Оплачен</option>
                    <option class="orders-status-option" value="in-progress" selected>В работе</option>
                    <option class="orders-status-option" value="completed">Завершено</option>
                  </select>
                  <p class="order-price">30 000р</p>
                  <p class="order-time">3 часа назад</p>

                </div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="order-card bg-white">
                <img src="src/image/Rectangle 55.png" alt="Service" class="order-image">
                <div class="order-details">
                  <h3 class="order-title">Название услуги</h3>
                  <p class="order-category">3D-моделирование</p>
                  <select class="order-status">
                    <option class="orders-status-option" value="paid">Оплачен</option>
                    <option class="orders-status-option" value="in-progress" selected>В работе</option>
                    <option class="orders-status-option" value="completed">Завершено</option>
                  </select>
                  <p class="order-price">30 000р</p>
                  <p class="order-time">3 часа назад</p>

                </div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="order-card bg-white">
                <img src="src/image/Rectangle 55.png" alt="Service" class="order-image">
                <div class="order-details">
                  <h3 class="order-title">Название услуги</h3>
                  <p class="order-category">3D-моделирование</p>
                  <select class="order-status">
                    <option class="orders-status-option" value="paid">Оплачен</option>
                    <option class="orders-status-option" value="in-progress" selected>В работе</option>
                    <option class="orders-status-option" value="completed">Завершено</option>
                  </select>
                  <p class="order-price">30 000р</p>
                  <p class="order-time">3 часа назад</p>

                </div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="order-card bg-white">
                <img src="src/image/Rectangle 55.png" alt="Service" class="order-image">
                <div class="order-details">
                  <h3 class="order-title">Название услуги</h3>
                  <p class="order-category">3D-моделирование</p>
                  <select class="order-status">
                    <option class="orders-status-option" value="paid">Оплачен</option>
                    <option class="orders-status-option" value="in-progress" selected>В работе</option>
                    <option class="orders-status-option" value="completed">Завершено</option>
                  </select>
                  <p class="order-price">30 000р</p>
                  <p class="order-time">3 часа назад</p>

                </div>
              </div>
            </div>
          </div>

          <div class="text-center mt-4">
            <button class="btn-view-all">Смотреть всё</button>
          </div>
        </div>

    </div>
  </section>

    <div id="footer-placeholder"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>