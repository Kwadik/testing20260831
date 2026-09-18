<script setup lang="ts">
import Header from '../components/Header.vue'
import HeroBanner from '../components/HeroBanner.vue'
import ServiceList from '../components/ServiceList.vue'
import SteamTopUp from '../components/SteamTopUp.vue'
import ProductSection from '../components/ProductSection.vue'
import RecommendedProductSection from '../components/RecommendedProductSection.vue'
import OtherProductSection from '../components/OtherProductSection.vue'

const emit = defineEmits<{
  orderCreated: [orderId: string]
}>()
</script>

<template>
  <Header />
  <HeroBanner />
  <section class="section-services">
    <section class="wrapper">
      <ServiceList />
      <div class="separator"></div>
      <SteamTopUp @order-created="emit('orderCreated', $event)" />
    </section>
  </section>
  <ProductSection @order-created="emit('orderCreated', $event)" />
  <RecommendedProductSection />
  <OtherProductSection />
</template>

<style scoped lang="scss">
@use '../styles/variables' as *;

.section-services {
  .wrapper {
    display: flex;
    flex-direction: column;
    padding: 20px;
    background: $color-surface;
    border-radius: $radius-base;

    .separator {
      margin: 16px 0;
      height: 1px;
      background: #E8EAED;
    }
  }
}
</style>