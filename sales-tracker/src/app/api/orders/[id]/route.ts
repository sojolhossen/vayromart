import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";

// PUT /api/orders/[id]
export async function PUT(
  request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
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

    // Verify order exists
    const existingOrder = await prisma.order.findUnique({
      where: { id },
    });

    if (!existingOrder) {
      return NextResponse.json(
        { success: false, error: "Order not found" },
        { status: 404 }
      );
    }

    const cost = productPrice !== undefined ? parseFloat(productPrice) : existingOrder.productPrice;
    const sell = productSellPrice !== undefined ? parseFloat(productSellPrice) : existingOrder.productSellPrice;
    const other = otherCost !== undefined ? parseFloat(otherCost) : existingOrder.otherCost;

    // Recalculate profit/loss
    const profit = Number((sell - cost - other).toFixed(2));

    const updatedOrder = await prisma.order.update({
      where: { id },
      data: {
        dateTime: dateTime ? new Date(dateTime) : undefined,
        status: status ?? undefined,
        customerName: customerName ?? undefined,
        customerNumber: customerNumber ?? undefined,
        productCode: productCode ?? undefined,
        productName: productName ?? undefined,
        productPrice: cost,
        productSellPrice: sell,
        otherCost: other,
        profit,
        address: address ?? undefined,
      },
    });

    return NextResponse.json({ success: true, data: updatedOrder });
  } catch (error: any) {
    console.error("PUT Order Error:", error);
    return NextResponse.json(
      { success: false, error: error.message || "Failed to update order" },
      { status: 500 }
    );
  }
}

// DELETE /api/orders/[id]
export async function DELETE(
  request: Request,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;

    const existingOrder = await prisma.order.findUnique({
      where: { id },
    });

    if (!existingOrder) {
      return NextResponse.json(
        { success: false, error: "Order not found" },
        { status: 404 }
      );
    }

    await prisma.order.delete({
      where: { id },
    });

    return NextResponse.json({ success: true, message: "Order deleted successfully" });
  } catch (error: any) {
    console.error("DELETE Order Error:", error);
    return NextResponse.json(
      { success: false, error: error.message || "Failed to delete order" },
      { status: 500 }
    );
  }
}
