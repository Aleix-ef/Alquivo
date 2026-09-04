<script setup>
import { ref, computed, onMounted } from "vue";
import { Building2, Plus } from "@lucide/vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api";
const route = useRoute(),
  router = useRouter(),
  items = ref([]),
  saving = ref(false),
  error = ref(""),
  show = computed(() => route.query.new === "1"),
  form = ref({
    name: "",
    type: "housing",
    address_line: "",
    city: "",
    purchase_price: null,
    current_value: null,
  }),
  money = (v) =>
    new Intl.NumberFormat("es-ES", {
      style: "currency",
      currency: "EUR",
      maximumFractionDigits: 0,
    }).format(v || 0);
async function load() {
  items.value = (await api.get("/properties")).data.data;
}
async function save() {
  saving.value = true;
  error.value = "";
  try {
    const { data } = await api.post("/properties", form.value);
    router.push("/properties/" + data.id);
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo guardar la propiedad.";
  } finally {
    saving.value = false;
  }
}
onMounted(load);
</script>
<template>
  <main class="page">
    <header class="heading">
      <div>
        <p class="eyebrow">Cartera</p>
        <h1>Propiedades</h1>
        <p>El mapa completo de tu patrimonio inmobiliario.</p>
      </div>
      <RouterLink class="button primary" to="?new=1"
        ><Plus :size="16" />Nueva propiedad</RouterLink
      >
    </header>
    <section v-if="items.length" class="cards">
      <RouterLink
        v-for="p in items"
        :key="p.id"
        :to="'/properties/' + p.id"
        class="card"
        ><div class="cover"><Building2 /></div>
        <section>
          <h2>{{ p.name }}</h2>
          <p>{{ p.address_line }} · {{ p.city }}</p>
          <strong>{{ money(p.current_value) }}</strong
          ><small>{{
            p.leases?.some((l) => l.status === "active")
              ? "Alquilada"
              : "Disponible"
          }}</small>
        </section></RouterLink
      >
    </section>
    <section v-else class="empty">
      <Building2 :size="35" />
      <h2>Añade tu primera propiedad</h2>
      <p>Solo necesitamos lo esencial. Podrás completar sus datos después.</p>
      <RouterLink class="button primary" to="?new=1"
        >Añadir propiedad</RouterLink
      >
    </section>
    <div
      v-if="show"
      class="drawer-bg"
      @click.self="router.replace('/properties')"
    >
      <form class="drawer" @submit.prevent="save">
        <p class="eyebrow">Nueva propiedad</p>
        <h2>Añade un activo</h2>
        <p v-if="error" class="error">{{ error }}</p>
        <label
          >Nombre<input
            v-model="form.name"
            required
            placeholder="Piso Gran Vía" /></label
        ><label
          >Tipo<select v-model="form.type">
            <option value="housing">Vivienda</option>
            <option value="commercial">Local</option>
            <option value="office">Oficina</option>
            <option value="garage">Garaje</option>
            <option value="land">Terreno</option>
            <option value="building">Edificio</option>
          </select></label
        ><label>Dirección<input v-model="form.address_line" required /></label
        ><label>Ciudad<input v-model="form.city" /></label
        ><label
          >Precio de compra<input
            v-model="form.purchase_price"
            type="number"
            min="0" /></label
        ><label
          >Valor actual<input
            v-model="form.current_value"
            type="number"
            min="0"
        /></label>
        <footer>
          <button class="button primary" :disabled="saving">
            {{ saving ? "Guardando…" : "Guardar y continuar" }}
          </button>
        </footer>
      </form>
    </div>
  </main>
</template>
