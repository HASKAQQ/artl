<?php
session_start();

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    return $stmt;
}


function redirectProfileArtistWithFlash(string $message): void
{
    $_SESSION['profile_artist_flash'] = $message;
    header('Location: profile-artist-edit.php');
    exit;
}

function parsePositiveIntList(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    $parts = array_filter(array_map('trim', explode(',', $raw)), static fn($v) => $v !== '');
    $ints = [];
    foreach ($parts as $part) {
        if (ctype_digit($part)) {
            $value = (int) $part;
            if ($value > 0) {
                $ints[$value] = $value;
            }
        }
    }

    return array_values($ints);
}

function ensureUsersSocialColumns(mysqli $conn): void
{
    $result = $conn->query('SHOW COLUMNS FROM users');
    if ($result === false) {
        return;
    }

    $existing = [];
    while ($row = $result->fetch_assoc()) {
        $field = (string) ($row['Field'] ?? '');
        if ($field !== '') {
            $existing[$field] = true;
        }
    }

    $columnsToAdd = [
        'social_telegram' => 'ALTER TABLE users ADD COLUMN social_telegram VARCHAR(255) DEFAULT NULL',
        'social_whatsapp' => 'ALTER TABLE users ADD COLUMN social_whatsapp VARCHAR(255) DEFAULT NULL',
        'social_email' => 'ALTER TABLE users ADD COLUMN social_email VARCHAR(255) DEFAULT NULL',
        'about' => 'ALTER TABLE users ADD COLUMN about TEXT DEFAULT NULL',
    ];

    foreach ($columnsToAdd as $columnName => $sql) {
        if (!isset($existing[$columnName])) {
            $conn->query($sql);
        }
    }
}

function ensureCategoryTables(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            categories VARCHAR(255) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 1,
            created_by_phone VARCHAR(20) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_categories_name (categories)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $categoryColumnsResult = $conn->query('SHOW COLUMNS FROM categories');
    $categoryColumns = [];
    if ($categoryColumnsResult !== false) {
        while ($row = $categoryColumnsResult->fetch_assoc()) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $categoryColumns[$field] = true;
            }
        }
    }

    $categoryColumnsToAdd = [
        'is_default' => 'ALTER TABLE categories ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 1',
        'created_by_phone' => 'ALTER TABLE categories ADD COLUMN created_by_phone VARCHAR(20) DEFAULT NULL',
        'created_at' => 'ALTER TABLE categories ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($categoryColumnsToAdd as $columnName => $sql) {
        if (!isset($categoryColumns[$columnName])) {
            $conn->query($sql);
        }
    }

    $conn->query(
        'CREATE TABLE IF NOT EXISTS profile_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_phone VARCHAR(20) NOT NULL,
            category_id INT UNSIGNED DEFAULT NULL,
            custom_category VARCHAR(32) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_profile_category (user_phone, category_id),
            UNIQUE KEY uq_profile_custom_category (user_phone, custom_category),
            INDEX idx_profile_user_phone (user_phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $profileCategoryColumnsResult = $conn->query('SHOW COLUMNS FROM profile_categories');
    $profileCategoryColumns = [];
    if ($profileCategoryColumnsResult !== false) {
        while ($row = $profileCategoryColumnsResult->fetch_assoc()) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $profileCategoryColumns[$field] = true;
            }
        }
    }

    $profileCategoryColumnsToAdd = [
        'user_phone' => 'ALTER TABLE profile_categories ADD COLUMN user_phone VARCHAR(20) DEFAULT NULL',
        'profile_user_id' => 'ALTER TABLE profile_categories ADD COLUMN profile_user_id INT UNSIGNED DEFAULT NULL',
        'category_id' => 'ALTER TABLE profile_categories ADD COLUMN category_id INT UNSIGNED DEFAULT NULL',
        'custom_category' => 'ALTER TABLE profile_categories ADD COLUMN custom_category VARCHAR(32) DEFAULT NULL',
        'created_at' => 'ALTER TABLE profile_categories ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($profileCategoryColumnsToAdd as $columnName => $sql) {
        if (!isset($profileCategoryColumns[$columnName])) {
            $conn->query($sql);
        }
    }

    if ((isset($profileCategoryColumns['profile_user_id']) || isset($profileCategoryColumnsToAdd['profile_user_id']))
        && (isset($profileCategoryColumns['user_phone']) || isset($profileCategoryColumnsToAdd['user_phone']))) {
        $conn->query(
            'UPDATE profile_categories pc
             INNER JOIN users u ON u.id = pc.profile_user_id
             SET pc.user_phone = u.phone
             WHERE (pc.user_phone IS NULL OR pc.user_phone = "") AND pc.profile_user_id IS NOT NULL'
        );
    }

    $defaultCategories = [
        'Цифровая живопись',
        'Графический дизайн',
        'Иллюстрация',
        'Живопись и графика',
        '3D-моделирование и визуализация',
        'Скульптура и 3D-печать',
        'Каллиграфия и леттеринг',
        'Прочее',
        'Все',
    ];

    $insertDefault = prepareOrFail(
        $conn,
        'INSERT INTO categories (categories, is_default) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE is_default = VALUES(is_default)'
    );
    foreach ($defaultCategories as $categoryName) {
        $insertDefault->bind_param('s', $categoryName);
        $insertDefault->execute();
    }
}

function parseCustomCategoryList(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $result = [];
    foreach ($decoded as $item) {
        if (!is_string($item)) {
            continue;
        }
        $value = trim($item);
        if ($value === '') {
            continue;
        }

        if (mb_strlen($value) > 32) {
            $value = mb_substr($value, 0, 32);
        }
        $result[$value] = $value;
    }

    return array_values($result);
}

