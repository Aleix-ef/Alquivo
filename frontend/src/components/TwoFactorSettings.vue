<script setup>
import { onMounted, ref } from "vue";
import api from "../api";

const status = ref(null),
  password = ref(""),
  code = ref(""),
  secret = ref(""),
  recovery = ref([]);
const busy = ref(false),
  error = ref(""),
  message = ref("");
async function load() {
  status.value = (await api.get("/account/two-factor")).data;
}
async function action(kind) {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  message.value = "";
  try {
    if (kind === "setup") {
      secret.value = (
        await api.post("/account/two-factor/setup", {
          current_password: password.value,
        })
      ).data.secret;
    } else if (kind === "confirm") {
      recovery.value = (
        await api.post("/account/two-factor/confirm", { code: code.value })
      ).data.recovery_codes;
      secret.value = "";
      message.value =
        "Doble factor activado. Hemos cerrado las otras sesiones de tu cuenta.";
      await load();
    } else {
      await api.delete("/account/two-factor", {
        data: { current_password: password.value, code: code.value },
      });
      recovery.value = [];
      message.value =
        "Doble factor desactivado. Hemos cerrado las otras sesiones.";
      await load();
    }
    password.value = "";
    code.value = "";
  } catch (e) {
    error.value =
      Object.values(e.response?.data?.errors || {}).flat()[0] ||
      e.response?.data?.message ||
      "No se pudo completar el cambio. Inténtalo de nuevo.";
  } finally {
    busy.value = false;
  }
}
onMounted(async () => {
  try {
    await load();
  } catch {
    error.value =
      "No se pudo cargar la seguridad de tu cuenta. Recarga la página para volver a intentarlo.";
  }
});
</script>

<template>
  <section class="settings-card">
    <div>
      <p class="eyebrow">Seguridad de acceso</p>
      <h2>Verificación en dos pasos</h2>
    </div>
    <p>
      Añade un código de tu móvil a tu contraseña. Disponible en todos los
      planes.
    </p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="message" class="success" role="status">{{ message }}</p>
    <p v-if="!status && !error" role="status">Cargando seguridad…</p>
    <template v-if="status">
      <p>
        <strong>{{
          status.enabled ? "Activada" : "Todavía no está activada"
        }}</strong>
      </p>
      <section v-if="recovery.length" aria-label="Códigos de recuperación">
        <h3>Guarda estos códigos ahora</h3>
        <p>
          No volveremos a mostrarlos. Cada uno permite entrar una sola vez si
          pierdes el móvil. Guárdalos en tu gestor de contraseñas, separados de
          tu dispositivo.
        </p>
        <ul class="recovery-codes">
          <li v-for="value in recovery" :key="value">
            <code>{{ value }}</code>
          </li>
        </ul>
        <button class="button secondary" type="button" @click="recovery = []">
          Ya los he guardado, ocultar
        </button>
      </section>
      <form v-if="secret" @submit.prevent="action('confirm')">
        <p>
          En tu aplicación de autenticación, añade una cuenta manual con el
          nombre <strong>Alquivo</strong>, tipo
          <strong>basado en tiempo</strong>, e introduce esta clave:
        </p>
        <label
          >Clave de configuración<input
            :value="secret"
            readonly
            aria-label="Clave secreta para la aplicación de autenticación"
        /></label>
        <p>
          No compartas esta clave. La configuración caduca a los 10 minutos.
        </p>
        <label
          >Código de 6 cifras<input
            v-model.trim="code"
            inputmode="numeric"
            autocomplete="one-time-code"
            pattern="[0-9]{6}"
            maxlength="6"
            required
        /></label>
        <button class="button primary" :disabled="busy">
          Confirmar activación
        </button>
        <button
          class="button secondary"
          type="button"
          :disabled="busy"
          @click="
            secret = '';
            code = '';
          "
        >
          Cancelar configuración
        </button>
      </form>
      <form
        v-else-if="!recovery.length"
        @submit.prevent="action(status.enabled ? 'disable' : 'setup')"
      >
        <p v-if="status.enabled">
          Te quedan {{ status.recovery_codes_remaining }} códigos de
          recuperación. Para renovar tus códigos o cambiar de móvil, desactiva y
          vuelve a activar la verificación. Conserva el móvil actual hasta
          terminar.
        </p>
        <label
          >Tu contraseña actual<input
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
        /></label>
        <label v-if="status.enabled"
          >Código del móvil o de recuperación<input
            v-model.trim="code"
            autocomplete="one-time-code"
            maxlength="40"
            required
        /></label>
        <button class="button secondary" :disabled="busy">
          {{
            busy
              ? "Un momento…"
              : status.enabled
                ? "Desactivar verificación en dos pasos"
                : "Configurar verificación en dos pasos"
          }}
        </button>
      </form>
    </template>
  </section>
</template>

<style scoped>
form {
  display: grid;
  gap: 1rem;
}
.recovery-codes {
  padding-left: 1.25rem;
  line-height: 1.9;
  overflow-wrap: anywhere;
}
code {
  font-size: 1rem;
}
</style>
