//input at bid
let button = document.getElementById("btnBid");
const name = "vonvon"
let currentBid = document.getElementById("currentBID");
let totalBidder = document.getElementById("totalBidder");
let totalB =10;
let current = 1000;
function bidded(){

    let amount = document.getElementById("amountBid")
    let prevBid = document.getElementById("prevBid");
    let info = document.createElement("div");
    info.id = "info";

    let pname = document.createElement("p")
    pname.id = "prevName";
    pname.innerHTML = name;
    
    let pamount = document.createElement("p")
    pamount.id = "prevAmount";
    pamount.innerHTML = amount.value + "$";

    

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
let prodName = "##########"
let conditon = ["New", "Userd"];
let retailedPrice = 3000;
let minIncrement = 67;
let sellerName = "MS_bidD"

let detailedInfo = document.getElementById("detailedInfos")
detailedInfo.innerHTML = ` <p id="deInfoName">Product Name: ${prodName  } </p>
                <p id="Condition">Condition: ${conditon[0]}</p>
                <p id="retailedPrice">Retailed Price: ${retailedPrice}$</p>
                <p id="minIncrement">Minimum Increment: ${minIncrement}$</p>
                <p id="sellerName">Seller Name: ${sellerName}</p>`

console.log(totalB)
currentBid.innerHTML = `<h2>CURRENT BID</h2>
<h3>${current}$</h3>
`;
totalBidder.innerHTML = `<h2>TOTAL BID</h2>
<h3>${totalB} bidder </h3>`



button.addEventListener('click', bidded);