function getDbConnection(): mysqli
{
    $conn = new mysqli('MySQL-8.0', 'root', '');

    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    $conn->query('CREATE DATABASE IF NOT EXISTS artlance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    $conn->query(
        'CREATE TABLE IF NOT EXISTS phone_auth (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL,
            verification_code CHAR(5) NOT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verified_at DATETIME NULL,
            INDEX idx_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->query(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(255) DEFAULT NULL,
            role VARCHAR(30) NOT NULL DEFAULT "Художник",
            avatar_path VARCHAR(255) DEFAULT NULL,
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->query(
        'CREATE TABLE IF NOT EXISTS artist_services (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_phone VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            description TEXT DEFAULT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_phone (user_phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->query(
        'CREATE TABLE IF NOT EXISTS service_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id INT UNSIGNED NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_service_id (service_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->query(
        'CREATE TABLE IF NOT EXISTS portfolio_works (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_phone VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_phone (user_phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->query(
        'CREATE TABLE IF NOT EXISTS portfolio_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            work_id INT UNSIGNED NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_work_id (work_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    return $conn;
}

$userPhone = $_SESSION['user_phone'] ?? '';
$userName = '';
$userId = 0;
$avatarPath = '';
$registeredAt = '';
$telegramLink = '';
$whatsappLink = '';
$emailLink = '';
$saveMessage = '';
$errorMessage = '';
$services = [];
$portfolioWorks = [];
$allCategories = [];
$defaultCategories = [];
$selectedDefaultCategoryIds = [];
$selectedCustomCategories = [];
$aboutText = '';
$profileCategoryHasUserPhone = true;
$profileCategoryHasProfileUserId = false;

if (isset($_SESSION['profile_artist_flash'])) {
    $saveMessage = (string) $_SESSION['profile_artist_flash'];
    unset($_SESSION['profile_artist_flash']);
}

try {
    $conn = getDbConnection();

    ensureUsersSocialColumns($conn);
    ensureCategoryTables($conn);

    $profileCategoriesColumnsResult = $conn->query('SHOW COLUMNS FROM profile_categories');
    if ($profileCategoriesColumnsResult !== false) {
        $profileCategoryHasUserPhone = false;
        $profileCategoryHasProfileUserId = false;
        while ($columnRow = $profileCategoriesColumnsResult->fetch_assoc()) {
            $field = (string) ($columnRow['Field'] ?? '');
            if ($field === 'user_phone') {
                $profileCategoryHasUserPhone = true;
            }
            if ($field === 'profile_user_id') {
                $profileCategoryHasProfileUserId = true;
            }
        }
    }

    if ($userPhone !== '' && isset($_GET['set_role'])) {
        $setRole = (string) $_GET['set_role'];
        if ($setRole === 'client') {
            $stmt = prepareOrFail($conn, 'UPDATE users SET role = "Заказчик" WHERE phone = ?');
            $stmt->bind_param('s', $userPhone);
            $stmt->execute();
            header('Location: profile-client-edit.php');
            exit;
        }
        if ($setRole === 'artist') {
            $stmt = prepareOrFail($conn, 'UPDATE users SET role = "Художник" WHERE phone = ?');
            $stmt->bind_param('s', $userPhone);
            $stmt->execute();
            header('Location: profile-artist-edit.php');
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_required_name') {
        header('Content-Type: application/json');

        $name = trim((string) ($_POST['name'] ?? ''));

        if ($userPhone === '') {
            echo json_encode(['success' => false, 'message' => 'Сессия истекла. Войдите снова.']);
            exit;
        }

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Имя обязательно для заполнения.']);
            exit;
        }

        $registeredAtForInsert = date('Y-m-d H:i:s');
        $existingStmt = prepareOrFail($conn, 'SELECT registered_at FROM users WHERE phone = ? LIMIT 1');
        $existingStmt->bind_param('s', $userPhone);
        $existingStmt->execute();
        $existingRow = $existingStmt->get_result()->fetch_assoc();

        if ($existingRow && !empty($existingRow['registered_at'])) {
            $registeredAtForInsert = (string) $existingRow['registered_at'];
        } else {
            $authStmt = prepareOrFail($conn, 'SELECT created_at FROM phone_auth WHERE phone = ? ORDER BY id DESC LIMIT 1');
            $authStmt->bind_param('s', $userPhone);
            $authStmt->execute();
            $authRow = $authStmt->get_result()->fetch_assoc();
            if ($authRow && !empty($authRow['created_at'])) {
                $registeredAtForInsert = (string) $authRow['created_at'];
            }
        }

        $saveStmt = prepareOrFail(
            $conn,
            'INSERT INTO users (phone, name, role, registered_at) VALUES (?, ?, "Художник", ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role)'
        );
        $saveStmt->bind_param('sss', $userPhone, $name, $registeredAtForInsert);
        $saveStmt->execute();

        echo json_encode(['success' => true, 'name' => $name]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_social_link'])) {
        $socialType = (string) ($_POST['social_type'] ?? '');
        $socialLink = trim((string) ($_POST['social_link'] ?? ''));

        $columnMap = [
            'telegram' => 'social_telegram',
            'whatsapp' => 'social_whatsapp',
            'email' => 'social_email',
        ];

        if (!isset($columnMap[$socialType])) {
            $errorMessage = 'Неизвестный тип соцсети.';
        } elseif ($socialLink === '') {
            $errorMessage = 'Введите ссылку или почту.';
        } else {
            if ($socialType === 'email') {
                if (!filter_var($socialLink, FILTER_VALIDATE_EMAIL)) {
                    $errorMessage = 'Введите корректный email.';
                }
            } else {
                if (!preg_match('~^https?://~i', $socialLink)) {
                    $socialLink = 'https://' . $socialLink;
                }
                if (!filter_var($socialLink, FILTER_VALIDATE_URL)) {
                    $errorMessage = 'Введите корректную ссылку.';
                }
            }
        }

        if ($errorMessage === '') {
            $column = $columnMap[$socialType];
            $sql = "INSERT INTO users (phone, {$column}) VALUES (?, ?) ON DUPLICATE KEY UPDATE {$column} = VALUES({$column})";
            $stmt = prepareOrFail($conn, $sql);
            $stmt->bind_param('ss', $userPhone, $socialLink);
            $stmt->execute();
            redirectProfileArtistWithFlash('Ссылка сохранена.');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_social_link'])) {
        $socialType = (string) ($_POST['social_type'] ?? '');

        $columnMap = [
            'telegram' => 'social_telegram',
            'whatsapp' => 'social_whatsapp',
            'email' => 'social_email',
        ];

        if (!isset($columnMap[$socialType])) {
            $errorMessage = 'Неизвестный тип соцсети.';
        } else {
            $column = $columnMap[$socialType];
            $sql = "UPDATE users SET {$column} = NULL WHERE phone = ?";
            $stmt = prepareOrFail($conn, $sql);
            $stmt->bind_param('s', $userPhone);
            $stmt->execute();
            redirectProfileArtistWithFlash('Ссылка удалена.');
        }
    }

    if ($userPhone !== '') {
        $stmt = prepareOrFail($conn, 'SELECT id, name, avatar_path, registered_at, social_telegram, social_whatsapp, social_email, about FROM users WHERE phone = ? LIMIT 1');
        $stmt->bind_param('s', $userPhone);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $userId = (int) ($existing['id'] ?? 0);
            $userName = (string) ($existing['name'] ?? '');
            $avatarPath = (string) ($existing['avatar_path'] ?? '');
            $registeredAt = (string) ($existing['registered_at'] ?? '');
            $telegramLink = (string) ($existing['social_telegram'] ?? '');
            $whatsappLink = (string) ($existing['social_whatsapp'] ?? '');
            $emailLink = (string) ($existing['social_email'] ?? '');
            $aboutText = (string) ($existing['about'] ?? '');
        } else {
            $authStmt = prepareOrFail($conn, 'SELECT created_at FROM phone_auth WHERE phone = ? ORDER BY id DESC LIMIT 1');
            $authStmt->bind_param('s', $userPhone);
            $authStmt->execute();
            $authRow = $authStmt->get_result()->fetch_assoc();
            $registeredAt = (string) ($authRow['created_at'] ?? '');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['portfolio_action'])) {
        $portfolioAction = (string) $_POST['portfolio_action'];
        $workId = (int) ($_POST['work_id'] ?? 0);

        if ($portfolioAction === 'save_work') {
            $workTitle = trim((string) ($_POST['work_title'] ?? ''));
            $deletePortfolioImageIds = parsePositiveIntList((string) ($_POST['delete_portfolio_image_ids'] ?? ''));
            if ($workTitle === '') {
                $errorMessage = 'Введите название работы.';
            } else {
                if ($workId > 0) {
                    $updWork = prepareOrFail($conn, 'UPDATE portfolio_works SET title = ? WHERE id = ? AND user_phone = ?');
                    $updWork->bind_param('sis', $workTitle, $workId, $userPhone);
                    $updWork->execute();

                    if (count($deletePortfolioImageIds) > 0) {
                        $placeholders = implode(',', array_fill(0, count($deletePortfolioImageIds), '?'));
                        $types = 'i' . str_repeat('i', count($deletePortfolioImageIds));
                        $params = array_merge([$workId], $deletePortfolioImageIds);
                        $delSelected = prepareOrFail($conn, "DELETE FROM portfolio_images WHERE work_id = ? AND id IN ($placeholders)");
                        $delSelected->bind_param($types, ...$params);
                        $delSelected->execute();
                    }
                } else {
                    $insWork = prepareOrFail($conn, 'INSERT INTO portfolio_works (user_phone, title) VALUES (?, ?)');
                    $insWork->bind_param('ss', $userPhone, $workTitle);
                    $insWork->execute();
                    $workId = (int) $insWork->insert_id;
                }

                if (isset($_FILES['work_images']) && is_array($_FILES['work_images']['name'])) {
                    $uploadDir = __DIR__ . '/uploads/portfolio';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    $sortOrder = 0;
                    for ($i = 0; $i < count($_FILES['work_images']['name']); $i++) {
                        if ((int) $_FILES['work_images']['error'][$i] !== UPLOAD_ERR_OK) {
                            continue;
                        }
                        $tmpName = $_FILES['work_images']['tmp_name'][$i];
                        $mime = mime_content_type($tmpName);
                        if (!isset($allowed[$mime])) {
                            continue;
                        }
                        $fileName = 'portfolio_' . preg_replace('/\D+/', '', $userPhone) . '_' . time() . '_' . $i . '.' . $allowed[$mime];
                        $targetPath = $uploadDir . '/' . $fileName;
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $path = 'uploads/portfolio/' . $fileName;
                            $insImg = prepareOrFail($conn, 'INSERT INTO portfolio_images (work_id, image_path, sort_order) VALUES (?, ?, ?)');
                            $insImg->bind_param('isi', $workId, $path, $sortOrder);
                            $insImg->execute();
                            $sortOrder++;
                        }
                    }
                }

                redirectProfileArtistWithFlash('Работа портфолио сохранена.');
            }
        }

        if ($portfolioAction === 'delete_work' && $workId > 0) {
            $delWork = prepareOrFail($conn, 'DELETE FROM portfolio_works WHERE id = ? AND user_phone = ?');
            $delWork->bind_param('is', $workId, $userPhone);
            $delWork->execute();
            $delImgs = prepareOrFail($conn, 'DELETE FROM portfolio_images WHERE work_id = ?');
            $delImgs->bind_param('i', $workId);
            $delImgs->execute();
            redirectProfileArtistWithFlash('Работа портфолио удалена.');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_action'])) {
        $serviceAction = (string) $_POST['service_action'];

        if ($serviceAction === 'save_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $serviceTitle = trim((string) ($_POST['service_title'] ?? ''));
            $serviceCategory = trim((string) ($_POST['service_category'] ?? ''));
            $servicePrice = (float) str_replace(',', '.', (string) ($_POST['service_price'] ?? '0'));
            $serviceDescription = trim((string) ($_POST['service_description'] ?? ''));
            $deleteServiceImageIds = parsePositiveIntList((string) ($_POST['delete_service_image_ids'] ?? ''));

            if ($serviceTitle === '' || $serviceCategory === '') {
                $errorMessage = 'Для услуги нужно заполнить название и категорию.';
            } else {
                if ($serviceId > 0) {
                    $upd = prepareOrFail($conn, 'UPDATE artist_services SET title = ?, category = ?, price = ?, description = ? WHERE id = ? AND user_phone = ?');
                    $upd->bind_param('ssdsis', $serviceTitle, $serviceCategory, $servicePrice, $serviceDescription, $serviceId, $userPhone);
                    $upd->execute();

                    if (count($deleteServiceImageIds) > 0) {
                        $placeholders = implode(',', array_fill(0, count($deleteServiceImageIds), '?'));
                        $types = 'i' . str_repeat('i', count($deleteServiceImageIds));
                        $params = array_merge([$serviceId], $deleteServiceImageIds);
                        $delSelected = prepareOrFail($conn, "DELETE FROM service_images WHERE service_id = ? AND id IN ($placeholders)");
                        $delSelected->bind_param($types, ...$params);
                        $delSelected->execute();
                    }
                } else {
                    $ins = prepareOrFail($conn, 'INSERT INTO artist_services (user_phone, title, category, price, description, image_path) VALUES (?, ?, ?, ?, ?, "")');
                    $ins->bind_param('sssds', $userPhone, $serviceTitle, $serviceCategory, $servicePrice, $serviceDescription);
                    $ins->execute();
                    $serviceId = (int) $ins->insert_id;
                }

                if (isset($_FILES['service_images']) && is_array($_FILES['service_images']['name'])) {
                    $uploadDir = __DIR__ . '/uploads/services';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    $sortOrder = 0;
                    for ($i = 0; $i < count($_FILES['service_images']['name']); $i++) {
                        if ((int) $_FILES['service_images']['error'][$i] !== UPLOAD_ERR_OK) {
                            continue;
                        }
                        $tmpName = $_FILES['service_images']['tmp_name'][$i];
                        $mime = mime_content_type($tmpName);
                        if (!isset($allowed[$mime])) {
                            continue;
                        }
                        $fileName = 'service_' . preg_replace('/\D+/', '', $userPhone) . '_' . time() . '_' . $i . '.' . $allowed[$mime];
                        $targetPath = $uploadDir . '/' . $fileName;
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $path = 'uploads/services/' . $fileName;
                            $insImg = prepareOrFail($conn, 'INSERT INTO service_images (service_id, image_path, sort_order) VALUES (?, ?, ?)');
                            $insImg->bind_param('isi', $serviceId, $path, $sortOrder);
                            $insImg->execute();
                            if ($sortOrder === 0) {
                                $setMain = prepareOrFail($conn, 'UPDATE artist_services SET image_path = ? WHERE id = ?');
                                $setMain->bind_param('si', $path, $serviceId);
                                $setMain->execute();
                            }
                            $sortOrder++;
                        }
                    }
                }

                $mainImageStmt = prepareOrFail($conn, 'SELECT image_path FROM service_images WHERE service_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
                $mainImageStmt->bind_param('i', $serviceId);
                $mainImageStmt->execute();
                $mainImageRow = $mainImageStmt->get_result()->fetch_assoc();
                $mainImagePath = (string) ($mainImageRow['image_path'] ?? '');
                $setMain = prepareOrFail($conn, 'UPDATE artist_services SET image_path = ? WHERE id = ? AND user_phone = ?');
                $setMain->bind_param('sis', $mainImagePath, $serviceId, $userPhone);
                $setMain->execute();

                redirectProfileArtistWithFlash('Услуга сохранена.');
            }
        }

        if ($serviceAction === 'delete_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            if ($serviceId > 0) {
                $del = prepareOrFail($conn, 'DELETE FROM artist_services WHERE id = ? AND user_phone = ?');
                $del->bind_param('is', $serviceId, $userPhone);
                $del->execute();
                $delImgs = prepareOrFail($conn, 'DELETE FROM service_images WHERE service_id = ?');
                $delImgs->bind_param('i', $serviceId);
                $delImgs->execute();
                redirectProfileArtistWithFlash('Услуга удалена.');
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
        $name = trim((string) ($_POST['profile_name'] ?? ''));
        $about = trim((string) ($_POST['profile_description'] ?? ''));
        $selectedCategoryIds = parsePositiveIntList((string) ($_POST['selected_category_ids'] ?? ''));
        $customCategories = parseCustomCategoryList((string) ($_POST['custom_categories'] ?? ''));

        if ($name === '') {
            $errorMessage = 'Вы обязаны ввести имя перед сохранением профиля.';
        } else {
            $avatarForDb = $avatarPath;

            if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['avatar_file']['tmp_name'];
                $mime = mime_content_type($tmpName);
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                if (isset($allowed[$mime])) {
                    $uploadDir = __DIR__ . '/uploads/avatars';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileName = 'avatar_' . preg_replace('/\D+/', '', $userPhone) . '_' . time() . '.' . $allowed[$mime];
                    $targetPath = $uploadDir . '/' . $fileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $avatarForDb = 'uploads/avatars/' . $fileName;
                    }
                } else {
                    $errorMessage = 'Можно загрузить только JPG, PNG или WEBP.';
                }
            }

            if ($errorMessage === '') {
                $upsert = prepareOrFail(
                    $conn,
                    'INSERT INTO users (phone, name, role, avatar_path, about) VALUES (?, ?, "Художник", ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), avatar_path = VALUES(avatar_path), about = VALUES(about), role = VALUES(role)'
                );
                $upsert->bind_param('ssss', $userPhone, $name, $avatarForDb, $about);
                $upsert->execute();

                if ($userId <= 0) {
                    $userIdStmt = prepareOrFail($conn, 'SELECT id FROM users WHERE phone = ? LIMIT 1');
                    $userIdStmt->bind_param('s', $userPhone);
                    $userIdStmt->execute();
                    $userIdRow = $userIdStmt->get_result()->fetch_assoc();
                    $userId = (int) ($userIdRow['id'] ?? 0);
                }

                if ($profileCategoryHasUserPhone) {
                    $delProfileCategories = prepareOrFail($conn, 'DELETE FROM profile_categories WHERE user_phone = ?');
                    $delProfileCategories->bind_param('s', $userPhone);
                    $delProfileCategories->execute();
                } elseif ($profileCategoryHasProfileUserId && $userId > 0) {
                    $delProfileCategories = prepareOrFail($conn, 'DELETE FROM profile_categories WHERE profile_user_id = ?');
                    $delProfileCategories->bind_param('i', $userId);
                    $delProfileCategories->execute();
                }

                if (count($selectedCategoryIds) > 0) {
                    if ($profileCategoryHasUserPhone && $profileCategoryHasProfileUserId && $userId > 0) {
                        $insProfileCategory = prepareOrFail($conn, 'INSERT INTO profile_categories (user_phone, profile_user_id, category_id) VALUES (?, ?, ?)');
                        foreach ($selectedCategoryIds as $categoryId) {
                            $insProfileCategory->bind_param('sii', $userPhone, $userId, $categoryId);
                            $insProfileCategory->execute();
                        }
                    } elseif ($profileCategoryHasUserPhone) {
                        $insProfileCategory = prepareOrFail($conn, 'INSERT INTO profile_categories (user_phone, category_id) VALUES (?, ?)');
                        foreach ($selectedCategoryIds as $categoryId) {
                            $insProfileCategory->bind_param('si', $userPhone, $categoryId);
                            $insProfileCategory->execute();
                        }
                    } elseif ($profileCategoryHasProfileUserId && $userId > 0) {
                        $insProfileCategory = prepareOrFail($conn, 'INSERT INTO profile_categories (profile_user_id, category_id) VALUES (?, ?)');
                        foreach ($selectedCategoryIds as $categoryId) {
                            $insProfileCategory->bind_param('ii', $userId, $categoryId);
                            $insProfileCategory->execute();
                        }
                    }
                }

                if (count($customCategories) > 0) {
                    if ($profileCategoryHasUserPhone && $profileCategoryHasProfileUserId && $userId > 0) {
                        $insCustomCategory = prepareOrFail($conn, 'INSERT INTO profile_categories (user_phone, profile_user_id, custom_category) VALUES (?, ?, ?)');
                        foreach ($customCategories as $customCategory) {
                            $insCustomCategory->bind_param('sis', $userPhone, $userId, $customCategory);
                            $insCustomCategory->execute();
                        }
                    } elseif ($profileCategoryHasUserPhone) {
                        $insCustomCategory = prepareOrFail($conn, 'INSERT INTO profile_categories (user_phone, custom_category) VALUES (?, ?)');
                        foreach ($customCategories as $customCategory) {
                            $insCustomCategory->bind_param('ss', $userPhone, $customCategory);
                            $insCustomCategory->execute();
                        }
                    } elseif ($profileCategoryHasProfileUserId && $userId > 0) {
                        $insCustomCategory = prepareOrFail($conn, 'INSERT INTO profile_categories (profile_user_id, custom_category) VALUES (?, ?)');
                        foreach ($customCategories as $customCategory) {
                            $insCustomCategory->bind_param('is', $userId, $customCategory);
                            $insCustomCategory->execute();
                        }
                    }
                }

                $userName = $name;
                $avatarPath = $avatarForDb;
                $aboutText = $about;

                $stmt = prepareOrFail($conn, 'SELECT registered_at FROM users WHERE phone = ? LIMIT 1');
                $stmt->bind_param('s', $userPhone);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $registeredAt = (string) ($row['registered_at'] ?? '');

                redirectProfileArtistWithFlash('Профиль сохранён.');
            }
        }
    }
    $portfolioStmt = prepareOrFail($conn, 'SELECT id, title, created_at FROM portfolio_works WHERE user_phone = ? ORDER BY id DESC');
    $portfolioStmt->bind_param('s', $userPhone);
    $portfolioStmt->execute();
    $portfolioRes = $portfolioStmt->get_result();
    while ($work = $portfolioRes->fetch_assoc()) {
        $imgStmt = prepareOrFail($conn, 'SELECT id, image_path FROM portfolio_images WHERE work_id = ? ORDER BY sort_order ASC, id ASC');
        $workId = (int) $work['id'];
        $imgStmt->bind_param('i', $workId);
        $imgStmt->execute();
        $imgRes = $imgStmt->get_result();
        $images = [];
        $imageItems = [];
        while ($img = $imgRes->fetch_assoc()) {
            $images[] = (string) $img['image_path'];
            $imageItems[] = ['id' => (int) ($img['id'] ?? 0), 'path' => (string) ($img['image_path'] ?? '')];
        }
        $work['images'] = $images;
        $work['image_items'] = $imageItems;
        $portfolioWorks[] = $work;
    }

    $servicesStmt = prepareOrFail($conn, 'SELECT id, title, category, price, description, image_path, created_at FROM artist_services WHERE user_phone = ? ORDER BY id DESC');
    $servicesStmt->bind_param('s', $userPhone);
    $servicesStmt->execute();
    $servicesRes = $servicesStmt->get_result();
    while ($serviceRow = $servicesRes->fetch_assoc()) {
        $imgStmt = prepareOrFail($conn, 'SELECT id, image_path FROM service_images WHERE service_id = ? ORDER BY sort_order ASC, id ASC');
        $serviceId = (int) $serviceRow['id'];
        $imgStmt->bind_param('i', $serviceId);
        $imgStmt->execute();
        $imgRes = $imgStmt->get_result();
        $images = [];
        $imageItems = [];
        while ($img = $imgRes->fetch_assoc()) {
            $images[] = (string) $img['image_path'];
            $imageItems[] = ['id' => (int) ($img['id'] ?? 0), 'path' => (string) ($img['image_path'] ?? '')];
        }
        if (count($images) === 0 && (string) ($serviceRow['image_path'] ?? '') !== '') {
            $images[] = (string) $serviceRow['image_path'];
        }
        $serviceRow['images'] = $images;
        $serviceRow['image_items'] = $imageItems;
        $services[] = $serviceRow;
    }

    $categoriesStmt = prepareOrFail($conn, 'SELECT id, categories, is_default, created_by_phone FROM categories ORDER BY is_default DESC, categories ASC');
    $categoriesStmt->execute();
    $categoriesRes = $categoriesStmt->get_result();
    while ($categoryRow = $categoriesRes->fetch_assoc()) {
        $allCategories[] = [
            'id' => (int) ($categoryRow['id'] ?? 0),
            'name' => (string) ($categoryRow['categories'] ?? ''),
            'is_default' => (int) ($categoryRow['is_default'] ?? 1) === 1,
            'created_by_phone' => (string) ($categoryRow['created_by_phone'] ?? ''),
        ];
    }

    if ($profileCategoryHasUserPhone) {
        $profileCategoriesStmt = prepareOrFail($conn, 'SELECT category_id, custom_category FROM profile_categories WHERE user_phone = ?');
        $profileCategoriesStmt->bind_param('s', $userPhone);
    } elseif ($profileCategoryHasProfileUserId && $userId > 0) {
        $profileCategoriesStmt = prepareOrFail($conn, 'SELECT category_id, custom_category FROM profile_categories WHERE profile_user_id = ?');
        $profileCategoriesStmt->bind_param('i', $userId);
    } else {
        $profileCategoriesStmt = prepareOrFail($conn, 'SELECT category_id, custom_category FROM profile_categories WHERE 1 = 0');
    }
    $profileCategoriesStmt->execute();
    $profileCategoriesRes = $profileCategoriesStmt->get_result();
    while ($profileCategoryRow = $profileCategoriesRes->fetch_assoc()) {
        $categoryId = (int) ($profileCategoryRow['category_id'] ?? 0);
        $customCategory = trim((string) ($profileCategoryRow['custom_category'] ?? ''));
        if ($categoryId > 0) {
            $selectedDefaultCategoryIds[$categoryId] = $categoryId;
        }
        if ($customCategory !== '') {
            $selectedCustomCategories[$customCategory] = $customCategory;
        }
    }

    foreach ($allCategories as $categoryRow) {
        if ($categoryRow['is_default']) {
            $defaultCategories[] = $categoryRow;
        }
    }



} catch (Throwable $e) {
    $errorMessage = 'Ошибка при сохранении профиля: ' . $e->getMessage();
}

$registrationLabel = $registeredAt !== '' ? 'Дата регистрации: ' . date('d.m.Y H:i', strtotime($registeredAt)) : 'Дата регистрации: ещё не заполнена';
$avatarSrc = $avatarPath !== '' ? htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
$showNameModal = $userName === '';

$socialLinks = [
    'telegram' => $telegramLink,
    'whatsapp' => $whatsappLink,
    'email' => $emailLink,
];

$defaultCategoriesJs = json_encode($defaultCategories, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
$selectedDefaultCategoryIdsJs = json_encode(array_values($selectedDefaultCategoryIds), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
$selectedCustomCategoriesJs = json_encode(array_values($selectedCustomCategories), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
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
  <?php include 'header.php'; ?>

  <!-- Профиль -->
  <section class="profile-section">
    <div class="container py-5">
      <!-- Профильная карточка -->
      <?php if ($errorMessage !== ""): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8"); ?></div>
      <?php endif; ?>
      <?php if ($saveMessage !== ""): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($saveMessage, ENT_QUOTES, "UTF-8"); ?></div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" id="profileForm">
      <?php if ($showNameModal): ?>
        <div id="requiredNameModal" style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2000;display:flex;align-items:center;justify-content:center;padding:16px;">
          <div style="max-width:420px;width:100%;background:#fff;border-radius:16px;padding:24px;">
            <h3 class="mb-3">Заполните профиль</h3>
            <p class="mb-3">Для продолжения обязательно введите имя.</p>
            <input type="text" class="form-control mb-3" id="requiredNameInput" placeholder="Введите имя" maxlength="255">
            <button type="button" class="btn btn-dark w-100" id="requiredNameSaveBtn">Сохранить</button>
          </div>
        </div>
      <?php endif; ?>

      <div class="profile-card bg-white row">
        <div class="col-4 col-lg-3 profile-col-wrapper">
          <div class="profile-avatar-wrapper position-relative">
            <img src="<?php echo $avatarSrc; ?>" alt="Avatar" class="profile-avatar" id="avatarImage">
            <input type="file" accept="image/png,image/jpeg,image/webp" id="avatarFile" name="avatar_file" class="d-none">
            <div class="avatar-overlay position-absolute">
              <span class="avatar-overlay-text">Сменить<br>аватар</span>
            </div>
          </div>
          <div class="profile-contacts">
            <button type="button" class="contact-link-btn" onclick="openSocialLinkModal('telegram', <?php echo json_encode($socialLinks['telegram'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)"><img src="src/image/icons/icons8-телеграм-100 1.svg" alt="Telegram"></button>
            <button type="button" class="contact-link-btn" onclick="openSocialLinkModal('whatsapp', <?php echo json_encode($socialLinks['whatsapp'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)"><img src="src/image/icons/icons8-whatsapp-100 1.svg" alt="WhatsApp"></button>
            <button type="button" class="contact-link-btn" onclick="openSocialLinkModal('email', <?php echo json_encode($socialLinks['email'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)"><img src="src/image/icons/icons8-почта-100 1.svg" alt="Email"></button>
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
            <input type="text" class="profile-name-input" value="<?php echo htmlspecialchars($userName, ENT_QUOTES, "UTF-8"); ?>" id="profileName" name="profile_name" placeholder="Введите имя" required>
            <div class="profile-role-toggle">
              <button class="role-btn active" type="button" data-switch-url="profile-artist-edit.php?set_role=artist">Художник <img
                  src="src/image/icons/icons8-кисть-100 1.svg" alt=""></button>
              <button class="role-btn" type="button" data-switch-url="profile-client-edit.php?set_role=client">Заказчик <img src="src/image/icons/icons8-заказ-100 1.svg"
                  alt=""></button>
            </div>
          </div>

          <p class="profile-registration"><?php echo htmlspecialchars($registrationLabel, ENT_QUOTES, "UTF-8"); ?></p>

          <div class="profile-tags" id="profileSelectedCategories">
            <?php foreach ($defaultCategories as $category): ?>
              <?php if (isset($selectedDefaultCategoryIds[(int) $category['id']])): ?>
                <p class="profile-tag" data-category-id="<?php echo (int) $category['id']; ?>"><?php echo htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ($selectedCustomCategories as $customCategory): ?>
              <p class="profile-tag profile-tag-custom"><?php echo htmlspecialchars((string) $customCategory, ENT_QUOTES, 'UTF-8'); ?><button class="profile-tag-remove" type="button" aria-label="Удалить категорию">×</button></p>
            <?php endforeach; ?>
            <button class="profile-tag-add" type="button" id="openCategoriesModalBtn">+</button>
          </div>

          <input type="hidden" name="selected_category_ids" id="selectedCategoryIdsInput" value="<?php echo htmlspecialchars(implode(',', array_values($selectedDefaultCategoryIds)), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="custom_categories" id="customCategoriesInput" value="<?php echo htmlspecialchars(json_encode(array_values($selectedCustomCategories), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">

          <textarea class="profile-description" name="profile_description" id="profileDescription" placeholder="О себе..."><?php echo htmlspecialchars($aboutText, ENT_QUOTES, 'UTF-8'); ?></textarea>
          <button class="btn-save-profile" type="submit" name="save_profile">Сохранить</button>
        </div>

      </div>
      </form>

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

      <!-- Портфолио -->
      <div class="section-collapsible" id="portfolioSection">
        <div class="section-header" onclick="toggleSection('portfolio')">
          <div class="section-title">
            <h2>Портфолио</h2>
            <button class="btn-add-card" type="button" onclick="event.stopPropagation(); openPortfolioEditor()">+</button>
          </div>
          <div class="header-actions">
            <span class="toggle-arrow" id="portfolioArrow">▼</span>
          </div>
        </div>
        <div class="section-content" id="portfolioContent">
          <div class="gallary-wrapper row g-3">
            <?php foreach ($portfolioWorks as $work): ?>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card" onclick='openPortfolioEditor(<?php echo json_encode($work, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                <?php $workImages = (array) ($work['images'] ?? []); ?>
                <img src="<?php echo htmlspecialchars((string) (($workImages[0] ?? '') ?: 'src/image/Rectangle 55.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Portfolio" class="portfolio-image js-slider-image" data-images='<?php echo htmlspecialchars(json_encode($workImages, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>' data-index="0">
                <?php if (count($workImages) > 1): ?>
                  <button type="button" class="slider-arrow slider-arrow-left" onclick="event.stopPropagation(); slideCardImage(this, -1)">‹</button>
                  <button type="button" class="slider-arrow slider-arrow-right" onclick="event.stopPropagation(); slideCardImage(this, 1)">›</button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card add-card" onclick="openPortfolioEditor()">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Услуги -->
      <div class="section-collapsible" id="servicesSection">
        <div class="section-header" onclick="toggleSection('services')">
          <div class="section-title">
            <h2>Услуги</h2>
            <button class="btn-add-card" type="button" onclick="event.stopPropagation(); openServiceEditor()">+</button>
          </div>
          <div class="header-actions">
            <span class="toggle-arrow" id="servicesArrow">▼</span>
          </div>
        </div>
        <div class="section-content" id="servicesContent">
          <div class="services-grid row">
            <?php foreach ($services as $service): ?>
            <div class="col-6 col-lg-4">
              <div class="service-item card h-100" onclick='openServiceEditor(<?php echo json_encode($service, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                <?php $serviceImages = (array) ($service['images'] ?? []); ?>
                <img src="<?php echo htmlspecialchars((string) (($serviceImages[0] ?? '') ?: 'src/image/Rectangle 55.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Service" class="service-image js-slider-image" data-images='<?php echo htmlspecialchars(json_encode($serviceImages, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>' data-index="0">
                <?php if (count($serviceImages) > 1): ?>
                  <button type="button" class="slider-arrow slider-arrow-left" onclick="event.stopPropagation(); slideCardImage(this, -1)">‹</button>
                  <button type="button" class="slider-arrow slider-arrow-right" onclick="event.stopPropagation(); slideCardImage(this, 1)">›</button>
                <?php endif; ?>
                <div class="service-info">
                  <h3 class="service-title"><?php echo htmlspecialchars((string) $service['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                  <p class="service-category"><?php echo htmlspecialchars((string) $service['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                  <div class="service-bottom">
                    <p class="service-price">от <?php echo (int) $service['price']; ?>р</p>
                    <p class="service-time"><?php echo htmlspecialchars(date('d.m.Y', strtotime((string) $service['created_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <div class="col-6 col-lg-4">
              <div class="service-item card h-100 add-card" onclick="openServiceEditor()">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Отзывы -->
      <div class="section-collapsible" id="reviewsSection">
        <div class="section-header" onclick="toggleSection('reviews')">
          <h2>Отзывы</h2>
          <span class="toggle-arrow" id="reviewsArrow">▼</span>
        </div>
        <div class="section-content" id="reviewsContent">
          <div class="reviews-list">
            <div class="review-card">
              <img src="src/image/Ellipse 2.png" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Ермакова Мария</h4>
                <p class="review-text">Большое спасибо! Выполнено все быстро качественно. Буду обращаться еще.</p>
              </div>
            </div>

            <div class="review-card">
              <img src="" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Елько Александр</h4>
                <p class="review-text">Большое спасибо!</p>
              </div>
            </div>

            <div class="review-card">
              <img src="src/image/Ellipse 3.png" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Строгая Наталья</h4>
                <p class="review-text">Выполнено все быстро качественно. Буду обращаться еще.</p>
              </div>
            </div>

            <div class="review-card">
              <img src="src/image/Ellipse 4.png" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Лисицин Ванечка</h4>
                <p class="review-text">СУПЕР КЛАСС ЛАЙК РЕСПЕКТ. ОЧЕНЬ КРУТО СДЕЛАЛА И НЕ ДОРОГО. БЕРИТЕ НЕ
                  ПОЖАЛЕЕТЕ!!!!!!!!!!!</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Модальное окно для портфолио -->
  <div class="modal-overlay" id="portfolioModal" onclick="closeModalOnOverlay(event, 'portfolioModal')">
    <div class="modal-content" style="position:relative;">
      <button type="button" class="btn-close" style="position:absolute; top:10px; right:10px;" onclick="closePortfolioEditor()"></button>
      <h3 class="modal-title">Создание работы</h3>
      <form method="post" enctype="multipart/form-data" id="portfolioForm" class="service-modal-form">
        <input type="hidden" name="portfolio_action" value="save_work">
        <input type="hidden" name="work_id" id="workId" value="0">
        <input type="hidden" name="delete_portfolio_image_ids" id="deletePortfolioImageIds" value="">
        <input type="text" class="modal-input" name="work_title" id="workTitle" placeholder="Название работы" required>
        <div class="modal-image-upload large">
          <span id="workImagesLabel">Добавить изображения</span>
          <input type="file" name="work_images[]" id="workImages" class="d-none" accept="image/png,image/jpeg,image/webp" multiple>
        </div>
        <div class="modal-images-preview" id="workImagesPreview"></div>
        <div class="modal-buttons d-flex gap-2">
          <button class="btn-modal-save" type="submit">Сохранить</button>
          <button class="btn-modal-delete" type="button" onclick="deletePortfolioWork()">Удалить</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Модальное окно для услуг -->
  <div class="modal-overlay" id="serviceModal" onclick="closeModalOnOverlay(event, 'serviceModal')">
    <div class="modal-content modal-content-large" style="position:relative;">
      <button type="button" class="btn-close" style="position:absolute; top:10px; right:10px;" onclick="closeServiceEditor()"></button>
      <h3 class="modal-title">Создание услуги</h3>
      <form method="post" enctype="multipart/form-data" id="serviceForm" class="service-modal-form">
        <input type="hidden" name="service_action" id="serviceAction" value="save_service">
        <input type="hidden" name="service_id" id="serviceId" value="0">
        <input type="hidden" name="delete_service_image_ids" id="deleteServiceImageIds" value="">
        <div class="modal-image-upload">
          <span id="serviceImageLabel">Добавить изображения</span>
          <input type="file" name="service_images[]" id="serviceImage" class="d-none" accept="image/png,image/jpeg,image/webp" multiple>
        </div>
        <div class="modal-images-preview" id="serviceImagesPreview"></div>
        <input type="text" class="modal-input" name="service_title" id="serviceTitle" placeholder="Название услуги" required>
        <select class="modal-input" name="service_category" id="serviceCategory" required>
          <option value="">Категория</option>
          <?php foreach ($allCategories as $category): ?>
            <option value="<?php echo htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <div class="input-group mb-3">
          <span class="input-group-text">Цена</span>
          <input type="text" class="form-control" name="service_price" id="servicePrice" placeholder="0">
        </div>
        <textarea class="modal-textarea service-description-textarea" name="service_description" id="serviceDescription" placeholder="Подробное описание..."></textarea>
        <div class="modal-buttons d-flex gap-2">
          <button class="btn-modal-save" type="submit">Сохранить</button>
          <button class="btn-modal-delete" type="button" onclick="deleteServiceItem()">Удалить</button>
        </div>
      </form>
    </div>
  </div>

  <div class="dropdown-edit" id="portfolioModalDropdown"></div>

  <div class="dropdown-edit" id="serviceModalDropdown"></div>

  <div class="modal-overlay" id="socialLinkModal" onclick="closeModalOnOverlay(event, 'socialLinkModal')">
    <div class="modal-content" style="position:relative;">
      <button type="button" class="btn-close" style="position:absolute; top:10px; right:10px;" onclick="closeSocialLinkModal()"></button>
      <h3 class="modal-title" id="socialLinkTitle">Ссылка на соцсеть</h3>
      <form method="post" class="service-modal-form">
        <input type="hidden" name="social_type" id="socialType" value="telegram">
        <input type="text" class="modal-input" name="social_link" id="socialLinkInput" placeholder="Вставьте ссылку или почту" maxlength="255">
        <div class="modal-buttons d-flex gap-2">
          <button class="btn-modal-save" type="submit" name="save_social_link">Сохранить</button>
          <button class="btn-modal-delete" type="submit" name="delete_social_link">Удалить</button>
        </div>
      </form>
    </div>
  </div>
  <div class="dropdown-edit" id="socialLinkModalDropdown"></div>

  <div class="modal-overlay" id="categoriesModal" onclick="closeModalOnOverlay(event, 'categoriesModal')">
    <div class="modal-content" style="position:relative;">
      <button type="button" class="btn-close" style="position:absolute; top:10px; right:10px;" onclick="closeCategoriesModal()"></button>
      <h3 class="modal-title">Категории профиля</h3>
      <div class="categories-modal-list" id="categoriesModalDefaultList"></div>
      <div class="categories-custom-input-wrap">
        <input type="text" class="modal-input" id="newCustomCategoryInput" maxlength="32" placeholder="Добавить свою категорию (до 32 символов)">
        <button class="btn-modal-save" type="button" id="addCustomCategoryBtn">Добавить</button>
      </div>
      <div class="categories-custom-list" id="categoriesModalCustomList"></div>
      <div class="modal-buttons d-flex gap-2">
        <button class="btn-modal-save" type="button" id="saveCategoriesBtn">Сохранить</button>
      </div>
    </div>
  </div>
  <div class="dropdown-edit" id="categoriesModalDropdown"></div>

  <!-- Футер -->
    <div id="footer-placeholder"></div>

  <script>
    const avatarImageEl = document.getElementById('avatarImage');
    const avatarFileInput = document.getElementById('avatarFile');
    const avatarOverlay = document.querySelector('.avatar-overlay');

    if (avatarImageEl && avatarFileInput) {
      avatarImageEl.style.cursor = 'pointer';
      avatarImageEl.addEventListener('click', () => avatarFileInput.click());
    }

    if (avatarOverlay && avatarFileInput) {
      avatarOverlay.style.cursor = 'pointer';
      avatarOverlay.addEventListener('click', () => avatarFileInput.click());
    }

    if (avatarFileInput && avatarImageEl) {
      avatarFileInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
          const fileUrl = URL.createObjectURL(this.files[0]);
          avatarImageEl.src = fileUrl;
        }
      });
    }

    const requiredNameModal = document.getElementById('requiredNameModal');
    const requiredNameInput = document.getElementById('requiredNameInput');
    const requiredNameSaveBtn = document.getElementById('requiredNameSaveBtn');
    const profileNameInput = document.getElementById('profileName');
    const profileForm = document.getElementById('profileForm');

    if (requiredNameModal && requiredNameInput && requiredNameSaveBtn && profileNameInput && profileForm) {
      const submitNameFromModal = function () {
        const enteredName = requiredNameInput.value.trim();

        if (enteredName === '') {
          alert('Имя обязательно для заполнения.');
          requiredNameInput.focus();
          return;
        }

        const body = new URLSearchParams();
        body.set('action', 'save_required_name');
        body.set('name', enteredName);

        fetch('profile-artist-edit.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: body.toString()
        })
          .then(response => response.json())
          .then(data => {
            if (!data.success) {
              alert(data.message || 'Ошибка сохранения имени');
              return;
            }

            profileNameInput.value = data.name || enteredName;
            requiredNameModal.remove();
          })
          .catch(() => {
            alert('Ошибка сохранения имени. Попробуйте ещё раз.');
          });
      };

      requiredNameSaveBtn.addEventListener('click', submitNameFromModal);
      requiredNameInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          submitNameFromModal();
        }
      });
    }


    const serviceModalEl = document.getElementById('serviceModal');
    const socialLinkModalEl = document.getElementById('socialLinkModal');
    const socialLinkTitleEl = document.getElementById('socialLinkTitle');
    const socialTypeEl = document.getElementById('socialType');
    const socialLinkInputEl = document.getElementById('socialLinkInput');
    const serviceIdEl = document.getElementById('serviceId');
    const serviceTitleEl = document.getElementById('serviceTitle');
    const serviceCategoryEl = document.getElementById('serviceCategory');
    const servicePriceEl = document.getElementById('servicePrice');
    const serviceDescriptionEl = document.getElementById('serviceDescription');
    const serviceImageEl = document.getElementById('serviceImage');
    const serviceImageLabelEl = document.getElementById('serviceImageLabel');
    const serviceImagesPreviewEl = document.getElementById('serviceImagesPreview');
    const deleteServiceImageIdsEl = document.getElementById('deleteServiceImageIds');

    const portfolioModalEl = document.getElementById('portfolioModal');
    const workIdEl = document.getElementById('workId');
    const workTitleEl = document.getElementById('workTitle');
    const workImagesEl = document.getElementById('workImages');
    const workImagesLabelEl = document.getElementById('workImagesLabel');
    const workImagesPreviewEl = document.getElementById('workImagesPreview');
    const deletePortfolioImageIdsEl = document.getElementById('deletePortfolioImageIds');

    const pendingDeleteServiceImageIds = new Set();
    const pendingDeletePortfolioImageIds = new Set();
    let selectedServiceFiles = [];
    let selectedWorkFiles = [];
    let existingServiceImages = [];
    let existingWorkImages = [];

    const categoriesModalEl = document.getElementById('categoriesModal');
    const openCategoriesModalBtnEl = document.getElementById('openCategoriesModalBtn');
    const categoriesModalDefaultListEl = document.getElementById('categoriesModalDefaultList');
    const categoriesModalCustomListEl = document.getElementById('categoriesModalCustomList');
    const newCustomCategoryInputEl = document.getElementById('newCustomCategoryInput');
    const addCustomCategoryBtnEl = document.getElementById('addCustomCategoryBtn');
    const saveCategoriesBtnEl = document.getElementById('saveCategoriesBtn');
    const selectedCategoriesContainerEl = document.getElementById('profileSelectedCategories');
    const selectedCategoryIdsInputEl = document.getElementById('selectedCategoryIdsInput');
    const customCategoriesInputEl = document.getElementById('customCategoriesInput');

    const defaultCategoriesData = <?php echo $defaultCategoriesJs ?: '[]'; ?>;
    let selectedDefaultCategoryIdsData = new Set(<?php echo $selectedDefaultCategoryIdsJs ?: '[]'; ?>);
    let selectedCustomCategoriesData = <?php echo $selectedCustomCategoriesJs ?: '[]'; ?>;

    function syncDeletedImageIdsField(fieldEl, idsSet) {
      if (!fieldEl) return;
      fieldEl.value = Array.from(idsSet).join(',');
    }

    function renderPreviewImages(container, images, options = {}) {
      if (!container) return;
      container.innerHTML = '';
      if (!Array.isArray(images) || images.length === 0) return;

      const onRemove = typeof options.onRemove === 'function' ? options.onRemove : null;

      images.forEach((item) => {
        const src = typeof item === 'string' ? item : String(item.path || '');
        const imageId = (item && typeof item === 'object' && Number(item.id) > 0) ? Number(item.id) : null;
        const newFileIndex = (item && typeof item === 'object' && Number.isInteger(item.newFileIndex)) ? item.newFileIndex : null;
        if (src === '') return;

        const card = document.createElement('div');
        card.className = 'modal-image-thumb-wrap';

        const img = document.createElement('img');
        img.className = 'modal-image-thumb';
        img.src = src;
        img.alt = 'preview';
        card.appendChild(img);

        if (onRemove) {
          const removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'modal-image-remove-btn';
          removeBtn.textContent = '×';
          removeBtn.addEventListener('click', () => {
            onRemove({ id: imageId, src, newFileIndex });
          });
          card.appendChild(removeBtn);
        }

        container.appendChild(card);
      });
    }

    function setInputFiles(inputEl, filesArray) {
      if (!inputEl) return;
      const transfer = new DataTransfer();
      (filesArray || []).forEach((file) => transfer.items.add(file));
      inputEl.files = transfer.files;
    }

    function refreshServiceImagesPreview() {
      const visibleExisting = existingServiceImages.filter((item) => !pendingDeleteServiceImageIds.has(Number(item.id || 0)));
      const newImages = selectedServiceFiles.map((file, index) => ({
        path: URL.createObjectURL(file),
        newFileIndex: index
      }));

      renderPreviewImages(serviceImagesPreviewEl, [...visibleExisting, ...newImages], {
        onRemove: ({ id, newFileIndex }) => {
          if (id) {
            pendingDeleteServiceImageIds.add(Number(id));
            syncDeletedImageIdsField(deleteServiceImageIdsEl, pendingDeleteServiceImageIds);
          }
          if (Number.isInteger(newFileIndex)) {
            selectedServiceFiles.splice(newFileIndex, 1);
            setInputFiles(serviceImageEl, selectedServiceFiles);
            serviceImageLabelEl.textContent = selectedServiceFiles.length > 0
              ? ('Выбрано изображений: ' + selectedServiceFiles.length)
              : 'Добавить изображения';
          }
          refreshServiceImagesPreview();
        }
      });
    }

    function refreshPortfolioImagesPreview() {
      const visibleExisting = existingWorkImages.filter((item) => !pendingDeletePortfolioImageIds.has(Number(item.id || 0)));
      const newImages = selectedWorkFiles.map((file, index) => ({
        path: URL.createObjectURL(file),
        newFileIndex: index
      }));

      renderPreviewImages(workImagesPreviewEl, [...visibleExisting, ...newImages], {
        onRemove: ({ id, newFileIndex }) => {
          if (id) {
            pendingDeletePortfolioImageIds.add(Number(id));
            syncDeletedImageIdsField(deletePortfolioImageIdsEl, pendingDeletePortfolioImageIds);
          }
          if (Number.isInteger(newFileIndex)) {
            selectedWorkFiles.splice(newFileIndex, 1);
            setInputFiles(workImagesEl, selectedWorkFiles);
            workImagesLabelEl.textContent = selectedWorkFiles.length > 0
              ? ('Выбрано изображений: ' + selectedWorkFiles.length)
              : 'Добавить изображения';
          }
          refreshPortfolioImagesPreview();
        }
      });
    }

    function openServiceEditor(serviceData = null) {
      if (!serviceModalEl) return;
      if (serviceIdEl) serviceIdEl.value = '0';
      if (serviceTitleEl) serviceTitleEl.value = '';
      if (serviceCategoryEl) serviceCategoryEl.value = '';
      if (servicePriceEl) servicePriceEl.value = '';
      if (serviceDescriptionEl) serviceDescriptionEl.value = '';
      if (serviceImageEl) serviceImageEl.value = '';
      if (serviceImageLabelEl) serviceImageLabelEl.textContent = 'Добавить изображения';
      pendingDeleteServiceImageIds.clear();
      syncDeletedImageIdsField(deleteServiceImageIdsEl, pendingDeleteServiceImageIds);
      selectedServiceFiles = [];
      existingServiceImages = [];
      setInputFiles(serviceImageEl, selectedServiceFiles);

      if (serviceData && typeof serviceData === 'object') {
        if (serviceIdEl) serviceIdEl.value = String(serviceData.id || 0);
        if (serviceTitleEl) serviceTitleEl.value = String(serviceData.title || '');
        if (serviceCategoryEl) serviceCategoryEl.value = String(serviceData.category || '');
        if (servicePriceEl) servicePriceEl.value = String(serviceData.price || '');
        if (serviceDescriptionEl) serviceDescriptionEl.value = String(serviceData.description || '');

        const serviceImagesForPreview = Array.isArray(serviceData.image_items) && serviceData.image_items.length > 0
          ? serviceData.image_items
          : (Array.isArray(serviceData.images) ? serviceData.images.map((path) => ({ path })) : []);

        if (serviceImageLabelEl && serviceImagesForPreview.length > 0) {
          serviceImageLabelEl.textContent = 'Изображений: ' + serviceImagesForPreview.length;
        }
        existingServiceImages = serviceImagesForPreview;
      }

      refreshServiceImagesPreview();
      serviceModalEl.style.display = 'block';
      const serviceModalDropdown = document.getElementById('serviceModalDropdown');
      if (serviceModalDropdown) serviceModalDropdown.style.display = 'flex';
    }

    function closeServiceEditor() {
      if (serviceModalEl) serviceModalEl.style.display = 'none';
      const serviceModalDropdown = document.getElementById('serviceModalDropdown');
      if (serviceModalDropdown) serviceModalDropdown.style.display = 'none';
    }

    function openSocialLinkModal(type, currentLink) {
      if (!socialLinkModalEl || !socialTypeEl || !socialLinkInputEl) return;
      socialTypeEl.value = String(type || 'telegram');
      socialLinkInputEl.value = String(currentLink || '');

      if (socialTypeEl.value === 'telegram') {
        if (socialLinkTitleEl) socialLinkTitleEl.textContent = 'Ссылка на Telegram';
        socialLinkInputEl.placeholder = 'Вставьте ссылку на Telegram';
      } else if (socialTypeEl.value === 'whatsapp') {
        if (socialLinkTitleEl) socialLinkTitleEl.textContent = 'Ссылка на WhatsApp';
        socialLinkInputEl.placeholder = 'Вставьте ссылку на WhatsApp';
      } else {
        if (socialLinkTitleEl) socialLinkTitleEl.textContent = 'Почта';
        socialLinkInputEl.placeholder = 'Введите email';
      }

      socialLinkModalEl.style.display = 'block';
      const socialLinkModalDropdown = document.getElementById('socialLinkModalDropdown');
      if (socialLinkModalDropdown) socialLinkModalDropdown.style.display = 'flex';
    }

    function closeSocialLinkModal() {
      if (socialLinkModalEl) socialLinkModalEl.style.display = 'none';
      const socialLinkModalDropdown = document.getElementById('socialLinkModalDropdown');
      if (socialLinkModalDropdown) socialLinkModalDropdown.style.display = 'none';
    }

    function deleteServiceItem() {
      if (!serviceIdEl || serviceIdEl.value === '0') {
        alert('Сначала выберите существующую услугу для удаления.');
        return;
      }
      if (!confirm('Удалить услугу?')) return;

      const form = document.createElement('form');
      form.method = 'post';
      form.innerHTML = '<input type="hidden" name="service_action" value="delete_service">' +
        '<input type="hidden" name="service_id" value="' + serviceIdEl.value + '">';
      document.body.appendChild(form);
      form.submit();
    }

    if (serviceImageEl && serviceImageLabelEl) {
      serviceImageLabelEl.style.cursor = 'pointer';
      serviceImageLabelEl.addEventListener('click', () => serviceImageEl.click());
      serviceImageEl.addEventListener('change', function () {
        if (this.files && this.files.length > 0) {
          selectedServiceFiles = [...selectedServiceFiles, ...Array.from(this.files)];
          setInputFiles(serviceImageEl, selectedServiceFiles);
          serviceImageLabelEl.textContent = 'Выбрано изображений: ' + selectedServiceFiles.length;
          refreshServiceImagesPreview();
        }
      });
    }

    function openPortfolioEditor(workData = null) {
      if (!portfolioModalEl) return;
      if (workIdEl) workIdEl.value = '0';
      if (workTitleEl) workTitleEl.value = '';
      if (workImagesEl) workImagesEl.value = '';
      if (workImagesLabelEl) workImagesLabelEl.textContent = 'Добавить изображения';
      pendingDeletePortfolioImageIds.clear();
      syncDeletedImageIdsField(deletePortfolioImageIdsEl, pendingDeletePortfolioImageIds);
      selectedWorkFiles = [];
      existingWorkImages = [];
      setInputFiles(workImagesEl, selectedWorkFiles);

      if (workData && typeof workData === 'object') {
        if (workIdEl) workIdEl.value = String(workData.id || 0);
        if (workTitleEl) workTitleEl.value = String(workData.title || '');

        const workImagesForPreview = Array.isArray(workData.image_items) && workData.image_items.length > 0
          ? workData.image_items
          : (Array.isArray(workData.images) ? workData.images.map((path) => ({ path })) : []);

        if (workImagesLabelEl && workImagesForPreview.length > 0) {
          workImagesLabelEl.textContent = 'Изображений: ' + workImagesForPreview.length;
        }
        existingWorkImages = workImagesForPreview;
      }

      refreshPortfolioImagesPreview();
      portfolioModalEl.style.display = 'block';
      const portfolioModalDropdown = document.getElementById('portfolioModalDropdown');
      if (portfolioModalDropdown) portfolioModalDropdown.style.display = 'flex';
    }

    function closePortfolioEditor() {
      if (portfolioModalEl) portfolioModalEl.style.display = 'none';
      const portfolioModalDropdown = document.getElementById('portfolioModalDropdown');
      if (portfolioModalDropdown) portfolioModalDropdown.style.display = 'none';
    }

    function deletePortfolioWork() {
      if (!workIdEl || workIdEl.value === '0') {
        alert('Сначала выберите существующую работу для удаления.');
        return;
      }
      if (!confirm('Удалить работу портфолио?')) return;

      const form = document.createElement('form');
      form.method = 'post';
      form.innerHTML = '<input type="hidden" name="portfolio_action" value="delete_work">' +
        '<input type="hidden" name="work_id" value="' + workIdEl.value + '">';
      document.body.appendChild(form);
      form.submit();
    }

    if (workImagesEl && workImagesLabelEl) {
      workImagesLabelEl.style.cursor = 'pointer';
      workImagesLabelEl.addEventListener('click', () => workImagesEl.click());
      workImagesEl.addEventListener('change', function () {
        if (this.files && this.files.length > 0) {
          selectedWorkFiles = [...selectedWorkFiles, ...Array.from(this.files)];
          setInputFiles(workImagesEl, selectedWorkFiles);
          workImagesLabelEl.textContent = 'Выбрано изображений: ' + selectedWorkFiles.length;
          refreshPortfolioImagesPreview();
        }
      });
    }


    function renderSelectedCategoriesInProfile() {
      if (!selectedCategoriesContainerEl) return;
      selectedCategoriesContainerEl.innerHTML = '';

      defaultCategoriesData.forEach((category) => {
        const categoryId = Number(category.id || 0);
        if (!selectedDefaultCategoryIdsData.has(categoryId)) return;
        const tag = document.createElement('p');
        tag.className = 'profile-tag';
        tag.dataset.categoryId = String(categoryId);
        tag.textContent = String(category.name || '');
        selectedCategoriesContainerEl.appendChild(tag);
      });

      selectedCustomCategoriesData.forEach((categoryName, index) => {
        const tag = document.createElement('p');
        tag.className = 'profile-tag profile-tag-custom';
        tag.textContent = String(categoryName);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'profile-tag-remove';
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', () => {
          selectedCustomCategoriesData.splice(index, 1);
          syncCategoriesHiddenInputs();
          renderSelectedCategoriesInProfile();
          renderCategoriesModal();
        });

        tag.appendChild(removeBtn);
        selectedCategoriesContainerEl.appendChild(tag);
      });

      const plusBtn = document.createElement('button');
      plusBtn.className = 'profile-tag-add';
      plusBtn.type = 'button';
      plusBtn.id = 'openCategoriesModalBtn';
      plusBtn.textContent = '+';
      plusBtn.addEventListener('click', openCategoriesModal);
      selectedCategoriesContainerEl.appendChild(plusBtn);
    }

    function syncCategoriesHiddenInputs() {
      if (selectedCategoryIdsInputEl) {
        selectedCategoryIdsInputEl.value = Array.from(selectedDefaultCategoryIdsData).join(',');
      }
      if (customCategoriesInputEl) {
        customCategoriesInputEl.value = JSON.stringify(selectedCustomCategoriesData);
      }
    }

    function renderCategoriesModal() {
      if (categoriesModalDefaultListEl) {
        categoriesModalDefaultListEl.innerHTML = '';
        defaultCategoriesData.forEach((category) => {
          const categoryId = Number(category.id || 0);
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'profile-tag category-modal-tag' + (selectedDefaultCategoryIdsData.has(categoryId) ? ' active' : '');
          btn.textContent = String(category.name || '');
          btn.addEventListener('click', () => {
            if (selectedDefaultCategoryIdsData.has(categoryId)) {
              selectedDefaultCategoryIdsData.delete(categoryId);
            } else {
              selectedDefaultCategoryIdsData.add(categoryId);
            }
            renderCategoriesModal();
          });
          categoriesModalDefaultListEl.appendChild(btn);
        });
      }

      if (categoriesModalCustomListEl) {
        categoriesModalCustomListEl.innerHTML = '';
        selectedCustomCategoriesData.forEach((categoryName, index) => {
          const tag = document.createElement('p');
          tag.className = 'profile-tag profile-tag-custom';
          tag.textContent = String(categoryName);

          const removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'profile-tag-remove';
          removeBtn.textContent = '×';
          removeBtn.addEventListener('click', () => {
            selectedCustomCategoriesData.splice(index, 1);
            renderCategoriesModal();
          });

          tag.appendChild(removeBtn);
          categoriesModalCustomListEl.appendChild(tag);
        });
      }
    }

    function openCategoriesModal() {
      if (!categoriesModalEl) return;
      renderCategoriesModal();
      categoriesModalEl.style.display = 'block';
      const categoriesModalDropdown = document.getElementById('categoriesModalDropdown');
      if (categoriesModalDropdown) categoriesModalDropdown.style.display = 'flex';
    }

    function closeCategoriesModal() {
      if (categoriesModalEl) categoriesModalEl.style.display = 'none';
      const categoriesModalDropdown = document.getElementById('categoriesModalDropdown');
      if (categoriesModalDropdown) categoriesModalDropdown.style.display = 'none';
    }

    if (openCategoriesModalBtnEl) {
      openCategoriesModalBtnEl.addEventListener('click', openCategoriesModal);
    }

    if (addCustomCategoryBtnEl && newCustomCategoryInputEl) {
      addCustomCategoryBtnEl.addEventListener('click', () => {
        const value = String(newCustomCategoryInputEl.value || '').trim();
        if (value === '') return;
        if (value.length > 32) {
          alert('Категория должна быть не длиннее 32 символов.');
          return;
        }
        if (selectedCustomCategoriesData.includes(value)) {
          newCustomCategoryInputEl.value = '';
          return;
        }
        selectedCustomCategoriesData.push(value);
        newCustomCategoryInputEl.value = '';
        renderCategoriesModal();
      });
    }

    if (saveCategoriesBtnEl) {
      saveCategoriesBtnEl.addEventListener('click', () => {
        syncCategoriesHiddenInputs();
        renderSelectedCategoriesInProfile();
        closeCategoriesModal();
      });
    }

    if (newCustomCategoryInputEl) {
      newCustomCategoryInputEl.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          if (addCustomCategoryBtnEl) addCustomCategoryBtnEl.click();
        }
      });
    }

    syncCategoriesHiddenInputs();
    renderSelectedCategoriesInProfile();

    function slideCardImage(buttonEl, direction) {
      const card = buttonEl.closest('.portfolio-card, .service-item');
      if (!card) return;
      const imageEl = card.querySelector('.js-slider-image');
      if (!imageEl) return;

      let images = [];
      try {
        images = JSON.parse(imageEl.getAttribute('data-images') || '[]');
      } catch (e) {
        images = [];
      }
      if (!Array.isArray(images) || images.length <= 1) return;

      let current = parseInt(imageEl.getAttribute('data-index') || '0', 10);
      current = (current + direction + images.length) % images.length;
      imageEl.setAttribute('data-index', String(current));
      imageEl.src = images[current];
    }

  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
