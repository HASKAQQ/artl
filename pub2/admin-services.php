<?php
session_start();

function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    return $stmt;
}

$services = [];

try {
    $conn = new mysqli('MySQL-8.0', 'root', '');
    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    $salesSubquery = 'SELECT service_id, COUNT(*) AS sales_count FROM artist_orders GROUP BY service_id';
    $servicesStmt = prepareOrFail(
        $conn,
        'SELECT s.id, s.title, s.category, s.price, s.created_at, u.id AS artist_user_id,
                COALESCE(NULLIF(TRIM(u.name), ""), s.user_phone) AS artist_name,
                COALESCE(o.sales_count, 0) AS sales_count
         FROM artist_services s
         LEFT JOIN users u ON u.phone = s.user_phone
         LEFT JOIN (' . $salesSubquery . ') o ON o.service_id = s.id
         ORDER BY s.id DESC'
    );
    $servicesStmt->execute();
    $servicesRes = $servicesStmt->get_result();
    while ($row = $servicesRes->fetch_assoc()) {
        $services[] = $row;
    }
} catch (Throwable $e) {
    $services = [];
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARTlance — фриланс-биржа для художников</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/main.js" defer></script>
</head>

<body>
        <div class="admin-nav">
        <header class="header admin-header-no-border" id="header">
            <nav class="navbar navbar-expand-lg navbar-light bg-white">
                <div class="container nav-container">
                    <button type="button" class="menu-btn" aria-label="Открыть навигацию">
                        <img src="src/image/icons/Group 27.svg" alt="menu">
                    </button>
                    <a href="index.php" class="logo navbar-brand text-decoration-none admin-header-logo">ARTlance</a>
                    <div class="admin-user-menu" id="adminUserMenu">
                        <button class="admin-avatar-btn" id="adminAvatarBtn" type="button" aria-label="Меню администратора"></button>
                        <div class="admin-user-dropdown" id="adminUserDropdown">
                            <a href="logout.php" class="admin-user-dropdown-item">Выйти</a>
                        </div>
                    </div>
                </div>
            </nav>
        </header>

        <div class="nav-menu">
            <div class="admin-side-header">
                <button type="button" class="admin-menu-close" aria-label="Закрыть навигацию">✕</button>
            </div>
            <h1 class="admin-menu-title">Панель администратора</h1>
            <div class="admin-menu-links">
                <a href="admin-main.php" class="admin-menu-link">Главная</a>
                <a href="admin-users.php" class="admin-menu-link">Пользователи</a>
                <a href="admin-services.php" class="admin-menu-link">Услуги</a>
                <a href="admin-orders.php" class="admin-menu-link">Заказы</a>
                <a href="admin-transactions.php" class="admin-menu-link">Транзакции</a>
            </div>
        </div>
    </div>
    <div class="admin adm-t">
        <div class="container adm-cont-table">
            <div class="row">
                <div class="col-12 col-lg-6">
                    <div class="admin-search-wrapper ">
                        <input type="text" class="form-control admin-search-input" placeholder="Поиск художников">
                        <button class="admin-search-btn">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z"
                                    stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="row desktop">
                <div class="col-12">
                    <table class="table align-middle big-table">
                        <thead>
                            <tr class="align-middle">
                                <th scope="col">ID</th>
                                <th scope="col">Услуга</th>
                                <th scope="col">Художник</th>
                                <th scope="col">Категория</th>
                                <th scope="col">Цена</th>
                                <th scope="col">Продажи</th>
                                <th scope="col">Дата <br> создания</th>
                                <th scope="col" id="smol">Редактировать</th>
                                <th scope="col" id="smol">Смотреть <br> профиль</th>
                                <th scope="col" id="smol">Заблокировать</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($services) > 0): ?>
                              <?php foreach ($services as $service): ?>
                                <tr class="align-middle">
                                  <td scope=""><?php echo (int) ($service['id'] ?? 0); ?></td>
                                  <td><?php echo htmlspecialchars((string) ($service['title'] ?? 'Услуга'), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td><?php echo htmlspecialchars((string) ($service['artist_name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td><?php echo htmlspecialchars((string) ($service['category'] ?? 'Без категории'), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td><?php echo number_format((float) ($service['price'] ?? 0), 0, '.', ' '); ?>р</td>
                                  <td><?php echo (int) ($service['sales_count'] ?? 0); ?></td>
                                  <td><?php echo htmlspecialchars(date('d.m.y', strtotime((string) ($service['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></td>
                                  <td><img src="src/image/icons/icons8-редактировать-100 1.svg" alt=""></td>
                                  <td>
                                    <?php if ((int) ($service['artist_user_id'] ?? 0) > 0): ?>
                                      <a href="admin-user-profile.php?user_id=<?php echo (int) $service['artist_user_id']; ?>"><img src="src/image/icons/icons8-показать-100 1.svg" alt=""></a>
                                    <?php endif; ?>
                                  </td>
                                  <td><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></td>
                                </tr>
                              <?php endforeach; ?>
                            <?php else: ?>
                              <tr class="align-middle"><td colspan="10">Нет услуг.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="container mob">
            <div class="adm-line">
                <div class="id">
                    <div class="adm-icon-wrapper"><img src="src/image/icons/Group 28.svg" alt=""></div>
                    <div class="adm-name">ID</div>
                    <div class="adm-id-info">5201</div>
                </div>
                <div class="adm-id-menu">
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Услуга</div>
                        <div class="adm-id-info">Название услуги</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Художник</div>
                        <div class="adm-id-info">Екатерина Кравчюк</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Категория</div>
                        <div class="adm-id-info">Цифровая живопись</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Цена</div>
                        <div class="adm-id-info">30 000р</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Продажи</div>
                        <div class="adm-id-info">10</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Дата</div>
                        <div class="adm-id-info">14.10.25</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Действия</div>
                        <div class="adm-id-info actions"><img src="src/image/icons/icons8-редактировать-100 1.svg"
                                alt=""> <img src="src/image/icons/icons8-показать-100 1.svg" alt=""> <img
                                src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></div>
                    </div>
                </div>
            </div>
            <div class="adm-line">
                <div class="id">
                    <div class="adm-icon-wrapper"><img src="src/image/icons/Group 28.svg" alt=""></div>
                    <div class="adm-name">ID</div>
                    <div class="adm-id-info">5201</div>
                </div>
                <div class="adm-id-menu">
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Услуга</div>
                        <div class="adm-id-info">Название услуги</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Художник</div>
                        <div class="adm-id-info">Екатерина Кравчюк</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Категория</div>
                        <div class="adm-id-info">Цифровая живопись</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Цена</div>
                        <div class="adm-id-info">30 000р</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Продажи</div>
                        <div class="adm-id-info">10</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Дата</div>
                        <div class="adm-id-info">14.10.25</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Действия</div>
                        <div class="adm-id-info actions"><img src="src/image/icons/icons8-редактировать-100 1.svg"
                                alt=""> <img src="src/image/icons/icons8-показать-100 1.svg" alt=""> <img
                                src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></div>
                    </div>
                </div>
            </div>
            <div class="adm-line">
                <div class="id">
                    <div class="adm-icon-wrapper"><img src="src/image/icons/Group 28.svg" alt=""></div>
                    <div class="adm-name">ID</div>
                    <div class="adm-id-info">5201</div>
                </div>
                <div class="adm-id-menu">
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Услуга</div>
                        <div class="adm-id-info">Название услуги</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Художник</div>
                        <div class="adm-id-info">Екатерина Кравчюк</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Категория</div>
                        <div class="adm-id-info">Цифровая живопись</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Цена</div>
                        <div class="adm-id-info">30 000р</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Продажи</div>
                        <div class="adm-id-info">10</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Дата</div>
                        <div class="adm-id-info">14.10.25</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Действия</div>
                        <div class="adm-id-info actions"><img src="src/image/icons/icons8-редактировать-100 1.svg"
                                alt=""> <img src="src/image/icons/icons8-показать-100 1.svg" alt=""> <img
                                src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></div>
                    </div>
                </div>
            </div>
            <div class="adm-line">
                <div class="id">
                    <div class="adm-icon-wrapper"><img src="src/image/icons/Group 28.svg" alt=""></div>
                    <div class="adm-name">ID</div>
                    <div class="adm-id-info">5201</div>
                </div>
                <div class="adm-id-menu">
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Услуга</div>
                        <div class="adm-id-info">Название услуги</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Художник</div>
                        <div class="adm-id-info">Екатерина Кравчюк</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Категория</div>
                        <div class="adm-id-info">Цифровая живопись</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Цена</div>
                        <div class="adm-id-info">30 000р</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Продажи</div>
                        <div class="adm-id-info">10</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Дата</div>
                        <div class="adm-id-info">14.10.25</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Действия</div>
                        <div class="adm-id-info actions"><img src="src/image/icons/icons8-редактировать-100 1.svg"
                                alt=""> <img src="src/image/icons/icons8-показать-100 1.svg" alt=""> <img
                                src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></div>
                    </div>
                </div>
            </div>
            <div class="adm-line">
                <div class="id">
                    <div class="adm-icon-wrapper"><img src="src/image/icons/Group 28.svg" alt=""></div>
                    <div class="adm-name">ID</div>
                    <div class="adm-id-info">5201</div>
                </div>
                <div class="adm-id-menu">
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Услуга</div>
                        <div class="adm-id-info">Название услуги</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Художник</div>
                        <div class="adm-id-info">Екатерина Кравчюк</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Категория</div>
                        <div class="adm-id-info">Цифровая живопись</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Цена</div>
                        <div class="adm-id-info">30 000р</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Продажи</div>
                        <div class="adm-id-info">10</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Дата</div>
                        <div class="adm-id-info">14.10.25</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Действия</div>
                        <div class="adm-id-info actions"><img src="src/image/icons/icons8-редактировать-100 1.svg"
                                alt=""> <img src="src/image/icons/icons8-показать-100 1.svg" alt=""> <img
                                src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></div>
                    </div>
                </div>
            </div>
            <div class="adm-line">
                <div class="id">
                    <div class="adm-icon-wrapper"><img src="src/image/icons/Group 28.svg" alt=""></div>
                    <div class="adm-name">ID</div>
                    <div class="adm-id-info">5201</div>
                </div>
                <div class="adm-id-menu">
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Услуга</div>
                        <div class="adm-id-info">Название услуги</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Художник</div>
                        <div class="adm-id-info">Екатерина Кравчюк</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Категория</div>
                        <div class="adm-id-info">Цифровая живопись</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Цена</div>
                        <div class="adm-id-info">30 000р</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Продажи</div>
                        <div class="adm-id-info">10</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Дата</div>
                        <div class="adm-id-info">14.10.25</div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Действия</div>
                        <div class="adm-id-info actions"><img src="src/image/icons/icons8-редактировать-100 1.svg"
                                alt=""> <img src="src/image/icons/icons8-показать-100 1.svg" alt=""> <img
                                src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>