import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

const PRODUCTS = [
  { code: "PRD-A101", name: "Wireless Bluetooth Earbuds", cost: 15.00, sell: 39.99, other: 5.00 },
  { code: "PRD-B202", name: "Orthopedic Memory Foam Pillow", cost: 12.00, sell: 29.99, other: 4.50 },
  { code: "PRD-C303", name: "Portable Espresso Maker", cost: 25.00, sell: 69.99, other: 8.00 },
  { code: "PRD-D404", name: "Mini LED Projector 1080P", cost: 45.00, sell: 119.99, other: 12.00 },
  { code: "PRD-E505", name: "Ergonomic Laptop Stand", cost: 8.00, sell: 24.99, other: 3.50 }
];

const CUSTOMERS = [
  { name: "John Doe", number: "01711223344", address: "123 Green Road, Dhanmondi, Dhaka" },
  { name: "Jane Smith", number: "01811223344", address: "Flat 4B, House 12, Road 5, Banani, Dhaka" },
  { name: "Alex Johnson", number: "01911223344", address: "Sector 4, Uttara, Dhaka" },
  { name: "Sarah Connor", number: "01511223344", address: "78 Outer Circular Road, Motijheel, Dhaka" },
  { name: "Michael Scott", number: "01611223344", address: "Chawkbazar, Chittagong" },
  { name: "Emma Watson", number: "01755667788", address: "Kullan, Sylhet" },
  { name: "David Miller", number: "01988776655", address: "Zero Point, Khulna" }
];

const STATUSES = ["Approved", "Processing", "Shipment", "Cancelled", "Returned"];

async function main() {
  console.log("Cleaning database...");
  await prisma.order.deleteMany({});

  console.log("Seeding orders...");

  const baseDate = new Date();
  
  // Create orders for the last 5 days
  const ordersData = [];
  let orderCounter = 1001;

  for (let i = 4; i >= 0; i--) {
    const currentDate = new Date(baseDate);
    currentDate.setDate(baseDate.getDate() - i);
    
    // Number of orders per day (varying between 3 and 6)
    const ordersCount = Math.floor(Math.random() * 4) + 3;

    for (let j = 0; j < ordersCount; j++) {
      const product = PRODUCTS[Math.floor(Math.random() * PRODUCTS.length)];
      const customer = CUSTOMERS[Math.floor(Math.random() * CUSTOMERS.length)];
      
      // Distribute statuses: Approved/Shipment are more common, Cancelled/Returned less common
      let status = "Approved";
      const statusRand = Math.random();
      if (statusRand < 0.35) {
        status = "Shipment";
      } else if (statusRand < 0.6) {
        status = "Processing";
      } else if (statusRand < 0.8) {
        status = "Approved";
      } else if (statusRand < 0.9) {
        status = "Returned";
      } else {
        status = "Cancelled";
      }

      // Generate a date-time for the order within the day (spaced hours)
      const orderTime = new Date(currentDate);
      orderTime.setHours(9 + j * 2, Math.floor(Math.random() * 60), 0, 0);

      const profit = Number((product.sell - product.cost - product.other).toFixed(2));
      
      const orderIdStr = `ORD-${orderTime.getFullYear()}${(orderTime.getMonth() + 1).toString().padStart(2, "0")}${orderTime.getDate().toString().padStart(2, "0")}-${orderCounter++}`;

      ordersData.push({
        orderId: orderIdStr,
        dateTime: orderTime,
        status,
        customerName: customer.name,
        customerNumber: customer.number,
        productCode: product.code,
        productName: product.name,
        productPrice: product.cost,
        productSellPrice: product.sell,
        otherCost: product.other,
        profit: profit,
        address: customer.address
      });
    }
  }

  // Create all orders in bulk
  for (const o of ordersData) {
    await prisma.order.create({
      data: o
    });
  }

  console.log(`Seeded ${ordersData.length} orders successfully!`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
