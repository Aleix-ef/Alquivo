<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../api";
import PasswordInput from "./PasswordInput.vue";
const emit = defineEmits(["changed"]);
const status = ref(null),
  devices = ref([]),
  selectedMethod = ref("email");
const password = ref(""),
  methodPassword = ref(""),
  methodCode = ref("");
const disablePassword = ref(""),
  disableCode = ref("");
const pendingSetup = ref(null),
  code = ref(""),
  emailCode = ref(""),
  rememberDevice = ref(false),
  recovery = ref([]);
const busy = ref(false),
  error = ref(""),
  message = ref("");
const methodNames = {
  email: "Código por correo",
  authenticator: "Aplicación de autenticación",
  both: "Correo y aplicación",
};
const emailAvailable = computed(
  () => status.value?.email_verified && status.value?.email_delivery_available,
);
const needsAuthenticator = computed(
  () =>
    status.value?.enabled &&
    !status.value.authenticator_configured &&
    selectedMethod.value !== "email",
);
const proofLabel = computed(() =>
  status.value?.method === "email"
    ? "Código enviado al correo o de recuperación"
    : "Código del autenticador o de recuperación",
);
async function load() {
  status.value = (await api.get("/account/two-factor")).data;
  selectedMethod.value = status.value.enabled ? status.value.method : "email";
  devices.value = status.value.enabled
    ? (await api.get("/account/two-factor/devices")).data.devices
    : [];
}
async function perform(callback) {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  message.value = "";
  try {
    await callback();
  } catch (e) {
    error.value =
      Object.values(e.response?.data?.errors || {}).flat()[0] ||
      e.response?.data?.message ||
      "No se ha podido completar el cambio. Inténtalo de nuevo.";
  } finally {
    busy.value = false;
  }
}
function setup() {
  return perform(async () => {
    pendingSetup.value = (
      await api.post("/account/two-factor/setup", {
        current_password: status.value.enabled
          ? methodPassword.value
          : password.value,
        method: selectedMethod.value,
        code: status.value.enabled ? methodCode.value : undefined,
      })
    ).data;
    password.value = "";
    methodPassword.value = "";
    methodCode.value = "";
    code.value = "";
    emailCode.value = "";
    message.value =
      pendingSetup.value.method === "email"
        ? "Te hemos enviado un código a tu correo."
        : "Añade Alquivo a tu autenticador para completar la configuración.";
  });
}
function confirm() {
  return perform(async () => {
    recovery.value = (
      await api.post("/account/two-factor/confirm", {
        code: code.value,
        email_code: emailCode.value || undefined,
        remember_device: rememberDevice.value,
      })
    ).data.recovery_codes;
    pendingSetup.value = null;
    code.value = "";
    emailCode.value = "";
    rememberDevice.value = false;
    await load();
    emit("changed");
    message.value =
      "Verificación activada. Guarda los códigos de recuperación. Las otras sesiones se han cerrado.";
  });
}
function cancelSetup() {
  return perform(async () => {
    await api.delete("/account/two-factor/setup");
    pendingSetup.value = null;
    code.value = "";
    emailCode.value = "";
    rememberDevice.value = false;
    message.value = "Configuración cancelada. Tu método actual no ha cambiado.";
  });
}
function resendSetup() {
  return perform(async () => {
    message.value = (
      await api.post("/account/two-factor/setup/resend")
    ).data.message;
  });
}
function sendSecurityCode(purpose) {
  return perform(async () => {
    message.value = (
      await api.post("/account/two-factor/security-code", {
        current_password:
          purpose === "disable" ? disablePassword.value : methodPassword.value,
        purpose,
        ...(purpose === "change_method"
          ? { method: selectedMethod.value }
          : {}),
      })
    ).data.message;
  });
}
function changeMethod() {
  if (needsAuthenticator.value) return setup();
  return perform(async () => {
    await api.put("/account/two-factor/method", {
      method: selectedMethod.value,
      current_password: methodPassword.value,
      code: methodCode.value,
    });
    methodPassword.value = "";
    methodCode.value = "";
    await load();
    emit("changed");
    message.value =
      "Método actualizado. Vuelve a autorizar los navegadores que quieras recordar.";
  });
}
function disable() {
  return perform(async () => {
    await api.delete("/account/two-factor", {
      data: {
        current_password: disablePassword.value,
        code: disableCode.value,
      },
    });
    disablePassword.value = "";
    disableCode.value = "";
    recovery.value = [];
    await load();
    emit("changed");
    message.value =
      "Verificación desactivada. Las otras sesiones se han cerrado.";
  });
}
function revokeDevice(id) {
  return perform(async () => {
    await api.delete(`/account/two-factor/devices/${id}`);
    await load();
    message.value =
      "Dispositivo revocado. Necesitará el código en su próximo acceso.";
  });
}
function revokeAllDevices() {
  return perform(async () => {
    await api.delete("/account/two-factor/devices");
    devices.value = [];
    message.value = "Dispositivos revocados. Tu verificación sigue activa.";
  });
}
onMounted(() => perform(load));
</script>

