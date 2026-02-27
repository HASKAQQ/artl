<?php
session_start();

$adminCategories = [];
$adminCategoryMessage = '';
$adminCategoryError = '';

try {
    $conn = new mysqli('MySQL-8.0', 'root', '');
    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    $conn->query(
        'CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            categories VARCHAR(255) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 1,
            created_by_phone VARCHAR(20) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $columnsRes = $conn->query('SHOW COLUMNS FROM categories');
    $columns = [];
    if ($columnsRes !== false) {
        while ($row = $columnsRes->fetch_assoc()) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
    }

    $columnsToAdd = [
        'is_default' => 'ALTER TABLE categories ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 1',
        'created_by_phone' => 'ALTER TABLE categories ADD COLUMN created_by_phone VARCHAR(20) DEFAULT NULL',
        'created_at' => 'ALTER TABLE categories ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($columnsToAdd as $columnName => $sql) {
        if (!isset($columns[$columnName])) {
            $conn->query($sql);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
        $newCategory = trim((string) ($_POST['new_category'] ?? ''));

        if ($newCategory === '') {
            $adminCategoryError = 'Введите название категории.';
        } elseif (mb_strlen($newCategory) > 255) {
            $adminCategoryError = 'Название категории слишком длинное.';
        } else {
            $findStmt = $conn->prepare('SELECT id FROM categories WHERE TRIM(categories) = TRIM(?) LIMIT 1');
            if ($findStmt) {
                $findStmt->bind_param('s', $newCategory);
                $findStmt->execute();
                $existing = $findStmt->get_result()->fetch_assoc();
                if ($existing) {
                    $existingId = (int) ($existing['id'] ?? 0);
                    if ($existingId > 0) {
                        $updStmt = $conn->prepare('UPDATE categories SET is_default = 1, created_by_phone = "admin" WHERE id = ?');
                        if ($updStmt) {
                            $updStmt->bind_param('i', $existingId);
                            $updStmt->execute();
                        }
                    }
                    $adminCategoryMessage = 'Категория уже была добавлена в список.';
                } else {
                    $insStmt = $conn->prepare('INSERT INTO categories (categories, is_default, created_by_phone) VALUES (?, 1, "admin")');
                    if ($insStmt) {
                        $insStmt->bind_param('s', $newCategory);
                        $insStmt->execute();
                        $adminCategoryMessage = 'Категория добавлена.';
                    }
                }
            }
        }
    }

    $conn->query(
        'DELETE c1 FROM categories c1
         INNER JOIN categories c2 ON TRIM(c1.categories) = TRIM(c2.categories) AND c1.id > c2.id'
    );

    $categoriesRes = $conn->query('SELECT id, TRIM(categories) AS categories, is_default, created_by_phone FROM categories WHERE TRIM(categories) <> "" ORDER BY is_default DESC, categories ASC');
    if ($categoriesRes !== false) {
        while ($row = $categoriesRes->fetch_assoc()) {
            $adminCategories[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['categories'] ?? ''),
                'is_default' => (int) ($row['is_default'] ?? 0) === 1,
                'created_by_phone' => (string) ($row['created_by_phone'] ?? ''),
            ];
        }
    }
} catch (Throwable $e) {
    $adminCategoryError = 'Ошибка загрузки категорий: ' . $e->getMessage();
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
    <div class="admin">
        <div class="container adm-cont">
            <div class="row justify-content-between">
                <div class="col-5">
                    <div class="main-admi-info">
                        <h2 class="admin-info-title">Всего пользователей:</h2>
                        <p class="admin-info-text">Художников: <span>42</span></p>
                        <p class="admin-info-text">Заказчиков: <span>42</span></p>
                    </div>
                </div>

                <div class="col-5">
                    <div class="main-admi-info">
                        <h2 class="admin-info-title">Активных заказов:</h2>
                        <p class="admin-info-text">В работе: <span>42</span></p>
                        <p class="admin-info-text">Завершено: <span>42</span></p>
                    </div>
                </div>

            </div>
        </div>
        <div class="container adm-cont-table">
            <div class="row">
                <div class="col-12">
                    <table class="table align-middle">
                        <thead>
                            <tr class="align-middle">
                                <th scope="col">Категории</th>
                                <th scope="col">Редактировать</th>
                                <th scope="col">Смотреть категорию</th>
                                <th scope="col">Удалить</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($adminCategoryError !== ''): ?>
                            <tr class="align-middle">
                                <td scope="row" colspan="4"><?php echo htmlspecialchars($adminCategoryError, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($adminCategoryMessage !== ''): ?>
                            <tr class="align-middle">
                                <td scope="row" colspan="4"><?php echo htmlspecialchars($adminCategoryMessage, ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($adminCategories as $category): ?>
                            <tr class="align-middle">
                                <td scope="row"><?php echo htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><img src="src/image/icons/icons8-редактировать-100 1.svg" alt=""></td>
                                <td><img src="src/image/icons/icons8-показать-100 1.svg" alt=""></td>
                                <td><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="align-middle">
                                <td scope="row">
                                    <form method="post" class="d-flex gap-2 flex-wrap">
                                        <input type="text" name="new_category" class="form-control" style="max-width:360px;" placeholder="Новая категория" maxlength="255">
                                        <button class="admin-btn" type="submit" name="add_category">Добавить категорию</button>
                                    </form>
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
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
        </div>
        <div class="container adm-cont-table">
            <div class="row">
                <div class="col-12">
                    <table class="table align-middle second-table">
                        <thead>
                            <tr class="align-middle">
                                <th scope="col">Отзывы</th>
                                <th scope="col">Удалить</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="align-middle">
                                <td scope="row">Цифровая живопись</td>
                                <td><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></td>
                            </tr>
                            <tr class="align-middle">
                                <td scope="row">Графический дизайн</td>
                                <td><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></td>
                            </tr>
                            <tr class="align-middle">
                                <td scope="row">Иллюстрация</td>
                                <td><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></td>
                            </tr>
                            <tr class="align-middle">
                                <td scope="row">Живопись и графика</td>
                                <td><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></td>
                            </tr>
                            <tr class="align-middle">
                                <td scope="row">3D-моделирование и визуализация</td>
                                <td><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></td>
                            </tr>
                            <tr class="align-middle">
                                <td scope="row">Скульптура и 3D-печать</td>
                                <td><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></td>
                            </tr>
                            <tr class="align-middle">
                                <td scope="row">Каллиграфия и леттеринг</td>
                                <td><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt=""></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>