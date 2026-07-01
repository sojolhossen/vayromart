import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";

// GET /api/orders
export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const search = searchParams.get("search") || "";
    const status = searchParams.get("status") || "";
    const startDateStr = searchParams.get("startDate");
    const endDateStr = searchParams.get("endDate");

    // Build filter conditions
    const where: any = {};

    if (search) {
      where.OR = [
        { orderId: { contains: search } },
        { customerName: { contains: search } },
        { customerNumber: { contains: search } },
        { productName: { contains: search } },
        { productCode: { contains: search } },
      ];
    }

    if (status && status !== "ALL") {
      where.status = status;
    }

    if (startDateStr || endDateStr) {
      where.dateTime = {};
      if (startDateStr) {
        where.dateTime.gte = new Date(startDateStr);
      }
      if (endDateStr) {
        // Extend to end of day
        const end = new Date(endDateStr);
        end.setHours(23, 59, 59, 999);
        where.dateTime.lte = end;
      }
    }

    const orders = await prisma.order.findMany({
      where,
      orderBy: {
        dateTime: "desc",
      },
    });

    return NextResponse.json({ success: true, data: orders });
  } catch (error: any) {
    console.error("GET Orders Error:", error);
    return NextResponse.json(
      { success: false, error: error.message || "Failed to fetch orders" },
      { status: 500 }
    );
  }
}

// POST /api/orders
export async function POST(request: Request) {
  try {
    const body = await request.json();
    const {
      dateTime,
      status,
      customerName,
      customerNumber,
      productCode,
      productName,
      productPrice,
      productSellPrice,
      otherCost,
      address,
    } = body;

    // Field validation
    if (!status || !customerName || !customerNumber || !productCode || !productName || !address) {
      return NextResponse.json(
        { success: false, error: "Missing required fields" },
        { status: 400 }
      );
    }

    const cost = parseFloat(productPrice) || 0;
    const sell = parseFloat(productSellPrice) || 0;
    const other = parseFloat(otherCost) || 0;
    
    // Automated Profit/Loss formula: Sell Price - Cost Price - Other Cost
    const profit = Number((sell - cost - other).toFixed(2));

    // Generate a unique, beautiful Order ID
    const dateObj = dateTime ? new Date(dateTime) : new Date();
    const datePrefix = dateObj.getFullYear() +
      (dateObj.getMonth() + 1).toString().padStart(2, "0") +
      dateObj.getDate().toString().padStart(2, "0");
    const randomSuffix = Math.floor(1000 + Math.random() * 9000);
    const orderId = `ORD-${datePrefix}-${randomSuffix}`;

    const newOrder = await prisma.order.create({
      data: {
        orderId,
        dateTime: dateObj,
        status,
        customerName,
        customerNumber,
        productCode,
        productName,
        productPrice: cost,
        productSellPrice: sell,
        otherCost: other,
        profit,
        address,
      },
    });

    return NextResponse.json({ success: true, data: newOrder });
  } catch (error: any) {
    console.error("POST Order Error:", error);
    return NextResponse.json(
      { success: false, error: error.message || "Failed to create order" },
      { status: 500 }
    );
  }
}
