<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { Plus, Send, Trash2, X, Copy, Maximize2, Minimize2 } from "@lucide/vue";
import { useRoute } from "vue-router";
import api from "../api";
import AssistantMascot from "./AssistantMascot.vue";
import AssistantActionPreview from "./AssistantActionPreview.vue";
import { contentParts, replyPose, safeSources } from "../assistantPresentation";
import {
  assistantRequest,
  actionProposalReferences,
  mergeAssistantMessages,
} from "../assistantActions";
import { assistantContext } from "../assistantHelp";
import HelpGuides from "./HelpGuides.vue";
import { useDialog } from "../composables/useDialog";

const open = ref(false);
const emit = defineEmits(["open-change"]);
const route = useRoute();
const tab = ref("chat");
const expanded = ref(false);
const copiedId = ref(null);
const copyError = ref("");
const context = computed(() => assistantContext(route.path));
const suggestions = computed(() => context.value.suggestions);
const quotaExhausted = computed(() => usage.value && usage.value.remaining < 1);
const resetDate = computed(() =>
  usage.value?.resets_at
    ? new Intl.DateTimeFormat("es-ES", {
        day: "numeric",
        month: "long",
      }).format(new Date(usage.value.resets_at))
    : "el próximo mes",
);
watch(open, (value) => emit("open-change", value));
const initialized = ref(false);
const loading = ref(false);
const sending = ref(false);
const available = ref(true);
const enabled = ref(false);
const capabilities = ref({
  chat: false,
  actions: false,
  documents: false,
  intelligence: false,
});
const pendingTurn = ref(null);
const recoverableTurn = computed(
  () =>
    pendingTurn.value &&
    ["processing", "unknown"].includes(pendingTurn.value.status),
);
const noticeVersion = ref("");
const retentionDays = ref(30);
const conversations = ref([]);
const conversationId = ref("");
const messages = ref([]);
const usage = ref(null);
const input = ref("");
const error = ref("");
const messageList = ref(null);
const panel = ref(null);
const celebrating = ref(false);
const historyFailed = ref(false);
const pendingDeletion = ref("");
const deletionError = ref("");
const deletionDialog = ref(null);
const deletionOpen = computed(() => !!pendingDeletion.value);
useDialog(open, panel, close);
useDialog(deletionOpen, deletionDialog, cancelDeletion);
function cancelDeletion() {
  if (loading.value) return;
  pendingDeletion.value = "";
  deletionError.value = "";
}
function requestDeletion(kind) {
  if (sending.value || loading.value) return;
  deletionError.value = "";
  pendingDeletion.value = kind;
}
async function confirmDeletion() {
  if (loading.value || sending.value) return;
  if (pendingDeletion.value === "all") await disableAssistant();
  else await removeConversation();
}
let celebrationTimer;
let generation = 0;
let mounted = true;
const requestControllers = new Set();
async function request(method, path, payload, timeout = 20000) {
  const controller = new AbortController();
  requestControllers.add(controller);
  try {
    return method === "get"
      ? await api.get(path, { signal: controller.signal, timeout })
      : await api.post(path, payload, { signal: controller.signal, timeout });
  } finally {
    requestControllers.delete(controller);
  }
}
onBeforeUnmount(() => {
  mounted = false;
  generation++;
  clearTimeout(celebrationTimer);
  for (const controller of requestControllers) controller.abort();
});
const mascotState = computed(() => {
  if (sending.value || loading.value) return "thinking";
  if (error.value || !available.value) return "uncertain";
  if (!messages.value.length) return "greeting";
  if (celebrating.value) return "answered";
  return replyPose(messages.value.at(-1));
});

function close() {
  if (deletionOpen.value) return;
  open.value = false;
}

