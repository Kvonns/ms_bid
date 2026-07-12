<?php
  include 'db.php';

  const AUCTION_DURATION_SECONDS = 100;

  function sendJson($payload)
  {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
  }

  function createAuctionStateTable($pdo)
  {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS auction_state (
        laptop_id TEXT PRIMARY KEY,
        round_started_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
      )
    ");
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
        UNIQUE (laptop_id, round_started_at)
      )
    ");
  }

  function getWinningBid($pdo, $laptop_id)
  {
    $winnerQuery = $pdo->prepare("
      SELECT username, amount
      FROM bids
      WHERE laptop_id = :laptop_id
      ORDER BY amount DESC, created_at ASC
      LIMIT 1
    ");
    $winnerQuery->execute([':laptop_id' => $laptop_id]);
    return $winnerQuery->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  function readAuctionState($pdo, $laptop_id)
  {
    $insertState = $pdo->prepare("
      INSERT INTO auction_state (laptop_id, round_started_at)
      VALUES (:laptop_id, NOW())
      ON CONFLICT (laptop_id) DO NOTHING
    ");
    $insertState->execute([':laptop_id' => $laptop_id]);

    $stateQuery = $pdo->prepare("
      SELECT round_started_at, EXTRACT(EPOCH FROM (NOW() - round_started_at)) AS elapsed_seconds
      FROM auction_state
      WHERE laptop_id = :laptop_id
    ");
    $stateQuery->execute([':laptop_id' => $laptop_id]);
    $state = $stateQuery->fetch(PDO::FETCH_ASSOC);

    $elapsedSeconds = $state ? (int) floor((float) $state['elapsed_seconds']) : 0;
    $timeRemaining = max(0, AUCTION_DURATION_SECONDS - $elapsedSeconds);

    return [
      'round_started_at' => $state ? $state['round_started_at'] : null,
      'time_remaining' => $timeRemaining,
      'is_expired' => $timeRemaining <= 0
    ];
  }

  function getAuctionState($pdo, $laptop_id)
  {
    try {
      return readAuctionState($pdo, $laptop_id);
    } catch (PDOException $error) {
      if ($error->getCode() !== '42P01') {
        throw $error;
      }

      createAuctionStateTable($pdo);
      return readAuctionState($pdo, $laptop_id);
    }
  }

  function getAuctionPayload($pdo, $laptop_id)
  {
    $state = getAuctionState($pdo, $laptop_id);

    $historyQuery = $pdo->prepare("
      SELECT username, amount, created_at
      FROM bids
      WHERE laptop_id = :laptop_id
      ORDER BY created_at DESC
      LIMIT 10
    ");
    $historyQuery->execute([':laptop_id' => $laptop_id]);
    $bids = $historyQuery->fetchAll(PDO::FETCH_ASSOC);

    $statsQuery = $pdo->prepare("
      SELECT COALESCE(MAX(amount), 0) AS current_bid, COUNT(*) AS total_bids
      FROM bids
      WHERE laptop_id = :laptop_id
    ");
    $statsQuery->execute([':laptop_id' => $laptop_id]);
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

    $winner = null;
    if ($state['is_expired']) {
      $winner = getWinningBid($pdo, $laptop_id);
    }

    return [
      'status' => 'ok',
      'bids' => $bids,
      'current_bid' => (float) ($stats['current_bid'] ?? 0),
      'total_bids' => (int) ($stats['total_bids'] ?? 0),
      'duration_seconds' => AUCTION_DURATION_SECONDS,
      'time_remaining' => $state['time_remaining'],
      'is_expired' => $state['is_expired'],
      'round_started_at' => $state['round_started_at'],
      'winner' => $winner
    ];
  }

  if (isset($_GET['action']) && $_GET['action'] == 'get_live_feed') {
    try {
      $laptop_id = isset($_GET['laptop_id']) ? trim($_GET['laptop_id']) : 0;
      sendJson(getAuctionPayload($pdo, $laptop_id));
    } catch (PDOException $ee) {
      sendJson(["status" => "error", "message" => $ee->getMessage()]);
    }
  }

  if (isset($_POST['action']) && $_POST['action'] == 'place_bid') {
    try {
      $username = isset($_POST['username']) ? trim($_POST['username']) : 'Guest';
      $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
      $laptop_id = isset($_POST['laptop_id']) ? trim($_POST['laptop_id']) : null;
      $minIncrement = isset($_POST['min_increment']) ? floatval($_POST['min_increment']) : 0;

      if ($amount <= 0 || !$laptop_id) {
        sendJson(["status" => "error", "message" => "Invalid bid amount."]);
      }

      $state = getAuctionState($pdo, $laptop_id);
      if ($state['is_expired']) {
        $payload = getAuctionPayload($pdo, $laptop_id);
        $payload['status'] = 'ended';
        $payload['message'] = 'This bidding round has ended.';
        sendJson($payload);
      }

      $currentQuery = $pdo->prepare("SELECT COALESCE(MAX(amount), 0) AS current_bid FROM bids WHERE laptop_id = :laptop_id");
      $currentQuery->execute([':laptop_id' => $laptop_id]);
      $currentBid = (float) $currentQuery->fetchColumn();

      if ($amount < ($currentBid + $minIncrement)) {
        sendJson([
          "status" => "error",
          "message" => "Bid must be at least $" . number_format($currentBid + $minIncrement) . "."
        ]);
      }

      $prepData = $pdo->prepare("INSERT INTO bids (username, amount, laptop_id) VALUES (:username, :amount, :laptop_id)");

      $prepData->execute([
        ':username'  => $username,
        ':amount'    => $amount,
        ':laptop_id' => $laptop_id
      ]);

      sendJson(getAuctionPayload($pdo, $laptop_id));
    } catch (PDOException $e) {
      sendJson(["status" => "error", "message" => $e->getMessage()]);
    }
  }

  if (isset($_POST['action']) && $_POST['action'] == 'reset_auction') {
    try {
      $laptop_id = isset($_POST['laptop_id']) ? trim($_POST['laptop_id']) : null;
      $round_started_at = isset($_POST['round_started_at']) ? trim($_POST['round_started_at']) : null;

      if (!$laptop_id) {
        sendJson(["status" => "error", "message" => "Missing auction item."]);
      }

      $state = getAuctionState($pdo, $laptop_id);

      if (!$state['is_expired']) {
        $payload = getAuctionPayload($pdo, $laptop_id);
        $payload['status'] = 'running';
        sendJson($payload);
      }

      if ($round_started_at && $state['round_started_at'] !== $round_started_at) {
        $payload = getAuctionPayload($pdo, $laptop_id);
        $payload['status'] = 'already_reset';
        sendJson($payload);
      }

      ensureAuctionWinnersTable($pdo);
      $winner = getWinningBid($pdo, $laptop_id);

      $pdo->beginTransaction();

      if ($winner) {
        $saveWinner = $pdo->prepare("
          INSERT INTO auction_winners (laptop_id, username, amount, round_started_at)
          VALUES (:laptop_id, :username, :amount, :round_started_at)
          ON CONFLICT (laptop_id, round_started_at) DO NOTHING
        ");
        $saveWinner->execute([
          ':laptop_id' => $laptop_id,
          ':username' => $winner['username'],
          ':amount' => $winner['amount'],
          ':round_started_at' => $state['round_started_at']
        ]);
      }

      $deleteBids = $pdo->prepare("DELETE FROM bids WHERE laptop_id = :laptop_id");
      $deleteBids->execute([':laptop_id' => $laptop_id]);

      $resetState = $pdo->prepare("UPDATE auction_state SET round_started_at = NOW() WHERE laptop_id = :laptop_id");
      $resetState->execute([':laptop_id' => $laptop_id]);

      $pdo->commit();

      $payload = getAuctionPayload($pdo, $laptop_id);
      $payload['status'] = 'reset';
      sendJson($payload);
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      sendJson(["status" => "error", "message" => $e->getMessage()]);
    }
  }

  ?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />

    <link
      href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css"
      rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Jura:wght@300..700&display=swap"
      rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
    <title>Bid</title>
  </head>

  <body>

    <img src="" alt="" />
    <div class="header">
      <div class="logo">Ms_BidD</div>
      <div class="nav">
        <a href="index.html">Home</a>
        <a href="liveBid.php">Live Bid</a>
        <a href="toBid.html">To Bid</a>
        <a href="profile.html">Profile</a>
      </div>
    </div>
    <div class="container-fluid px-0" id="container">
      <div class="row g-3 mx-0" id="idk">
        <div class="col-md-8">
          <div id="biddingCard">
            <div class="liveAution">
              <p>Live actions</p>
            </div>
            <div class="infoProd" id="infoProd"></div>

            <div class="visual" id="visualImage"></div>

            <div class="detailed" id="detailedInfo">
              <div class="detailedInfo" id="detailedInfos"></div>

              <div class="currentBid" id="currentBid">
                <div id="auctionTimer"></div>
                <div id="currentBID"></div>
                <div id="totalBidder"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div id="placingBid">
            <div id="inputBid">
              <p>Place Your bid</p>
              <p id="minBid">Min bid amount $</p>
              <div id="forBid">
                <input type="number" name="amount" id="amountBid" />
                <button id="btnBid" type="button">BidD</button>
              </div>
            </div>
            <div class="prevBid" id="prevBid">
              <p>Bid history</p>
              <div id="info">
                <p id="prevName">@name</p>
                <p id="prevAmount">2000$</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal fade" id="winnerModal" tabindex="-1" aria-labelledby="winnerModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content winner-modal">
          <div class="modal-header">
            <h5 class="modal-title" id="winnerModalLabel">Auction winner</h5>
          </div>
          <div class="modal-body" id="winnerModalBody"></div>
        </div>
      </div>
    </div>
    <script type="module" src="script.js"></script>
  </body>

  </html>
