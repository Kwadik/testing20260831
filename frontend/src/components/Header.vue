<script setup lang="ts">
import CatalogMenu from './CatalogMenu.vue'
import {onMounted, onUnmounted, ref} from "vue";

const isDropdownOpen = ref(false);
const catalogRef = ref<HTMLDivElement | null>(null);

const toggleDropdown = () => {
  isDropdownOpen.value = !isDropdownOpen.value;
};

const closeDropdown = () => {
  isDropdownOpen.value = false;
};

// Закрытие меню при клике в любое другое место экрана
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as Node;
  if (catalogRef.value && !catalogRef.value.contains(target)) {
    closeDropdown();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

</script>

<template>
  <header>
    <div class="catalog" :class="{'is-open': isDropdownOpen}" ref="catalogRef">
      <button type="button" class="button catalog-button dropdown-trigger" @click="toggleDropdown">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M6.66667 2.5H4.16667C3.24619 2.5 2.5 3.24619 2.5 4.16667V6.66667C2.5 7.58714 3.24619 8.33333 4.16667 8.33333H6.66667C7.58714 8.33333 8.33333 7.58714 8.33333 6.66667V4.16667C8.33333 3.24619 7.58714 2.5 6.66667 2.5Z" fill="white" stroke="white"/>
          <path d="M6.66667 11.667H4.16667C3.24619 11.667 2.5 12.4132 2.5 13.3337V15.8337C2.5 16.7541 3.24619 17.5003 4.16667 17.5003H6.66667C7.58714 17.5003 8.33333 16.7541 8.33333 15.8337V13.3337C8.33333 12.4132 7.58714 11.667 6.66667 11.667Z" fill="white" stroke="white"/>
          <path d="M15.8307 2.5H13.3307C12.4103 2.5 11.6641 3.24619 11.6641 4.16667V6.66667C11.6641 7.58714 12.4103 8.33333 13.3307 8.33333H15.8307C16.7512 8.33333 17.4974 7.58714 17.4974 6.66667V4.16667C17.4974 3.24619 16.7512 2.5 15.8307 2.5Z" fill="white" stroke="white"/>
          <path d="M15.8307 11.667H13.3307C12.4103 11.667 11.6641 12.4132 11.6641 13.3337V15.8337C11.6641 16.7541 12.4103 17.5003 13.3307 17.5003H15.8307C16.7512 17.5003 17.4974 16.7541 17.4974 15.8337V13.3337C17.4974 12.4132 16.7512 11.667 15.8307 11.667Z" fill="white" stroke="white"/>
        </svg>

        <span>Каталог</span>
      </button>

      <Transition name="dropdown-fade">
        <CatalogMenu v-if="isDropdownOpen"/>
      </Transition>
    </div>

    <div class="search">
      <div class="search-field">
        <input
            type="search"
            placeholder="Игра, приложение или услуга..."
        />
        <button class="favorites-button" type="button">
          <svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3.53337 0.00589531C4.6349 -0.048146 5.49403 0.303359 6.3795 1.00132C6.60259 1.17716 6.79955 1.38436 7.00612 1.5501C8.02249 0.56183 9.05199 -0.0705397 10.4613 0.0063015C11.4378 0.053504 12.3556 0.522422 13.0081 1.30751C13.6902 2.12205 14.0509 3.2738 13.9942 4.36397C13.8606 6.93314 11.7182 9.37306 10.043 11.0036C9.6769 11.3684 9.23157 11.7515 8.84484 12.0884C8.60631 12.2961 8.25413 12.598 7.98745 12.7436C7.73666 12.882 7.4633 12.9663 7.18264 12.9918C6.31776 13.0614 5.85194 12.6781 5.21615 12.1409C4.88648 11.8622 4.56327 11.5747 4.24682 11.2787C2.52903 9.68437 0.186441 7.05231 0.0130291 4.4995C-0.072802 3.35296 0.267979 2.21672 0.960112 1.34177C1.63272 0.506659 2.52082 0.081057 3.53337 0.00589531Z" fill="#76829B"/>
          </svg>
        </button>
      </div>
      <button class="search-button" type="button">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9.16667 15.8333C12.8486 15.8333 15.8333 12.8486 15.8333 9.16667C15.8333 5.48477 12.8486 2.5 9.16667 2.5C5.48477 2.5 2.5 5.48477 2.5 9.16667C2.5 12.8486 5.48477 15.8333 9.16667 15.8333Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M17.4974 17.5003L13.9141 13.917" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>

    <button type="button" class="profile">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M8.10027 10.6352C8.2008 10.6318 8.30137 10.63 8.40196 10.6297L10.6744 10.629C11.1284 10.629 11.7251 10.6126 12.167 10.6562C13.2557 10.77 14.2721 11.2545 15.0459 12.0285C15.9385 12.9159 16.4451 14.1195 16.4558 15.3781C16.4562 16.2597 16.0235 16.9954 15.263 17.4307C14.6614 17.7751 14.0977 17.7177 13.4317 17.7178L11.9184 17.7176L7.60251 17.718L6.41592 17.7185C6.09653 17.7186 5.7073 17.7314 5.39874 17.6716C4.99782 17.595 4.62467 17.4126 4.31797 17.1433C3.86187 16.7439 3.58417 16.1789 3.54657 15.5738C3.47145 14.4078 3.96667 13.1025 4.74129 12.2417C5.64183 11.2412 6.77047 10.7208 8.10027 10.6352Z" fill="#76829B"/>
        <path d="M9.77204 1.8806C11.9544 1.75576 13.8249 3.42327 13.9506 5.6056C14.0762 7.78792 12.4094 9.65908 10.2271 9.78547C8.04372 9.91194 6.17141 8.24406 6.04572 6.06064C5.92003 3.87723 7.58857 2.00552 9.77204 1.8806Z" fill="#76829B"/>
      </svg>
    </button>
  </header>
</template>

<style scoped lang="scss">
@use '../styles/variables' as *;

header {
  position: relative;
  height: $header-height;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 76px;
  padding-left: $container-padding;
  padding-right: $container-padding;
  background: $color-surface;
  border-bottom: 1px solid #F2F4F7;

  .catalog {
    flex-shrink: 0;

    &-button {
      height: 44px;
    }
  }

  .search {
    flex-grow: 1;
    height: 44px;
    max-width: 688px;
    display: flex;
    align-items: center;
    gap: 0;
    background: $color-primary;
    border: 2px solid transparent;
    border-radius: $radius-base;
    overflow: hidden;

    &-field {
      flex-grow: 1;
      display: flex;
      align-items: center;
      gap: 8px;
      background: $color-surface;
      border: 2px solid transparent;
      border-radius: $radius-small;
      overflow: hidden;
      padding-left: 12px;
      padding-right: 2px;
      height: 100%;

      input {
        background: transparent;
        flex-grow: 1;
        font-weight: 600;
        font-size: 12px;
        letter-spacing: -0.03em;
      }

      .favorites-button {
        width: 32px;
        height: 32px;
        flex-shrink: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #eff1f5;
        border-radius: 6px;
      }
    }

    &-button {
      width: 44px;
      height: 100%;
      flex-shrink: 0;
      display: flex;
      justify-content: center;
      align-items: center;
    }
  }

  .profile {
    width: 44px;
    height: 44px;
    display: flex;
    justify-content: center;
    align-items: center;
    background: $color-background;
    border-radius: $radius-base;
  }
}
</style>