async function copyReply(message) {
  copyError.value = "";
  try {
    await navigator.clipboard.writeText(message.content);
    copiedId.value = message.id;
  } catch {
    copyError.value =
      "No se pudo copiar. Puedes seleccionar el texto de la respuesta.";
  }
}
function onComposerKey(event) {
  if (event.key === "Enter" && !event.shiftKey && !event.isComposing) {
    event.preventDefault();
    send();
  }
}
function chooseSuggestion(suggestion) {
  input.value = suggestion;
  nextTick(() => panel.value?.querySelector("textarea")?.focus());
}

async function toggle() {
  if (open.value) return close();
  open.value = true;
  await nextTick();
  panel.value?.focus();
  if (!initialized.value && !loading.value) await initialize();
}
defineExpose({ toggle });

async function initialize() {
  const version = generation;
  loading.value = true;
  error.value = "";
  try {
    const { data } = await request("get", "/assistant/conversations");
    if (!mounted || version !== generation) return;
    available.value = data.available;
    enabled.value = data.enabled;
    capabilities.value = {
      chat: data.capabilities?.chat === true,
      actions: data.capabilities?.actions === true,
      documents: false,
      intelligence: false,
    };
    noticeVersion.value = data.notice_version;
    retentionDays.value = data.retention_days;
    usage.value = data.usage;
    conversations.value = data.conversations;
    initialized.value = true;
    if (conversations.value.length) {
      conversationId.value = String(conversations.value[0].id);
      await loadConversation();
    }
  } catch {
    if (!mounted) return;
    error.value = "No hemos podido abrir el asistente.";
  } finally {
    if (mounted) loading.value = false;
  }
}

async function loadConversation() {
  const version = ++generation;
  const selectedId = conversationId.value;
  pendingTurn.value = null;
  // A failed fetch must never display the previous chat under a new identifier.
  messages.value = [];
  historyFailed.value = false;
  error.value = "";
  celebrating.value = false;
  if (!conversationId.value) {
    return;
  }
  loading.value = true;
  error.value = "";
  try {
    const { data } = await request(
      "get",
      `/assistant/conversations/${selectedId}`,
    );
    if (
      !mounted ||
      version !== generation ||
      selectedId !== conversationId.value
    )
      return;
    messages.value = data.messages;
    await scrollToBottom();
  } catch {
    if (!mounted || version !== generation) return;
    historyFailed.value = true;
    error.value = "No hemos podido recuperar esta conversación.";
  } finally {
    if (mounted && version === generation) loading.value = false;
  }
}

function newConversation() {
  if (sending.value || loading.value) return;
  generation++;
  pendingTurn.value = null;
  conversationId.value = "";
  historyFailed.value = false;
  messages.value = [];
  input.value = "";
  error.value = "";
}

async function removeConversation() {
  if (!conversationId.value || sending.value || loading.value) return;
  loading.value = true;
  try {
    await api.delete(`/assistant/conversations/${conversationId.value}`);
    if (!mounted) return;
    generation++;
    pendingTurn.value = null;
    conversations.value = conversations.value.filter(
      (conversation) => String(conversation.id) !== conversationId.value,
    );
    conversationId.value = "";
    messages.value = [];
    historyFailed.value = false;
    pendingDeletion.value = "";
    error.value = "";
  } catch {
    deletionError.value =
      "No hemos podido eliminar la conversación. Inténtalo de nuevo.";
  } finally {
    loading.value = false;
  }
}

async function enableAssistant() {
  loading.value = true;
  error.value = "";
  try {
    await api.post("/assistant/activation", {
      accepted: true,
      notice_version: noticeVersion.value,
    });
    if (!mounted) return;
    enabled.value = true;
    await initialize();
  } catch {
    error.value = "No hemos podido activar el asistente.";
  } finally {
    loading.value = false;
  }
}

async function disableAssistant() {
  if (sending.value || loading.value) return;
  loading.value = true;
  try {
    await api.delete("/assistant/activation");
    if (!mounted) return;
    generation++;
    pendingTurn.value = null;
    enabled.value = false;
    conversations.value = [];
    conversationId.value = "";
    messages.value = [];
    input.value = "";
    historyFailed.value = false;
    pendingDeletion.value = "";
    error.value = "";
  } catch {
    deletionError.value =
      "No hemos podido desactivar el asistente. Inténtalo de nuevo.";
  } finally {
    loading.value = false;
  }
}

