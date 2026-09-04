<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import {
  ArrowLeft,
  Camera,
  Home,
  KeyRound,
  WalletCards,
  Wrench,
  FileText,
  Pencil,
} from "@lucide/vue";
import { useRoute } from "vue-router";
import api from "../api";
const route = useRoute(),
  property = ref(null),
  cover = ref(null),
  uploading = ref(false),
  editing = ref(false),
  editForm = ref({}),
  money = (v) =>
    new Intl.NumberFormat("es-ES", {
      style: "currency",
      currency: "EUR",
      maximumFractionDigits: 0,
    }).format(v || 0),
  activeLease = computed(() =>
    property.value?.leases?.find((l) => l.status === "active"),
  ),
  income = computed(
    () =>
      property.value?.transactions
        ?.filter((t) => t.direction === "income" && t.status === "paid")
        .reduce((a, t) => a + Number(t.amount), 0) || 0,
  ),
  expenses = computed(
    () =>
      property.value?.transactions
        ?.filter((t) => t.direction === "expense" && t.status === "paid")
        .reduce((a, t) => a + Number(t.amount), 0) || 0,
  ),
  yieldRate = computed(() =>
    property.value?.current_value && activeLease.value
      ? (
          ((Number(activeLease.value.monthly_rent) * 12) /
            Number(property.value.current_value)) *
          100
        ).toFixed(2)
      : null,
  );
