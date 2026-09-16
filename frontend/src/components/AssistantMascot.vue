<script setup>
import { computed } from "vue";
import base from "../assets/assistant/base.png";
import greeting from "../assets/assistant/greeting.png";
import wink from "../assets/assistant/wink.png";
import thinking from "../assets/assistant/thinking.png";

const props = defineProps({
  state: { type: String, default: "idle" },
  animated: { type: Boolean, default: true },
});
const poses = {
  idle: base,
  greeting,
  answered: wink,
  thinking,
  uncertain: thinking,
};
const pose = computed(() => poses[props.state] || base);
</script>

<template>
  <span
    class="assistant-mascot"
    :class="[`is-${state}`, { 'is-animated': animated }]"
    aria-hidden="true"
  >
    <Transition name="mascot-pose" mode="out-in">
      <img
        :key="pose"
        :src="pose"
        alt=""
        width="1254"
        height="1254"
        draggable="false"
      />
    </Transition>
  </span>
</template>

<style scoped>
.assistant-mascot {
  display: inline-grid;
  place-items: center;
  width: var(--mascot-size, 52px);
  height: var(--mascot-size, 52px);
  flex: 0 0 var(--mascot-size, 52px);
  pointer-events: none;
  transform-origin: 50% 85%;
}
.assistant-mascot img {
  grid-area: 1 / 1;
  width: 100%;
  height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 4px 5px #02081224);
}
.is-greeting.is-animated {
  animation: mascot-hello 900ms ease-out both;
}
.is-answered.is-animated {
  animation: mascot-hello 650ms ease-out both;
}
.is-thinking.is-animated {
  animation: mascot-think 3s ease-in-out infinite;
}
.mascot-pose-enter-active,
.mascot-pose-leave-active {
  transition: opacity 140ms ease;
}
.mascot-pose-enter-from,
.mascot-pose-leave-to {
  opacity: 0;
}
@keyframes mascot-hello {
  0% {
    transform: translateY(3px) rotate(-3deg);
  }
  40% {
    transform: translateY(-2px) rotate(3deg);
  }
  75% {
    transform: rotate(-1deg);
  }
  100% {
    transform: none;
  }
}
@keyframes mascot-think {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-3px) rotate(-1deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .assistant-mascot,
  .mascot-pose-enter-active,
  .mascot-pose-leave-active {
    animation: none;
    transition: none;
  }
}
</style>