function acceptResponse(data, turn) {
  if (data.run?.id) turn.runId = data.run.id;
  turn.status =
    data.run?.status || (data.assistant_message ? "completed" : "processing");
  if (data.usage) usage.value = data.usage;
  if (data.user_message || data.assistant_message) {
    messages.value = mergeAssistantMessages(
      messages.value.filter(
        (message) => message.id !== `temporary-${turn.clientRequestId}`,
      ),
      [data.user_message, data.assistant_message],
    );
  }
  if (data.assistant_message) {
    pendingTurn.value = null;
    if (data.assistant_message.metadata?.kind === "answer") {
      clearTimeout(celebrationTimer);
      celebrating.value = true;
      celebrationTimer = setTimeout(() => {
        celebrating.value = false;
      }, 2400);
    }
  }
  if (data.conversation) {
    const index = conversations.value.findIndex(
      (conversation) => String(conversation.id) === turn.conversationId,
    );
    if (index >= 0) conversations.value[index] = data.conversation;
  }
}

function receiveFailure(exception, turn) {
  const data = exception.response?.data || {};
  acceptResponse(data, turn);
  const terminal =
    data.run?.status === "failed" ||
    (exception.response &&
      exception.response.status < 500 &&
      exception.response.status !== 409);
  if (terminal) {
    pendingTurn.value = null;
    input.value = turn.message;
    messages.value = messages.value.filter(
      (message) => message.id !== `temporary-${turn.clientRequestId}`,
    );
  } else {
    // Retain the original text, context and key until the server confirms its outcome.
    turn.status = data.run?.status === "processing" ? "processing" : "unknown";
    pendingTurn.value = turn;
  }
  error.value =
    data.errors?.message?.[0] ||
    (exception.response?.status < 500 && data.message) ||
    (terminal
      ? "No he podido responder. Puedes enviar otra consulta; los intentos iniciados también cuentan."
      : "No sabemos si la respuesta terminó. Comprueba el estado para recuperar la misma consulta sin duplicarla.");
}

async function send(suggestion, resuming = false) {
  const content = (suggestion || input.value).trim();
  if (
    !content ||
    sending.value ||
    loading.value ||
    historyFailed.value ||
    !available.value ||
    !enabled.value
  )
    return;
  if (!resuming && recoverableTurn.value) {
    error.value = "Comprueba primero el estado de la consulta anterior.";
    return;
  }
  if (!resuming && usage.value && usage.value.remaining < 1) {
    error.value = "Has alcanzado el límite mensual del asistente.";
    return;
  }

  const version = generation;
  pendingTurn.value =
    resuming && pendingTurn.value
      ? pendingTurn.value
      : assistantRequest(pendingTurn.value, {
          message: content,
          propertyId: context.value.propertyId ?? null,
          conversationId: conversationId.value,
        });
  const turn = pendingTurn.value;
  sending.value = true;
  error.value = "";
  input.value = "";
  const temporaryId = `temporary-${turn.clientRequestId}`;
  messages.value = mergeAssistantMessages(messages.value, [
    { id: temporaryId, role: "user", content },
  ]);
  await scrollToBottom();

  try {
    if (!conversationId.value) {
      const { data } = await request("post", "/assistant/conversations");
      if (!mounted || version !== generation) return;
      conversationId.value = String(data.id);
      turn.conversationId = conversationId.value;
      conversations.value.unshift(data);
    }
    const { data } = await request(
      "post",
      `/assistant/conversations/${turn.conversationId}/messages`,
      {
        message: turn.message,
        property_id: turn.propertyId,
        client_request_id: turn.clientRequestId,
      },
      65000,
    );
    if (
      !mounted ||
      version !== generation ||
      turn.conversationId !== conversationId.value
    )
      return;
    acceptResponse(data, turn);
  } catch (requestError) {
    if (!mounted || version !== generation) return;
    receiveFailure(requestError, turn);
  } finally {
    if (mounted && version === generation) {
      sending.value = false;
      await scrollToBottom();
    }
  }
}

