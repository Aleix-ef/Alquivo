<script setup>
import { computed, reactive, ref, useId } from "vue";
import { Paperclip, Send, X, CircleCheck } from "@lucide/vue";
import api, { csrf } from "../api";
import { useSession } from "../session";
import { useProduct } from "../stores/product";
import { validateSupportFiles } from "../supportFiles";
import PrivacyNotice from "./PrivacyNotice.vue";

const props = defineProps({ public: Boolean });
const emit = defineEmits(["busy-change"]);
const session = useSession(),
  product = useProduct(),
  id = useId();
const form = reactive({
  name: session.user?.name || "",
  email: session.user?.email || "",
  subject: "",
  message: "",
  company_website: "",
  privacy_acknowledged: false,
});
const files = ref([]),
  busy = ref(false),
  error = ref(""),
  errors = ref({}),
  success = ref(null);
const fileInput = ref(null);
const supportEmail = computed(() => product.features.support_email);
const mailLink = computed(() =>
  supportEmail.value
    ? `mailto:${encodeURIComponent(supportEmail.value)}`
    : null,
);
const attachmentErrors = computed(() =>
  Object.entries(errors.value)
    .filter(([key]) => key.startsWith("attachments"))
    .flatMap(([, messages]) => messages),
);
function selectFiles(event) {
  const next = [...files.value, ...Array.from(event.target.files || [])];
  error.value = validateSupportFiles(next);
  if (!error.value) files.value = next;
  event.target.value = "";
}
async function submit() {
  if (busy.value) return;
  error.value = validateSupportFiles(files.value);
  errors.value = {};
  if (error.value) return;
  busy.value = true;
  emit("busy-change", true);
  const payload = new FormData();
  for (const [key, value] of Object.entries(form))
    payload.append(
      key,
      typeof value === "boolean" ? (value ? "1" : "0") : value,
    );
  files.value.forEach((file) => payload.append("attachments[]", file));
  try {
    await csrf();
    const { data } = await api.post(
      props.public ? "/public/support" : "/support",
      payload,
      { timeout: 60000 },
    );
    success.value = {
      ...data,
      email: props.public ? form.email : session.user?.email,
    };
    form.subject = "";
    form.message = "";
    form.privacy_acknowledged = false;
    files.value = [];
  } catch (e) {
    errors.value = e.response?.data?.errors || {};
    error.value =
      e.response?.status === 429
        ? "Has enviado varias consultas. Espera unos minutos antes de volver a intentarlo."
        : e.response?.status === 413
          ? "Los adjuntos superan el tamaño que admite el servidor."
          : e.response?.data?.message ||
            "No hemos podido confirmar el envío. El texto sigue aquí; comprueba tu conexión antes de reintentarlo.";
  } finally {
    busy.value = false;
    emit("busy-change", false);
  }
}
</script>
<template>
  <section class="support-form-wrap">
    <div v-if="success" class="support-form-success" role="status">
      <CircleCheck :size="30" aria-hidden="true" />
      <h3>Consulta enviada</h3>
      <p>
        Te responderemos en {{ success.email }}. Gracias por ayudarnos a mejorar
        Alquivo.
      </p>
      <p class="support-reference">Referencia: {{ success.reference }}</p>
      <button class="button secondary" type="button" @click="success = null">
        Escribir otra consulta
      </button>
    </div>
    <form v-else class="support-form" @submit.prevent="submit">
      <fieldset :disabled="busy">
        <div class="support-form-row">
          <label :for="`${id}-name`"
            >Nombre
            <input
              :id="`${id}-name`"
              v-model="form.name"
              name="name"
              autocomplete="name"
              required
              maxlength="100"
              :aria-invalid="!!errors.name"
              :aria-describedby="errors.name ? `${id}-name-error` : undefined"
            />
            <span
              v-if="errors.name"
              :id="`${id}-name-error`"
              class="support-field-error"
              >{{ errors.name[0] }}</span
            >
          </label>
          <label :for="`${id}-email`"
            >Correo de respuesta
            <input
              :id="`${id}-email`"
              v-model="form.email"
              type="email"
              name="email"
              autocomplete="email"
              required
              maxlength="254"
              :readonly="!props.public"
              :aria-invalid="!!errors.email"
              :aria-describedby="errors.email ? `${id}-email-error` : undefined"
            />
            <span
              v-if="errors.email"
              :id="`${id}-email-error`"
              class="support-field-error"
              >{{ errors.email[0] }}</span
            >
          </label>
        </div>
        <label :for="`${id}-subject`"
          >Asunto
          <input
            :id="`${id}-subject`"
            v-model="form.subject"
            required
            minlength="3"
            maxlength="150"
            placeholder="Por ejemplo: no puedo subir una factura"
            :aria-invalid="!!errors.subject"
          />
          <span v-if="errors.subject" class="support-field-error">{{
            errors.subject[0]
          }}</span>
        </label>
        <label :for="`${id}-message`"
          >Mensaje
          <textarea
            :id="`${id}-message`"
            v-model="form.message"
            rows="5"
            required
            minlength="10"
            maxlength="5000"
            placeholder="Cuéntanos qué necesitas. Si algo falla, explica qué estabas haciendo y qué mensaje aparece."
            :aria-invalid="!!errors.message"
          ></textarea>
          <span v-if="errors.message" class="support-field-error">{{
            errors.message[0]
          }}</span>
        </label>
        <div class="support-honeypot" aria-hidden="true" inert>
          <label
            >Sitio web<input
              v-model="form.company_website"
              name="company_website"
              tabindex="-1"
              autocomplete="off"
          /></label>
        </div>
        <div class="support-attachments">
          <label :for="`${id}-files`"
            ><Paperclip :size="17" aria-hidden="true" /> Adjuntos
            (opcional)</label
          >
          <input
            :id="`${id}-files`"
            ref="fileInput"
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.pdf"
            multiple
            :aria-describedby="`${id}-files-help`"
            @change="selectFiles"
          />
          <p :id="`${id}-files-help`">
            Hasta 3 archivos de 2 MB cada uno. JPG, PNG, WebP o PDF.
          </p>
          <ul v-if="files.length">
            <li v-for="(file, index) in files" :key="index">
              <span>{{ file.name }}</span
              ><button
                type="button"
                class="support-remove-file"
                :aria-label="`Quitar ${file.name}`"
                @click="files.splice(index, 1)"
              >
                <X :size="18" />
              </button>
            </li>
          </ul>
          <p
            v-for="message in attachmentErrors"
            :key="message"
            class="support-field-error"
          >
            {{ message }}
          </p>
        </div>
        <p class="support-privacy-note">
          No adjuntes contraseñas, datos bancarios ni documentos de identidad.
          Oculta los datos personales de terceros en tus capturas.
        </p>
        <PrivacyNotice context="support" />
        <label class="support-consent"
          ><input
            v-model="form.privacy_acknowledged"
            type="checkbox"
            required
          /><span
            >He leído la
            <RouterLink to="/privacy" target="_blank" rel="noopener"
              >política de privacidad</RouterLink
            >. Mis datos y adjuntos se usarán para atender esta consulta.</span
          ></label
        >
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <button
          class="button primary support-submit"
          type="submit"
          :disabled="busy"
        >
          <Send :size="18" aria-hidden="true" />{{
            busy ? "Enviando consulta…" : "Enviar a soporte"
          }}
        </button>
        <p v-if="busy" role="status">
          Estamos enviando tu consulta. Mantén esta ventana abierta.
        </p>
      </fieldset>
    </form>
    <p v-if="mailLink" class="support-email-alternative">
      También puedes escribir a <a :href="mailLink">{{ supportEmail }}</a
      >.
    </p>
  </section>
</template>
