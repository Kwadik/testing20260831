export interface Order {
    id: string
    status: string
    sku: string
    amount: number
    currency: string
    delivery: {
        status: string
        code: string | null
    } | null
}

export interface CreateOrderResponse {
    data: Order
}

export interface OrderStatusResponse {
    data: Order
}

export async function createOrder(sku: string): Promise<CreateOrderResponse> {
    const response = await fetch('/api/orders', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            sku,
        }),
    })

    if (!response.ok) {
        throw new Error('Не удалось создать заказ.')
    }

    return response.json()
}

export async function payOrder(orderId: string): Promise<void> {
    const response = await fetch(`/api/orders/${orderId}/pay`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
        },
    })

    if (!response.ok) {
        throw new Error('Не удалось выполнить оплату.')
    }
}

export async function getOrder(orderId: string): Promise<OrderStatusResponse> {
    const response = await fetch(`/api/orders/${orderId}`, {
        headers: {
            'Accept': 'application/json',
        },
    })

    if (!response.ok) {
        throw new Error('Не удалось получить статус заказа.')
    }

    return response.json()
}