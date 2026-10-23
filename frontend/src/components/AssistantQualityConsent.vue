<script setup>
import { onMounted, ref } from "vue";
import api from "../api";

defineProps({ enabled: Boolean, busy: Boolean });
const settings = ref(null);
const accepted = ref(false);
const loading = ref(false);
const error = ref("");
const message = ref("");
async function load() {
  loading.value = true;
  error.value = "";
  try {
    settings.value = (await api.get("/assistant/quality-consent")).data;
  } catch {
    error.value = "No hemos podido cargar tu elección. No se ha cambiado.";
  } finally {
    loading.value = false;
  }
}
async function change(sharing) {
  if (loading.value || (sharing && !accepted.value)) return;
  loading.value = true;
  error.value = "";
  message.value = "";
  try {
    settings.value = (
      sharing
        ? await api.post("/assistant/quality-consent", {
            accepted: true,
            notice_version: settings.value.notice_version,
          })
        : await api.delete("/assistant/quality-consent")
    ).data;
    accepted.value = false;
    message.value = sharing
      ? "Gracias. Solo se compartirán las nuevas conversaciones a partir de ahora."
      : "Permiso retirado y registros de mejora eliminados. Tu historial de chat sigue disponible.";
  } catch (e) {
    error.value =
      e.response?.status === 409
        ? "Espera a que termine la consulta y vuelve a intentarlo."
        : "No hemos podido guardar tu elección. Inténtalo de nuevo.";
  } finally {
    loading.value = false;
  }
}
onMounted(load);
</script>

<template>
  <section class="quality-consent">
    <h2>Ayuda a mejorar Alquivo AI</h2>
    <p>
      Compartir es opcional. Puedes usar el asistente aunque no lo permitas.
      Solo afecta a las conversaciones nuevas; no se recuperan las anteriores.
    </p>
    <template v-if="settings">
      <details class="privacy-first-layer">
        <summary>Información de privacidad</summary>
        <p>{{ settings.notice_text }}</p>
        <p>
          Los mensajes pueden contener datos personales de otras personas. Evita
          incluir información innecesaria o sensible. Este permiso no autoriza
          publicar tus conversaciones ni entrenar modelos de OpenAI.
        </p>
        <RouterLink to="/privacy#mejora-ia">Política de privacidad</RouterLink>
      </details>
      <p v-if="settings.enabled" class="quality-status">
        Estás compartiendo para mejorar.
      </p>
      <template v-else>
        <label class="quality-check">
          <input
            v-model="accepted"
            type="checkbox"
            :disabled="loading || busy || !enabled"
          />
          Permito la revisión de mis nuevas conversaciones y sus datos de
          diagnóstico para mejorar Alquivo AI.
        </label>
        <button
          class="button secondary"
          :disabled="!enabled || !accepted || loading || busy"
          @click="change(true)"
        >
          Permitir compartir para mejorar
        </button>
        <p v-if="!enabled">
          Activa primero el asistente si quieres participar.
        </p>
      </template>
      <button
        v-if="settings.has_consent"
        class="button secondary"
        :disabled="loading || busy"
        @click="change(false)"
      >
        Dejar de compartir y borrar registros
      </button>
    </template>
    <p v-else-if="loading" role="status">Cargando tu elección…</p>
    <button v-else class="button secondary" @click="load">
      Volver a cargar
    </button>
    <p v-if="message" role="status">{{ message }}</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped>
.quality-consent {
  padding: 20px;
  display: grid;
  gap: 16px;
  font-size: 1rem;
  line-height: 1.6;
}
.quality-consent h2 {
  font-size: 1.2rem;
  margin: 0;
}
.quality-consent p {
  margin: 0;
}
.quality-check {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
}
.quality-check input {
  flex: 0 0 auto;
  width: 20px;
  height: 20px;
  margin-top: 3px;
}
.quality-status {
  color: var(--accent, #7dd3c7);
}
.quality-consent .button {
  white-space: normal;
}
</style>
