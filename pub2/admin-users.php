<?php
session_start();

const ADMIN_PHONE = '79930170672';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
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

    // Миграция старых структур users (если таблица создана раньше без нужных полей)
    $columns = [];
    $columnsResult = $conn->query('SHOW COLUMNS FROM users');
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $columns[$column['Field']] = true;
        }
    }

    if (!isset($columns['avatar_path'])) {
        $conn->query('ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL');
    }
    if (!isset($columns['is_blocked'])) {
        $conn->query('ALTER TABLE users ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0');
    }
    if (!isset($columns['role'])) {
        $conn->query('ALTER TABLE users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT "Художник"');
    }
    if (!isset($columns['registered_at'])) {
        $conn->query('ALTER TABLE users ADD COLUMN registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    // Только номер администратора может иметь роль 'Админ'
    $conn->query('UPDATE users SET role = "Художник" WHERE role = "Админ" AND phone <> "' . ADMIN_PHONE . '"');
    $conn->query('UPDATE users SET role = "Админ" WHERE phone = "' . ADMIN_PHONE . '"');

    return $conn;
}

function normalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}


function isAdminUserId(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (string) ($row['phone'] ?? '') === ADMIN_PHONE;
}

$errorMessage = '';
$successMessage = '';
$users = [];
$viewUser = null;

