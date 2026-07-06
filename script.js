import data from "./data.js";

let storedLaptop = JSON.parse(localStorage.getItem("selectedLaptop"));
let laptopData = storedLaptop || JSON.parse(data)[0];

const AUCTION_DURATION_SECONDS = 30;
const WINNER_POPUP_MS = 5000;

const button = document.getElementById("btnBid");
const amountInput = document.getElementById("amountBid");
const minBid = document.getElementById("minBid");
const currentBid = document.getElementById("currentBID");
const totalBidder = document.getElementById("totalBidder");
const auctionTimer = document.getElementById("auctionTimer");
const infoProd = document.getElementById("infoProd");
const image = document.getElementById("visualImage");
const detailedInfo = document.getElementById("detailedInfos");
const prevBid = document.getElementById("prevBid");
const winnerModalElement = document.getElementById("winnerModal");
const winnerModalBody = document.getElementById("winnerModalBody");
const winnerModal = winnerModalElement && window.bootstrap
  ? new bootstrap.Modal(winnerModalElement, { backdrop: "static", keyboard: false })
  : null;
const loggedInUser = localStorage.getItem("loggedInUser");

const name = loggedInUser || "Guest";
let totalB = 0;
let current = 0;
let defaultMinimum = laptopData.minIncrement || 67;
let auctionEndsAt = Date.now() + AUCTION_DURATION_SECONDS * 1000;
let activeRoundStartedAt = null;
let winnerShownForRound = null;
let resetTimeout = null;
let isResetting = false;

infoProd.innerHTML = `
  <h2 class="infoName">${laptopData.id}</h2>
  <p class="infoDes">${laptopData.description.join(" | ")}</p>
`;

image.innerHTML = `
  <img src="${laptopData.img}" alt="${laptopData.name}" />
`;

detailedInfo.innerHTML = `
  <p id="deInfoName">Product Name: ${laptopData.name}</p>
  <p id="Condition">Condition: ${laptopData.condition}</p>
  <p id="retailedPrice">Retailed Price: $${laptopData.retailedPriced.toLocaleString()}</p>
  <p id="minIncrement">Minimum Increment: $${defaultMinimum}</p>
  <p id="sellerName">Seller Name: ${laptopData.sellerName}</p>
`;

function formatMoney(value) {
  return Number(value || 0).toLocaleString();
}

function formatTime(totalSeconds) {
  let minutes = Math.floor(totalSeconds / 60);
  let seconds = totalSeconds % 60;
  return `${minutes}:${seconds.toString().padStart(2, "0")}`;
}

function secondsRemaining() {
  return Math.max(0, Math.ceil((auctionEndsAt - Date.now()) / 1000));
}

function setBidControls(disabled) {
  button.disabled = disabled;
  amountInput.disabled = disabled;
  button.textContent = disabled ? "Closed" : "BidD";
}

function updateBidSummary() {
  currentBid.innerHTML = `<h2>CURRENT BID</h2>
<h3>$${formatMoney(current)}</h3>`;
  totalBidder.innerHTML = `<h2>TOTAL BID</h2>
<h3>${totalB} bidder${totalB === 1 ? "" : "s"}</h3>`;
  minBid.innerHTML = `Min bid amount $${formatMoney(current + defaultMinimum)}`;
  amountInput.placeholder = current + defaultMinimum;
}

function updateTimer() {
  let remaining = secondsRemaining();
  auctionTimer.innerHTML = `<h2>TIME LEFT</h2>
<h3>${formatTime(remaining)}</h3>`;

  if (remaining <= 0) {
    setBidControls(true);
  }
}

function renderBidHistory(bids) {
  if (!prevBid) return;

  prevBid.innerHTML = "<p>Bid history</p>";

  if (!bids.length) {
    let emptyState = document.createElement("p");
    emptyState.className = "emptyBidHistory";
    emptyState.textContent = "No bids yet";
    prevBid.appendChild(emptyState);
    return;
  }

  bids.forEach((bid) => {
    let info = document.createElement("div");
    info.id = "info";
    info.className = "bid-row";

    let pname = document.createElement("p");
    pname.id = "prevName";
    pname.textContent = bid.username;

    let pamount = document.createElement("p");
    pamount.id = "prevAmount";
    pamount.textContent = `$${formatMoney(bid.amount)}`;

    info.appendChild(pname);
    info.appendChild(pamount);
    prevBid.appendChild(info);
  });
}

