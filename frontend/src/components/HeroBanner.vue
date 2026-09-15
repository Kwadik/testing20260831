<script setup lang="ts">
import {onMounted, onUnmounted, ref} from "vue";

interface HeroSlide {
  id: number
  image: string
  alt: string
}

const AUTOPLAY_INTERVAL = 5000
const activeSlide = ref(0)
const slides: HeroSlide[] = [
  {
    id: 1,
    image: '/images/hero-banner/1.png',
    alt: 'banner-image-1',
  },
  {
    id: 2,
    image: '/images/hero-banner/2.png',
    alt: 'banner-image-2',
  },
  {
    id: 3,
    image: '/images/hero-banner/3.png',
    alt: 'banner-image-3',
  },
  {
    id: 4,
    image: '/images/hero-banner/4.png',
    alt: 'banner-image-4',
  },
  {
    id: 5,
    image: '/images/hero-banner/5.png',
    alt: 'banner-image-5',
  },
]

const nextSlide = () => {
  activeSlide.value = (activeSlide.value + 1) % slides.length
}

const prevSlide = () => {
  activeSlide.value =
      (activeSlide.value - 1 + slides.length) % slides.length
}

const goToSlide = (index: number) => {
  activeSlide.value = index
  resetAutoplay()
}

const handleNext = () => {
  nextSlide()
  resetAutoplay()
}

const handlePrev = () => {
  prevSlide()
  resetAutoplay()
}

let autoplayTimer: ReturnType<typeof setInterval>

const startAutoplay = () => {
  autoplayTimer = setInterval(nextSlide, AUTOPLAY_INTERVAL)
}

const stopAutoplay = () => {
  clearInterval(autoplayTimer)
}

const resetAutoplay = () => {
  stopAutoplay()
  startAutoplay()
}

onMounted(() => {
  startAutoplay()
})

onUnmounted(() => {
  stopAutoplay()
})

</script>

<template>
<section class="section-hero">
  <div class="slider">
    <div class="slider__slides">
      <div
          v-for="(slide, index) in slides"
          :key="slide.id"
          class="slider__slide"
          :class="{ active: index === activeSlide }"
      >
        <img :src="slide.image" :alt="slide.alt" />
      </div>
    </div>
    <div class="slider__nav">
      <div class="wrapper">
        <button
            type="button"
            class="slider__prev"
            aria-label="Предыдущий слайд"
            @click="handlePrev"
        >
          <svg width="11" height="8" viewBox="0 0 11 8" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3.48047 7.00391C3.42318 7.0612 3.35872 7.10417 3.28711 7.13281C3.21549 7.16146 3.13672 7.17578 3.05078 7.17578C2.9362 7.17578 2.82878 7.14714 2.72852 7.08984C2.62826 7.03255 2.54948 6.96094 2.49219 6.875L0.171875 3.99609C0.114583 3.9388 0.0716146 3.87435 0.0429688 3.80273C0.0143229 3.73112 0 3.65234 0 3.56641C0 3.48047 0.0143229 3.39453 0.0429688 3.30859C0.0716146 3.22266 0.114583 3.15104 0.171875 3.09375L2.49219 0.257812C2.54948 0.171875 2.62826 0.107422 2.72852 0.0644531C2.82878 0.0214844 2.9362 0 3.05078 0C3.2513 0 3.41602 0.0644531 3.54492 0.193359C3.67383 0.322266 3.73828 0.486979 3.73828 0.6875C3.73828 0.773438 3.72396 0.859375 3.69531 0.945312C3.66667 1.03125 3.63802 1.10286 3.60938 1.16016L2.23438 2.83594H10.2695C10.4701 2.83594 10.6419 2.90755 10.7852 3.05078C10.9284 3.19401 11 3.36589 11 3.56641C11 3.76693 10.9284 3.93164 10.7852 4.06055C10.6419 4.18945 10.4701 4.25391 10.2695 4.25391H2.23438L3.60938 5.97266C3.63802 6.05859 3.66667 6.13737 3.69531 6.20898C3.72396 6.2806 3.73828 6.35938 3.73828 6.44531C3.73828 6.5599 3.7168 6.66732 3.67383 6.76758C3.63086 6.86784 3.56641 6.94661 3.48047 7.00391Z" fill="black"/>
          </svg>
        </button>
        <button
            type="button"
            class="slider__next"
            aria-label="Следующий слайд"
            @click="handleNext"
        >
          <svg width="11" height="8" viewBox="0 0 11 8" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7.51953 0.128906C7.57682 0.10026 7.64128 0.0716147 7.71289 0.0429688C7.78451 0.0143228 7.86328 0 7.94922 0C8.0638 0 8.17122 0.0214844 8.27148 0.0644531C8.37175 0.107422 8.45052 0.171875 8.50781 0.257812L10.8281 3.13672C10.8854 3.19401 10.9284 3.26562 10.957 3.35156C10.9857 3.4375 11 3.52344 11 3.60938C11 3.69531 10.9857 3.77409 10.957 3.8457C10.9284 3.91732 10.8854 3.98177 10.8281 4.03906L8.50781 6.875C8.45052 6.96094 8.37175 7.03255 8.27148 7.08984C8.17122 7.14714 8.0638 7.17578 7.94922 7.17578C7.7487 7.17578 7.58398 7.10417 7.45508 6.96094C7.32617 6.81771 7.26172 6.64583 7.26172 6.44531C7.26172 6.35938 7.27604 6.2806 7.30469 6.20898C7.33333 6.13737 7.36198 6.05859 7.39062 5.97266L8.76562 4.29688H0.730469C0.529948 4.29688 0.358073 4.22526 0.214844 4.08203C0.0716146 3.9388 0 3.78125 0 3.60938C0 3.40885 0.0716146 3.23698 0.214844 3.09375C0.358073 2.95052 0.529948 2.87891 0.730469 2.87891H8.76562L7.39062 1.16016C7.36198 1.10286 7.33333 1.03125 7.30469 0.945312C7.27604 0.859375 7.26172 0.773438 7.26172 0.6875C7.26172 0.572917 7.2832 0.472656 7.32617 0.386719C7.36914 0.300781 7.43359 0.214844 7.51953 0.128906Z" fill="black"/>
          </svg>
        </button>
      </div>
    </div>
    <div class="slider__dots">
      <button
          v-for="(slide, index) in slides"
          :key="slide.id"
          type="button"
          class="dot"
          :class="{ active: index === activeSlide }"
          :aria-label="`Перейти к слайду ${index + 1}`"
          @click="goToSlide(index)"
      ><span></span></button>
    </div>
  </div>
