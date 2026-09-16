export async function fetchAllPages(api, url, options = {}) {
  const items = [];
  let page = 1;
  let lastPage = 1;

  do {
    const response = await api.get(url, {
      ...options,
      params: { ...(options.params || {}), page },
    });
    const payload = response.data;

    if (!Array.isArray(payload?.data)) {
      throw new TypeError(`La respuesta paginada de ${url} no es válida.`);
    }

    items.push(...payload.data);
    lastPage = Math.max(1, Number(payload.last_page || 1));
    page += 1;
  } while (page <= lastPage);

  return items;
}