function showWinner(payload) {
  winnerShownForRound = payload.round_started_at || "current-round";

  if (payload.winner) {
    winnerModalBody.innerHTML = `
      <p class="winner-label">Top 1 winner</p>
      <h2>${payload.winner.username}</h2>
      <p class="winner-amount">$${formatMoney(payload.winner.amount)}</p>
      <p>The next bidding round will start in a few seconds.</p>
    `;
  } else {
    winnerModalBody.innerHTML = `
      <p class="winner-label">Auction ended</p>
      <h2>No winner</h2>
      <p>No bids were placed in this round. A new round will start in a few seconds.</p>
    `;
  }

  if (winnerModal) {
    winnerModal.show();
  } else {
    alert(winnerModalBody.textContent);
  }

  clearTimeout(resetTimeout);
  resetTimeout = setTimeout(() => resetAuction(payload.round_started_at), WINNER_POPUP_MS);
}

function applyAuctionPayload(payload) {
  if (!payload || payload.status === "error") {
    console.error("Live bid error:", payload?.message || "Unknown error");
    return;
  }

  current = Number(payload.current_bid) || 0;
  totalB = Number(payload.total_bids) || 0;
  activeRoundStartedAt = payload.round_started_at || activeRoundStartedAt;
  auctionEndsAt = Date.now() + (Number(payload.time_remaining) || 0) * 1000;

  updateBidSummary();
  renderBidHistory(payload.bids || []);
  updateTimer();

  if (payload.is_expired) {
    setBidControls(true);

    if (winnerShownForRound !== payload.round_started_at && !isResetting) {
      showWinner(payload);
    }
  } else {
    setBidControls(false);
  }
}

async function updateFeedBid() {
  try {
    let response = await fetch(`liveBid.php?action=get_live_feed&laptop_id=${encodeURIComponent(laptopData.id)}`, {
      cache: "no-store",
    });
    let payload = await response.json();
    applyAuctionPayload(payload);
  } catch (error) {
    console.error("Error keeping stream live:", error);
  }
}

async function resetAuction(roundStartedAt) {
  if (isResetting) return;

  isResetting = true;

  try {
    let formData = new FormData();
    formData.append("action", "reset_auction");
    formData.append("laptop_id", laptopData.id);
    formData.append("round_started_at", roundStartedAt || activeRoundStartedAt || "");

    let response = await fetch("liveBid.php", { method: "POST", body: formData });
    let payload = await response.json();

    if (winnerModal) {
      winnerModal.hide();
    }

    winnerShownForRound = null;
    amountInput.value = "";
    applyAuctionPayload(payload);
  } catch (error) {
    console.error("Error resetting auction:", error);
  } finally {
    isResetting = false;
  }
}

async function bidded() {
  let amount = Number(amountInput.value);
  let minimumBid = current + defaultMinimum;
  const username = name;
  let laptopID = laptopData.id;

  if (!amount || amount < minimumBid) {
    amountInput.value = "";
    amountInput.placeholder = `Minimum $${formatMoney(minimumBid)}`;
    return;
  }

  if (secondsRemaining() <= 0) {
    setBidControls(true);
    return;
  }

  let formData = new FormData();
  formData.append("action", "place_bid");
  formData.append("username", username);
  formData.append("amount", amount);
  formData.append("laptop_id", laptopID);
  formData.append("min_increment", defaultMinimum);

  button.disabled = true;

  try {
    let response = await fetch("liveBid.php", { method: "POST", body: formData });
    let payload = await response.json();

    if (payload.status === "error") {
      amountInput.value = "";
      amountInput.placeholder = payload.message || "Please put a valid amount";
      return;
    }

    amountInput.value = "";
    applyAuctionPayload(payload);
  } catch (error) {
    console.error("Error placing bid:", error);
  } finally {
    if (secondsRemaining() > 0) {
      button.disabled = false;
    }
  }
}

updateBidSummary();
updateTimer();
updateFeedBid();
setInterval(updateFeedBid, 1000);
setInterval(updateTimer, 1000);
button.addEventListener("click", bidded);
