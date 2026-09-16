<script setup lang="ts">
import ProductCardDefault from '../components/ProductCardDefault.vue'
</script>

<template>
  <section class="other-products">
    <div class="other-products__header">
      <h2 class="title">Другие товары</h2>
      <button type="button" class="button button-secondary">Показать все</button>
    </div>
    <div class="other-products__content">
      <ProductCardDefault />
      <ProductCardDefault />
      <ProductCardDefault />
      <ProductCardDefault />
      <ProductCardDefault />
    </div>
  </section>
</template>

<style scoped lang="scss">
@use '../styles/variables' as *;

.other-products {
  display: flex;
  flex-direction: column;
  gap: $spacing-4;

  &__header {
    display: flex;
    justify-content: space-between;
    gap: $spacing-4;

    .title {
      white-space: nowrap;
      font-weight: 700;
      font-size: 20px;
    }
  }

  &__content {
    display: flex;
    flex-wrap: wrap;
    gap: $spacing-4;
  }
}
</style>