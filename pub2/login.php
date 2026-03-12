<?php
session_start();

const ADMIN_PHONE = '79930170672';


function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    return $stmt;
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
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $columns = [];
    $columnsResult = $conn->query('SHOW COLUMNS FROM users');
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $columns[$column['Field']] = true;
        }
    }

    if (!isset($columns['is_blocked'])) {
        $conn->query('ALTER TABLE users ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0');
    }

    $conn->query('UPDATE users SET role = "Художник" WHERE role = "Админ" AND phone <> "' . ADMIN_PHONE . '"');
    $conn->query('UPDATE users SET role = "Админ" WHERE phone = "' . ADMIN_PHONE . '"');

    return $conn;
}

// Обработка отправки номера телефона
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $conn = getDbConnection();

        if ($_POST['action'] === 'send_code') {
            $phone = $_POST['phone'] ?? '';
            $normalizedPhone = preg_replace('/\D+/', '', $phone);

            if (strlen($normalizedPhone) !== 11) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Номер телефона должен содержать ровно 11 цифр'
                ]);
                exit;
            }

            // Генерируем случайный 5-значный код
            $code = str_pad((string) rand(0, 99999), 5, '0', STR_PAD_LEFT);

            $stmt = prepareOrFail($conn, 'INSERT INTO phone_auth (phone, verification_code) VALUES (?, ?)');
            $stmt->bind_param('ss', $normalizedPhone, $code);
            $stmt->execute();

            // Сохраняем в сессии
            $_SESSION['verification_code'] = $code;
            $_SESSION['phone'] = $normalizedPhone;
            $_SESSION['auth_id'] = $stmt->insert_id;
            $_SESSION['code_time'] = time();

            echo json_encode([
                'success' => true,
                'code' => $code // В реальном приложении этого не должно быть!
            ]);
            exit;
        }

        if ($_POST['action'] === 'verify_code') {
            $enteredCode = $_POST['code'] ?? '';
            $sessionPhone = $_SESSION['phone'] ?? '';

            if ($sessionPhone === '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Сначала запросите код'
                ]);
                exit;
            }

            $stmt = prepareOrFail(
                $conn,
                'SELECT id, verification_code FROM phone_auth WHERE phone = ? ORDER BY id DESC LIMIT 1'
            );
            $stmt->bind_param('s', $sessionPhone);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            $savedCode = $row['verification_code'] ?? '';

            if ($row && hash_equals($savedCode, $enteredCode)) {
                $updateStmt = prepareOrFail(
                    $conn,
                    'UPDATE phone_auth SET is_verified = 1, verified_at = NOW() WHERE id = ?'
                );
                $updateStmt->bind_param('i', $row['id']);
                $updateStmt->execute();

                $createdAtStmt = prepareOrFail(
                    $conn,
                    'SELECT created_at FROM phone_auth WHERE id = ? LIMIT 1'
                );
                $createdAtStmt->bind_param('i', $row['id']);
                $createdAtStmt->execute();
                $createdAtRow = $createdAtStmt->get_result()->fetch_assoc();
                $registeredAt = (string) ($createdAtRow['created_at'] ?? date('Y-m-d H:i:s'));

                $userStmt = prepareOrFail(
                    $conn,
                    'INSERT INTO users (phone, registered_at) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE phone = phone'
                );
                $userStmt->bind_param('ss', $sessionPhone, $registeredAt);
                $userStmt->execute();

                if ($sessionPhone === ADMIN_PHONE) {
                    $setAdminStmt = prepareOrFail($conn, 'UPDATE users SET role = "Админ" WHERE phone = ?');
                    $setAdminStmt->bind_param('s', $sessionPhone);
                    $setAdminStmt->execute();
                } else {
                    $setUserRoleStmt = prepareOrFail($conn, 'UPDATE users SET role = "Художник" WHERE phone = ? AND role = "Админ"');
                    $setUserRoleStmt->bind_param('s', $sessionPhone);
                    $setUserRoleStmt->execute();
                }

                $blockedStmt = prepareOrFail($conn, 'SELECT is_blocked FROM users WHERE phone = ? LIMIT 1');
                $blockedStmt->bind_param('s', $sessionPhone);
                $blockedStmt->execute();
                $blockedRow = $blockedStmt->get_result()->fetch_assoc();
                if ($blockedRow && (int) ($blockedRow['is_blocked'] ?? 0) === 1) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Вы заблокированы администрацией сайта за нарушение правил'
                    ]);
                    exit;
                }

                $redirectUrl = 'profile-artist-edit.php';
                $_SESSION['is_admin'] = false;

                $roleStmt = prepareOrFail($conn, 'SELECT role FROM users WHERE phone = ? LIMIT 1');
                $roleStmt->bind_param('s', $sessionPhone);
                $roleStmt->execute();
                $roleRow = $roleStmt->get_result()->fetch_assoc();
                if (($roleRow['role'] ?? '') === 'Заказчик') {
                    $redirectUrl = 'profile-client-edit.php';
                }

                if ($sessionPhone === ADMIN_PHONE) {
                    $redirectUrl = 'admin-main.php';
                    $_SESSION['is_admin'] = true;
                }

                // Код правильный - создаем пользователя/сессию
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_phone'] = $sessionPhone;
                $_SESSION['user_auth_id'] = (int) $row['id'];

                echo json_encode([
                    'success' => true,
                    'redirect' => $redirectUrl
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Неверный код'
                ]);
            }
            exit;
        }
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка сервера: ' . $e->getMessage()
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - ARTlance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>

</head>
<body>
  <?php include 'header.php'; ?>

    <div class="login-page">
        <div class="container-fluid h-100">
            <div class="row justify-content-center align-items-center h-100">
                <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                    <div class="login-card" id="phoneStep">
                        <h1 class="login-title">Войти или создать профиль</h1>

                        <form id="phoneForm">
                            <div class="mb-4">
                                <input type="tel" class="form-control login-input" id="phoneInput" placeholder="Телефон" maxlength="11" inputmode="numeric" pattern="\d{11}" required>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="termsCheck" required>
                                <label class="form-check-label terms-label" for="termsCheck">
                                    Я ознакомлен(а), понимаю и принимаю <a href="#" class="terms-link">правила для художников и заказчиков</a>
                                </label>
                            </div>

                            <button type="submit" class="btn login-btn w-100">Получить код</button>
                        </form>
                    </div>

                    <div class="login-card d-none" id="codeStep">
                        <h1 class="code-title">Откройте сообщения на телефоне<br>и введите код</h1>

                        <form id="codeForm">
                            <div class="code-inputs mb-4">
                                <input type="text" class="code-input" maxlength="1" id="code1" required>
                                <input type="text" class="code-input" maxlength="1" id="code2" required>
                                <input type="text" class="code-input" maxlength="1" id="code3" required>
                                <input type="text" class="code-input" maxlength="1" id="code4" required>
                                <input type="text" class="code-input" maxlength="1" id="code5" required>
                            </div>

                            <button type="button" class="btn login-btn w-100" id="resendBtn">Запросить новый код</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

  <?php include 'footer.php'; ?>

    <script>
      const phoneInput = document.getElementById('phoneInput');
      if (phoneInput) {
        phoneInput.addEventListener('input', function () {
          const digitsOnly = this.value.replace(/\D/g, '').slice(0, 11);
          this.value = digitsOnly;
        });
      }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
