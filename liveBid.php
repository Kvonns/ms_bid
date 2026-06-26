  <?php
  include 'db.php';

  if (isset($_GET['action']) && $_GET['action'] == 'get_live_feed') {
    try {
      $laptop_id = isset($_GET['laptop_id']) ? trim($_GET['laptop_id']) : 0;
      $prepData = $pdo->prepare("SELECT username, amount, created_at FROM bids WHERE laptop_id = :laptop_id 
      ORDER BY created_at DESC LIMIT 10");
      $prepData->execute([
        ':laptop_id' => $laptop_id
      ]);
      $allBid = $prepData->fetchAll(PDO::FETCH_ASSOC);
      echo json_encode($allBid);
    } catch (PDOException $ee) {
      echo json_encode(["error" => $ee->getMessage()]);
    }
    exit;
  }
  if (isset($_POST['action']) && $_POST['action'] == 'place_bid') {
    try {
      $username = isset($_POST['username']) ? trim($_POST['username']) : 'Guest';
      $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
      $laptop_id = isset($_POST['laptop_id']) ? trim($_POST['laptop_id']) : null;
      if ($amount <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid bid amount."]);
        exit;
      }
      $prepData = $pdo->prepare("INSERT INTO bids (username, amount, laptop_id) VALUES (:username, :amount, :laptop_id)");

      $prepData->execute([
        ':username'  => $username,
        ':amount'    => $amount,
        ':laptop_id' => $laptop_id
      ]);


      $all_bids = $prepData->fetchAll(PDO::FETCH_ASSOC);

      echo json_encode($all_bids);
      exit;
    } catch (PDOException $e) {
      echo json_encode(["status" => "error", "message" => $e->getMessage()]);
      exit;
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
    <script type="module" src="script.js"></script>
  </body>

  </html>