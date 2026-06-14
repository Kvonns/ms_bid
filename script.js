//input at bid
let laptopData = JSON.parse(localStorage.getItem('selectedLaptop'));
console.log(laptopData); // Here is your clicked item's data!

let button = document.getElementById("btnBid");
const name = "vonvon"
let currentBid = document.getElementById("currentBID");
let totalBidder = document.getElementById("totalBidder");
let totalB =0;
let current = 0;
let defaultMinimum = 67
let infoProd = document.getElementById("infoProd");
infoProd.innerHTML = `              <h2 class="infoName">${laptopData.name}</h2>
              <p class="infoDes">${laptopData.description[0] + " " + laptopData.description[0]}</p>`
function bidded(){

    let amount = document.getElementById("amountBid")
    let prevBid = document.getElementById("prevBid");
    let info = document.createElement("div");
    info.id = "info";
    if (amount.value > defaultMinimum){
        if (amount.value > current){
            let pname = document.createElement("p")
    pname.id = "prevName";
    pname.innerHTML = name;
    
    let pamount = document.createElement("p")
    pamount.id = "prevAmount";
    pamount.innerHTML = amount.value + "$";
    current = amount.value
        info.appendChild(pname)
    info.appendChild(pamount)
    prevBid.appendChild(info)
    totalB += 1;
    currentBid.innerHTML = `<h2>CURRENT BID</h2>
<h3>${amount.value}</h3>
`;
    totalBidder.innerHTML = `<h2>TOTAL BID</h2>
<h3>${totalB}</h3>`
    console.log("Click")
    console.log(totalB)
        }
    }
    

    



}


let image = document.getElementById("visualImage")
image.innerHTML = `<img
                src="${laptopData.img}"
                alt=""
              />`

let detailedInfo = document.getElementById("detailedInfos")
detailedInfo.innerHTML = ` <p id="deInfoName">Product Name: ${laptopData.name} </p>
                <p id="Condition">Condition: '${laptopData.condition}'</p>
                <p id="retailedPrice">Retailed Price: ${laptopData.retailedPriced}$</p>
                <p id="minIncrement">Minimum Increment: ${defaultMinimum}$</p>
                <p id="sellerName">Seller Name: ${laptopData.sellerName}</p>`

console.log(totalB)
currentBid.innerHTML = `<h2>CURRENT BID</h2>
<h3>${current}$</h3>
`;
totalBidder.innerHTML = `<h2>TOTAL BID</h2>
<h3>${totalB} bidder </h3>`



button.addEventListener('click', bidded);

//Laptop list

    

