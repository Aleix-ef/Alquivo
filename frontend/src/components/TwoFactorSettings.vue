<script setup>
import { onMounted, ref } from "vue";
import api from "../api";
import PasswordInput from "./PasswordInput.vue";

const status = ref(null),
  devices = ref([]),
  password = ref(""),
  code = ref(""),
  methodPassword = ref(""),
  methodCode = ref(""),
  selectedMethod = ref("authenticator"),
  secret = ref(""),
  recovery = ref([]);
const busy = ref(false),
  error = ref(""),
  message = ref("");
async function load() {
  status.value = (await api.get("/account/two-factor")).data;
  selectedMethod.value = status.value.method;
  devices.value = status.value.enabled
    ? (await api.get("/account/two-factor/devices")).data.devices
    : [];
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
    } else if (kind === "method") {
      await api.put("/account/two-factor/method", {
        method: selectedMethod.value,
        current_password: methodPassword.value,
        code: methodCode.value,
      });
      methodPassword.value = "";
      methodCode.value = "";
      message.value =
        "Método actualizado. Por seguridad, tendrás que volver a verificar tus dispositivos recordados.";
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
async function revokeDevice(id) {
  error.value = "";
  try {
    await api.delete(`/account/two-factor/devices/${id}`);
    await load();
    message.value = "Dispositivo revocado. Volverá a pedir el segundo factor.";
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo revocar el dispositivo.";
  }
}
async function revokeAllDevices() {
  error.value = "";
  try {
    await api.delete("/account/two-factor/devices");
    devices.value = [];
    message.value = "Se han revocado todos los dispositivos recordados.";
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudieron revocar los dispositivos.";
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
      Elige cómo verificar los accesos nuevos y qué dispositivos de confianza
      recordar.
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
      <p v-if="!status.enabled">
        Actívala primero con una aplicación de autenticación. Después podrás
        elegir correo o ambos métodos, si el envío de email está configurado.
      </p>
      <section
        v-if="status.enabled"
        class="mfa-methods"
        aria-labelledby="mfa-method-title"
      >
        <h3 id="mfa-method-title">Método para nuevos accesos</h3>
        <p>
          En la opción «Ambos» tendrás que confirmar el código de tu
          autenticador y el que llegue a tu correo.
        </p>
        <form @submit.prevent="action('method')">
          <label
            >Método de verificación
            <select v-model="selectedMethod">
              <option value="authenticator">Aplicación de autenticación</option>
              <option
                value="email"
                :disabled="
                  !status.email_verified || !status.email_delivery_available
                "
              >
                Código por correo
              </option>
              <option
                value="both"
                :disabled="
                  !status.email_verified || !status.email_delivery_available
                "
              >
                Ambos métodos
              </option>
            </select>
          </label>
          <p v-if="!status.email_verified" class="muted">
            Confirma {{ status.email_hint }} antes de activar el método por
            correo.
          </p>
          <p v-else-if="!status.email_delivery_available" class="muted">
            El envío de correo aún no está configurado. Mientras tanto, usa el
            autenticador.
          </p>
          <label
            >Contraseña actual<PasswordInput
              v-model="methodPassword"
              autocomplete="current-password"
              required
          /></label>
          <label
            >Código actual del autenticador o de recuperación<input
              v-model.trim="methodCode"
              inputmode="text"
              autocomplete="one-time-code"
              maxlength="40"
              required
          /></label>
          <button
            class="button secondary"
            :disabled="busy || selectedMethod === status.method"
          >
            Guardar método de verificación
          </button>
        </form>
      </section>
      <section
        v-if="status.enabled"
        class="trusted-devices"
        aria-labelledby="trusted-devices-title"
      >
        <h3 id="trusted-devices-title">Dispositivos recordados</h3>
        <p>
          Al iniciar sesión puedes recordar un dispositivo durante 90 días.
          Desde aquí puedes revocarlo cuando quieras.
        </p>
        <p v-if="!devices.length" class="muted">
          No hay dispositivos recordados.
        </p>
        <ul v-else>
          <li v-for="device in devices" :key="device.id">
            <span
              ><strong>{{ device.name || "Navegador" }}</strong
              ><small
                >Último acceso:
                {{
                  device.last_used_at
                    ? new Date(device.last_used_at).toLocaleString("es-ES")
                    : "—"
                }}</small
              ></span
            >
            <button
              class="button secondary"
              type="button"
              @click="revokeDevice(device.id)"
            >
              Revocar
            </button>
          </li>
        </ul>
        <button
          v-if="devices.length"
          class="button secondary"
          type="button"
          @click="revokeAllDevices"
        >
          Revocar todos
        </button>
      </section>
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
          >Tu contraseña actual<PasswordInput
            v-model="password"
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
.mfa-methods,
.trusted-devices {
  display: grid;
  gap: 0.8rem;
  margin-block: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid var(--line, rgba(255, 255, 255, 0.12));
}
.trusted-devices ul {
  display: grid;
  gap: 0.65rem;
  margin: 0;
  padding: 0;
  list-style: none;
}
.trusted-devices li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}
.trusted-devices li span {
  display: grid;
  min-width: 0;
  gap: 0.2rem;
}
.trusted-devices li strong,
.trusted-devices li small {
  overflow-wrap: anywhere;
}
code {
  font-size: 1rem;
}
</style>