</section>
</template>

<style scoped lang="scss">
@use '../styles/variables' as *;

.section-hero {
  .slider {
    position: relative;
    height: 260px;
    border-radius: $radius-base 0 $radius-base $radius-base;
    overflow: hidden;

    &__slides {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
    }

    &__slide {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      overflow: hidden;
      display: flex;
      justify-content: flex-end;
      transition: opacity $transition-slow;

      &.active {
        opacity: 1;
      }

      img {
        display: block;
        max-width: initial;
        width: auto;
        height: 100%;
      }
    }

    &__nav {
      position: absolute;
      top: 0;
      right: 0;
      border-radius: 0 0 0 22px;
      width: 98px;
      height: 48px;
      background: $color-page-background;
      display: flex;
      justify-content: flex-end;

      &:before {
        position: absolute;
        top: 0;
        left: -11px;
        width: 12px;
        height: 12px;
        background: radial-gradient(circle at 0 0, transparent 12px, $color-page-background 12px);
        transform: rotate(-90deg);
        content: '';
      }

      &:after {
        position: absolute;
        right: 0;
        bottom: -11px;
        width: 12px;
        height: 12px;
        background: radial-gradient(circle at 0 0, transparent 12px, $color-page-background 12px);
        transform: rotate(-90deg);
        content: '';
      }

      .wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 22px;
        border: 1px solid #e5e9f1;
        border-radius: 48px;
        padding: 3px;
        width: 90px;
        height: 40px;
        background: #f4f5f7;

        & > * {
          display: flex;
          justify-content: center;
          align-items: center;
          width: 20px;
          height: 20px;
        }
      }
    }

    &__dots {
      position: absolute;
      right: 16px;
      bottom: 8px;
      display: flex;
      gap: 4px;

      .dot {
        width: 20px;
        height: 20px;
        display: flex;
        justify-content: center;
        align-items: center;

        &.active {
          pointer-events: none;

          span {
            background: $color-surface;
          }
        }

        span {
          display: flex;
          border-radius: $radius-base;
          width: 100%;
          height: 4px;
          background: rgba(255, 255, 255, 0.45);
        }
      }
    }
  }
}
</style>