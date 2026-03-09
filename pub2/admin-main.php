<?php
session_start();

const ADMIN_PHONE = 'admin';

$adminCategories = [];
$adminCategoryMessage = '';
$adminCategoryError = '';
$editingCategoryId = (int) ($_GET['edit_id'] ?? 0);
$categoryCreatorUserIds = [];
$adminReviews = [];
$reviewSearchQuery = trim((string) ($_GET['review_q'] ?? ''));

function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }
    return $stmt;
}

function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }
    $result = $conn->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return $result !== false && $result->num_rows > 0;
}

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['category_action'] ?? '');

        if ($action === 'add') {
            $newCategory = trim((string) ($_POST['new_category'] ?? ''));
            if ($newCategory === '') {
                $adminCategoryError = 'Введите название категории.';
            } elseif (mb_strlen($newCategory) > 30) {
                $adminCategoryError = 'Название категории должно быть не длиннее 30 символов.';
            } else {
                $findStmt = prepareOrFail($conn, 'SELECT id FROM categories WHERE TRIM(categories) = TRIM(?) LIMIT 1');
                $findStmt->bind_param('s', $newCategory);
                $findStmt->execute();
                $existing = $findStmt->get_result()->fetch_assoc();

                if ($existing) {
                    $existingId = (int) ($existing['id'] ?? 0);
                    if ($existingId > 0) {
                        $updStmt = prepareOrFail($conn, 'UPDATE categories SET is_default = 1, created_by_phone = "admin" WHERE id = ?');
                        $updStmt->bind_param('i', $existingId);
                        $updStmt->execute();
                    }
                    $adminCategoryMessage = 'Категория уже была добавлена в список.';
                } else {
                    $insStmt = prepareOrFail($conn, 'INSERT INTO categories (categories, is_default, created_by_phone) VALUES (?, 1, "admin")');
                    $insStmt->bind_param('s', $newCategory);
                    $insStmt->execute();
                    $adminCategoryMessage = 'Категория добавлена.';
                }
            }
        }

        if ($action === 'save_edit') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $newCategoryName = trim((string) ($_POST['edit_category_name'] ?? ''));
            if ($categoryId <= 0 || $newCategoryName === '') {
                $adminCategoryError = 'Не удалось обновить категорию.';
            } elseif (mb_strlen($newCategoryName) > 30) {
                $adminCategoryError = 'Название категории должно быть не длиннее 30 символов.';
            } else {
                $findStmt = prepareOrFail($conn, 'SELECT id FROM categories WHERE TRIM(categories) = TRIM(?) AND id <> ? LIMIT 1');
                $findStmt->bind_param('si', $newCategoryName, $categoryId);
                $findStmt->execute();
                $existing = $findStmt->get_result()->fetch_assoc();

                if ($existing) {
                    $adminCategoryError = 'Категория с таким названием уже существует.';
                } else {
                    $updStmt = prepareOrFail($conn, 'UPDATE categories SET categories = ? WHERE id = ?');
                    $updStmt->bind_param('si', $newCategoryName, $categoryId);
                    $updStmt->execute();
                    $adminCategoryMessage = 'Категория обновлена.';
                }
            }
            $editingCategoryId = 0;
        }

        if ($action === 'delete') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            if ($categoryId > 0) {
                $delProfileLinksStmt = prepareOrFail($conn, 'DELETE FROM profile_categories WHERE category_id = ?');
                $delProfileLinksStmt->bind_param('i', $categoryId);
                $delProfileLinksStmt->execute();

                $delCategoryStmt = prepareOrFail($conn, 'DELETE FROM categories WHERE id = ?');
                $delCategoryStmt->bind_param('i', $categoryId);
                $delCategoryStmt->execute();
                $adminCategoryMessage = 'Категория удалена.';
            }
        }

        if ($action === 'delete_review' && hasColumn($conn, 'reviews', 'id')) {
            $reviewId = (int) ($_POST['review_id'] ?? 0);
            if ($reviewId > 0) {
                $deleteReviewStmt = prepareOrFail($conn, 'DELETE FROM reviews WHERE id = ? LIMIT 1');
                $deleteReviewStmt->bind_param('i', $reviewId);
                $deleteReviewStmt->execute();
                $adminCategoryMessage = 'Отзыв удалён.';
            }
        }
    }

    $conn->query(
        'DELETE c1 FROM categories c1
         INNER JOIN categories c2 ON TRIM(c1.categories) = TRIM(c2.categories) AND c1.id > c2.id'
    );

    $userMapRes = $conn->query('SELECT id, phone FROM users');
    $userIdByPhone = [];
    if ($userMapRes !== false) {
        while ($userRow = $userMapRes->fetch_assoc()) {
            $phone = trim((string) ($userRow['phone'] ?? ''));
            $id = (int) ($userRow['id'] ?? 0);
            if ($phone !== '' && $id > 0) {
                $userIdByPhone[$phone] = $id;
            }
        }
    }

    $categoriesRes = $conn->query('SELECT id, TRIM(categories) AS categories, is_default, created_by_phone FROM categories WHERE TRIM(categories) <> "" ORDER BY is_default DESC, categories ASC');
    if ($categoriesRes !== false) {
        while ($row = $categoriesRes->fetch_assoc()) {
            $createdByPhone = trim((string) ($row['created_by_phone'] ?? ''));
            $creatorUserId = $createdByPhone !== '' && isset($userIdByPhone[$createdByPhone]) ? (int) $userIdByPhone[$createdByPhone] : 0;
            $adminCategories[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['categories'] ?? ''),
                'is_default' => (int) ($row['is_default'] ?? 0) === 1,
                'created_by_phone' => $createdByPhone,
                'creator_user_id' => $creatorUserId,
            ];
        }
    }


    if (hasColumn($conn, 'reviews', 'id') && hasColumn($conn, 'reviews', 'reviews') && hasColumn($conn, 'reviews', 'user_id')) {
        $hasReviewerName = hasColumn($conn, 'reviews', 'reviewer_name');

        $reviewSql = 'SELECT r.id, r.reviews, '
            . ($hasReviewerName ? 'r.reviewer_name' : 'NULL AS reviewer_name') . ' '
            . 'FROM reviews r '
            . 'WHERE 1=1';

        $types = '';
        $params = [];

        if ($reviewSearchQuery !== '') {
            $reviewSql .= $hasReviewerName
                ? ' AND (r.reviewer_name LIKE ? OR r.reviews LIKE ?)'
                : ' AND r.reviews LIKE ?';
            $searchLike = '%' . $reviewSearchQuery . '%';
            if ($hasReviewerName) {
                $types .= 'ss';
                $params[] = $searchLike;
                $params[] = $searchLike;
            } else {
                $types .= 's';
                $params[] = $searchLike;
            }
        }

        $reviewSql .= ' ORDER BY r.id DESC';

        $reviewStmt = prepareOrFail($conn, $reviewSql);
        if ($types !== '') {
            $reviewStmt->bind_param($types, ...$params);
        }
        $reviewStmt->execute();
        $reviewRes = $reviewStmt->get_result();

        while ($reviewRow = $reviewRes->fetch_assoc()) {
            $adminReviews[] = [
                'id' => (int) ($reviewRow['id'] ?? 0),
                'reviewer_name' => trim((string) ($reviewRow['reviewer_name'] ?? '')),
                'text' => (string) ($reviewRow['reviews'] ?? ''),
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
                            <?php foreach ($adminCategories as $category): ?>
                            <tr class="align-middle">
                                <td scope="row">
                                    <?php if ($editingCategoryId === (int) $category['id']): ?>
                                        <form method="post" class="d-flex gap-2 flex-wrap align-items-center">
                                            <input type="hidden" name="category_action" value="save_edit">
                                            <input type="hidden" name="category_id" value="<?php echo (int) $category['id']; ?>">
                                            <input type="text" class="form-control" style="max-width:360px;" name="edit_category_name" maxlength="30" value="<?php echo htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="admin-btn">Сохранить</button>
                                            <a href="admin-main.php" class="admin-btn text-decoration-none" style="display:inline-flex;align-items:center;">Отмена</a>
                                        </form>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="admin-main.php?edit_id=<?php echo (int) $category['id']; ?>" aria-label="Редактировать категорию">
                                        <img src="src/image/icons/icons8-редактировать-100 1.svg" alt="">
                                    </a>
                                </td>
                                <td>
                                    <?php if ((string) $category['created_by_phone'] !== '' && (string) $category['created_by_phone'] !== 'admin' && (int) $category['creator_user_id'] > 0): ?>
                                        <a href="admin-user-profile.php?user_id=<?php echo (int) $category['creator_user_id']; ?>" aria-label="Смотреть пользователя категории">
                                            <img src="src/image/icons/icons8-показать-100 1.svg" alt="">
                                        </a>
                                    <?php else: ?>
                                        <span style="opacity:.35;display:inline-flex;" title="Для админ-категории просмотр недоступен">
                                            <img src="src/image/icons/icons8-показать-100 1.svg" alt="">
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Удалить категорию?');">
                                        <input type="hidden" name="category_action" value="delete">
                                        <input type="hidden" name="category_id" value="<?php echo (int) $category['id']; ?>">
                                        <button type="submit" style="background:transparent;border:none;padding:0;">
                                            <img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt="">
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="align-middle">
                                <td scope="row">
                                    <form method="post" class="d-flex gap-2 flex-wrap align-items-center">
                                        <input type="hidden" name="category_action" value="add">
                                        <input type="text" name="new_category" class="form-control" style="max-width:360px;" placeholder="Новая категория" maxlength="30">
                                        <button class="admin-btn" type="submit" name="add_category">Добавить категорию</button>
                                        <?php if ($adminCategoryError !== ''): ?>
                                            <span style="color:#c62828;font-weight:600;"><?php echo htmlspecialchars($adminCategoryError, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php elseif ($adminCategoryMessage !== ''): ?>
                                            <span style="color:#2e7d32;font-weight:600;"><?php echo htmlspecialchars($adminCategoryMessage, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
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
                    <form class="admin-search-wrapper" method="get">
                        <input type="text" name="review_q" class="form-control admin-search-input" placeholder="Поиск по имени и тексту отзыва" value="<?php echo htmlspecialchars($reviewSearchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="admin-search-btn" type="submit">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z"
                                    stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </form>
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
                            <?php if (count($adminReviews) > 0): ?>
                                <?php foreach ($adminReviews as $review): ?>
                                    <tr class="align-middle">
                                        <td scope="row">
                                            <b><?php echo htmlspecialchars($review['reviewer_name'] !== '' ? $review['reviewer_name'] : 'Пользователь', ENT_QUOTES, 'UTF-8'); ?></b>
                                            <br>
                                            <?php echo htmlspecialchars($review['text'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Удалить отзыв?');">
                                                <input type="hidden" name="category_action" value="delete_review">
                                                <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                                <button type="submit" style="background:transparent;border:none;padding:0;">
                                                    <img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt="">
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="align-middle">
                                    <td scope="row">Отзывов пока нет.</td>
                                    <td>—</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
