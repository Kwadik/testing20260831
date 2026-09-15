<script setup lang="ts">
import Header from '../components/Header.vue'
import HeroBanner from '../components/HeroBanner.vue'
import ServiceList from '../components/ServiceList.vue'
</script>

<template>
  <Header />
  <HeroBanner />
  <section class="section-services">
    <section class="wrapper">
      <ServiceList />
      <div class="separator"></div>
      <!--  <SteamTopUp />-->
    </section>
  </section>
<!--  <ProductSection-->
<!--      title="Популярные товары"-->
<!--  />-->
<!--  <ProductSection-->
<!--      title="Рекомендованные товары"-->
<!--  />-->
<!--  <ProductSection-->
<!--      title="Другие товары"-->
<!--  />-->
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