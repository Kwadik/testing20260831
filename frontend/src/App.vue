<script setup lang="ts">
import { ref } from 'vue'
import HomePage from './pages/HomePage.vue'
import OrderPage from './pages/OrderPage.vue'

const orderId = ref<string | null>(null)

function handleOrderCreated(id: string): void {
  orderId.value = id
}

function handleBackToCatalog(): void {
  orderId.value = null
}
</script>

<template>
  <div class="page">
    <OrderPage
        v-if="orderId"
        :order-id="orderId"
        @back="handleBackToCatalog"
    />

    <HomePage
        v-else
        @order-created="handleOrderCreated"
    />
  </div>
</template>