async function load() {
  property.value = (await api.get("/properties/" + route.params.id)).data;
  editForm.value = {
    name: property.value.name,
    address_line: property.value.address_line,
    city: property.value.city || "",
    current_value: property.value.current_value,
    outstanding_debt: property.value.outstanding_debt,
    area: property.value.area,
    valuation_date: new Date().toISOString().slice(0, 10),
  };
  if (property.value.photos?.length) {
    const r = await api.get("/property-photos/" + property.value.photos[0].id, {
      responseType: "blob",
    });
    cover.value = URL.createObjectURL(r.data);
  }
}
async function saveProperty() {
  await api.put(`/properties/${property.value.id}`, editForm.value);
  editing.value = false;
  await load();
}
async function upload(e) {
  if (!e.target.files[0]) return;
  uploading.value = true;
  const d = new FormData();
  d.append("photo", e.target.files[0]);
  await api.post(`/properties/${route.params.id}/photos`, d);
  if (cover.value) URL.revokeObjectURL(cover.value);
  await load();
  uploading.value = false;
}
onMounted(load);
onBeforeUnmount(() => cover.value && URL.revokeObjectURL(cover.value));
</script>
<template>
  <main v-if="property" class="page detail-page">
    <RouterLink class="back" to="/properties"
      ><ArrowLeft :size="16" />Propiedades</RouterLink
    >
    <section
      class="property-hero"
      :style="
        cover
          ? {
              backgroundImage: `linear-gradient(90deg,rgba(17,35,27,.8),rgba(17,35,27,.25)),url(${cover})`,
            }
          : {}
      "
    >
      <div>
        <span class="pill">{{ activeLease ? "Alquilada" : "Disponible" }}</span>
        <h1>{{ property.name }}</h1>
        <p>{{ property.address_line }} · {{ property.city }}</p>
      </div>
      <div class="hero-actions">
        <button
          class="button photo-button"
          type="button"
          @click="editing = true"
        >
          <Pencil :size="16" />Editar
        </button>
        <label class="button photo-button"
          ><Camera :size="16" />{{ uploading ? "Subiendo…" : "Añadir foto"
          }}<input type="file" accept="image/*" hidden @change="upload"
        /></label>
      </div>
    </section>
    <section class="asset-metrics">
      <article>
        <span>Valor actual</span
        ><strong>{{ money(property.current_value) }}</strong>
      </article>
      <article>
        <span>Renta mensual</span
        ><strong>{{
          activeLease ? money(activeLease.monthly_rent) : "—"
        }}</strong>
      </article>
      <article>
        <span>Rentabilidad bruta</span
        ><strong>{{ yieldRate ? yieldRate + "%" : "—" }}</strong>
      </article>
      <article>
        <span>Resultado registrado</span
        ><strong>{{ money(income - expenses) }}</strong>
      </article>
    </section>
    <section class="detail-grid">
      <article class="panel detail-panel">
        <header>
          <Home />
          <div>
            <p class="eyebrow">Activo</p>
            <h2>Información patrimonial</h2>
          </div>
        </header>
        <dl>
          <div>
            <dt>Precio de compra</dt>
            <dd>{{ money(property.purchase_price) }}</dd>
          </div>
          <div>
            <dt>Deuda pendiente</dt>
            <dd>{{ money(property.outstanding_debt) }}</dd>
          </div>
          <div>
            <dt>Superficie</dt>
            <dd>{{ property.area ? property.area + " m²" : "—" }}</dd>
          </div>
          <div>
            <dt>Tipo</dt>
            <dd>{{ property.type }}</dd>
          </div>
        </dl>
      </article>
      <article class="panel detail-panel">
        <header>
          <KeyRound />
          <div>
            <p class="eyebrow">Alquiler</p>
            <h2>{{ activeLease ? "Contrato activo" : "Sin arrendamiento" }}</h2>
          </div>
        </header>
        <template v-if="activeLease"
          ><strong>{{
            activeLease.participants?.map((p) => p.name).join(", ")
          }}</strong>
          <p>
            Desde {{ activeLease.start_date }}
            {{ activeLease.end_date ? "hasta " + activeLease.end_date : "" }}
          </p>
          <RouterLink to="/leases">Ver alquiler</RouterLink></template
        ><RouterLink v-else class="button secondary" to="/leases?new=1"
          >Crear alquiler</RouterLink
        >
      </article>
      <article class="panel detail-panel">
        <header>
          <WalletCards />
          <div>
            <p class="eyebrow">Finanzas</p>
            <h2>Actividad</h2>
          </div>
        </header>
        <dl>
          <div>
            <dt>Ingresos</dt>
            <dd>{{ money(income) }}</dd>
          </div>
          <div>
            <dt>Gastos</dt>
            <dd>{{ money(expenses) }}</dd>
          </div>
        </dl>
        <RouterLink to="/finance">Ver movimientos</RouterLink>
      </article>
      <article class="panel detail-panel">
        <header>
          <Wrench />
          <div>
            <p class="eyebrow">Atención</p>
            <h2>
              {{
                property.issues?.filter((i) => i.status !== "resolved")
                  .length || 0
              }}
              incidencias abiertas
            </h2>
          </div>
        </header>
        <RouterLink :to="'/issues?property=' + property.id"
          >Gestionar incidencias</RouterLink
        >
      </article>
      <article class="panel detail-panel">
        <header>
          <FileText />
          <div>
            <p class="eyebrow">Archivo</p>
            <h2>{{ property.documents?.length || 0 }} documentos</h2>
          </div>
        </header>
        <RouterLink :to="'/documents?property=' + property.id"
          >Ver documentos</RouterLink
        >
      </article>
    </section>
    <div v-if="editing" class="drawer-bg" @click.self="editing = false">
      <form class="drawer" @submit.prevent="saveProperty">
        <p class="eyebrow">Editar activo</p>
        <h2>Actualiza la propiedad</h2>
        <label>Nombre<input v-model="editForm.name" required /></label>
        <label
          >Dirección<input v-model="editForm.address_line" required
        /></label>
        <label>Ciudad<input v-model="editForm.city" /></label>
        <label
          >Valor actual<input
            v-model="editForm.current_value"
            type="number"
            min="0"
        /></label>
        <label
          >Fecha de valoración<input
            v-model="editForm.valuation_date"
            type="date"
        /></label>
        <label
          >Deuda pendiente<input
            v-model="editForm.outstanding_debt"
            type="number"
            min="0"
        /></label>
        <label
          >Superficie<input
            v-model="editForm.area"
            type="number"
            min="0"
            step="0.01"
        /></label>
        <footer><button class="button primary">Guardar cambios</button></footer>
      </form>
    </div>
  </main>
  <div v-else class="empty">Cargando propiedad…</div>
</template>
