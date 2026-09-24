<script setup>
import { ref, watch, onBeforeUnmount } from "vue";
import { useRoute } from "vue-router";
import { LifeBuoy, X } from "@lucide/vue";
import SupportChat from "./SupportChat.vue";
import { useSession } from "../session";
import { useDialog } from "../composables/useDialog";
const open = ref(false),
  busy = ref(false),
  panel = ref(null);
const route = useRoute();
const session = useSession();
const opened = ref(false);
const checking = ref(false),
  confirmedAccount = ref(false);
const emit = defineEmits(["open-change"]);
watch(open, (value) => {
  emit("open-change", value);
});
watch(
  () => session.ready,
  (ready) => {
    if (!ready) confirmedAccount.value = false;
  },
);
async function openChat() {
  if (checking.value) return;
  checking.value = true;
  open.value = true;
  try {
    confirmedAccount.value = await session.restore();
  } finally {
    opened.value = true;
    checking.value = false;
  }
}
onBeforeUnmount(() => emit("open-change", false));
function close() {
  if (!busy.value) open.value = false;
}
useDialog(open, panel, close);
watch(() => route.fullPath, close);
</script>
<template>
  <button
    v-show="!open"
    class="public-support-trigger"
    type="button"
    aria-haspopup="dialog"
    aria-controls="public-support-dialog"
    :aria-expanded="open"
    @click="openChat"
  >
    <LifeBuoy :size="21" aria-hidden="true" /><span>¿Te ayudamos?</span>
  </button>
  <Teleport to="body">
    <div v-show="open" class="public-support-backdrop" @click.self="close">
      <section
        id="public-support-dialog"
        ref="panel"
        class="public-support-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="public-support-title"
        tabindex="-1"
      >
        <header>
          <div>
            <p class="eyebrow">Hablemos</p>
            <h2 id="public-support-title">¿En qué podemos ayudarte?</h2>
            <p>Dudas, problemas o sugerencias. Escríbenos aquí.</p>
          </div>
          <button
            type="button"
            class="public-support-close"
            :disabled="busy"
            aria-label="Cerrar soporte"
            @click="close"
          >
            <X :size="24" />
          </button>
        </header>
        <p v-if="checking" role="status">Preparando el chat…</p>
        <SupportChat
          v-if="opened"
          v-show="!checking"
          :mode="confirmedAccount ? 'account' : 'public'"
          :active="open && !checking"
          @busy-change="busy = $event"
        />
      </section>
    </div>
  </Teleport>
</template>
