<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Product } from '../data/catalog'
import { createOrder } from '../api/orders'

const props = defineProps<{
  product: Product
}>()

const emit = defineEmits<{
  orderCreated: [orderId: string]
}>()

const imageSrc = computed(() =>
    props.product.image ?? '/images/other/product-card-default-image.png'
)

const isLoading = ref(false)
const errorMessage = ref('')

async function handleBuy(): Promise<void> {
  if (isLoading.value) {
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await createOrder(props.product.sku)

    console.log('Order created:', response.data)

    emit('orderCreated', response.data.id)
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось создать заказ.'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="product-card">
    <div class="product-card__image">
      <img
          :src="imageSrc"
          :alt="props.product.name"
      />
    </div>

    <div class="product-card__content">
      <div class="product-card__title">
        {{ props.product.name }}
      </div>

      <div class="product-card__sum">
        <div class="value">{{ props.product.price }}</div>
        <div class="old-price">1 990 ₽</div>
      </div>

      <button
          type="button"
          class="button product-card__submit"
          :disabled="isLoading"
          @click="handleBuy"
      >
        {{ isLoading ? 'Создание заказа...' : 'Купить' }}
      </button>

      <p
          v-if="errorMessage"
          class="product-card__error"
      >
        {{ errorMessage }}
      </p>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '../styles/variables' as *;
@use '../styles/mixins' as *;

.product-card {
  width: 227px;
  display: flex;
  flex-direction: column;
  border-radius: $radius-card;
  background: $color-surface;
  overflow: hidden;
  transition:
      transform $transition-base,
      box-shadow $transition-base;

  &:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px 0 #d1d9e4;
  }

  &__image {
    height: 152px;
    display: flex;
    justify-content: center;
    overflow: hidden;

    img {
      display: block;
      height: 100%;
      width: auto;
    }
  }

  &__content {
    display: flex;
    flex-direction: column;
    gap: $spacing-3;
    padding: 13px;
  }

  &__title {
    @include text-row-truncate(2);
    font-weight: 800;
    font-size: 10px;
    line-height: 14px;
  }

  &__sum {
    display: inline-flex;
    align-items: baseline;
    gap: $spacing-2;

    .value {
      font-weight: 700;
      font-size: 20px;
      line-height: 20px;
      color: #4C9A2A;
    }

    .old-price {
      font-weight: 700;
      font-size: 12px;
      color: $color-text-muted;
      text-decoration: line-through;
    }
  }

  &__submit {
    width: 100%;
    height: 42px;
    justify-content: center;
    font-weight: 800;
    font-size: 12px;
  }

  &__error {
    margin: 0;
    font-size: 11px;
    line-height: 14px;
    color: $color-error;
  }
}
</style>