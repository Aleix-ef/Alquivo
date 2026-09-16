import { defineStore } from "pinia";
import api from "../api";

let controller;
let sequence = 0;
export const useFiscality = defineStore("fiscality", {
  state: () => ({ data: null, loading: false, error: "", downloading: false }),
  actions: {
    async load(year = 2025) {
      controller?.abort();
      controller = new AbortController();
      const current = ++sequence;
      this.loading = true;
      this.error = "";
      try {
        const { data } = await api.get("/fiscality", {
          params: { year },
          signal: controller.signal,
        });
        if (current === sequence) this.data = data;
      } catch (error) {
        if (current === sequence && error.code !== "ERR_CANCELED") {
          this.error =
            "No hemos podido cargar la fiscalidad. Inténtalo de nuevo.";
          throw error;
        }
      } finally {
        if (current === sequence) this.loading = false;
      }
    },
    async saveProfile(year, profile) {
      await api.put(`/fiscality/${year}/profile`, profile);
      await this.load(year);
    },
    async saveProperty(year, propertyId, revision, inputs) {
      await api.put(`/fiscality/${year}/properties/${propertyId}`, {
        revision,
        inputs,
      });
      await this.load(year);
    },
    async download(year, format, propertyId = null, snapshotId = null) {
      if (this.downloading) return;
      this.downloading = true;
      try {
        const id =
          snapshotId ??
          (
            await api.post(`/fiscality/${year}/reports`, {
              property_id: propertyId,
            })
          ).data.id;
        const response = await api.get(`/fiscality/reports/${id}/${format}`, {
          responseType: "blob",
        });
        const url = URL.createObjectURL(response.data);
        const link = document.createElement("a");
        link.href = url;
        link.download = `alquivo-fiscal-${year}-${id}.${format}`;
        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        if (!snapshotId) await this.load(year);
      } catch (error) {
        if (error.response?.data instanceof Blob) {
          try {
            error.fiscalMessage = JSON.parse(
              await error.response.data.text(),
            ).message;
          } catch {
            /* Non-JSON error. */
          }
        }
        throw error;
      } finally {
        this.downloading = false;
      }
    },
    clear() {
      controller?.abort();
      sequence++;
      this.$reset();
    },
  },
});
