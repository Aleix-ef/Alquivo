<script setup>
import {
  Send,
  Paperclip,
  X,
  ArrowLeft,
  MessageCircle,
  RefreshCw,
} from "@lucide/vue";
import { supportStatuses } from "../supportChat.js";
import { useSupportChat } from "../composables/useSupportChat.js";
const props = defineProps({
  mode: { type: String, default: "account" },
  active: { type: Boolean, default: true },
});
const emit = defineEmits(["busy-change"]);
const {
  id,
  team,
  emailLink,
  product,
  conversations,
  selected,
  messages,
  loading,
  busy,
  error,
  notice,
  connected,
  canManage,
  hasOlder,
  page,
  lastPage,
  filter,
  transcript,
  composer,
  form,
  draft,
  date,
  filesSelected,
  changedDraft,
  selectConversation,
  newConversation,
  refresh,
  submit,
  closeConversation,
  olderMessages,
  download,
  changePage,
} = useSupportChat(props, emit);
</script>

<template>
  <section
    class="support-chat"
    :class="{ 'support-chat-team': team }"
    aria-label="Conversaciones de soporte"
  >
    <div class="support-chat-toolbar">
      <p>
        {{
          team ? "Bandeja del equipo" : "Te responderemos en este mismo chat"
        }}
      </p>
      <button
        class="button secondary"
        type="button"
        :disabled="busy || loading"
        @click="refresh"
      >
        <RefreshCw :size="16" /> Actualizar
      </button>
    </div>
    <p v-if="!team" class="support-chat-note">
      No es atención inmediata. Tu consulta queda guardada para que el equipo
      pueda responderte.
    </p>
    <p v-if="mode === 'public'" class="support-chat-note">
      Sin iniciar sesión, el chat solo está disponible mientras siga activa la
      sesión de este navegador.
      <RouterLink to="/login">Entra en tu cuenta</RouterLink> para conservar tus
      nuevas conversaciones entre dispositivos.
    </p>
    <RouterLink
      v-if="canManage && !team"
      to="/support/inbox"
      class="button secondary"
      >Abrir bandeja del equipo</RouterLink
    >
    <p v-if="loading && !selected" role="status">Cargando conversaciones…</p>
    <p v-if="!connected && !loading" class="support-chat-note">
      No se está actualizando el chat. Conservamos tu borrador mientras
      mantengas esta ventana abierta.
    </p>
    <div v-if="!selected" class="support-chat-list">
      <label v-if="team" :for="`${id}-filter`"
        >Mostrar<select :id="`${id}-filter`" v-model="filter">
          <option value="">Todas las consultas</option>
          <option
            v-for="(label, status) in supportStatuses"
            :key="status"
            :value="status"
          >
            {{ label }}
          </option>
        </select></label
      >
      <button
        v-for="conversation in conversations"
        :key="conversation.id"
        type="button"
        class="support-chat-thread"
        @click="selectConversation(conversation)"
      >
        <span
          ><strong>{{ conversation.subject }}</strong
          ><small v-if="team"
            >{{ conversation.name }} ·
            {{
              conversation.source === "visitor" ? "Visitante" : "Cuenta"
            }}</small
          ><small>{{ date(conversation.updated_at) }}</small></span
        >
        <span
          ><span v-if="conversation.unread" class="support-unread"
            >Sin leer</span
          ><small>{{ supportStatuses[conversation.status] }}</small></span
        >
      </button>
      <p v-if="!conversations.length && !loading">
        {{
          team
            ? "No hay consultas en esta vista."
            : "Todavía no tienes conversaciones. Puedes escribirnos abajo."
        }}
      </p>
      <div v-if="lastPage > 1" class="support-chat-toolbar">
        <button
          class="button secondary"
          :disabled="page <= 1 || loading"
          @click="changePage(page - 1)"
        >
          Anterior</button
        ><span>Página {{ page }} de {{ lastPage }}</span
        ><button
          class="button secondary"
          :disabled="page >= lastPage || loading"
          @click="changePage(page + 1)"
        >
          Siguiente
        </button>
      </div>
    </div>
    <template v-else>
      <header class="support-chat-heading">
        <button
          class="button secondary"
          :disabled="busy"
          @click="newConversation"
        >
          <ArrowLeft :size="16" /> Conversaciones
        </button>
        <h3>{{ selected.subject }}</h3>
        <p>
          {{ supportStatuses[selected.status] }} · Referencia
          {{ selected.id.slice(0, 8) }}
        </p>
        <p v-if="team">
          {{ selected.name }} · {{ selected.email || "Sin correo indicado"
          }}<br />{{
            selected.source === "visitor"
              ? "Visitante: identidad y correo no verificados. No compartir datos de ninguna cuenta."
              : "Consulta vinculada a una cuenta. No uses el correo indicado como prueba de identidad."
          }}
        </p>
        <button
          v-if="selected.status !== 'closed'"
          class="button secondary"
          :disabled="busy"
          @click="closeConversation"
        >
          Marcar como resuelta
        </button>
      </header>
      <div
        ref="transcript"
        class="support-chat-transcript"
        role="log"
        aria-label="Historial de mensajes"
        aria-live="polite"
        aria-relevant="additions"
      >
        <button
          v-if="hasOlder"
          class="button secondary"
          :disabled="loading"
          @click="olderMessages"
        >
          Ver mensajes anteriores
        </button>
        <p v-if="loading && !messages.length" role="status">
          Cargando mensajes…
        </p>
        <article
          v-for="message in messages"
          :key="message.id"
          class="support-chat-message"
          :class="{
            mine: message.author_role === (team ? 'support' : 'customer'),
          }"
        >
          <strong>{{
            message.author_role === "support"
              ? "Equipo Alquivo"
              : team
                ? selected.name
                : "Tú"
          }}</strong>
          <p>{{ message.body }}</p>
          <ul v-if="message.attachments.length">
            <li v-for="file in message.attachments" :key="file.id">
              <button
                type="button"
                class="support-chat-download"
                @click="download(file)"
              >
                <Paperclip :size="16" /> {{ file.filename }} ·
                {{ Math.ceil(file.size / 1024) }} KB
              </button>
            </li>
          </ul>
          <time :datetime="message.created_at">{{
            date(message.created_at)
          }}</time>
        </article>
      </div>
      <p v-if="selected.status === 'closed'" class="support-chat-note">
        Si escribes de nuevo, la consulta se reabrirá.
      </p>
    </template>
    <form
      v-if="!team || selected"
      class="support-chat-compose support-form"
      @submit.prevent="submit"
    >
      <fieldset :disabled="busy || loading">
        <template v-if="!selected">
          <h3><MessageCircle :size="20" /> Nueva consulta</h3>
          <template v-if="mode === 'public'">
            <label :for="`${id}-name`"
              >Nombre<input
                :id="`${id}-name`"
                v-model="form.name"
                autocomplete="name"
                required
                maxlength="100"
            /></label>
            <label :for="`${id}-email`"
              >Correo de contacto (opcional)<input
                :id="`${id}-email`"
                v-model="form.email"
                autocomplete="email"
                type="email"
                maxlength="254"
            /></label>
          </template>
          <label :for="`${id}-subject`"
            >Asunto<input
              :id="`${id}-subject`"
              v-model="form.subject"
              required
              minlength="3"
              maxlength="150"
              placeholder="¿En qué necesitas ayuda?"
          /></label>
        </template>
        <label :for="`${id}-message`"
          >{{ selected ? "Tu respuesta" : "Mensaje"
          }}<textarea
            :id="`${id}-message`"
            ref="composer"
            v-model="draft.message"
            rows="4"
            required
            maxlength="5000"
            placeholder="Cuéntanos qué necesitas…"
            @input="changedDraft"
          ></textarea>
        </label>
        <div class="support-honeypot" aria-hidden="true" inert>
          <label
            >Sitio web<input
              v-model="form.company_website"
              tabindex="-1"
              autocomplete="off"
          /></label>
        </div>
        <label :for="`${id}-attachments`"
          >Adjuntar una captura o documento<input
            :id="`${id}-attachments`"
            type="file"
            multiple
            accept=".jpg,.jpeg,.png,.webp,.pdf"
            @change="filesSelected"
        /></label>
        <p class="support-chat-note">
          Hasta 3 archivos de 2 MB por mensaje. JPG, PNG, WebP o PDF. No envíes
          contraseñas ni documentos de identidad; oculta datos de terceros.
        </p>
        <ul v-if="draft.files.length" class="support-chat-files">
          <li v-for="(file, index) in draft.files" :key="index">
            {{ file.name
            }}<button
              type="button"
              class="button secondary"
              :aria-label="`Quitar ${file.name}`"
              @click="
                draft.files.splice(index, 1);
                changedDraft();
              "
            >
              <X :size="16" />
            </button>
          </li>
        </ul>
        <label v-if="!selected" class="support-consent"
          ><input
            v-model="form.privacy_acknowledged"
            type="checkbox"
            required
          /><span
            >He leído la
            <RouterLink to="/privacy" target="_blank" rel="noopener"
              >política de privacidad</RouterLink
            >. La conversación y sus adjuntos se guardarán para atender mi
            consulta.</span
          ></label
        >
        <button class="button primary support-submit" type="submit">
          <Send :size="18" /> {{ busy ? "Guardando…" : "Enviar mensaje" }}
        </button>
      </fieldset>
    </form>
    <p v-if="notice" role="status" class="support-chat-note">{{ notice }}</p>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <p v-if="emailLink" class="support-chat-note">
      Si no puedes utilizar el chat, también puedes escribir a
      <a :href="emailLink">{{ product.features.support_email }}</a
      >.
    </p>
  </section>
</template>
