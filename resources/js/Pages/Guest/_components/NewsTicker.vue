<template>
  <template v-if="items.length > 0">
  <div class="ticker-bar" :style="{ top: navTop + 'px' }">
    <div class="ticker-label">{{ tickerLabel }}</div>
    <div class="ticker-track" @mouseenter="pauseTicker" @mouseleave="resumeTicker">
      <div class="ticker-inner" :style="tickerStyle">
        <span
          v-for="(item, index) in doubledNews"
          :key="index"
          class="ticker-item"
          @click="openPopup(item)"
        >
          <span class="tag">
            {{ item.categoryLabel }}
          </span>
          {{ item.title }}
        </span>
      </div>
    </div>
  </div>

  <Transition name="fade">
    <div v-if="activeNews" class="ticker-modal-overlay" @click.self="closePopup">
      <div class="ticker-modal-card" :dir="isRtl ? 'rtl' : 'ltr'">
        <button class="ticker-modal-close" @click="closePopup" aria-label="Close">✕</button>
        <img v-if="activeNews.image" :src="activeNews.image" :alt="activeNews.title" class="ticker-modal-image" />
        <div class="ticker-modal-body">
          <div class="ticker-modal-tag">
            {{ activeNews.categoryLabel }}
          </div>
          <h2 class="ticker-modal-title">{{ activeNews.title }}</h2>
          <p class="ticker-modal-description">{{ activeNews.description }}</p>
        </div>
      </div>
    </div>
  </Transition>
  </template>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()

const t = computed(() => page.props.translations?.home?.ticker || {})

const tickerLabel = computed(() => t.value.label || 'Breaking')

const items = computed(() => {
  const data = page.props.newsTickers || []
  if (data.length === 0) {
    return []
  }
  return data.map(item => ({
    category: item.category,
    categoryLabel: item.categoryLabel,
    title: item.title || '',
    description: item.description || '',
    image: item.image || '',
  }))
})

const doubledNews = computed(() => [...items.value, ...items.value])

const isPaused = ref(false)
const offset = ref(0)
let animationFrame = null
let lastTime = null
const speed = 0.04

const isRtl = computed(() => page.props.locale === 'ar')

const navHidden = ref(false)
let scrollY = 0

const navTop = computed(() => {
  const top = navHidden.value ? 0 : (window.innerWidth >= 768 ? 80 : 56)
  console.log('[Ticker] navTop:', top, 'navHidden:', navHidden.value)
  return top
})

function onScroll() {
  const sy = window.scrollY
  const wasHidden = navHidden.value
  if (sy > scrollY && sy > 100) {
    navHidden.value = true
  } else if (sy < scrollY) {
    navHidden.value = false
  }
  if (sy < 50) {
    navHidden.value = false
  }
  scrollY = sy
  if (wasHidden !== navHidden.value) {
    console.log('[Ticker] navHidden changed:', wasHidden, '->', navHidden.value, 'at scrollY:', sy)
  }
}

const tickerStyle = computed(() => ({
  transform: isRtl.value ? `translateX(${offset.value}px)` : `translateX(-${offset.value}px)`,
  display: 'flex',
  whiteSpace: 'nowrap',
  willChange: 'transform'
}))

function getTickerHalfWidth() {
  const inner = document.querySelector('.ticker-inner')
  if (!inner) return 10000
  return inner.scrollWidth / 2
}

function animate(timestamp) {
  if (!lastTime) lastTime = timestamp
  const delta = timestamp - lastTime
  lastTime = timestamp

  if (!isPaused.value) {
    offset.value += speed * delta
    const half = getTickerHalfWidth()
    if (offset.value >= half) offset.value -= half
  }
  animationFrame = requestAnimationFrame(animate)
}

function pauseTicker() { isPaused.value = true }
function resumeTicker() { isPaused.value = false }

const activeNews = ref(null)

function openPopup(item) {
  activeNews.value = item
  isPaused.value = true
}

function closePopup() {
  activeNews.value = null
  isPaused.value = false
}

function handleKeydown(e) {
  if (e.key === 'Escape') closePopup()
}

onMounted(() => {
  animationFrame = requestAnimationFrame(animate)
  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('scroll', onScroll, { passive: true })
  onScroll()
})

onUnmounted(() => {
  cancelAnimationFrame(animationFrame)
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('scroll', onScroll)
})
</script>

<style scoped>
.ticker-bar {
  background: #0f0f1c;
  border-top: 1px solid rgba(255,255,255,0.08);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  display: flex;
  align-items: center;
  height: 40px;
  overflow: hidden;
  position: sticky;
  z-index: 40;
}

.ticker-label {
  background: #e63946;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  padding: 0 14px;
  height: 100%;
  display: flex;
  align-items: center;
  white-space: nowrap;
  flex-shrink: 0;
  z-index: 1;
}

.ticker-track {
  flex: 1;
  overflow: hidden;
  cursor: default;
}

.ticker-item {
  display: inline-flex;
  align-items: center;
  color: #fff;
  font-size: 13px;
  padding: 0 28px;
  transition: color 0.15s;
}

.ticker-item:hover { color: #93c5fd; cursor: pointer; }

.ticker-item::after {
  content: '';
  display: inline-block;
  width: 4px;
  height: 4px;
  background: rgba(255,255,255,0.25);
  border-radius: 50%;
  margin-left: 28px;
}

.tag {
  font-size: 10px;
  font-weight: 600;
  border-radius: 3px;
  padding: 2px 6px;
  margin-right: 8px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  background: rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.7);
}


.ticker-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.ticker-modal-card {
  background: #fff;
  border-radius: 12px;
  max-width: 540px;
  width: 100%;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  position: relative;
}

.ticker-modal-close {
  position: absolute;
  top: 12px;
  right: 12px;
  background: rgba(0,0,0,0.5);
  border: none;
  font-size: 16px;
  color: #fff;
  cursor: pointer;
  line-height: 1;
  padding: 6px 10px;
  border-radius: 6px;
  transition: background 0.15s;
  z-index: 2;
}

.ticker-modal-close:hover { background: rgba(0,0,0,0.7); }

.ticker-modal-image {
  width: 100%;
  height: 200px;
  object-fit: cover;
  display: block;
}

.ticker-modal-body {
  padding: 24px;
}

.ticker-modal-tag {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  border-radius: 4px;
  padding: 4px 10px;
  margin-bottom: 12px;
}


.ticker-modal-title {
  font-size: 18px;
  font-weight: 600;
  color: #111;
  line-height: 1.4;
  margin: 0 0 12px;
}

.ticker-modal-description {
  font-size: 14px;
  color: #555;
  line-height: 1.7;
  margin: 0;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: scale(0.97);
}
</style>
