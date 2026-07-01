import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";

// GET /api/customers/[number]
export async function GET(
  request: Request,
  { params }: { params: Promise<{ number: string }> }
) {
  try {
    const { number } = await params;

    if (!number) {
      return NextResponse.json(
        { success: false, error: "Customer phone number is required" },
        { status: 400 }
      );
    }

    // Find all orders for this customer
    const orders = await prisma.order.findMany({
      where: {
        customerNumber: number,
      },
      orderBy: {
        dateTime: "desc",
      },
    });

    if (orders.length === 0) {
      return NextResponse.json({
        success: true,
        data: {
          exists: false,
          orders: [],
          stats: {
            totalOrders: 0,
            totalRevenue: 0,
            totalProfit: 0,
            statusCounts: {},
          },
        },
      });
    }

    // Use latest order for current name and address
    const latestOrder = orders[0];
    const customerName = latestOrder.customerName;
    const latestAddress = latestOrder.address;

    // Calculate aggregate metrics
    let totalRevenue = 0;
    let totalProfit = 0;
    const statusCounts: Record<string, number> = {
      Approved: 0,
      Processing: 0,
      Shipment: 0,
      Cancelled: 0,
      Returned: 0,
    };

    orders.forEach((order) => {
      // Aggregate status counts
      if (statusCounts[order.status] !== undefined) {
        statusCounts[order.status]++;
      } else {
        statusCounts[order.status] = 1;
      }

      // Include all non-cancelled, non-returned orders in total revenue/profit
      if (order.status !== "Cancelled" && order.status !== "Returned") {
        totalRevenue += order.productSellPrice;
      }
      // Calculate overall profit (including losses or deductions if applicable)
      totalProfit += order.profit;
    });

    // Calculate order success rate: (Shipment + Approved + Processing) / Total Orders
    const successfulStatusesCount =
      (statusCounts["Shipment"] || 0) +
      (statusCounts["Approved"] || 0) +
      (statusCounts["Processing"] || 0);
    const successRate = orders.length > 0 ? (successfulStatusesCount / orders.length) * 100 : 0;

    return NextResponse.json({
      success: true,
      data: {
        exists: true,
        customerName,
        customerNumber: number,
        latestAddress,
        orders,
        stats: {
          totalOrders: orders.length,
          totalRevenue: Number(totalRevenue.toFixed(2)),
          totalProfit: Number(totalProfit.toFixed(2)),
          successRate: Number(successRate.toFixed(2)),
          statusCounts,
        },
      },
    });
  } catch (error: any) {
    console.error("GET Customer Error:", error);
    return NextResponse.json(
      { success: false, error: error.message || "Failed to look up customer info" },
      { status: 500 }
    );
  }
}
