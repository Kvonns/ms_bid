<?php
session_start();

// ─── Admin Credentials (hardcoded) ────────────────────────────────────────────
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123');

// ─── DB Connection ─────────────────────────────────────────────────────────────
require_once 'db.php';

function sendJson($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// ─── Ensure Laptops Table ──────────────────────────────────────────────────────
function ensureLaptopsTable($pdo) {
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
}

// ─── API Endpoints ─────────────────────────────────────────────────────────────
if (isset($_POST['admin_action'])) {
    if (!isset($_SESSION['admin_logged_in'])) sendJson(['success' => false, 'message' => 'Unauthorized']);

    $action = $_POST['admin_action'];

    // ── LAPTOPS ──
    if ($action === 'add_laptop') {
        ensureLaptopsTable($pdo);
        $id = trim($_POST['id']);
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $cond = trim($_POST['condition']);
        $price = floatval($_POST['retailed_price']);
        $inc = floatval($_POST['min_increment']);
        $seller = trim($_POST['seller_name']);
        $img = trim($_POST['img']);
        try {
            $pdo->prepare("INSERT INTO laptops (id,name,description,condition,retailed_price,min_increment,seller_name,img) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$id,$name,$desc,$cond,$price,$inc,$seller,$img]);
            sendJson(['success' => true]);
        } catch (PDOException $e) { sendJson(['success' => false, 'message' => $e->getMessage()]); }
    }

    if ($action === 'edit_laptop') {
        ensureLaptopsTable($pdo);
        try {
            $pdo->prepare("UPDATE laptops SET name=?,description=?,condition=?,retailed_price=?,min_increment=?,seller_name=?,img=? WHERE id=?")
                ->execute([trim($_POST['name']),trim($_POST['description']),trim($_POST['condition']),floatval($_POST['retailed_price']),floatval($_POST['min_increment']),trim($_POST['seller_name']),trim($_POST['img']),trim($_POST['id'])]);
            sendJson(['success' => true]);
        } catch (PDOException $e) { sendJson(['success' => false, 'message' => $e->getMessage()]); }
    }

    if ($action === 'delete_laptop') {
        try {
            $pdo->prepare("DELETE FROM laptops WHERE id=?")->execute([trim($_POST['id'])]);
            sendJson(['success' => true]);
        } catch (PDOException $e) { sendJson(['success' => false, 'message' => $e->getMessage()]); }
    }

    // ── USERS ──
    if ($action === 'delete_user') {
        try {
            $pdo->prepare("DELETE FROM userdata WHERE username=?")->execute([trim($_POST['username'])]);
            sendJson(['success' => true]);
        } catch (PDOException $e) { sendJson(['success' => false, 'message' => $e->getMessage()]); }
    }

    // ── BIDS ──
    if ($action === 'clear_bids') {
        try {
            if (!empty($_POST['laptop_id'])) {
                $pdo->prepare("DELETE FROM bids WHERE laptop_id=?")->execute([trim($_POST['laptop_id'])]);
            } else {
                $pdo->exec("DELETE FROM bids");
            }
            sendJson(['success' => true]);
        } catch (PDOException $e) { sendJson(['success' => false, 'message' => $e->getMessage()]); }
    }

    // ── ORDERS ──
    if ($action === 'update_order_status') {
        try {
            $pdo->prepare("UPDATE auction_winners SET status=? WHERE id=?")->execute([trim($_POST['status']),intval($_POST['id'])]);
            sendJson(['success' => true]);
        } catch (PDOException $e) { sendJson(['success' => false, 'message' => $e->getMessage()]); }
    }
}

// ─── GET data endpoints ────────────────────────────────────────────────────────
if (isset($_GET['admin_action'])) {
    if (!isset($_SESSION['admin_logged_in'])) sendJson(['success' => false, 'message' => 'Unauthorized']);

    if ($_GET['admin_action'] === 'get_data') {
        ensureLaptopsTable($pdo);

        $users = $pdo->query("SELECT id, username, email, created_at FROM userdata ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $laptops = $pdo->query("SELECT * FROM laptops ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $bids = $pdo->query("SELECT b.*, l.name as laptop_name FROM bids b LEFT JOIN laptops l ON b.laptop_id = l.id ORDER BY b.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        $orders = $pdo->query("SELECT aw.*, l.name as laptop_name FROM auction_winners aw LEFT JOIN laptops l ON aw.laptop_id = l.id ORDER BY aw.won_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        $totalBidAmount = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM auction_winners")->fetchColumn();
        $totalUsers = count($users);
        $totalLaptops = count($laptops);
        $totalOrders = count($orders);

        sendJson([
            'success' => true,
            'stats' => ['users' => $totalUsers, 'laptops' => $totalLaptops, 'orders' => $totalOrders, 'revenue' => (float)$totalBidAmount],
            'users' => $users,
            'laptops' => $laptops,
            'bids' => $bids,
            'orders' => $orders
        ]);
    }
}

// ─── Admin Login / Logout ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Invalid admin credentials.';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$isLoggedIn = isset($_SESSION['admin_logged_in']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"/>
  <title>Ms_BidD — Admin Panel</title>
  <style>
    :root {
      --bg: #060d14;
      --bg2: #0b1822;
      --bg3: #0f2030;
      --accent: #ff6565;
      --teal: #00e5c0;
      --border: #1a3040;
      --text: #dce8f0;
      --muted: #4a6a7a;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { background:var(--bg); color:var(--text); font-family:'Segoe UI',sans-serif; min-height:100vh; }

    /* ── Login ── */
    .login-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; background: radial-gradient(ellipse at center, #0d2035 0%, #060d14 70%); }
    .login-card { background:var(--bg2); border:1px solid var(--border); border-radius:20px; padding:48px 40px; width:100%; max-width:420px; box-shadow:0 0 60px rgba(255,101,101,.15); }
    .login-card h1 { color:var(--accent); font-size:1.8rem; font-weight:900; letter-spacing:2px; margin-bottom:6px; }
    .login-card p { color:var(--muted); font-size:.85rem; margin-bottom:32px; }
    .admin-input { background:var(--bg3) !important; border:1px solid var(--border) !important; color:var(--text) !important; border-radius:10px !important; padding:12px 14px !important; }
    .admin-input:focus { border-color:var(--teal) !important; box-shadow:0 0 8px rgba(0,229,192,.3) !important; outline:none !important; }
    .login-btn { background:var(--accent); color:#fff; border:none; border-radius:10px; padding:12px; font-weight:800; font-size:1rem; width:100%; transition:all .2s; cursor:pointer; }
    .login-btn:hover { background:#e05050; transform:translateY(-1px); }

    /* ── Layout ── */
    .sidebar { position:fixed; top:0; left:0; width:240px; height:100vh; background:var(--bg2); border-right:1px solid var(--border); display:flex; flex-direction:column; z-index:100; }
    .sidebar-logo { padding:24px 20px 16px; border-bottom:1px solid var(--border); }
    .sidebar-logo span { color:var(--accent); font-size:1.3rem; font-weight:900; letter-spacing:1px; }
    .sidebar-logo small { color:var(--muted); font-size:.7rem; display:block; }
    .sidebar-nav { flex:1; padding:16px 0; overflow-y:auto; }
    .nav-item-btn { display:flex; align-items:center; gap:12px; padding:12px 20px; color:var(--muted); font-size:.9rem; font-weight:600; cursor:pointer; transition:all .2s; border:none; background:none; width:100%; text-align:left; }
    .nav-item-btn:hover, .nav-item-btn.active { color:var(--text); background:var(--bg3); border-left:3px solid var(--accent); }
    .sidebar-footer { padding:16px 20px; border-top:1px solid var(--border); }
    .logout-btn { display:flex; align-items:center; gap:8px; color:var(--accent); font-size:.85rem; font-weight:600; text-decoration:none; }
    .logout-btn:hover { color:#ff9090; }
    .main { margin-left:240px; min-height:100vh; padding:32px 36px; }

    /* ── Topbar ── */
    .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:32px; }
    .topbar h2 { font-size:1.6rem; font-weight:800; color:var(--teal); }
    .topbar small { color:var(--muted); }

    /* ── Stat Cards ── */
    .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:36px; }
    .stat-card { background:var(--bg2); border:1px solid var(--border); border-radius:16px; padding:24px 20px; position:relative; overflow:hidden; transition:transform .2s; }
    .stat-card:hover { transform:translateY(-3px); }
    .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
    .stat-card.users::before { background:var(--teal); }
    .stat-card.laptops::before { background:#a78bfa; }
    .stat-card.orders::before { background:var(--accent); }
    .stat-card.revenue::before { background:#f59e0b; }
    .stat-num { font-size:2rem; font-weight:900; color:var(--text); }
    .stat-label { color:var(--muted); font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-top:4px; }
    .stat-icon { position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:2.5rem; opacity:.12; }

    /* ── Tables ── */
    .panel { background:var(--bg2); border:1px solid var(--border); border-radius:16px; overflow:hidden; margin-bottom:28px; }
    .panel-header { padding:18px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
    .panel-header h3 { font-size:1rem; font-weight:800; color:var(--text); }
    .panel-body { padding:0; }
    .admin-table { width:100%; border-collapse:collapse; }
    .admin-table th { background:var(--bg3); color:var(--muted); font-size:.75rem; text-transform:uppercase; letter-spacing:1px; padding:12px 20px; text-align:left; font-weight:700; }
    .admin-table td { padding:14px 20px; border-bottom:1px solid var(--border); font-size:.875rem; vertical-align:middle; }
    .admin-table tr:last-child td { border-bottom:none; }
    .admin-table tr:hover td { background:rgba(255,255,255,.02); }
    .badge-status { padding:4px 10px; border-radius:20px; font-size:.72rem; font-weight:700; text-transform:uppercase; }
    .badge-won { background:rgba(0,229,192,.15); color:var(--teal); }
    .badge-purchased { background:rgba(167,139,250,.15); color:#a78bfa; }
    .thumb { width:44px; height:32px; object-fit:cover; border-radius:6px; }

    /* ── Buttons ── */
    .btn-accent { background:var(--accent); color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:.8rem; font-weight:700; cursor:pointer; transition:all .2s; }
    .btn-accent:hover { background:#e05050; }
    .btn-teal { background:rgba(0,229,192,.15); color:var(--teal); border:1px solid rgba(0,229,192,.3); border-radius:8px; padding:6px 12px; font-size:.78rem; font-weight:700; cursor:pointer; transition:all .2s; }
    .btn-teal:hover { background:rgba(0,229,192,.3); }
    .btn-del { background:rgba(255,101,101,.1); color:var(--accent); border:1px solid rgba(255,101,101,.3); border-radius:8px; padding:6px 12px; font-size:.78rem; font-weight:700; cursor:pointer; transition:all .2s; }
    .btn-del:hover { background:rgba(255,101,101,.25); }

    /* ── Modal ── */
    .modal-content { background:var(--bg2) !important; border:1px solid var(--border) !important; color:var(--text) !important; border-radius:16px !important; }
    .modal-header { border-bottom:1px solid var(--border) !important; }
    .modal-footer { border-top:1px solid var(--border) !important; }
    .modal-title { color:var(--teal) !important; font-weight:800 !important; }
    .form-control, .form-select { background:var(--bg3) !important; border:1px solid var(--border) !important; color:var(--text) !important; border-radius:8px !important; }
    .form-control:focus, .form-select:focus { border-color:var(--teal) !important; box-shadow:0 0 6px rgba(0,229,192,.3) !important; }
    .form-label { color:var(--muted); font-size:.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }

    /* ── Sections ── */
    .section { display:none; }
    .section.active { display:block; }

    /* ── Loading ── */
    .spinner { width:40px; height:40px; border:3px solid var(--border); border-top-color:var(--teal); border-radius:50%; animation:spin .8s linear infinite; margin:60px auto; }
    @keyframes spin { to { transform:rotate(360deg); } }

    /* ── Search ── */
    .search-input { background:var(--bg3); border:1px solid var(--border); color:var(--text); border-radius:8px; padding:8px 14px; font-size:.85rem; outline:none; }
    .search-input:focus { border-color:var(--teal); }
  </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<!-- ══════════════════ LOGIN ══════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <h1>⚙ ADMIN</h1>
    <p>Ms_BidD Control Panel — Restricted Access</p>
    <?php if (!empty($loginError)): ?>
      <div class="alert alert-danger py-2 mb-3" style="background:rgba(255,101,101,.15);border:1px solid rgba(255,101,101,.3);border-radius:8px;color:#ff9090;font-size:.85rem;"><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="admin_login" value="1"/>
      <div class="mb-3">
        <label class="form-label">USERNAME</label>
        <input type="text" name="username" class="form-control admin-input" placeholder="admin" required autofocus/>
      </div>
      <div class="mb-4">
        <label class="form-label">PASSWORD</label>
        <input type="password" name="password" class="form-control admin-input" placeholder="••••••••" required/>
      </div>
      <button type="submit" class="login-btn">ACCESS PANEL</button>
    </form>
    <div class="text-center mt-4">
      <a href="index.html" style="color:var(--muted);font-size:.8rem;text-decoration:none;">← Back to Ms_BidD</a>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════ ADMIN PANEL ══════════════════ -->

<!-- Sidebar -->
<div class="sidebar">
  <div class="sidebar-logo">
    <span>Ms_BidD</span>
    <small>Admin Control Panel</small>
  </div>
  <nav class="sidebar-nav">
    <button class="nav-item-btn active" onclick="showSection('dashboard',this)"><i class="bi bi-speedometer2"></i> Dashboard</button>
    <button class="nav-item-btn" onclick="showSection('laptops',this)"><i class="bi bi-laptop"></i> Laptops</button>
    <button class="nav-item-btn" onclick="showSection('users',this)"><i class="bi bi-people"></i> Users</button>
    <button class="nav-item-btn" onclick="showSection('bids',this)"><i class="bi bi-activity"></i> Live Bids</button>
    <button class="nav-item-btn" onclick="showSection('orders',this)"><i class="bi bi-bag-check"></i> Orders</button>
  </nav>
  <div class="sidebar-footer">
    <a href="?logout=1" class="logout-btn"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</div>

<!-- Main -->
<div class="main">

  <!-- ── Dashboard ── -->
  <div id="sec-dashboard" class="section active">
    <div class="topbar">
      <div><h2>Dashboard</h2><small>Overview of your auction platform</small></div>
      <button class="btn-teal" onclick="loadData()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div class="stat-grid">
      <div class="stat-card users"><div class="stat-num" id="stat-users">—</div><div class="stat-label">Total Users</div><i class="bi bi-people stat-icon"></i></div>
      <div class="stat-card laptops"><div class="stat-num" id="stat-laptops">—</div><div class="stat-label">Laptops Listed</div><i class="bi bi-laptop stat-icon"></i></div>
      <div class="stat-card orders"><div class="stat-num" id="stat-orders">—</div><div class="stat-label">Auction Winners</div><i class="bi bi-trophy stat-icon"></i></div>
      <div class="stat-card revenue"><div class="stat-num" id="stat-revenue">—</div><div class="stat-label">Total Revenue</div><i class="bi bi-currency-dollar stat-icon"></i></div>
    </div>
    <div class="panel">
      <div class="panel-header"><h3>Recent Orders</h3></div>
      <div class="panel-body"><table class="admin-table" id="dash-orders-table"><thead><tr><th>Laptop</th><th>Winner</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody id="dash-orders-body"><tr><td colspan="5"><div class="spinner"></div></td></tr></tbody></table></div>
    </div>
  </div>

  <!-- ── Laptops ── -->
  <div id="sec-laptops" class="section">
    <div class="topbar">
      <div><h2>Laptop Management</h2><small>Create, edit, and remove auction products</small></div>
      <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#laptopModal" onclick="openLaptopModal()"><i class="bi bi-plus-lg"></i> Add Laptop</button>
    </div>
    <div class="panel">
      <div class="panel-header"><h3>All Laptops</h3><input class="search-input" id="laptopSearch" placeholder="🔍 Search laptops…" oninput="filterTable('laptops-body',this.value)"/></div>
      <div class="panel-body"><table class="admin-table"><thead><tr><th>Image</th><th>ID</th><th>Name</th><th>Condition</th><th>Retail Price</th><th>Min Increment</th><th>Seller</th><th>Actions</th></tr></thead><tbody id="laptops-body"><tr><td colspan="8"><div class="spinner"></div></td></tr></tbody></table></div>
    </div>
  </div>

  <!-- ── Users ── -->
  <div id="sec-users" class="section">
    <div class="topbar"><div><h2>User Management</h2><small>View and manage registered bidders</small></div></div>
    <div class="panel">
      <div class="panel-header"><h3>All Users</h3><input class="search-input" id="userSearch" placeholder="🔍 Search users…" oninput="filterTable('users-body',this.value)"/></div>
      <div class="panel-body"><table class="admin-table"><thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Joined</th><th>Actions</th></tr></thead><tbody id="users-body"><tr><td colspan="5"><div class="spinner"></div></td></tr></tbody></table></div>
    </div>
  </div>

  <!-- ── Bids ── -->
  <div id="sec-bids" class="section">
    <div class="topbar">
      <div><h2>Live Bids</h2><small>Monitor and manage active bids</small></div>
      <button class="btn-del" onclick="clearAllBids()"><i class="bi bi-trash"></i> Clear All Bids</button>
    </div>
    <div class="panel">
      <div class="panel-header"><h3>All Active Bids</h3><input class="search-input" id="bidSearch" placeholder="🔍 Search bids…" oninput="filterTable('bids-body',this.value)"/></div>
      <div class="panel-body"><table class="admin-table"><thead><tr><th>Bidder</th><th>Laptop</th><th>Amount</th><th>Time</th><th>Actions</th></tr></thead><tbody id="bids-body"><tr><td colspan="5"><div class="spinner"></div></td></tr></tbody></table></div>
    </div>
  </div>

  <!-- ── Orders ── -->
  <div id="sec-orders" class="section">
    <div class="topbar"><div><h2>Orders & Winners</h2><small>Manage auction outcomes and order statuses</small></div></div>
    <div class="panel">
      <div class="panel-header"><h3>All Orders</h3><input class="search-input" id="orderSearch" placeholder="🔍 Search orders…" oninput="filterTable('orders-body',this.value)"/></div>
      <div class="panel-body"><table class="admin-table"><thead><tr><th>Laptop</th><th>Winner</th><th>Amount</th><th>Status</th><th>Won At</th><th>Actions</th></tr></thead><tbody id="orders-body"><tr><td colspan="6"><div class="spinner"></div></td></tr></tbody></table></div>
    </div>
  </div>

</div><!-- /main -->

<!-- ══ Laptop Modal ══ -->
<div class="modal fade" id="laptopModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="laptopModalTitle">Add Laptop</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="laptopForm">
        <div class="modal-body">
          <input type="hidden" id="laptop-edit-mode" value="add"/>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Laptop ID</label><input type="text" id="l-id" class="form-control" placeholder="e.g. mac-999" required/></div>
            <div class="col-md-6"><label class="form-label">Name</label><input type="text" id="l-name" class="form-control" required/></div>
            <div class="col-12"><label class="form-label">Description (pipe-separated)</label><input type="text" id="l-desc" class="form-control" placeholder="512GB SSD | 16GB RAM | OLED Display"/></div>
            <div class="col-md-6"><label class="form-label">Condition</label>
              <select id="l-condition" class="form-select">
                <option>New</option><option>Open Box</option><option>Used - Excellent</option><option>Used - Very Good</option><option>Used - Good</option><option>Used - Like New</option><option>Certified Refurbished</option>
              </select>
            </div>
            <div class="col-md-3"><label class="form-label">Retail Price ($)</label><input type="number" id="l-price" class="form-control" min="0" required/></div>
            <div class="col-md-3"><label class="form-label">Min Increment ($)</label><input type="number" id="l-inc" class="form-control" min="1" value="50" required/></div>
            <div class="col-md-6"><label class="form-label">Seller Name</label><input type="text" id="l-seller" class="form-control" required/></div>
            <div class="col-md-6"><label class="form-label">Image URL</label><input type="url" id="l-img" class="form-control" required/></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-del" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-accent">Save Laptop</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
let allData = null;

// ── Navigation ──────────────────────────────────────────────────────────────────
function showSection(id, btn) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-item-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('sec-' + id).classList.add('active');
  if (btn) btn.classList.add('active');
}

// ── Load Data ───────────────────────────────────────────────────────────────────
async function loadData() {
  const res = await fetch('admin.php?admin_action=get_data');
  allData = await res.json();
  if (!allData.success) return;

  const fmt = n => '$' + Number(n).toLocaleString();
  const fmtDate = d => d ? new Date(d).toLocaleString() : '—';

  // Stats
  document.getElementById('stat-users').textContent = allData.stats.users;
  document.getElementById('stat-laptops').textContent = allData.stats.laptops;
  document.getElementById('stat-orders').textContent = allData.stats.orders;
  document.getElementById('stat-revenue').textContent = fmt(allData.stats.revenue);

  // Dashboard recent orders
  const dashBody = document.getElementById('dash-orders-body');
  dashBody.innerHTML = allData.orders.slice(0,8).map(o => `
    <tr>
      <td>${esc(o.laptop_name || o.laptop_id)}</td>
      <td><strong>${esc(o.username)}</strong></td>
      <td style="color:#00e5c0">${fmt(o.amount)}</td>
      <td><span class="badge-status ${o.status==='Won'?'badge-won':'badge-purchased'}">${esc(o.status||'Won')}</span></td>
      <td style="color:#4a6a7a">${fmtDate(o.won_at)}</td>
    </tr>`).join('') || '<tr><td colspan="5" class="text-center" style="color:#4a6a7a;padding:24px">No orders yet</td></tr>';

  // Laptops
  document.getElementById('laptops-body').innerHTML = allData.laptops.map(l => `
    <tr>
      <td><img src="${esc(l.img)}" class="thumb" onerror="this.src='https://via.placeholder.com/44x32'"></td>
      <td><code style="color:#a78bfa;font-size:.78rem">${esc(l.id)}</code></td>
      <td><strong>${esc(l.name)}</strong></td>
      <td><span class="badge-status badge-won">${esc(l.condition)}</span></td>
      <td>${fmt(l.retailed_price)}</td>
      <td>${fmt(l.min_increment)}</td>
      <td style="color:#4a6a7a">${esc(l.seller_name)}</td>
      <td>
        <button class="btn-teal me-1" onclick="editLaptop(${JSON.stringify(l).replace(/"/g,'&quot;')})">Edit</button>
        <button class="btn-del" onclick="deleteLaptop('${esc(l.id)}','${esc(l.name)}')">Delete</button>
      </td>
    </tr>`).join('') || '<tr><td colspan="8" class="text-center" style="color:#4a6a7a;padding:24px">No laptops</td></tr>';

  // Users
  document.getElementById('users-body').innerHTML = allData.users.map(u => `
    <tr>
      <td style="color:#4a6a7a">${u.id}</td>
      <td><strong>${esc(u.username)}</strong></td>
      <td style="color:#4a6a7a">${esc(u.email||'—')}</td>
      <td style="color:#4a6a7a">${fmtDate(u.created_at)}</td>
      <td><button class="btn-del" onclick="deleteUser('${esc(u.username)}')">Delete</button></td>
    </tr>`).join('') || '<tr><td colspan="5" class="text-center" style="color:#4a6a7a;padding:24px">No users</td></tr>';

  // Bids
  document.getElementById('bids-body').innerHTML = allData.bids.map(b => `
    <tr>
      <td><strong>${esc(b.username)}</strong></td>
      <td>${esc(b.laptop_name || b.laptop_id)}</td>
      <td style="color:#00e5c0"><strong>$${Number(b.amount).toLocaleString()}</strong></td>
      <td style="color:#4a6a7a">${fmtDate(b.created_at)}</td>
      <td><button class="btn-del" onclick="clearBidsByLaptop('${esc(b.laptop_id)}')">Clear Laptop Bids</button></td>
    </tr>`).join('') || '<tr><td colspan="5" class="text-center" style="color:#4a6a7a;padding:24px">No active bids</td></tr>';

  // Orders
  document.getElementById('orders-body').innerHTML = allData.orders.map(o => `
    <tr>
      <td>${esc(o.laptop_name || o.laptop_id)}</td>
      <td><strong>${esc(o.username)}</strong></td>
      <td style="color:#00e5c0">${fmt(o.amount)}</td>
      <td><span class="badge-status ${o.status==='Won'?'badge-won':'badge-purchased'}">${esc(o.status||'Won')}</span></td>
      <td style="color:#4a6a7a">${fmtDate(o.won_at)}</td>
      <td>
        <select class="form-select form-select-sm" style="width:140px;display:inline-block" onchange="updateOrderStatus(${o.id},this.value)">
          <option value="Won" ${o.status==='Won'?'selected':''}>Won</option>
          <option value="Purchased" ${o.status==='Purchased'?'selected':''}>Purchased</option>
          <option value="Shipped" ${o.status==='Shipped'?'selected':''}>Shipped</option>
          <option value="Delivered" ${o.status==='Delivered'?'selected':''}>Delivered</option>
        </select>
      </td>
    </tr>`).join('') || '<tr><td colspan="6" class="text-center" style="color:#4a6a7a;padding:24px">No orders</td></tr>';
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function filterTable(tbodyId, query) {
  const q = query.toLowerCase();
  document.querySelectorAll(`#${tbodyId} tr`).forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ── Laptop CRUD ────────────────────────────────────────────────────────────────
function openLaptopModal() {
  document.getElementById('laptop-edit-mode').value = 'add';
  document.getElementById('laptopModalTitle').textContent = 'Add New Laptop';
  document.getElementById('laptopForm').reset();
  document.getElementById('l-id').removeAttribute('readonly');
}

function editLaptop(l) {
  document.getElementById('laptop-edit-mode').value = 'edit';
  document.getElementById('laptopModalTitle').textContent = 'Edit Laptop';
  document.getElementById('l-id').value = l.id;
  document.getElementById('l-id').setAttribute('readonly', true);
  document.getElementById('l-name').value = l.name;
  document.getElementById('l-desc').value = l.description;
  document.getElementById('l-condition').value = l.condition;
  document.getElementById('l-price').value = l.retailed_price;
  document.getElementById('l-inc').value = l.min_increment;
  document.getElementById('l-seller').value = l.seller_name;
  document.getElementById('l-img').value = l.img;
  new bootstrap.Modal(document.getElementById('laptopModal')).show();
}

document.getElementById('laptopForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  const mode = document.getElementById('laptop-edit-mode').value;
  const fd = new FormData();
  fd.append('admin_action', mode === 'edit' ? 'edit_laptop' : 'add_laptop');
  fd.append('id', document.getElementById('l-id').value);
  fd.append('name', document.getElementById('l-name').value);
  fd.append('description', document.getElementById('l-desc').value);
  fd.append('condition', document.getElementById('l-condition').value);
  fd.append('retailed_price', document.getElementById('l-price').value);
  fd.append('min_increment', document.getElementById('l-inc').value);
  fd.append('seller_name', document.getElementById('l-seller').value);
  fd.append('img', document.getElementById('l-img').value);
  const res = await fetch('admin.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    bootstrap.Modal.getInstance(document.getElementById('laptopModal'))?.hide();
    loadData();
  } else alert('Error: ' + data.message);
});

async function deleteLaptop(id, name) {
  if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
  const fd = new FormData();
  fd.append('admin_action', 'delete_laptop');
  fd.append('id', id);
  const res = await fetch('admin.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) loadData(); else alert('Error: ' + data.message);
}

// ── User CRUD ──────────────────────────────────────────────────────────────────
async function deleteUser(username) {
  if (!confirm(`Delete user "${username}"? All their data will remain.`)) return;
  const fd = new FormData();
  fd.append('admin_action', 'delete_user');
  fd.append('username', username);
  const res = await fetch('admin.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) loadData(); else alert('Error: ' + data.message);
}

// ── Bid Management ─────────────────────────────────────────────────────────────
async function clearBidsByLaptop(laptopId) {
  if (!confirm(`Clear all bids for laptop "${laptopId}"?`)) return;
  const fd = new FormData();
  fd.append('admin_action', 'clear_bids');
  fd.append('laptop_id', laptopId);
  const res = await fetch('admin.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) loadData(); else alert('Error: ' + data.message);
}

async function clearAllBids() {
  if (!confirm('Clear ALL bids from the entire platform? This cannot be undone!')) return;
  const fd = new FormData();
  fd.append('admin_action', 'clear_bids');
  const res = await fetch('admin.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) loadData(); else alert('Error: ' + data.message);
}

// ── Order Status ───────────────────────────────────────────────────────────────
async function updateOrderStatus(id, status) {
  const fd = new FormData();
  fd.append('admin_action', 'update_order_status');
  fd.append('id', id);
  fd.append('status', status);
  const res = await fetch('admin.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (!data.success) alert('Error: ' + data.message);
}

// ── Init ───────────────────────────────────────────────────────────────────────
<?php if ($isLoggedIn): ?>
loadData();
<?php endif; ?>
</script>
</body>
</html>
