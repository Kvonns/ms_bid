let datas = [];
let listcon = document.getElementById("containerTo");

async function loadLaptops() {
  try {
    const res = await fetch("server.php?action=get_laptops");
    const json = await res.json();
    if (json.success && json.laptops) {
      datas = json.laptops;
      renderLaptops();
    }
  } catch (e) {
    console.error("Error loading laptops:", e);
  }
}

function renderLaptops() {
  listcon.innerHTML = "";
  for (let i = 0; i < datas.length; i++) {
    let laptop = datas[i];
    let listCard = document.createElement("div");

    listCard.className = "col-md-3";
    listCard.innerHTML = `
      <div class="biddingCard displayBid">
        <img class="panImage" src="${laptop.img}" alt="${laptop.name}" />
        <div class="btninfo">
          <p>${laptop.name}</p>
          <button onclick="clickTogo(this, '${laptop.id}', ${i})">BidD</button>
        </div>
      </div>
    `;

    listcon.appendChild(listCard);
  }
}

window.clickTogo = function (button, id, index) {
  let sentData = datas[index];
  localStorage.setItem("selectedLaptop", JSON.stringify(sentData));
  window.location.href = "liveBid.php";
};

loadLaptops();

