export type ProductType = 'topup' | 'key' | 'subscription' | 'giftcard'
export type ProductCurrency = 'RUB'

export interface Product {
    sku: string
    name: string
    type: ProductType
    price: number
    currency: ProductCurrency
    image?: string
}

export const products: Product[] = [
    {
        sku: 'STEAM-TOPUP-500',
        name: 'Пополнение Steam 500 ₽',
        type: 'topup',
        price: 500,
        currency: 'RUB',
    },
    {
        sku: 'STEAM-TOPUP-500',
        name: 'Пополнение Steam 500 ₽',
        type: 'topup',
        price: 500,
        currency: 'RUB',
    },
    {
        sku: 'STEAM-TOPUP-1000',
        name: 'Пополнение Steam 1000 ₽',
        type: 'topup',
        price: 1000,
        currency: 'RUB',
    },
    {
        sku: 'STEAM-TOPUP-2500',
        name: 'Пополнение Steam 2500 ₽',
        type: 'topup',
        price: 2500,
        currency: 'RUB',
    },
    {
        sku: 'KEY-CS2-PRIME',
        name: 'CS2 Prime Status ключ',
        type: 'key',
        price: 1290,
        currency: 'RUB',
    },
    {
        sku: 'KEY-GTA5',
        name: 'GTA V ключ активации',
        type: 'key',
        price: 1990,
        currency: 'RUB',
    },
    {
        sku: 'KEY-EFT',
        name: 'Escape from Tarkov ключ',
        type: 'key',
        price: 3490,
        currency: 'RUB',
    },
    {
        sku: 'SUB-DISCORD-1M',
        name: 'Discord Nitro 1 месяц',
        type: 'subscription',
        price: 399,
        currency: 'RUB',
    },
    {
        sku: 'SUB-YT-3M',
        name: 'YouTube Premium 3 месяца',
        type: 'subscription',
        price: 1490,
        currency: 'RUB',
    },
    {
        sku: 'SUB-SPOTIFY-1M',
        name: 'Spotify Premium 1 месяц',
        type: 'subscription',
        price: 299,
        currency: 'RUB',
    },
    {
        sku: 'GIFT-PSN-1000',
        name: 'PlayStation Store карта 1000 ₽',
        type: 'giftcard',
        price: 1000,
        currency: 'RUB',
    },
    {
        sku: 'GIFT-XBOX-1500',
        name: 'Xbox Gift Card 1500 ₽',
        type: 'giftcard',
        price: 1500,
        currency: 'RUB',
    },
    {
        sku: 'GIFT-ROBLOX-800',
        name: 'Roblox 800 Robux',
        type: 'giftcard',
        price: 890,
        currency: 'RUB',
    }
]