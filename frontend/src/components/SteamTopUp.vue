<script setup lang="ts">

import { ref } from 'vue'

const currencies = ['$', '₸', '₽']
const activeCurrency = ref('$')

</script>

<template>
  <div class="service-top-up">
    <div class="service-info">
      <div class="service-image">
        <img src="/images/services/steam.png" alt="steam-image">
      </div>
      <div class="service-info__content">
        <div class="action-name">
          <span class="text">Пополнение Steam</span>
          <span class="discount">5%</span>
        </div>
        <div class="promocode-field">
          <div class="dropdown">
            <div class="dropdown-trigger">
              Ввести промокод
              <svg width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0.75 0.75L3.75 3.75L6.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="login">
      <div class="icon">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M8.10027 10.6352C8.2008 10.6318 8.30137 10.63 8.40196 10.6297L10.6744 10.629C11.1284 10.629 11.7251 10.6126 12.167 10.6562C13.2557 10.77 14.2721 11.2545 15.0459 12.0285C15.9385 12.9159 16.4451 14.1195 16.4558 15.3781C16.4562 16.2597 16.0235 16.9954 15.263 17.4307C14.6614 17.7751 14.0977 17.7177 13.4317 17.7178L11.9184 17.7176L7.60251 17.718L6.41592 17.7185C6.09653 17.7186 5.7073 17.7314 5.39874 17.6716C4.99782 17.595 4.62467 17.4126 4.31797 17.1433C3.86187 16.7439 3.58417 16.1789 3.54657 15.5738C3.47145 14.4078 3.96667 13.1025 4.74129 12.2417C5.64183 11.2412 6.77047 10.7208 8.10027 10.6352Z" fill="#76829B"/>
          <path d="M9.77204 1.8806C11.9544 1.75576 13.8249 3.42327 13.9506 5.6056C14.0762 7.78792 12.4094 9.65908 10.2271 9.78547C8.04372 9.91194 6.17141 8.24406 6.04572 6.06064C5.92003 3.87723 7.58857 2.00552 9.77204 1.8806Z" fill="#76829B"/>
        </svg>
      </div>
      <div class="field">
        <input type="text" name="login" placeholder="Логин Steam" />
      </div>
      <div class="tip">
        <button type="button" class="tip-button">
          <svg width="5" height="10" viewBox="0 0 5 10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3.95508 8.23242L3.82617 8.75977C3.43945 8.91211 3.13086 9.02734 2.90039 9.10547C2.66992 9.1875 2.40234 9.22852 2.09766 9.22852C1.62891 9.22852 1.26367 9.11523 1.00195 8.88867C0.744141 8.6582 0.615234 8.36719 0.615234 8.01562C0.615234 7.87891 0.625 7.74023 0.644531 7.59961C0.664062 7.45508 0.695312 7.29297 0.738281 7.11328L1.21875 5.40234C1.26172 5.23828 1.29688 5.08398 1.32422 4.93945C1.35547 4.79102 1.37109 4.65625 1.37109 4.53516C1.37109 4.31641 1.32617 4.16406 1.23633 4.07812C1.14648 3.99219 0.974609 3.94922 0.720703 3.94922C0.595703 3.94922 0.466797 3.96875 0.333984 4.00781C0.205078 4.04688 0.09375 4.08398 0 4.11914L0.128906 3.5918C0.445312 3.46289 0.748047 3.35352 1.03711 3.26367C1.32617 3.16992 1.59961 3.12305 1.85742 3.12305C2.32227 3.12305 2.67969 3.23633 2.92969 3.46289C3.18359 3.68555 3.31055 3.97656 3.31055 4.33594C3.31055 4.41016 3.30078 4.54102 3.28125 4.72852C3.26562 4.91602 3.23438 5.08789 3.1875 5.24414L2.70703 6.94922C2.66797 7.08594 2.63281 7.24219 2.60156 7.41797C2.57031 7.59375 2.55469 7.72656 2.55469 7.81641C2.55469 8.04297 2.60547 8.19727 2.70703 8.2793C2.80859 8.36133 2.98438 8.40234 3.23438 8.40234C3.35156 8.40234 3.48438 8.38281 3.63281 8.34375C3.78125 8.30078 3.88867 8.26367 3.95508 8.23242ZM4.07812 1.07812C4.07812 1.375 3.96484 1.62891 3.73828 1.83984C3.51562 2.04687 3.24609 2.15039 2.92969 2.15039C2.61328 2.15039 2.3418 2.04687 2.11523 1.83984C1.88867 1.62891 1.77539 1.375 1.77539 1.07812C1.77539 0.78125 1.88867 0.527344 2.11523 0.316406C2.3418 0.105469 2.61328 0 2.92969 0C3.24609 0 3.51562 0.105469 3.73828 0.316406C3.96484 0.527344 4.07812 0.78125 4.07812 1.07812Z" fill="white"/>
          </svg>
        </button>
      </div>
    </div>
    <div class="sum">
      <div class="icon">
        <svg width="8" height="10" viewBox="0 0 8 10" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M4.6993 6.52857H2.96503V7.31429H6.08392V8.57143H2.96503V10H1.04895V8.57143H0V7.31429H1.04895V6.52857H0V4.8H1.04895V0H4.6993C5.62238 0 6.40094 0.32381 7.03496 0.971429C7.67832 1.61904 8 2.4 8 3.31429C8 4.22857 7.68299 4.99524 7.04895 5.61429C6.42425 6.22381 5.64103 6.52857 4.6993 6.52857ZM4.6993 1.84286H2.96503V4.8H4.6993C5.09091 4.8 5.41725 4.65714 5.67832 4.37143C5.94872 4.08571 6.08392 3.73333 6.08392 3.31429C6.08392 2.89524 5.94872 2.54762 5.67832 2.27143C5.41725 1.98571 5.09091 1.84286 4.6993 1.84286Z" fill="white"/>
        </svg>
      </div>
      <div class="sum-value">
        <span class="label">Сумма</span>
        <span class="value">500₽</span>
      </div>
      <div class="currency">
        <button
            v-for="currency in currencies"
            :key="currency"
            type="button"
            class="currency-item"
            :class="{ active: activeCurrency === currency }"
            @click="activeCurrency = currency"
        >
          {{ currency }}
        </button>
      </div>
    </div>
    <div class="submit">
      <button type="button" class="button">Оплатить 500$</button>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '../styles/variables' as *;