try {
    $conn = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($action === 'edit_phone' && $userId > 0) {
            if (isAdminUserId($conn, $userId)) {
                $errorMessage = 'Нельзя изменить номер администратора.';
            }

            $newPhone = normalizePhone((string) ($_POST['new_phone'] ?? ''));

            if ($errorMessage !== '') {
                // Действие запрещено для администратора
            } elseif (strlen($newPhone) !== 11) {
                $errorMessage = 'Номер должен содержать ровно 11 цифр.';
            } else {
                $stmt = $conn->prepare('UPDATE users SET phone = ? WHERE id = ?');
                $stmt->bind_param('si', $newPhone, $userId);
                if ($stmt->execute()) {
                    $successMessage = 'Номер пользователя обновлён.';
                } else {
                    $errorMessage = 'Не удалось обновить номер: ' . $stmt->error;
                }
            }
        }

        if ($action === 'toggle_block' && $userId > 0) {
            if (isAdminUserId($conn, $userId)) {
                $errorMessage = 'Нельзя заблокировать администратора.';
            } else {
                $stmt = $conn->prepare('UPDATE users SET is_blocked = IF(is_blocked = 1, 0, 1) WHERE id = ?');
            $stmt->bind_param('i', $userId);
                if ($stmt->execute()) {
                    $successMessage = 'Статус блокировки пользователя обновлён.';
                } else {
                    $errorMessage = 'Не удалось обновить блокировку: ' . $stmt->error;
                }
            }
        }
    }

    $query = trim((string) ($_GET['q'] ?? ''));
    $roleFilter = trim((string) ($_GET['role_filter'] ?? ''));
    $statusFilter = trim((string) ($_GET['status_filter'] ?? ''));

    $sql = 'SELECT id, name, phone, role, is_blocked, registered_at FROM users WHERE 1=1';
    $types = '';
    $params = [];

    if ($query !== '') {
        $sql .= ' AND (name LIKE ? OR phone LIKE ?)';
        $search = '%' . $query . '%';
        $types .= 'ss';
        $params[] = $search;
        $params[] = $search;
    }

    if ($roleFilter === 'artist') {
        $sql .= ' AND role = "Художник"';
    } elseif ($roleFilter === 'client') {
        $sql .= ' AND role = "Заказчик"';
    }

    if ($statusFilter === 'blocked') {
        $sql .= ' AND is_blocked = 1';
    } elseif ($statusFilter === 'active') {
        $sql .= ' AND is_blocked = 0';
    }

    $sql .= ' ORDER BY id DESC';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка загрузки пользователей: ' . $conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        throw new RuntimeException('Ошибка поиска пользователей: ' . $conn->error);
    }


    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    $nameSuggestions = [];
    $nameRes = $conn->query('SELECT DISTINCT name FROM users WHERE name IS NOT NULL AND name <> "" ORDER BY name ASC LIMIT 100');
    if ($nameRes) {
        while ($nameRow = $nameRes->fetch_assoc()) {
            $nameSuggestions[] = (string) $nameRow['name'];
        }
    }

    $viewId = (int) ($_GET['view_id'] ?? 0);
    if ($viewId > 0) {
        $stmt = $conn->prepare('SELECT id, name, phone, role, is_blocked, registered_at FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $viewId);
        $stmt->execute();
        $viewUser = $stmt->get_result()->fetch_assoc() ?: null;
    }
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
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
            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success mt-3"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="row">
                <div class="col-12 col-lg-8">
                    <form method="get">
                        <div class="admin-search-wrapper">
                            <input type="text" name="q" list="userNameSuggestions" class="form-control admin-search-input" placeholder="Поиск по имени" value="<?php echo htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="admin-search-btn" type="submit" aria-label="Поиск">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z"
                                        stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                        <datalist id="userNameSuggestions">
                            <?php foreach ($nameSuggestions as $suggestion): ?>
                                <option value="<?php echo htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8'); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div class="admin-filter-row mt-2">
                            <select name="role_filter" class="form-select">
                                <option value="">Все роли</option>
                                <option value="artist" <?php echo (($_GET['role_filter'] ?? '') === 'artist') ? 'selected' : ''; ?>>Художник</option>
                                <option value="client" <?php echo (($_GET['role_filter'] ?? '') === 'client') ? 'selected' : ''; ?>>Заказчик</option>
                            </select>
                            <select name="status_filter" class="form-select">
                                <option value="">Все статусы</option>
                                <option value="blocked" <?php echo (($_GET['status_filter'] ?? '') === 'blocked') ? 'selected' : ''; ?>>Заблокированный</option>
                                <option value="active" <?php echo (($_GET['status_filter'] ?? '') === 'active') ? 'selected' : ''; ?>>Активный</option>
                            </select>
                            <button class="btn admin-filter-apply-btn" type="submit">Применить</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="row desktop">
                <div class="col-12">
                    <table class="table align-middle big-table">
                        <thead>
                            <tr class="align-middle">
                                <th scope="col">ID</th>
                                <th scope="col">Пользователь</th>
                                <th scope="col">Телефон</th>
                                <th scope="col">Роль</th>
                                <th scope="col">Дата регистрации</th>
                                <th scope="col">Редактировать</th>
                                <th scope="col">Смотреть <br> профиль</th>
                                <th scope="col">Заблокировать</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($users) === 0): ?>
                            <tr><td colspan="8" class="text-center">Пользователи не найдены</td></tr>
                        <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="align-middle">
                                <td><?php echo (int) $user['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) ($user['name'] ?: 'Без имени'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(date('d.m.y', strtotime((string) $user['registered_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ((string) ($user['phone'] ?? '') === ADMIN_PHONE): ?>
                                    <span class="d-inline-block" title="Нельзя поменять номер">
                                        <button type="button" class="btn p-0 border-0 bg-transparent" disabled style="cursor:not-allowed;">
                                            <img src="src/image/icons/icons8-редактировать-100 1.svg" alt="Редактировать" style="opacity:0.35;">
                                        </button>
                                    </span>
                                    <?php else: ?>
                                    <button type="button" class="btn p-0 border-0 bg-transparent" onclick="editUserPhone(<?php echo (int) $user['id']; ?>, '<?php echo htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <img src="src/image/icons/icons8-редактировать-100 1.svg" alt="Редактировать">
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((string) ($user['phone'] ?? '') === ADMIN_PHONE): ?>
                                    <span class="d-inline-block" title="Вы находитесь в профиле">
                                        <button type="button" class="btn p-0 border-0 bg-transparent d-inline-block" disabled style="cursor:not-allowed;">
                                            <img src="src/image/icons/icons8-показать-100 1.svg" alt="Смотреть профиль" style="opacity:0.35;">
                                        </button>
                                    </span>
                                    <?php else: ?>
                                    <a href="admin-user-profile.php?user_id=<?php echo (int) $user['id']; ?>" class="d-inline-block">
                                        <img src="src/image/icons/icons8-показать-100 1.svg" alt="Смотреть профиль">
                                    </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="action" value="toggle_block">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                        <?php if ((string) ($user['phone'] ?? '') === ADMIN_PHONE): ?>
                                        <span class="d-inline-block" title="Нельзя заблокировать">
                                            <button type="button" class="btn p-0 border-0 bg-transparent" disabled style="cursor:not-allowed;">
                                                <img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt="Заблокировать" style="opacity: 1;">
                                            </button>
                                        </span>
                                        <?php else: ?>
                                        <button type="submit" class="btn p-0 border-0 bg-transparent" title="<?php echo ((int) $user['is_blocked'] === 1) ? 'Разблокировать' : 'Заблокировать'; ?>">
                                            <img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt="Заблокировать" style="opacity: <?php echo ((int) $user['is_blocked'] === 1) ? '0.35' : '1'; ?>;">
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="container mob">
            <?php if (count($users) === 0): ?>
            <div class="alert alert-secondary">Пользователи не найдены</div>
            <?php else: ?>
            <?php foreach ($users as $user): ?>
            <div class="adm-line">
                <div class="id">
                    <div class="adm-icon-wrapper"><img src="src/image/icons/Group 28.svg" alt=""></div>
                    <div class="adm-name">ID</div>
                    <div class="adm-id-info"><?php echo (int) $user['id']; ?></div>
                </div>
                <div class="adm-id-menu">
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Пользователь</div>
                        <div class="adm-id-info"><?php echo htmlspecialchars((string) ($user['name'] ?: 'Без имени'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Телефон</div>
                        <div class="adm-id-info"><?php echo htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Роль</div>
                        <div class="adm-id-info"><?php echo htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Дата</div>
                        <div class="adm-id-info"><?php echo htmlspecialchars(date('d.m.y', strtotime((string) $user['registered_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="adm-id-menu-line">
                        <div class="adm-icon-wrapper"></div>
                        <div class="adm-name">Действия</div>
                        <div class="adm-id-info actions d-flex gap-2">
                            <?php if ((string) ($user['phone'] ?? '') === ADMIN_PHONE): ?>
                            <span class="d-inline-block" title="Нельзя поменять номер"><button type="button" class="btn p-0 border-0 bg-transparent" disabled style="cursor:not-allowed;"><img src="src/image/icons/icons8-редактировать-100 1.svg" alt="" style="opacity:0.35;"></button></span>
                            <?php else: ?>
                            <button type="button" class="btn p-0 border-0 bg-transparent" onclick="editUserPhone(<?php echo (int) $user['id']; ?>, '<?php echo htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8'); ?>')"><img src="src/image/icons/icons8-редактировать-100 1.svg" alt=""></button>
                            <?php endif; ?>
                            <?php if ((string) ($user['phone'] ?? '') === ADMIN_PHONE): ?>
                            <span class="d-inline-block" title="Вы находитесь в профиле"><button type="button" class="btn p-0 border-0 bg-transparent" disabled style="cursor:not-allowed;"><img src="src/image/icons/icons8-показать-100 1.svg" alt="" style="opacity:0.35;"></button></span>
                            <?php else: ?>
                            <a href="admin-user-profile.php?user_id=<?php echo (int) $user['id']; ?>"><img src="src/image/icons/icons8-показать-100 1.svg" alt=""></a>
                            <?php endif; ?>
                            <form method="post" class="m-0 d-inline">
                                <input type="hidden" name="action" value="toggle_block">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                <?php if ((string) ($user['phone'] ?? '') === ADMIN_PHONE): ?>
                                <span class="d-inline-block" title="Нельзя заблокировать"><button type="button" class="btn p-0 border-0 bg-transparent" disabled style="cursor:not-allowed;"><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt="" style="opacity:1;"></button></span>
                                <?php else: ?>
                                <button type="submit" class="btn p-0 border-0 bg-transparent"><img src="src/image/icons/icons8-заблокировать-пользователя-100 1.svg" alt="" style="opacity: <?php echo ((int) $user['is_blocked'] === 1) ? '0.35' : '1'; ?>;"></button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <form id="editPhoneForm" method="post" class="d-none">
        <input type="hidden" name="action" value="edit_phone">
        <input type="hidden" name="user_id" id="editPhoneUserId">
        <input type="hidden" name="new_phone" id="editPhoneValue">
    </form>

    <?php if ($viewUser): ?>
      <div class="modal fade show" style="display:block; background: rgba(0,0,0,0.4);" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Профиль пользователя</h5>
              <a href="admin-users.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
              <p><b>ID:</b> <?php echo (int) $viewUser['id']; ?></p>
              <p><b>Имя:</b> <?php echo htmlspecialchars((string) ($viewUser['name'] ?: 'Без имени'), ENT_QUOTES, 'UTF-8'); ?></p>
              <p><b>Телефон:</b> <?php echo htmlspecialchars((string) $viewUser['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
              <p><b>Роль:</b> <?php echo htmlspecialchars((string) $viewUser['role'], ENT_QUOTES, 'UTF-8'); ?></p>
              <p><b>Дата регистрации:</b> <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string) $viewUser['registered_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
              <p><b>Статус:</b> <?php echo ((int) $viewUser['is_blocked'] === 1) ? 'Заблокирован' : 'Активен'; ?></p>
            </div>
            <div class="modal-footer">
              <a href="admin-users.php" class="btn btn-secondary">Закрыть</a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <script>
      function editUserPhone(userId, currentPhone) {
        const newPhone = prompt('Введите новый номер (11 цифр):', currentPhone || '');
        if (newPhone === null) return;

        const digits = String(newPhone).replace(/\D/g, '');
        if (digits.length !== 11) {
          alert('Номер должен содержать ровно 11 цифр.');
          return;
        }

        document.getElementById('editPhoneUserId').value = userId;
        document.getElementById('editPhoneValue').value = digits;
        document.getElementById('editPhoneForm').submit();
      }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
