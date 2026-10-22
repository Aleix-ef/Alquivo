<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useSession } from "../session";
import api from "../api";
import ConfirmDialog from "../components/ConfirmDialog.vue";
import TwoFactorSettings from "../components/TwoFactorSettings.vue";
import PasswordInput from "../components/PasswordInput.vue";
import { accountSection, accountSections } from "../accountSettings";
import { useConfirmDialog } from "../composables/useConfirmDialog";

const session = useSession();
const router = useRouter();
const route = useRoute();
const section = computed(() => accountSection(route.query, route.hash));
const sectionDescription = computed(
  () => accountSections.find((s) => s.id === section.value).description,
);
const changingEmail = computed(
  () => account.email.trim().toLowerCase() !== session.user?.email,
);
const emailSecurityState = ref("");
const sendingSecurityCode = ref(false);
const usage = ref(null);
const usageError = ref("");
const account = reactive({
  current_password: "",
  two_factor_code: "",
  name: session.user?.name || "",
  email: session.user?.email || "",
  portfolio_name: session.portfolio?.name || "",
  currency: session.portfolio?.currency || "EUR",
  country_code: session.portfolio?.country_code || "ES",
});
const password = reactive({
  current_password: "",
  password: "",
  password_confirmation: "",
});
const accountState = ref({ saving: false, error: "", success: "" });
const passwordState = ref({ saving: false, error: "", success: "" });
const verificationState = ref("");
const deletion = reactive({ current_password: "", confirmation: "" });
const deletionError = ref("");
const confirmation = useConfirmDialog();

async function saveAccount() {
  accountState.value = { saving: true, error: "", success: "" };
  try {
    const emailWasChanged = changingEmail.value;
    const { data } = await api.put("/account", account);
    session.save(data);
    account.current_password = "";
    account.two_factor_code = "";
    accountState.value.success = emailWasChanged
      ? "Cambios guardados. Confirma tu nuevo correo antes de cerrar sesión. Si no puedes acceder al correo, conserva tus códigos de recuperación."
      : "Cambios guardados.";
  } catch (e) {
    accountState.value.error =
      e.response?.data?.message || "No se pudieron guardar los cambios.";
  } finally {
    accountState.value.saving = false;
  }
}

watch(
  () => account.email,
  () => {
    account.two_factor_code = "";
    emailSecurityState.value = "";
  },
);
async function sendEmailChangeCode() {
  if (sendingSecurityCode.value || !account.current_password) return;
  sendingSecurityCode.value = true;
  emailSecurityState.value = "";
  try {
    emailSecurityState.value = (
      await api.post("/account/two-factor/security-code", {
        purpose: "change_email",
        current_password: account.current_password,
        new_email: account.email,
      })
    ).data.message;
  } catch (e) {
    emailSecurityState.value =
      e.response?.data?.message || "No se ha podido enviar el código.";
  } finally {
    sendingSecurityCode.value = false;
  }
}

async function savePassword() {
  passwordState.value = { saving: true, error: "", success: "" };
  try {
    const { data } = await api.put("/account/password", password);
    password.current_password = "";
    password.password = "";
    password.password_confirmation = "";
    passwordState.value.success = data.message;
  } catch (e) {
    passwordState.value.error =
      e.response?.data?.message || "No se pudo cambiar la contraseña.";
  } finally {
    passwordState.value.saving = false;
  }
}
async function resendVerification() {
  try {
    verificationState.value = (
      await api.post("/auth/email/resend")
    ).data.message;
  } catch (error) {
    verificationState.value =
      error.response?.status === 429
        ? "Espera unos minutos antes de solicitar otro enlace."
        : "No hemos podido enviar el enlace. Inténtalo más tarde.";
  }
}
async function deleteAccount() {
  deletionError.value = "";
  if (
    !(await confirmation.ask({
      title: "¿Eliminar definitivamente tu cuenta?",
      description:
        "Se borrarán tu cartera, inmuebles, documentos y movimientos. Esta acción no se puede deshacer.",
      confirmLabel: "Eliminar cuenta",
      danger: true,
    }))
  )
    return;
  try {
    await api.delete("/account", { data: deletion });
    session.clear();
    router.push("/login");
  } catch (e) {
    deletionError.value =
      e.response?.data?.message || "No se pudo eliminar la cuenta.";
  }
}
const megabytes = (bytes) =>
  `${(Number(bytes || 0) / 1024 / 1024).toFixed(1)} MB`;
