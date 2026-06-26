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
const loggedInUser = localStorage.getItem("loggedInUser");
        const users = JSON.parse(localStorage.getItem("usersDB")) || [];
        const user = users.find(
          (item) =>
            loggedInUser &&
            item.username.toLowerCase() === loggedInUser.toLowerCase()
        );

const name = loggedInUser || "Guest";
let totalB = laptopData.bidderCount || 0;
let current = laptopData.currentBid || 0;
let defaultMinimum = laptopData.minIncrement || 67;

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

function updateBidSummary() {
  currentBid.innerHTML = `<h2>CURRENT BID</h2>
<h3>$${current.toLocaleString()}</h3>`;
  totalBidder.innerHTML = `<h2>TOTAL BID</h2>
<h3>${totalB} bidder</h3>`;
  minBid.innerHTML = `Min bid amount $${(current + defaultMinimum).toLocaleString()}`;
  amountInput.placeholder = current + defaultMinimum;
  console.log(laptopData.id);
}
async function updateFeedBid() {
  try {
    // TEMPORARY DEBUG CODE
let response = await fetch(`liveBid.php?action=get_live_feed&laptop_id=${laptopData.id} `);
let rawText = await response.text(); // Get raw text instead of JSON
console.log("Php is reading: ", rawText); 
let bidds = JSON.parse(rawText);

    
    let prevBid = document.getElementById("prevBid");
    if (!prevBid) return;
    
    if (bidds.length === 0) return;
    
    prevBid.innerHTML = "";

    bidds.forEach(bid => {
      let info = document.createElement("div");
      info.id = "info";
      info.className = "bid-row";

      let pname = document.createElement("p");
      pname.id = "prevName";
      pname.innerHTML = bid.username;

      let pamount = document.createElement("p");
      pamount.id = "prevAmount";
      pamount.innerHTML = `$${Number(bid.amount).toLocaleString()}`;
      
      info.appendChild(pname);
      info.appendChild(pamount);
      prevBid.appendChild(info); // Append inside the loop so every item shows up
    });

  } catch (error) {
    console.error("Error keeping stream live:", error);
  }
}

async function bidded() {
  let amount = Number(amountInput.value);
  let minimumBid = current + defaultMinimum;
  const username = name;
  let laptopID = laptopData.id;
  console.log(laptopID)

  if (!amount || amount < minimumBid) {
    amountInput.placeholder = "pls put a valid amount";
    return;
  }


  let formData = new FormData();
  // FIX 3: Ensure this matches your server.php string exactly ('place_bid')
  formData.append('action', 'place_bid'); 
  formData.append('username', username);
  formData.append('amount', amount);
  formData.append('laptop_id',laptopID);

  // Send to database
  await fetch('liveBid.php', { method: 'POST', body: formData });
  
  amountInput.value = ''; // Clear input box

  // Instantly refresh the visual log and updates numbers locally
  current = amount;
  totalB += 1;
  
  updateFeedBid();
  updateBidSummary();
}

// CRITICAL: Call the loop interval to keep it automatically updating in real-time
setInterval(updateFeedBid, 2000);

// Initial page calls
updateBidSummary();
updateFeedBid();
button.addEventListener("click", bidded);

