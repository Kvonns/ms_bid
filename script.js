import data from "./data.js";

let storedLaptop = JSON.parse(localStorage.getItem("selectedLaptop"));
let laptopData = storedLaptop || JSON.parse(data)[0];

let button = document.getElementById("btnBid");
let amountInput = document.getElementById("amountBid");
let minBid = document.getElementById("minBid");
let currentBid = document.getElementById("currentBID");
let totalBidder = document.getElementById("totalBidder");
let infoProd = document.getElementById("infoProd");
let image = document.getElementById("visualImage");
let detailedInfo = document.getElementById("detailedInfos");

const name = localStorage.getItem("loggedInUser") || "Guest";
let totalB = laptopData.bidderCount || 0;
let current = laptopData.currentBid || 0;
let defaultMinimum = laptopData.minIncrement || 67;

infoProd.innerHTML = `
  <h2 class="infoName">${laptopData.name}</h2>
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

function updateBidSummary() {
  currentBid.innerHTML = `<h2>CURRENT BID</h2>
<h3>$${current.toLocaleString()}</h3>`;
  totalBidder.innerHTML = `<h2>TOTAL BID</h2>
<h3>${totalB} bidder</h3>`;
  minBid.innerHTML = `Min bid amount $${(current + defaultMinimum).toLocaleString()}`;
  amountInput.placeholder = current + defaultMinimum;
}

function bidded() {
  let amount = Number(amountInput.value);
  let minimumBid = current + defaultMinimum;

  if (amount < minimumBid) {
    return;
  }

  let prevBid = document.getElementById("prevBid");
  let info = document.createElement("div");
  info.id = "info";

  let pname = document.createElement("p");
  pname.id = "prevName";
  pname.innerHTML = name;

  let pamount = document.createElement("p");
  pamount.id = "prevAmount";
  pamount.innerHTML = `$${amount.toLocaleString()}`;

  current = amount;
  totalB += 1;

  info.appendChild(pname);
  info.appendChild(pamount);
  prevBid.appendChild(info);
  amountInput.value = "";
  updateBidSummary();
}

updateBidSummary();
button.addEventListener("click", bidded);