onMounted(async () => {
  try {
    if (route.query.verified === "1") await session.restore();
    usage.value = (await api.get("/account/usage")).data;
  } catch {
    usageError.value = "No se pudo cargar el uso del plan.";
  }
});
</script>

<template>
  <main class="page settings-page">
    <header class="heading">
      <div>
        <p class="eyebrow">Configuración</p>
        <h1>Tu cuenta</h1>
        <p>{{ sectionDescription }}</p>
      </div>
    </header>
    <div class="account-settings-layout">
      <nav class="account-settings-nav" aria-label="Secciones de configuración">
        <RouterLink
          v-for="item in accountSections"
          :key="item.id"
          :to="{
            path: '/settings',
            query: { ...route.query, section: item.id },
          }"
          :aria-current="section === item.id ? 'page' : undefined"
          >{{ item.label }}</RouterLink
        >
      </nav>
      <div class="account-settings-content">
        <section
          v-show="section === 'profile'"
          class="settings-grid"
          aria-label="Perfil"
        >
          <form class="settings-card" @submit.prevent="saveAccount">
            <div>
              <p class="eyebrow">Perfil</p>
              <h2>Datos generales</h2>
            </div>
            <p v-if="accountState.error" class="error">
              {{ accountState.error }}
            </p>
            <p v-if="accountState.success" class="success">
              {{ accountState.success }}
            </p>
            <label>Nombre<input v-model="account.name" required /></label>
            <label
              >Email<input v-model="account.email" type="email" required
            /></label>
            <label
              v-if="account.email.trim().toLowerCase() !== session.user?.email"
            >
              Confirma tu contraseña para cambiar el correo
              <PasswordInput
                v-model="account.current_password"
                required
                autocomplete="current-password"
              />
            </label>
            <template
              v-if="changingEmail && session.user?.two_factor_confirmed_at"
            >
              <button
                v-if="session.user.two_factor_method === 'email'"
                class="button secondary"
                type="button"
                :disabled="sendingSecurityCode || !account.current_password"
                @click="sendEmailChangeCode"
              >
                Enviar código a mi correo actual
              </button>
              <p v-if="emailSecurityState" role="status">
                {{ emailSecurityState }}
              </p>
              <label
                >{{
                  session.user.two_factor_method === "email"
                    ? "Código del correo actual o de recuperación"
                    : "Código del autenticador o de recuperación"
                }}<input
                  v-model.trim="account.two_factor_code"
                  autocomplete="one-time-code"
                  maxlength="40"
                  required
              /></label>
            </template>
            <label
              >Nombre de la cartera<input
                v-model="account.portfolio_name"
                required
            /></label>
            <div class="field-row">
              <label
                >Moneda<input :value="account.currency" readonly /><small
                  >La beta utiliza euros. No cambiamos la moneda de importes ya
                  registrados.</small
                ></label
              ><label
                >País<input
                  v-model="account.country_code"
                  maxlength="2"
                  required
              /></label>
            </div>
            <footer>
              <button class="button primary" :disabled="accountState.saving">
                {{ accountState.saving ? "Guardando…" : "Guardar cambios" }}
              </button>
            </footer>
          </form>
          <section class="settings-card">
            <h2>Correo de la cuenta</h2>
            <template v-if="!session.user?.email_verified_at"
              ><p>
                Confirma tu correo para recibir códigos de acceso y avisos de
                seguridad. Puedes seguir utilizando Alquivo mientras tanto.
              </p>
              <button
                class="button secondary"
                type="button"
                @click="resendVerification"
              >
                Reenviar verificación
              </button>
              <p v-if="verificationState" role="status">
                {{ verificationState }}
              </p></template
            >
            <p v-else class="success">Correo verificado.</p>
          </section>
        </section>
        <section
          v-show="section === 'security'"
          class="settings-grid"
          aria-label="Seguridad"
        >
          <TwoFactorSettings @changed="session.restore()" />
          <form class="settings-card" @submit.prevent="savePassword">
            <div>
              <p class="eyebrow">Seguridad</p>
              <h2>Cambiar contraseña</h2>
            </div>
            <p v-if="passwordState.error" class="error">
              {{ passwordState.error }}
            </p>
            <p v-if="passwordState.success" class="success">
              {{ passwordState.success }}
            </p>
            <label
              >Contraseña actual<PasswordInput
                v-model="password.current_password"
                required
                autocomplete="current-password"
            /></label>
            <label
              >Nueva contraseña<PasswordInput
                v-model="password.password"
                required
                minlength="8"
                autocomplete="new-password"
            /></label>
            <label
              >Confirmar contraseña<PasswordInput
                v-model="password.password_confirmation"
                required
                autocomplete="new-password"
            /></label>
            <small>Utiliza al menos 8 caracteres, con letras y números.</small>
            <footer>
              <button class="button primary" :disabled="passwordState.saving">
                {{
                  passwordState.saving
                    ? "Actualizando…"
                    : "Actualizar contraseña"
                }}
              </button>
            </footer>
          </form>
        </section>
        <section
          v-show="section === 'plan'"
          class="settings-grid"
          aria-label="Plan y uso"
        >
          <p v-if="usageError" class="error" role="alert">{{ usageError }}</p>
          <section class="settings-card">
            <div>
              <p class="eyebrow">Plan y uso</p>
              <h2>Capacidad de tu cuenta</h2>
            </div>
            <div v-if="usage" class="usage">
              <span
                ><strong>Plan {{ usage.name }}</strong
                ><small
                  >{{ megabytes(usage.storage.used) }} de
                  {{ megabytes(usage.storage.limit) }}</small
                ></span
              >
              <div>
                <i
                  :style="{
                    width: `${Math.min(100, usage.storage.percentage)}%`,
                  }"
                ></i>
              </div>
              <RouterLink class="button secondary" to="/plans"
                >Ver planes y límites</RouterLink
              >
            </div>
          </section>
        </section>
        <section
          v-show="section === 'privacy'"
          class="settings-grid"
          aria-label="Privacidad"
        >
          <form
            class="settings-card danger-card"
            @submit.prevent="deleteAccount"
          >
            <div>
              <p class="eyebrow">Zona sensible</p>
              <h2>Eliminar cuenta</h2>
            </div>
            <p class="muted">
              Borra de forma permanente la cartera, propiedades, documentos y
              movimientos.
            </p>
            <p v-if="deletionError" class="error">{{ deletionError }}</p>
            <label
              >Contraseña actual<PasswordInput
                v-model="deletion.current_password"
                autocomplete="current-password"
                required /></label
            ><label
              >Escribe ELIMINAR<input v-model="deletion.confirmation" required
            /></label>
            <footer>
              <button
                class="button danger"
                :disabled="deletion.confirmation !== 'ELIMINAR'"
              >
                Eliminar definitivamente
              </button>
            </footer>
          </form>
        </section>
      </div>
    </div>
    <ConfirmDialog
      :dialog="confirmation.dialog.value"
      @confirm="confirmation.confirm"
      @cancel="confirmation.cancel"
    />
  </main>
</template>

<style scoped>
.account-settings-layout {
  display: grid;
  grid-template-columns: 180px minmax(0, 1fr);
  gap: 28px;
  align-items: start;
}
.account-settings-nav {
  display: grid;
  gap: 8px;
  position: sticky;
  top: 24px;
}
.account-settings-nav a {
  display: flex;
  align-items: center;
  min-height: 48px;
  padding: 12px 16px;
  border-radius: 10px;
  color: var(--muted);
  border: 1px solid transparent;
  text-decoration: none;
  font-size: 1rem;
}
.account-settings-nav a[aria-current="page"] {
  color: var(--green);
  border-color: var(--line);
  background: var(--surface);
  font-weight: 600;
}
.account-settings-content {
  min-width: 0;
}
.settings-card label {
  font-size: 1rem;
}
@media (max-width: 1100px) {
  .account-settings-content .settings-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 760px) {
  .account-settings-layout {
    grid-template-columns: minmax(0, 1fr);
    gap: 20px;
  }
  .account-settings-nav {
    position: static;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
