import data from "./data.js";


let datas = JSON.parse(data);
let listcon = document.getElementById("containerTo");

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

window.clickTogo = function (button, id, index) {
  let sentData = datas[index];
  localStorage.setItem("selectedLaptop", JSON.stringify(sentData));
  window.location.href = "liveBid.php";
};


