<?php
require_once 'db.php';

function sendJson($payload)
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function loginFailedResponse()
{
    sendJson([
        'success' => false,
        'message' => 'Invalid username or password.'
    ]);
}

function ensureAuctionWinnersTable($pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS auction_winners (
            id BIGSERIAL PRIMARY KEY,
            laptop_id TEXT NOT NULL,
            username TEXT NOT NULL,
            amount NUMERIC NOT NULL,
            round_started_at TIMESTAMPTZ NOT NULL,
            won_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            status TEXT DEFAULT 'Won',
            UNIQUE (laptop_id, round_started_at)
        )
    ");
    
    try {
        $pdo->exec("ALTER TABLE auction_winners ADD COLUMN status TEXT DEFAULT 'Won'");
    } catch (PDOException $e) {
        // column likely exists
    }
}

function getProfileSummary($pdo, $username)
{
    ensureAuctionWinnersTable($pdo);

    $userQuery = $pdo->prepare("
        SELECT username, email
        FROM userdata
        WHERE LOWER(username) = LOWER(:username)
        LIMIT 1
    ");
    $userQuery->execute([':username' => $username]);
    $user = $userQuery->fetch(PDO::FETCH_ASSOC);

    $activeQuery = $pdo->prepare("
        WITH user_bids AS (
            SELECT laptop_id, MAX(amount) AS user_bid, MAX(created_at) AS last_bid_at
            FROM bids
            WHERE LOWER(username) = LOWER(:username)
            GROUP BY laptop_id
        ),
        auction_tops AS (
            SELECT laptop_id, MAX(amount) AS current_bid
            FROM bids
            GROUP BY laptop_id
        )
        SELECT
            user_bids.laptop_id,
            user_bids.user_bid,
            auction_tops.current_bid,
            user_bids.last_bid_at,
            CASE
                WHEN user_bids.user_bid >= auction_tops.current_bid THEN 'Leading bid'
                ELSE 'Outbid'
            END AS status
        FROM user_bids
        INNER JOIN auction_tops ON auction_tops.laptop_id = user_bids.laptop_id
        ORDER BY user_bids.last_bid_at DESC
    ");
    $activeQuery->execute([':username' => $username]);
    $activeBids = $activeQuery->fetchAll(PDO::FETCH_ASSOC);

    $winsQuery = $pdo->prepare("
        SELECT COUNT(*) AS won_items, COALESCE(SUM(amount), 0) AS total_spent
        FROM auction_winners
        WHERE LOWER(username) = LOWER(:username)
    ");
    $winsQuery->execute([':username' => $username]);
    $wins = $winsQuery->fetch(PDO::FETCH_ASSOC);

    $victoriesQuery = $pdo->prepare("
        SELECT laptop_id, amount, won_at, COALESCE(status, 'Won') as status
        FROM auction_winners
        WHERE LOWER(username) = LOWER(:username)
        ORDER BY won_at DESC
    ");
    $victoriesQuery->execute([':username' => $username]);
    $victories = $victoriesQuery->fetchAll(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'user' => [
            'username' => $user['username'] ?? $username,
            'email' => $user['email'] ?? null
        ],
        'stats' => [
            'active_bids' => count($activeBids),
            'won_items' => (int) ($wins['won_items'] ?? 0),
            'total_spent' => (float) ($wins['total_spent'] ?? 0)
        ],
        'bidding_summary' => array_map(function ($victory) {
            return [
                'laptop_id' => $victory['laptop_id'],
                'amount' => (float) $victory['amount'],
                'won_at' => $victory['won_at'],
                'status' => $victory['status']
            ];
        }, $victories)
    ];
}

if (isset($_GET['action']) && $_GET['action'] == 'getProfile') {
    try {
        $username = isset($_GET['username']) ? trim($_GET['username']) : '';

        if (!$username) {
            sendJson([
                'success' => true,
                'user' => null,
                'stats' => [
                    'active_bids' => 0,
                    'won_items' => 0,
                    'total_spent' => 0
                ],
                'bidding_summary' => []
            ]);
        }

        sendJson(getProfileSummary($pdo, $username));
    } catch (PDOException $error) {
        sendJson([
            'success' => false,
            'message' => $error->getMessage()
        ]);
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'insertUser') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : null;
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;
    $password = isset($_POST['password']) ? trim($_POST['password']) : null;

    $existingUser = $pdo->prepare("
        SELECT username
        FROM userdata
        WHERE LOWER(username) = LOWER(:username) OR LOWER(email) = LOWER(:email)
        LIMIT 1
    ");
    $existingUser->execute([
        ':username' => $username,
        ':email' => $email
    ]);

    if ($existingUser->fetch(PDO::FETCH_ASSOC)) {
        sendJson([
            'success' => false,
            'message' => 'Username or email is already registered.'
        ]);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $prepData = $pdo->prepare("INSERT INTO userdata (username, email, password) VALUES (:username, :email, :password)");
    $prepData->execute([
        ':username' => $username,
        ':email'    => $email,
        ':password' => $hashedPassword
    ]);

    if ($prepData->rowCount() > 0) {
        sendJson([
            'success' => true,
            'message' => 'User inserted',
            'username' => $username,
            'email' => $email
        ]);
    } else {
        sendJson(['success' => false, 'message' => 'Insert failed']);
    }
}
if (isset($_POST['action']) && $_POST['action'] == 'loginUser') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : null;
    $password = isset($_POST['password']) ? trim($_POST['password']) : null;

    // 1. Fetch only ONE single user row using ->fetch() instead of fetchAll()
    $prepData = $pdo->prepare("SELECT * FROM userdata WHERE username = :username");
    $prepData->execute([':username' => $username]);
    $user = $prepData->fetch(PDO::FETCH_ASSOC); // Notice: singular 'user'

    // 2. Verify if the user exists and password matches
    if ($user && password_verify($password, $user['password'])) {
        sendJson([
            'success' => true,
            'message' => 'Login successful!',
            'username' => $user['username'],
            'email' => $user['email']
        ]);
    } else {
        loginFailedResponse();
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'get_laptops') {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS laptops (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT NOT NULL,
                condition TEXT NOT NULL,
                retailed_price NUMERIC NOT NULL DEFAULT 0,
                min_increment NUMERIC NOT NULL DEFAULT 50,
                seller_name TEXT NOT NULL,
                img TEXT NOT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $laptops = $pdo->query("SELECT * FROM laptops WHERE is_active = TRUE ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        // Format to match the data.js structure
        $formatted = array_map(function($l) {
            return [
                'id'            => $l['id'],
                'name'          => $l['name'],
                'description'   => explode(' | ', $l['description']),
                'condition'     => $l['condition'],
                'retailedPriced'=> (float) $l['retailed_price'],
                'minIncrement'  => (float) $l['min_increment'],
                'sellerName'    => $l['seller_name'],
                'img'           => $l['img'],
                'currentBid'    => 0,
                'bidderCount'   => 0,
            ];
        }, $laptops);
        sendJson(['success' => true, 'laptops' => $formatted]);
    } catch (PDOException $error) {
        sendJson(['success' => false, 'message' => $error->getMessage()]);
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'get_winner_info') {
    $laptop_id = isset($_GET['laptop_id']) ? trim($_GET['laptop_id']) : '';
    $username = isset($_GET['username']) ? trim($_GET['username']) : '';

    $query = $pdo->prepare("SELECT amount, won_at FROM auction_winners WHERE laptop_id = :laptop_id AND LOWER(username) = LOWER(:username) ORDER BY won_at DESC LIMIT 1");
    $query->execute([':laptop_id' => $laptop_id, ':username' => $username]);
    $winner = $query->fetch(PDO::FETCH_ASSOC);

    sendJson(['success' => true, 'winner' => $winner]);
}

if (isset($_POST['action']) && $_POST['action'] == 'placeOrder') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $laptop_id = isset($_POST['laptop_id']) ? trim($_POST['laptop_id']) : '';

    try {
        if ($laptop_id) {
            $stmt = $pdo->prepare("UPDATE auction_winners SET status = 'Purchased' WHERE laptop_id = :laptop_id AND LOWER(username) = LOWER(:username)");
            $stmt->execute([':laptop_id' => $laptop_id, ':username' => $username]);
        } else {
            $stmt = $pdo->prepare("UPDATE auction_winners SET status = 'Purchased' WHERE status = 'Won' AND LOWER(username) = LOWER(:username)");
            $stmt->execute([':username' => $username]);
        }
        sendJson(['success' => true, 'message' => 'Order placed successfully']);
    } catch (PDOException $e) {
        sendJson(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Helper function to send a generic error

exit;
