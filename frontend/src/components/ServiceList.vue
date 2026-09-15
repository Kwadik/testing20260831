<script setup lang="ts">

interface Service {
  id: string
  image: string
  alt: string
  name: string
}

const services: Service[] = [
  {
    id: 'steam',
    image: '/images/services/steam.png',
    alt: 'steam-image',
    name: 'Steam',
  },
  {
    id: 'telegram',
    image: '/images/services/telegram.png',
    alt: 'telegram-image',
    name: 'Telegram',
  },
  {
    id: 'roblox',
    image: '/images/services/roblox.png',
    alt: 'roblox-image',
    name: 'Roblox',
  },
  {
    id: 'brawl-stars',
    image: '/images/services/brawl-stars.png',
    alt: 'brawl-stars-image',
    name: 'Brawl Stars',
  },
  {
    id: 'pubg-mob',
    image: '/images/services/pubg-mob.png',
    alt: 'pubg-mob-image',
    name: 'PUBG Mobile',
  },
  {
    id: 'app-store',
    image: '/images/services/app-store.png',
    alt: 'app-store-image',
    name: 'App Store',
  },
  {
    id: 'chatgpt',
    image: '/images/services/chatgpt.png',
    alt: 'chatgpt-image',
    name: 'ChatGPT',
  },
  {
    id: 'play-station',
    image: '/images/services/play-station.png',
    alt: 'play-station-image',
    name: 'PlayStation',
  },
  {
    id: 'tiktok',
    image: '/images/services/tiktok.png',
    alt: 'tiktok-image',
    name: 'TikTok',
  },
  {
    id: 'mobile-leg',
    image: '/images/services/mobile-leg.png',
    alt: 'mobile-leg-image',
    name: 'Mobile Legends',
  },
]

</script>

<template>
<div class="services">
  <button
      v-for="(service) in services"
      :key="service.id"
      class="services__button"
  >
    <img :src="service.image" :alt="service.alt" />
    <span>{{service.name}}</span>
  </button>
  <button
      class="services__button more"
  >
    <img src="/images/services/more.png" alt="Ещё" />
    <span>еще 841</span>
  </button>
</div>
</template>

<style scoped lang="scss">
@use '../styles/variables' as *;
@use '../styles/mixins' as *;

.services {
  display: flex;
  flex-wrap: wrap;
  gap: 16px 6px;

  &__button {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    width: 100px;
    padding: 4px;
    transition:
        transform $transition-base,
        box-shadow $transition-base;

    &:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 8px 0 #d1d9e4;
    }

    img {
      display: block;
      width: auto;
      height: 72px;
    }

    span {
      @include text-truncate();
      font-size: 16px;
      line-height: 24px;
      font-weight: 700;
      color: $color-text-secondary;
      white-space: nowrap;
    }

    &.more {

      span {
        color: $color-text-muted;
      }
    }
  }
}
</style>