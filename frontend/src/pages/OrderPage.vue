<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { getOrder, payOrder } from '../api/orders'

const props = defineProps<{
  orderId: string
}>()

const emit = defineEmits<{
  back: []
}>()

const isLoading = ref(false)
const errorMessage = ref('')
const order = ref<Awaited<ReturnType<typeof getOrder>>['data'] | null>(null)

let pollingTimer: ReturnType<typeof setInterval> | null = null

async function loadOrder(): Promise<void> {
  try {
    const response = await getOrder(props.orderId)

    order.value = response.data

    if (response.data.status === 'delivered') {
      stopPolling()
    }
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось получить статус заказа.'
  }
}

async function handlePay(): Promise<void> {
  if (isLoading.value) {
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    await payOrder(props.orderId)

    await loadOrder()
    startPolling()
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось выполнить оплату.'
  } finally {
    isLoading.value = false
  }
}

function startPolling(): void {
  stopPolling()

  pollingTimer = setInterval(() => {
    void loadOrder()
  }, 1000)
}

function stopPolling(): void {
  if (pollingTimer !== null) {
    clearInterval(pollingTimer)
    pollingTimer = null
  }
}

function handleBack(): void {
  stopPolling()
  emit('back')
}

onMounted(async () => {
  await loadOrder()

  if (
      order.value &&
      order.value.status !== 'delivered'
  ) {
    startPolling()
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <main class="order-page">
    <div class="order-page__inner">
      <button
          type="button"
          class="order-page__back"
          @click="handleBack"
      >
        ← Вернуться в каталог
      </button>

      <div class="order-page__card">
        <div class="order-page__title">
          Заказ
        </div>

        <div
            v-if="errorMessage"
            class="order-page__error"
        >
          {{ errorMessage }}
        </div>

        <template v-else-if="order">
          <div class="order-page__row">
            <span>Товар</span>
            <strong>{{ order.sku }}</strong>
          </div>

          <div class="order-page__row">
            <span>Сумма</span>
            <strong>{{ order.amount }} {{ order.currency }}</strong>
          </div>

          <div class="order-page__row">
            <span>Статус</span>
            <strong>{{ order.status }}</strong>
          </div>

          <button
              v-if="order.status === 'created'"
              type="button"
              class="button order-page__pay"
              :disabled="isLoading"
              @click="handlePay"
          >
            {{ isLoading ? 'Оплата...' : 'Оплатить' }}
          </button>

          <div
              v-else-if="order.status === 'paid'"
              class="order-page__waiting"
          >
            Оплата получена. Выдаём ключ...
          </div>

          <div
              v-else-if="order.status === 'delivered'"
              class="order-page__success"
          >
            <div class="order-page__success-title">
              Заказ успешно выполнен
            </div>

            <div
                v-if="order.delivery?.code"
                class="order-page__code"
            >
              {{ order.delivery.code }}
            </div>
          </div>

          <div
              v-else
              class="order-page__waiting"
          >
            Статус заказа: {{ order.status }}
          </div>
        </template>

        <div
            v-else
            class="order-page__waiting"
        >
          Загрузка заказа...
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped lang="scss">
@use '../styles/variables' as *;

.order-page {
  min-height: 100vh;
  padding: 40px 20px;
  background: $color-background;

  &__inner {
    max-width: 600px;
    margin: 0 auto;
  }

  &__back {
    margin-bottom: 20px;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
  }

  &__card {
    padding: 24px;
    border-radius: $radius-base;
    background: $color-surface;
  }

  &__title {
    margin-bottom: 24px;
    font-size: 24px;
    font-weight: 800;
  }

  &__row {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 12px 0;
    border-bottom: 1px solid #e8eaed;

    span {
      color: $color-text-muted;
    }

    strong {
      text-align: right;
    }
  }

  &__pay {
    width: 100%;
    margin-top: 24px;
    justify-content: center;
  }

  &__waiting {
    margin-top: 24px;
    text-align: center;
    font-weight: 700;
  }

  &__success {
    margin-top: 24px;
    text-align: center;
  }

  &__success-title {
    margin-bottom: 16px;
    font-size: 18px;
    font-weight: 800;
  }

  &__code {
    padding: 16px;
    border-radius: $radius-base;
    background: #f3f5f7;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 1px;
    word-break: break-all;
  }

  &__error {
    margin-bottom: 20px;
    color: $color-error;
    font-size: 13px;
  }
}
</style>