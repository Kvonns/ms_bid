let laptop= [
{
    
    id: "mac-111",
    name: "Macbook Pro M5",
    description: ["1000GB SSD", "24GB RAM"],
    condition: "Used",
    retailedPriced: 3000,
    sellerName: "MSBIDD",
    img: "https://i.pinimg.com/736x/dd/6b/e4/dd6be478c7fcd08d5438c5f512577419.jpg"
  
    
},
{
    id: "mac-112",
    name: "Macbook air M4",
    description: ["1000GB SSD", "24GB RAM"],
    condition: "Used",
    retailedPriced: 999,
    sellerName: "MSBIDD",
    img: "https://i.pinimg.com/736x/bf/2e/aa/bf2eaa5e593822dccd31650ec880ff0b.jpg"
}
]
let data = JSON.stringify(laptop, null ,2)
console.log(data)
export default data;