async function recoverResponse() {
  const turn = pendingTurn.value;
  if (!turn || sending.value || loading.value) return;
  if (!turn.runId) return send(turn.message, true);
  const version = generation;
  sending.value = true;
  error.value = "";
  try {
    const { data } = await request("get", `/assistant/runs/${turn.runId}`);
    if (
      !mounted ||
      version !== generation ||
      turn.conversationId !== conversationId.value
    )
      return;
    acceptResponse(data, turn);
  } catch (exception) {
    if (!mounted || version !== generation) return;
    receiveFailure(exception, turn);
  } finally {
    if (mounted && version === generation) {
      sending.value = false;
      await scrollToBottom();
    }
  }
}

async function scrollToBottom() {
  await nextTick();
  if (messageList.value)
    messageList.value.scrollTop = messageList.value.scrollHeight;
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="assistant-backdrop" @click.self="close">
      <div
        class="assistant-widget"
        :class="{ 'is-open': open, 'is-expanded': expanded }"
      >
        <section
          v-if="open"
          :inert="deletionOpen"
          id="alquivo-assistant-panel"
          ref="panel"
          tabindex="-1"
          class="assistant-panel"
          role="dialog"
          aria-modal="true"
          aria-label="Pregunta a Alquivo"
        >
          <header class="assistant-header">
            <AssistantMascot
              :state="mascotState"
              class="assistant-header-mascot"
            />
            <div>
              <strong>Asistente IA de Alquivo</strong>
              <small>Respuestas sobre los datos de tu cartera</small>
            </div>
            <button
              class="assistant-icon-button assistant-expand"
              :aria-label="expanded ? 'Reducir panel' : 'Ampliar panel'"
              @click="expanded = !expanded"
            >
              <Minimize2 v-if="expanded" :size="18" /><Maximize2
                v-else
                :size="18"
              />
            </button>
            <button
              class="assistant-icon-button"
              title="Nueva conversación"
              aria-label="Nueva conversación"
              :disabled="sending || loading || !enabled"
              @click="newConversation"
            >
              <Plus :size="18" />
            </button>
            <button
              class="assistant-icon-button"
              aria-label="Cerrar asistente"
              @click="close"
            >
              <X :size="18" />
            </button>
          </header>

          <nav class="assistant-tabs" aria-label="Opciones del asistente">
            <button :aria-pressed="tab === 'chat'" @click="tab = 'chat'">
              Preguntar por mis datos
            </button>
            <button :aria-pressed="tab === 'help'" @click="tab = 'help'">
              Cómo usar Alquivo
            </button>
          </nav>
          <div v-if="tab === 'help'" class="assistant-help-scroll">
            <HelpGuides @navigate="close" /><RouterLink
              class="assistant-support-link"
              to="/support"
              @click="close"
              >Necesito ayuda de soporte →</RouterLink
            >
          </div>
          <div v-show="tab === 'chat'" class="assistant-chat">
            <p class="assistant-context">
              Consultando: {{ context.label }}
              <span
                >·
                {{
                  capabilities.actions
                    ? "Los cambios requieren tu confirmación"
                    : "Solo lectura"
                }}</span
              >
            </p>

            <div v-if="conversations.length" class="assistant-history">
              <select
                v-model="conversationId"
                aria-label="Conversación"
                :disabled="sending || loading"
                @change="loadConversation"
              >
                <option value="">Nueva conversación</option>
                <option
                  v-for="conversation in conversations"
                  :key="conversation.id"
                  :value="String(conversation.id)"
                >
                  {{ conversation.title || "Conversación nueva" }}
                </option>
              </select>
              <button
                v-if="conversationId"
                class="assistant-icon-button"
                title="Eliminar conversación"
                aria-label="Eliminar conversación"
                :disabled="sending || loading"
                @click="requestDeletion('conversation')"
              >
                <Trash2 :size="16" />
              </button>
            </div>

            <div
              ref="messageList"
              class="assistant-messages"
              aria-live="polite"
            >
              <div v-if="loading" class="assistant-loading">
                <AssistantMascot state="thinking" />
                <span>Preparando tu información…</span>
              </div>

              <div
                v-else-if="!initialized || historyFailed"
                class="assistant-empty"
              >
                <AssistantMascot
                  state="uncertain"
                  class="assistant-welcome-mascot"
                />
                <strong>No he podido cargar la conversación</strong>
                <p>
                  Puedes volver a intentarlo. Recuperar el historial no consume
                  consultas de IA.
                </p>
                <button
                  class="button secondary"
                  @click="initialized ? loadConversation() : initialize()"
                >
                  Volver a cargar
                </button>
              </div>

              <div v-else-if="!available" class="assistant-empty">
                <AssistantMascot
                  state="uncertain"
                  class="assistant-welcome-mascot"
                />
                <strong>El asistente está casi listo</strong>
                <p>
                  El asistente no está disponible en este momento. Puedes seguir
                  usando el resto de Alquivo.
                </p>
                <nav
                  class="assistant-suggestions"
                  aria-label="Consulta tus datos directamente"
                >
                  <RouterLink to="/finance" @click="open = false"
                    >Revisar ingresos y gastos</RouterLink
                  >
                  <RouterLink to="/leases" @click="open = false"
                    >Consultar mis contratos</RouterLink
                  >
                  <RouterLink to="/calendar" @click="open = false"
                    >Ver próximos vencimientos</RouterLink
                  >
                </nav>
              </div>

              <div v-else-if="!enabled" class="assistant-empty">
                <AssistantMascot
                  state="greeting"
                  class="assistant-consent-mascot"
                />
                <strong>Activa Alquivo AI</strong>
                <p class="assistant-consent-summary">
                  Para responder, enviaremos a OpenAI tus preguntas y los datos
                  necesarios de tu cartera. No se envía el contenido de tus
                  archivos.
                </p>
                <p class="assistant-consent-summary">
                  Tu historial se elimina a los {{ retentionDays }} días. Puedes
                  desactivar el asistente y borrarlo cuando quieras.
                </p>
                <details class="assistant-privacy-details">
                  <summary>Cómo se usan y protegen mis datos</summary>
                  <div>
                    <p>
                      Se comparte el historial reciente y la información de
                      inmuebles y finanzas necesaria para cada respuesta. Para
                      localizar contactos puede compartirse su nombre, pero no
                      su teléfono actual, correo, DNI, dirección completa ni
                      notas existentes.
                    </p>
                    <p>
                      Si están disponibles las acciones, podrás pedir borradores
                      de gastos y cobros, actualizar teléfonos y añadir notas a
                      inmuebles. Nada se guarda sin que revises y confirmes la
                      propuesta. Los teléfonos o notas nuevos que escribas en el
                      chat sí se enviarán a OpenAI; puedes usar los formularios
                      de Alquivo si prefieres no compartirlos.
                    </p>
                    <p>
                      Evita escribir DNI, datos bancarios, datos de salud u otra
                      información sensible. Los nombres y títulos de tus
                      inmuebles o incidencias pueden compartirse. OpenAI puede
                      conservar registros de seguridad hasta 30 días, salvo
                      excepciones legales.
                    </p>
                  </div>
                </details>
                <RouterLink to="/privacy" @click="close"
                  >Ver la política de privacidad completa</RouterLink
                >
                <button
                  class="button primary"
                  :disabled="loading"
                  @click="enableAssistant"
                >
                  Activar asistente
                </button>
              </div>

              <div v-else-if="!messages.length" class="assistant-empty">
                <AssistantMascot
                  state="greeting"
                  class="assistant-welcome-mascot"
                />
                <strong>¿Qué quieres entender?</strong>
                <p>
                  Puedo consultar tus inmuebles, finanzas, alquileres y próximos
                  asuntos. Solo respondo sobre Alquivo y tus datos; si falta
                  información, te lo diré.
                </p>
                <p v-if="capabilities.actions">
                  También puedo preparar gastos y cobros, cambiar un teléfono y
                  añadir una nota a un inmueble. Siempre podrás revisar y editar
                  cada propuesta antes de confirmarla.
                </p>
                <div class="assistant-suggestions">
                  <button
                    v-if="capabilities.actions"
                    :disabled="sending || quotaExhausted"
                    @click="
                      chooseSuggestion(
                        'Quiero registrar un gasto de fontanería',
                      )
                    "
                  >
                    Preparar un gasto
                  </button>
                  <template v-if="capabilities.actions">
                    <button
                      :disabled="sending || quotaExhausted"
                      @click="
                        chooseSuggestion(
                          'Quiero registrar un cobro de alquiler',
                        )
                      "
                    >
                      Registrar un cobro
                    </button>
                    <button
                      :disabled="sending || quotaExhausted"
                      @click="
                        chooseSuggestion(
                          'Quiero actualizar el teléfono de un contacto',
                        )
                      "
                    >
                      Cambiar un teléfono
                    </button>
                    <button
                      :disabled="sending || quotaExhausted"
                      @click="
                        chooseSuggestion('Quiero añadir una nota a un inmueble')
                      "
                    >
                      Añadir una nota
                    </button>
                  </template>
                  <button
                    v-for="suggestion in suggestions"
                    :key="suggestion"
                    :disabled="sending || quotaExhausted"
                    @click="chooseSuggestion(suggestion)"
                  >
                    {{ suggestion }}
                  </button>
                </div>
              </div>

              <template v-for="message in messages" :key="message.id">
                <article class="assistant-message" :class="message.role">
                  <AssistantMascot
                    v-if="message.role === 'assistant'"
                    :state="replyPose(message)"
                    :animated="false"
                    class="assistant-reply-mascot"
                  />
                  <div class="assistant-reply-body">
                    <p>
                      <template
                        v-for="(part, index) in contentParts(message.content)"
                        :key="index"
                      >
                        <RouterLink
                          v-if="part.to"
                          :to="part.to"
                          @click="close"
                          >{{ part.text }}</RouterLink
                        ><template v-else>{{ part.text }}</template>
                      </template>
                    </p>
                    <template v-if="message.role === 'assistant'">
                      <AssistantActionPreview
                        v-for="proposal in actionProposalReferences(
                          message.metadata,
                        )"
                        :key="proposal.id"
                        :proposal-id="proposal.id"
                        :enabled="capabilities.actions && enabled && available"
                        @navigate="close"
                      />
                      <nav
                        v-if="safeSources(message.metadata).length"
                        class="assistant-sources"
                        aria-label="Datos consultados para esta respuesta"
                      >
                        <span>Datos consultados</span>
                        <RouterLink
                          v-for="source in safeSources(message.metadata)"
                          :key="source.path"
                          :to="source.path"
                          @click="close"
                          >{{ source.label }} →</RouterLink
                        >
                      </nav>
                      <div class="assistant-reply-actions">
                        <button @click="copyReply(message)">
                          <Copy :size="14" />{{
                            copiedId === message.id
                              ? "Copiado"
                              : "Copiar respuesta"
                          }}</button
                        ><small v-if="message.created_at">{{
                          new Date(message.created_at).toLocaleString("es-ES", {
                            day: "2-digit",
                            month: "2-digit",
                            year: "numeric",
                            hour: "2-digit",
                            minute: "2-digit",
                          })
                        }}</small>
                      </div>
                      <RouterLink
                        v-if="message.metadata?.kind === 'support_required'"
                        to="/support"
                        class="assistant-support-link"
                        @click="close"
                        >Abrir ayuda y soporte →</RouterLink
                      >
                      <button
                        v-if="
                          ['insufficient_data', 'read_only'].includes(
                            message.metadata?.kind,
                          )
                        "
                        class="assistant-help-action"
                        @click="tab = 'help'"
                      >
                        Ver cómo completar o corregir mis datos
                      </button>
                    </template>
                  </div>
                </article>
              </template>
              <div v-if="sending" class="assistant-thinking">
                <AssistantMascot state="thinking" />
                <span>Consultando tus datos. Puede tardar unos segundos…</span>
              </div>
            </div>

            <p v-if="error" class="assistant-error" role="alert">{{ error }}</p>
            <p v-if="copyError" class="assistant-error" role="status">
              {{ copyError }}
            </p>
            <div
              v-if="recoverableTurn && !sending"
              class="assistant-run-recovery"
              role="status"
            >
              <p>
                {{
                  pendingTurn.status === "processing"
                    ? "Tu consulta sigue en proceso."
                    : "Hay una consulta pendiente de comprobar."
                }}
                Recuperarla no inicia otra consulta de IA.
              </p>
              <button
                class="button secondary"
                :disabled="loading"
                @click="recoverResponse"
              >
                Comprobar respuesta
              </button>
            </div>
            <p v-if="quotaExhausted" class="assistant-quota" role="status">
              Has agotado el uso de IA de este mes. Se renueva el
              {{ resetDate }}. Las guías de «Cómo usar Alquivo» siguen
              disponibles.
            </p>
            <footer class="assistant-composer">
              <textarea
                v-model="input"
                rows="2"
                maxlength="2000"
                :disabled="
                  sending ||
                  loading ||
                  historyFailed ||
                  !available ||
                  !enabled ||
                  recoverableTurn ||
                  quotaExhausted
                "
                placeholder="Pregunta sobre tu patrimonio…"
                aria-label="Mensaje para el asistente"
                @keydown="onComposerKey"
              ></textarea>
              <button
                aria-label="Enviar pregunta"
                :disabled="
                  !input.trim() ||
                  sending ||
                  loading ||
                  historyFailed ||
                  !available ||
                  !enabled ||
                  recoverableTurn ||
                  quotaExhausted
                "
                @click="send()"
              >
                <Send :size="17" />
              </button>
              <small v-if="usage"
                >Hasta {{ usage.remaining }} consultas más · Renovación:
                {{ resetDate }}. También se aplica un límite de uso para
                consultas extensas.</small
              >
              <small
                >Las consultas iniciadas cuentan aunque fallen. Recuperar la
                misma consulta no la duplica. Revisa los datos
                importantes.</small
              >
              <button
                v-if="enabled"
                type="button"
                class="assistant-disable"
                :disabled="sending || loading"
                @click="requestDeletion('all')"
              >
                Desactivar y borrar historial
              </button>
            </footer>
          </div>
        </section>
      </div>
    </div></Teleport
  >
  <Teleport to="body">
    <div
      v-if="deletionOpen"
      class="assistant-confirm-backdrop"
      @click.self="cancelDeletion"
    >
      <section
        ref="deletionDialog"
        class="assistant-confirm-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="assistant-delete-title"
        aria-describedby="assistant-delete-description"
        tabindex="-1"
      >
        <h2 id="assistant-delete-title">
          {{
            pendingDeletion === "all"
              ? "¿Desactivar el asistente?"
              : "¿Eliminar esta conversación?"
          }}
        </h2>
        <p id="assistant-delete-description">
          {{
            pendingDeletion === "all"
              ? "Se borrarán todas tus conversaciones. Podrás activar el asistente de nuevo, pero no recuperar el historial."
              : "Se borrarán los mensajes de esta conversación. Esta acción no se puede deshacer."
          }}
          Tus inmuebles y datos de gestión se conservarán.
        </p>
        <p v-if="deletionError" class="error" role="alert">
          {{ deletionError }}
        </p>
        <div class="assistant-confirm-actions">
          <button
            class="button secondary"
            :disabled="loading"
            @click="cancelDeletion"
          >
            Cancelar
          </button>
          <button
            class="button primary"
            :disabled="loading"
            @click="confirmDeletion"
          >
            {{ loading ? "Eliminando…" : "Confirmar eliminación" }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
