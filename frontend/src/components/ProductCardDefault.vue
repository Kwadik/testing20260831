<script setup lang="ts">

</script>

<template>
  <div class="product-card">
    <div class="product-card__image">
      <img src="/images/other/product-card-default-image.png" />
    </div>
    <div class="product-card__content">
      <div class="product-card__title">💥 DOOM 2016 💀 STEAM KEY 🔑 РФ+СНГ</div>
      <div class="product-card__sum">
        <div class="value">990 ₽</div>
        <div class="old-price">1 990 ₽</div>
      </div>
      <button type="button" class="button product-card__submit">Купить</button>
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
}
</style>