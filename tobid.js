


//import data
import data from './data.js'

let datas = JSON.parse(data)
console.log(datas[0].id)
// listing laptop
let listcon = document.getElementById("containerTo")

console.log(datas.length)
for(let i  =  0 ; i < datas.length ; i++){
  let listCard = document.createElement('div')
listCard.classList = "col-md-3"
  listCard.innerHTML = `
            <div class="biddingCard" id="displayBid">
              <img
                id="panImage"
                src="${datas[i].img}"
                alt=""
              />
              <div class="btninfo">
                <p>${datas[i].name}</p>
                <button onclick="clickTogo(this, '${datas[i].id}' ,${i})" >BidD</button>
              </div>
            </div>
          `
          datas[i].name
listcon.appendChild(listCard);


}
window.clickTogo = function(button, id, index){
  console.log(id)
  console.log(datas[index])
  let sentData = datas[index]
  // Save the selected laptop data to localStorage as a string
  localStorage.setItem('selectedLaptop', JSON.stringify(sentData));
  
  // Redirect to your bidding/details page
  window.location.href = 'liveBid.html';
}


