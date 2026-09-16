<script setup>
import { ref } from "vue";
import api from "../api";
import HelpGuides from "../components/HelpGuides.vue";
import SupportForm from "../components/SupportForm.vue";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import { useConfirmDialog } from "../composables/useConfirmDialog";
import { useSession } from "../session";
const session = useSession();
const confirmation = useConfirmDialog();
const privacyBusy = ref(false),
  privacyNotice = ref("");
async function clearAssistant() {
  if (
    privacyBusy.value ||
    !(await confirmation.ask({
      title: "¿Eliminar el historial del asistente?",
      description:
        "Se borrarán tus conversaciones y se desactivará el asistente. No se borrará ningún dato de tu cartera. Esta acción no se puede deshacer.",
      confirmLabel: "Eliminar historial",
      danger: true,
    }))
  )
    return;
  privacyBusy.value = true;
  privacyNotice.value = "";
  try {
    await api.delete("/assistant/activation");
    privacyNotice.value = "Historial eliminado y asistente desactivado.";
  } catch {
    privacyNotice.value =
      "No se pudo eliminar el historial. Inténtalo de nuevo o contacta con soporte.";
  } finally {
    privacyBusy.value = false;
  }
}
</script>
<template>
  <main class="page support-page">
    <header class="heading">
      <div>
        <p class="eyebrow">Estamos para ayudarte</p>
        <h1>Ayuda y soporte</h1>
        <p>Resuelve tus dudas y sigue con tus alquileres.</p>
      </div>
    </header>
    <div class="support-layout">
      <section class="panel support-compose">
        <h2>Escríbenos sin salir de Alquivo</h2>
        <p>
          Si tienes un problema con tu cuenta, tu suscripción o algo no
          funciona, cuéntanos qué estabas haciendo y qué mensaje aparece.
          También puedes proponernos mejoras: estamos construyendo la beta
          contigo.
        </p>
        <SupportForm />
      </section>
      <HelpGuides />
    </div>
    <section v-if="session.user?.email_verified_at" class="panel">
      <h2>Privacidad del asistente</h2>
      <p>
        Si has utilizado el asistente en versiones anteriores, puedes eliminar
        sus conversaciones aunque la función esté temporalmente desactivada.
      </p>
      <button
        class="button secondary"
        :disabled="privacyBusy"
        @click="clearAssistant"
      >
        {{ privacyBusy ? "Eliminando…" : "Eliminar mi historial de IA" }}
      </button>
      <p v-if="privacyNotice" role="status">{{ privacyNotice }}</p>
    </section>
    <ConfirmDialog
      :dialog="confirmation.dialog.value"
      @confirm="confirmation.confirm"
      @cancel="confirmation.cancel"
    />
  </main>
</template>
