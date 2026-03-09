<?php
session_start();

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

function ensureTransactionsTable(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS admin_transactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED DEFAULT NULL,
            user_phone VARCHAR(20) DEFAULT NULL,
            user_name_snapshot VARCHAR(255) DEFAULT NULL,
            operation_type VARCHAR(100) NOT NULL DEFAULT "Пополнение",
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT "Ожидает",
            details VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_transaction_user_id (user_id),
            INDEX idx_transaction_user_phone (user_phone),
            INDEX idx_transaction_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

$transactions = [];
$errorMessage = '';
$searchQuery = trim((string) ($_GET['q'] ?? ''));

try {
    $conn = new mysqli('MySQL-8.0', 'root', '');
    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    ensureTransactionsTable($conn);

    $sql = 'SELECT t.id, t.operation_type, t.amount, t.status, t.details, t.created_at,
                   COALESCE(NULLIF(TRIM(u.name), ""), NULLIF(TRIM(t.user_name_snapshot), ""), t.user_phone, "Пользователь") AS user_name
            FROM admin_transactions t
            LEFT JOIN users u ON u.id = t.user_id
            WHERE 1=1';
    $types = '';
    $params = [];

    if ($searchQuery !== '') {
        $sql .= ' AND COALESCE(NULLIF(TRIM(u.name), ""), NULLIF(TRIM(t.user_name_snapshot), ""), t.user_phone, "") LIKE ?';
        $types .= 's';
        $params[] = '%' . $searchQuery . '%';
    }

    $sql .= ' ORDER BY t.id DESC';

    $stmt = prepareOrFail($conn, $sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $amount = (float) ($row['amount'] ?? 0);
        $transactions[] = [
            'id' => (int) ($row['id'] ?? 0),
            'user_name' => (string) ($row['user_name'] ?? 'Пользователь'),
            'operation_type' => (string) ($row['operation_type'] ?? 'Операция'),
            'amount' => ($amount >= 0 ? '+' : '-') . number_format(abs($amount), 0, '.', ' ') . 'р',
            'created_at' => (string) ($row['created_at'] ?? ''),
            'status' => (string) ($row['status'] ?? 'Ожидает'),
            'details' => trim((string) ($row['details'] ?? '')) !== '' ? (string) $row['details'] : '—',
        ];
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
    <title>Транзакции — ARTlance</title>
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
                <form class="admin-search-wrapper" method="get">
                    <input type="text" name="q" class="form-control admin-search-input" placeholder="Поиск по имени" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="admin-search-btn" type="submit" aria-label="Поиск">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="row desktop">
            <div class="col-12">
                <table class="table align-middle big-table">
                    <thead>
                    <tr class="align-middle">
                        <th scope="col">ID</th>
                        <th scope="col">Пользователь</th>
                        <th scope="col">Тип операции</th>
                        <th scope="col">Сумма</th>
                        <th scope="col">Дата</th>
                        <th scope="col">Статус</th>
                        <th scope="col" id="details">Детали</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $tr): ?>
                            <tr class="align-middle">
                                <td><?php echo (int) $tr['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $tr['user_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $tr['operation_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $tr['amount'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(date('d.m.y', strtotime((string) $tr['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $tr['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $tr['details'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">Транзакций пока нет.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mob-adm">
            <?php if (count($transactions) > 0): ?>
                <?php foreach ($transactions as $tr): ?>
                    <div class="adm-line">
                        <div class="id">
                            <div class="adm-icon-wrapper"><img src="src/image/icons/Group 28.svg" alt=""></div>
                            <div class="adm-name">ID</div>
                            <div class="adm-id-info"><?php echo (int) $tr['id']; ?></div>
                        </div>
                        <div class="adm-id-menu">
                            <div class="adm-id-menu-line"><div class="adm-icon-wrapper"></div><div class="adm-name">Пользователь</div><div class="adm-id-info"><?php echo htmlspecialchars((string) $tr['user_name'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <div class="adm-id-menu-line"><div class="adm-icon-wrapper"></div><div class="adm-name">Тип операции</div><div class="adm-id-info"><?php echo htmlspecialchars((string) $tr['operation_type'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <div class="adm-id-menu-line"><div class="adm-icon-wrapper"></div><div class="adm-name">Сумма</div><div class="adm-id-info"><?php echo htmlspecialchars((string) $tr['amount'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <div class="adm-id-menu-line"><div class="adm-icon-wrapper"></div><div class="adm-name">Дата</div><div class="adm-id-info"><?php echo htmlspecialchars(date('d.m.y', strtotime((string) $tr['created_at'])), ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <div class="adm-id-menu-line"><div class="adm-icon-wrapper"></div><div class="adm-name">Статус</div><div class="adm-id-info"><?php echo htmlspecialchars((string) $tr['status'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <div class="adm-id-menu-line"><div class="adm-icon-wrapper"></div><div class="adm-name">Детали</div><div class="adm-id-info"><?php echo htmlspecialchars((string) $tr['details'], ENT_QUOTES, 'UTF-8'); ?></div></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Транзакций пока нет.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