<template>
  <section class="settings-card mfa-settings">
    <div>
      <p class="eyebrow">Seguridad de acceso</p>
      <h2>Verificación en dos pasos</h2>
    </div>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="message" class="success" role="status">{{ message }}</p>
    <p v-if="!status && !error" role="status">Cargando seguridad…</p>
    <template v-if="status">
      <p>
        <strong>{{
          status.enabled
            ? `Activada · ${methodNames[status.method]}`
            : "Todavía no está activada"
        }}</strong>
      </p>
      <p>
        Recibe un código por correo para los accesos nuevos. También puedes
        elegir una aplicación de autenticación.
      </p>
      <p v-if="!status.email_verified" class="muted">
        Verifica {{ status.email_hint }} desde Perfil para poder recibir
        códigos.
      </p>
      <p v-else-if="!status.email_delivery_available" class="muted">
        El envío de correo no está disponible ahora. Puedes usar el
        autenticador.
      </p>
      <form v-if="!status.enabled && !pendingSetup" @submit.prevent="setup">
        <label
          >Cómo quieres verificarte<select
            v-model="selectedMethod"
            :disabled="busy"
          >
            <option value="email" :disabled="!emailAvailable">
              Código por correo
            </option>
            <option value="authenticator">Aplicación de autenticación</option>
            <option value="both" :disabled="!emailAvailable">
              Correo y aplicación
            </option>
          </select></label
        >
        <label
          >Contraseña actual<PasswordInput
            v-model="password"
            autocomplete="current-password"
            required
        /></label>
        <button
          class="button primary"
          :disabled="
            busy || (selectedMethod !== 'authenticator' && !emailAvailable)
          "
        >
          {{
            selectedMethod === "email"
              ? "Enviar código a mi correo"
              : "Configurar verificación"
          }}
        </button>
      </form>
      <form v-if="pendingSetup" @submit.prevent="confirm">
        <p v-if="pendingSetup.method === 'email'">
          Introduce el código enviado a {{ pendingSetup.email_hint }}. Caduca en
          5 minutos.
        </p>
        <template v-if="pendingSetup.secret"
          ><p>
            Añade una cuenta Alquivo en tu autenticador, basada en tiempo, con
            esta clave. La configuración caduca a los 10 minutos.
          </p>
          <label
            >Clave de configuración<input
              :value="pendingSetup.secret"
              readonly /></label
        ></template>
        <label
          >{{
            pendingSetup.method === "email"
              ? "Código del correo"
              : "Código del autenticador"
          }}<input
            v-model.trim="code"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            pattern="[0-9]{6}"
            required
        /></label>
        <label
          v-if="
            pendingSetup.method === 'both' &&
            pendingSetup.email_confirmation_required
          "
          >Código enviado a tu correo<input
            v-model.trim="emailCode"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            pattern="[0-9]{6}"
            required
        /></label>
        <label class="mfa-remember"
          ><input v-model="rememberDevice" type="checkbox" />No volver a pedir
          el código en este navegador durante 90 días.</label
        ><small>No lo actives en un dispositivo compartido.</small>
        <div class="mfa-actions">
          <button class="button primary" :disabled="busy">
            Confirmar activación</button
          ><button
            v-if="pendingSetup.email_confirmation_required"
            class="button secondary"
            type="button"
            :disabled="busy"
            @click="resendSetup"
          >
            Reenviar código</button
          ><button
            class="button secondary"
            type="button"
            :disabled="busy"
            @click="cancelSetup"
          >
            Cancelar
          </button>
        </div>
      </form>
      <section
        v-if="recovery.length"
        class="mfa-section"
        aria-label="Códigos de recuperación"
      >
        <h3>Guarda estos códigos</h3>
        <p>
          Solo se muestran ahora. Cada uno permite recuperar el acceso una vez
          si no puedes utilizar tu correo o autenticador. Guárdalos en un lugar
          seguro.
        </p>
        <ul class="recovery-codes">
          <li v-for="value in recovery" :key="value">
            <code>{{ value }}</code>
          </li>
        </ul>
        <button class="button secondary" type="button" @click="recovery = []">
          Ya los he guardado
        </button>
      </section>
      <template v-if="status.enabled && !pendingSetup">
        <section class="mfa-section" aria-labelledby="trusted-devices-title">
          <h3 id="trusted-devices-title">Dispositivos de confianza</h3>
          <p>
            Marca «Recordar este dispositivo» al entrar para no repetir el
            código durante 90 días en ese navegador. Borrar sus cookies o
            cambiar la seguridad de la cuenta obliga a verificarlo otra vez.
          </p>
          <p v-if="!devices.length" class="muted">
            Aún no hay dispositivos recordados.
          </p>
          <ul v-else class="device-list">
            <li v-for="device in devices" :key="device.id">
              <span
                ><strong>{{ device.name || "Navegador" }}</strong
                ><small
                  >Recordado hasta
                  {{
                    new Date(device.expires_at).toLocaleDateString("es-ES")
                  }}</small
                ></span
              ><button
                class="button secondary"
                type="button"
                :disabled="busy"
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
            :disabled="busy"
            @click="revokeAllDevices"
          >
            Revocar todos
          </button>
        </section>
        <details class="mfa-section">
          <summary>Cambiar método de verificación</summary>
          <p>
            Para «Correo y aplicación» se necesitan ambos códigos al entrar.
            Confirma el cambio con tu método actual.
          </p>
          <form @submit.prevent="changeMethod">
            <label
              >Nuevo método<select
                v-model="selectedMethod"
                :disabled="busy"
                @change="methodCode = ''"
              >
                <option value="email" :disabled="!emailAvailable">
                  Código por correo
                </option>
                <option value="authenticator">
                  Aplicación de autenticación
                </option>
                <option value="both" :disabled="!emailAvailable">
                  Correo y aplicación
                </option>
              </select></label
            ><label
              >Contraseña actual<PasswordInput
                v-model="methodPassword"
                autocomplete="current-password"
                required
            /></label>
            <button
              v-if="status.method === 'email'"
              class="button secondary"
              type="button"
              :disabled="
                busy || !methodPassword || selectedMethod === status.method
              "
              @click="sendSecurityCode('change_method')"
            >
              Enviar código de confirmación</button
            ><label
              >{{ proofLabel
              }}<input
                v-model.trim="methodCode"
                autocomplete="one-time-code"
                maxlength="40"
                required /></label
            ><button
              class="button secondary"
              :disabled="busy || selectedMethod === status.method"
            >
              {{
                needsAuthenticator
                  ? "Continuar con el autenticador"
                  : "Guardar método"
              }}
            </button>
          </form>
        </details>
        <details class="mfa-section">
          <summary>Desactivar verificación en dos pasos</summary>
          <p>
            Te quedan {{ status.recovery_codes_remaining }} códigos de
            recuperación. Si desactivas la verificación, tu contraseña será la
            única protección al entrar.
          </p>
          <form @submit.prevent="disable">
            <label
              >Contraseña actual<PasswordInput
                v-model="disablePassword"
                autocomplete="current-password"
                required /></label
            ><button
              v-if="status.method === 'email'"
              class="button secondary"
              type="button"
              :disabled="busy || !disablePassword"
              @click="sendSecurityCode('disable')"
            >
              Enviar código de confirmación</button
            ><label
              >{{ proofLabel
              }}<input
                v-model.trim="disableCode"
                autocomplete="one-time-code"
                maxlength="40"
                required /></label
            ><button class="button secondary" :disabled="busy">
              Desactivar verificación
            </button>
          </form>
        </details>
      </template>
    </template>
  </section>
</template>

<style scoped>
.mfa-settings form {
  display: grid;
  gap: 16px;
}
.mfa-section {
  display: grid;
  gap: 14px;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--line);
}
details.mfa-section {
  display: block;
}
summary {
  cursor: pointer;
  padding-block: 10px;
  min-height: 44px;
  font-weight: 600;
}
.mfa-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.mfa-remember {
  display: flex !important;
  align-items: flex-start;
  gap: 12px !important;
  font-size: 1rem !important;
}
.mfa-remember input {
  flex: 0 0 auto;
  width: 20px;
  height: 20px;
  margin: 2px 0 0;
}
.recovery-codes {
  line-height: 1.9;
  overflow-wrap: anywhere;
}
.device-list {
  display: grid;
  gap: 16px;
  padding: 0;
  list-style: none;
}
.device-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.device-list li span {
  display: grid;
  gap: 8px;
  min-width: 0;
  overflow-wrap: anywhere;
}
code {
  font-size: 1rem;
}
</style>