@use '../styles/mixins' as *;

.service-top-up {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 16px;

  .service-info {
    width: 286px;
    display: flex;
    align-items: center;
    gap: 12px;

    .service-image {
      width: 72px;
      flex-shrink: 0;

      img {
        display: block;
        width: 100%;
        height: auto;
      }
    }

    &__content {
      flex-grow: 1;
      display: flex;
      align-items: flex-start;
      flex-direction: column;
      gap: 4px;

      .action-name {
        display: flex;
        gap: 8px;
        align-items: center;

        .text {
          @include text-truncate();
          font-size: 16px;
          font-weight: 700;
          line-height: 27px;
          color: $color-text-secondary;
        }

        .discount {
          display: flex;
          justify-content: center;
          align-items: center;
          height: 20px;
          background: #6EB83F;
          border-radius: 9999px;
          padding: 0 8px;
          color: #ffffff;
          font-size: 11px;
          font-weight: 700;
        }
      }

      .promocode-field {
        .dropdown {
          padding: 4px 12px;
          background: #268bf31a;
          border-radius: $radius-small;

          &-trigger {
            display: flex;
            align-items: center;
            gap: 2px;
            font-size: 12px;
            font-weight: 700;
            line-height: 18px;
          }
        }
      }
    }
  }

  .login {
    width: 284px;
    height: 64px;
    padding: 0 20px;
    background: $color-background;
    border-radius: $radius-base;
    display: flex;
    align-items: center;
    gap: 12px;

    .icon {
      flex-shrink: 0;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .field {
      flex-grow: 1;

      input {
        width: 100%;
        background: transparent;
        font-size: 15px;
        font-weight: 700;
        color: $color-text-muted;

        &::placeholder {
          color: $color-text-muted;
        }
      }
    }

    .tip {

      &-button {
        width: 20px;
        height: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #A0A8B5;
        border-radius: 6px;

        svg {
          display: block;
          margin-left: 1px;
        }
      }
    }
  }

  .sum {
    width: 340px;
    height: 64px;
    padding: 0 16px;
    background: $color-background;
    border-radius: $radius-base;
    display: flex;
    align-items: center;
    gap: 12px;

    .icon {
      flex-shrink: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: #76829B;
    }

    .sum-value {
      flex-grow: 1;
      display: flex;
      flex-direction: column;

      .label {
        font-size: 12px;
        font-weight: 700;
        line-height: 16px;
        color: $color-text-muted;
      }

      .value {
        font-size: 18px;
        font-weight: 700;
        line-height: 18px;
        color: $color-text-secondary;
      }
    }

    .currency {
      flex-shrink: 0;
      display: flex;
      gap: 6px;

      &-item {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 36px;
        height: 36px;
        border-radius: $radius-small;
        background: #E8EAED;
        font-size: 16px;
        font-weight: 700;
        color: $color-text-muted;

        &.active {
          background: $color-primary;
          color: $color-surface;
        }

        &:hover {
          background: $color-primary-hover;
          color: $color-surface;
        }
      }
    }
  }

  .submit {
    width: 200px;
    height: 64px;

    .button {
      justify-content: center;
      width: 100%;
      height: 100%;
      font-size: 16px;
      font-weight: 700;
    }
  }
}
</style>