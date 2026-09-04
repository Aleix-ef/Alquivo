<script setup>
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useSession } from "../session";
import api from "../api";

const session = useSession();
const router = useRouter();
const route = useRoute();
const usage = ref(null);
const account = reactive({
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

async function saveAccount() {
  accountState.value = { saving: true, error: "", success: "" };
  try {
    const { data } = await api.put("/account", account);
    session.save(data);
    accountState.value.success = "Cambios guardados.";
  } catch (e) {
    accountState.value.error =
      e.response?.data?.message || "No se pudieron guardar los cambios.";
  } finally {
    accountState.value.saving = false;
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
  verificationState.value = (await api.post("/auth/email/resend")).data.message;
}
async function deleteAccount() {
  deletionError.value = "";
  if (
    !window.confirm(
      "Esta acción elimina definitivamente tu cartera, documentos y cuenta. ¿Continuar?",
    )
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
  if (route.query.verified === "1") await session.restore();
  usage.value = (await api.get("/account/usage")).data;
});
</script>

<template>
  <main class="page settings-page">
    <header class="heading">
      <div>
        <p class="eyebrow">Configuración</p>
        <h1>Tu cuenta</h1>
        <p>Datos del propietario y preferencias básicas de la cartera.</p>
      </div>
    </header>
    <div class="settings-grid">
      <form class="settings-card" @submit.prevent="saveAccount">
        <div>
          <p class="eyebrow">Perfil</p>
          <h2>Datos generales</h2>
        </div>
        <p v-if="accountState.error" class="error">{{ accountState.error }}</p>
        <p v-if="accountState.success" class="success">
          {{ accountState.success }}
        </p>
        <label>Nombre<input v-model="account.name" required /></label>
        <label
          >Email<input v-model="account.email" type="email" required
        /></label>
        <label
          >Nombre de la cartera<input v-model="account.portfolio_name" required
        /></label>
        <div class="field-row">
          <label
            >Moneda<select v-model="account.currency">
              <option>EUR</option>
              <option>USD</option>
              <option>GBP</option>
            </select></label
          ><label
            >País<input v-model="account.country_code" maxlength="2" required
          /></label>
        </div>
        <footer>
          <button class="button primary" :disabled="accountState.saving">
            {{ accountState.saving ? "Guardando…" : "Guardar cambios" }}
          </button>
        </footer>
      </form>
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
          >Contraseña actual<input
            v-model="password.current_password"
            type="password"
            required
            autocomplete="current-password"
        /></label>
        <label
          >Nueva contraseña<input
            v-model="password.password"
            type="password"
            required
            minlength="8"
            autocomplete="new-password"
        /></label>
        <label
          >Confirmar contraseña<input
            v-model="password.password_confirmation"
            type="password"
            required
            autocomplete="new-password"
        /></label>
        <small>Utiliza al menos 8 caracteres, con letras y números.</small>
        <footer>
          <button class="button primary" :disabled="passwordState.saving">
            {{
              passwordState.saving ? "Actualizando…" : "Actualizar contraseña"
            }}
          </button>
        </footer>
      </form>
      <section class="settings-card">
        <div>
          <p class="eyebrow">Plan y seguridad</p>
          <h2>Estado de la cuenta</h2>
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
              :style="{ width: `${Math.min(100, usage.storage.percentage)}%` }"
            ></i>
          </div>
          <RouterLink class="button secondary" to="/plans"
            >Ver planes y límites</RouterLink
          >
        </div>
        <template v-if="!session.user?.email_verified_at"
          ><p class="muted">Tu correo todavía no está verificado.</p>
          <button
            class="button secondary"
            type="button"
            @click="resendVerification"
          >
            Reenviar verificación
          </button>
          <p v-if="verificationState" class="success">
            {{ verificationState }}
          </p></template
        >
        <p v-else class="success">Correo verificado.</p>
      </section>
      <form class="settings-card danger-card" @submit.prevent="deleteAccount">
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
          >Contraseña actual<input
            v-model="deletion.current_password"
            type="password"
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
    </div>
  </main>
</template>
