import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch,
  useId,
} from "vue";
import api, { csrf } from "../api";
import { useSession } from "../session";
import { useProduct } from "../stores/product";
import { validateSupportFiles } from "../supportFiles";
import { mergeSupportMessages, supportEndpoint } from "../supportChat.js";

export function useSupportChat(props, emit) {
  const session = useSession();
  const product = useProduct();
  const emailLink = computed(() =>
    product.features.support_email
      ? `mailto:${encodeURIComponent(product.features.support_email)}`
      : null,
  );
  const id = useId();
  const base = computed(() => supportEndpoint(props.mode));
  const team = computed(() => props.mode === "team");
  const conversations = ref([]),
    selected = ref(null),
    messages = ref([]);
  const loading = ref(false),
    busy = ref(false),
    error = ref(""),
    notice = ref(""),
    connected = ref(false);
  const canManage = ref(false),
    mailNotificationsAvailable = ref(null),
    hasOlder = ref(false),
    page = ref(1),
    lastPage = ref(1),
    filter = ref("open");
  const transcript = ref(null),
    composer = ref(null);
  const newId = ref(crypto.randomUUID());
  const form = reactive({
    name: session.user?.name || "",
    email: session.user?.email || "",
    subject: "",
    privacy_acknowledged: false,
    company_website: "",
  });
  const blankDraft = () => ({
    message: "",
    files: [],
    client_id: crypto.randomUUID(),
  });
  const drafts = reactive({ new: blankDraft() });
  const draft = computed(() => drafts[selected.value?.id || "new"]);
  let generation = 0,
    timer,
    polling = false,
    mounted = false;
  const controllers = new Set();
  async function get(url, options = {}) {
    const controller = new AbortController();
    controllers.add(controller);
    try {
      return await api.get(url, {
        ...options,
        signal: controller.signal,
        timeout: 15000,
      });
    } finally {
      controllers.delete(controller);
    }
  }
  function messageFor(exception) {
    if (exception.response?.status === 429)
      return "Has enviado varias peticiones. Espera un momento antes de volver a intentarlo.";
    if (exception.response?.status === 419)
      return "Tu sesión ha caducado. Recarga la página. Conserva el texto antes de hacerlo.";
    if (exception.response?.status === 404)
      return "El ticket ya no está disponible. Si escribías como visitante, puede haber caducado la sesión de este navegador.";
    if (exception.response?.status === 403 && team.value)
      return "Esta bandeja requiere una cuenta autorizada, correo verificado y doble factor activado.";
    const validation = Object.values(
      exception.response?.data?.errors || {},
    ).flat();
    return (
      validation[0] ||
      (exception.response?.status < 500 && exception.response?.data?.message) ||
      "No hemos podido confirmar la operación. El texto sigue aquí. Comprueba tu conexión antes de reintentarlo."
    );
  }
  function date(value) {
    return new Intl.DateTimeFormat("es-ES", {
      dateStyle: "short",
      timeStyle: "short",
    }).format(new Date(value));
  }
  function changedDraft() {
    draft.value.client_id = crypto.randomUUID();
  }
  function filesSelected(event) {
    const files = [
      ...draft.value.files,
      ...Array.from(event.target.files || []),
    ];
    const problem = validateSupportFiles(files);
    error.value = problem;
    if (!problem) {
      draft.value.files = files;
      changedDraft();
    }
    event.target.value = "";
  }
  async function loadList() {
    const version = generation;
    const { data } = await get(base.value, {
      params: {
        page: page.value,
        ...(filter.value ? { status: filter.value } : {}),
      },
    });
    if (!mounted || version !== generation || !props.active) return;
    conversations.value = data.conversations;
    lastPage.value = data.last_page;
    canManage.value = data.can_manage;
    if (team.value)
      mailNotificationsAvailable.value = data.email_notifications_available;
    connected.value = true;
  }
  async function loadMessages({ initial = false, older = false } = {}) {
    if (!selected.value) return;
    const conversationId = selected.value.id,
      version = generation;
    const nearBottom =
      !transcript.value ||
      transcript.value.scrollHeight -
        transcript.value.scrollTop -
        transcript.value.clientHeight <
        90;
    const params = older
      ? { before: messages.value[0]?.id }
      : initial
        ? {}
        : { after: messages.value.at(-1)?.id || 0 };
    const { data } = await get(`${base.value}/${conversationId}`, { params });
    if (
      !mounted ||
      !props.active ||
      version !== generation ||
      selected.value?.id !== conversationId
    )
      return;
    const oldHeight = transcript.value?.scrollHeight || 0;
    messages.value = initial
      ? data.messages
      : mergeSupportMessages(messages.value, data.messages);
    selected.value = data.conversation;
    if (initial || older) hasOlder.value = data.has_older;
    await nextTick();
    if (transcript.value) {
      if (older)
        transcript.value.scrollTop += transcript.value.scrollHeight - oldHeight;
      else if (initial || nearBottom)
        transcript.value.scrollTop = transcript.value.scrollHeight;
    }
    const latest = messages.value.at(-1)?.id;
    if (
      latest &&
      !older &&
      (initial || nearBottom) &&
      !document.hidden &&
      props.active &&
      (initial || data.messages.length)
    ) {
      await api.post(
        `${base.value}/${conversationId}/read`,
        { message_id: latest },
        { timeout: 15000 },
      );
      const item = conversations.value.find(
        (item) => item.id === conversationId,
      );
      if (item) item.unread = false;
    }
  }
  async function selectConversation(conversation) {
    if (busy.value) return;
    generation++;
    selected.value = conversation;
    if (!drafts[conversation.id]) drafts[conversation.id] = blankDraft();
    messages.value = [];
    hasOlder.value = false;
    error.value = "";
    notice.value = "";
    loading.value = true;
    try {
      await loadMessages({ initial: true });
    } catch (exception) {
      error.value = messageFor(exception);
    } finally {
      loading.value = false;
    }
  }
  function newConversation() {
    if (busy.value) return;
    generation++;
    selected.value = null;
    messages.value = [];
    error.value = "";
    notice.value = "";
  }
  async function refresh() {
    if (
      !props.active ||
      document.hidden ||
      busy.value ||
      loading.value ||
      polling
    )
      return;
    polling = true;
    const recovering = !connected.value;
    try {
      await loadList();
      if (selected.value) await loadMessages();
      connected.value = true;
      if (recovering) error.value = "";
    } catch (exception) {
      if (exception.code !== "ERR_CANCELED") {
        connected.value = false;
        error.value = messageFor(exception);
      }
    } finally {
      polling = false;
    }
  }
  async function start() {
    if (!props.active || !mounted) return;
    loading.value = true;
    error.value = "";
    try {
      await csrf({ timeout: 15000 });
      await loadList();
      if (selected.value) await loadMessages({ initial: true });
    } catch (exception) {
      if (exception.code !== "ERR_CANCELED")
        error.value = messageFor(exception);
    } finally {
      loading.value = false;
    }
  }
  async function submit() {
    if (busy.value || loading.value || (team.value && !selected.value)) return;
    error.value = validateSupportFiles(draft.value.files);
    if (error.value || !draft.value.message.trim()) return;
    busy.value = true;
    emit("busy-change", true);
    const version = generation;
    const payload = new FormData();
    payload.append("message", draft.value.message);
    payload.append("client_id", draft.value.client_id);
    if (!selected.value) {
      payload.append("conversation_id", newId.value);
      for (const [key, value] of Object.entries(form))
        payload.append(
          key,
          typeof value === "boolean" ? (value ? "1" : "0") : value,
        );
    }
    draft.value.files.forEach((file) => payload.append("attachments[]", file));
    try {
      const { data } = await api.post(
        selected.value
          ? `${base.value}/${selected.value.id}/messages`
          : base.value,
        payload,
        { timeout: 60000 },
      );
      if (!mounted || version !== generation) return;
      if (!selected.value) {
        drafts.new = blankDraft();
        newId.value = crypto.randomUUID();
        form.subject = "";
        form.privacy_acknowledged = false;
      }
      drafts[data.id] = blankDraft();
      selected.value = data;
      notice.value = team.value
        ? "Respuesta guardada en el ticket."
        : "Mensaje guardado en el ticket. Te responderemos aquí.";
      // The send has succeeded. A refresh failure must not suggest resending it.
      try {
        await loadMessages();
        await loadList();
      } catch {
        error.value =
          "Tu mensaje está guardado, pero no hemos podido actualizar el historial. Pulsa Actualizar; no hace falta reenviarlo.";
      }
      await nextTick();
      if (transcript.value)
        transcript.value.scrollTop = transcript.value.scrollHeight;
      composer.value?.focus();
    } catch (exception) {
      error.value = messageFor(exception);
    } finally {
      busy.value = false;
      emit("busy-change", false);
    }
  }
  async function closeConversation() {
    if (busy.value || !selected.value) return;
    busy.value = true;
    emit("busy-change", true);
    try {
      const { data } = await api.post(
        `${base.value}/${selected.value.id}/close`,
      );
      selected.value = data;
      await loadList();
      notice.value =
        "Consulta marcada como resuelta. Puedes reabrirla escribiendo de nuevo.";
    } catch (exception) {
      error.value = messageFor(exception);
    } finally {
      busy.value = false;
      emit("busy-change", false);
    }
  }
  async function olderMessages() {
    if (loading.value) return;
    loading.value = true;
    try {
      await loadMessages({ older: true });
    } catch (exception) {
      error.value = messageFor(exception);
    } finally {
      loading.value = false;
    }
  }
  async function download(file) {
    try {
      const { data } = await get(
        `${base.value}/${selected.value.id}/attachments/${file.id}`,
        { responseType: "blob" },
      );
      const url = URL.createObjectURL(data),
        link = document.createElement("a");
      link.href = url;
      link.download = file.filename;
      link.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch {
      error.value =
        "No hemos podido descargar el adjunto. Comprueba tu sesión y vuelve a intentarlo.";
    }
  }
  async function changePage(value) {
    page.value = value;
    await refresh();
  }
  watch(filter, () => {
    page.value = 1;
    refresh();
  });
  watch(
    () => props.active,
    (active) => {
      if (active) start();
      else {
        generation++;
        for (const controller of controllers) controller.abort();
      }
    },
  );
  watch(
    () => props.mode,
    () => {
      generation++;
      selected.value = null;
      conversations.value = [];
      messages.value = [];
      canManage.value = false;
      mailNotificationsAvailable.value = null;
      filter.value = "open";
      for (const key of Object.keys(drafts)) delete drafts[key];
      drafts.new = blankDraft();
      form.name = session.user?.name || "";
      form.email = session.user?.email || "";
      form.subject = "";
      form.privacy_acknowledged = false;
      newId.value = crypto.randomUUID();
      start();
    },
  );
  onMounted(() => {
    mounted = true;
    start();
    timer = setInterval(refresh, 15000);
    document.addEventListener("visibilitychange", refresh);
  });
  onBeforeUnmount(() => {
    mounted = false;
    generation++;
    clearInterval(timer);
    for (const controller of controllers) controller.abort();
    document.removeEventListener("visibilitychange", refresh);
    emit("busy-change", false);
  });
  return {
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
    mailNotificationsAvailable,
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
  };
}
