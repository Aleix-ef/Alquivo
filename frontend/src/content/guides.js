// Public editorial content only. Never populate these pages from a user's portfolio.
export const guides = [
  {
    path: "/guias/organizar-alquileres",
    title: "Cómo organizar la gestión de tus alquileres",
    description:
      "Una guía para propietarios: organiza inmuebles, inquilinos, contratos, cobros y documentos sin perderte entre hojas de cálculo.",
    summary:
      "Para gestionar tus alquileres con claridad, reúne la información por inmueble, separa lo previsto de lo cobrado y revisa los pendientes cada mes. Puedes empezar con una sola propiedad y ampliar después.",
    sections: [
      {
        title: "1. Crea una ficha por inmueble",
        paragraphs: [
          "Usa un nombre que reconozcas fácilmente y anota dirección, tipo de inmueble y estado. Mantén separados el precio de compra y el valor estimado actual: uno es un dato histórico y el otro una estimación, no una venta garantizada.",
          "Si tienes varios inmuebles, usa el mismo criterio para todos. Evitarás confundir un gasto de una vivienda con el de otra.",
        ],
      },
      {
        title: "2. Vincula el contrato y el contacto correcto",
        paragraphs: [
          "Guarda quién ocupa el inmueble, cómo contactar con esa persona, la renta mensual y las fechas del contrato. Conserva el documento firmado junto a su inmueble para poder encontrarlo cuando lo necesites.",
          "Recoge solo los datos personales necesarios. No copies documentos sensibles en notas compartidas ni los incluyas en nombres de archivos que otras personas puedan ver.",
        ],
      },
      {
        title: "3. Separa lo que esperas cobrar de lo que has cobrado",
        paragraphs: [
          "Una renta prevista no es un ingreso cobrado. Registra el vencimiento y mantén el pago pendiente hasta comprobar que has recibido el importe. Si registras pagos parciales por separado, comprueba que su suma no duplique la renta mensual.",
          "En Alquivo el seguimiento se hace con los movimientos que registras. No hay una conexión bancaria que confirme automáticamente los ingresos.",
        ],
      },
      {
        title: "4. Guarda gastos, justificantes e incidencias",
        paragraphs: [
          "Asocia cada gasto a su inmueble y conserva el justificante. Distingue una reparación puntual de un gasto periódico para interpretar mejor los cambios de un mes a otro.",
          "Una incidencia necesita, como mínimo, una descripción clara y un estado actualizado. Tenerla junto al inmueble ayuda a recordar qué sigue pendiente, aunque hayas hablado de ella por teléfono.",
        ],
      },
      {
        title: "5. Haz una revisión mensual breve",
        paragraphs: [
          "Comprueba las rentas pendientes, los gastos sin clasificar, los contratos próximos a finalizar y las incidencias abiertas. Revisa también que no falten justificantes y exporta la información que necesites compartir con tu asesor.",
          "El objetivo no es rellenar más campos, sino poder responder: qué he cobrado, qué he gastado y qué necesita atención.",
        ],
        checklist: [
          "Revisar cobros y vencimientos.",
          "Asignar gastos al inmueble correcto.",
          "Guardar los justificantes pendientes.",
          "Actualizar incidencias y fechas de contratos.",
          "Consultar el resultado del periodo y exportar si hace falta.",
        ],
      },
      {
        title: "¿Hoja de cálculo o aplicación para gestionar alquileres?",
        paragraphs: [
          "Una hoja de cálculo puede ser suficiente si tienes pocos movimientos y la mantienes al día. Una aplicación resulta útil cuando quieres relacionar contratos, documentos, cobros e incidencias sin buscar en varios sitios.",
          "Alquivo está pensado para propietarios particulares y pequeños inversores, no para la operativa de una gran agencia. Puedes empezar con una propiedad y comprobar si esta forma de trabajar encaja contigo.",
        ],
      },
    ],
  },
  {
    path: "/guias/control-cobros-gastos-alquiler",
    title: "Cómo llevar el control de cobros y gastos del alquiler",
    description:
      "Aprende a distinguir rentas pendientes, cobros recibidos y gastos por inmueble para entender el resultado mensual de tus alquileres.",
    summary:
      "El control de un alquiler empieza por separar tres cosas: lo que debe cobrarse, lo que realmente se ha cobrado y los gastos del mismo periodo. Agrupar esos movimientos por inmueble permite detectar pendientes y entender de dónde sale el resultado.",
    sections: [
      {
        title: "Qué datos necesitas en cada movimiento",
        paragraphs: [
          "Anota el inmueble, concepto, importe, fecha y estado del movimiento. Para los cobros pendientes, conserva también su vencimiento. Añade un justificante cuando corresponda y utiliza descripciones que puedas entender meses después.",
          "No mezcles una renta mensual con otros ingresos sin identificarlos: un ajuste o una devolución puede explicar un mes distinto sin que haya cambiado el alquiler.",
        ],
      },
      {
        title: "Pendiente no significa cobrado",
        paragraphs: [
          "Un contrato de 800 € al mes describe una renta acordada, pero no acredita que hayas recibido ese dinero. Comprueba el cobro antes de marcarlo como pagado y revisa los pendientes por fecha de vencimiento.",
          "Alquivo permite organizar el seguimiento de ingresos y gastos registrados. No mueve dinero, no cobra al inquilino y no verifica movimientos bancarios por sí solo.",
        ],
      },
      {
        title: "Un ejemplo sencillo de resultado mensual",
        paragraphs: [
          "Imagina que has cobrado 800 € y has registrado 180 € de gastos pagados en ese mismo mes. La diferencia de esos movimientos es 620 €. Si los 800 € siguen pendientes, no debes tratarlos como dinero ya recibido.",
          "Este ejemplo solo compara entradas y salidas registradas. No calcula el IRPF, no representa el beneficio fiscal y no incluye automáticamente amortizaciones, financiación u otros costes que no hayas considerado. Un resultado mensual tampoco equivale a una rentabilidad anual.",
        ],
      },
      {
        title: "Compara periodos con el mismo criterio",
        paragraphs: [
          "Antes de comparar dos meses, comprueba si estás mirando fechas de vencimiento o fechas de pago y si hay movimientos pendientes incluidos. Una reparación extraordinaria puede empeorar el resultado de un mes sin modificar la renta del contrato.",
          "Si un gasto corresponde a varios inmuebles, deja constancia del criterio de reparto y evita registrar el importe completo en cada uno. La calidad del resumen depende de la información que introduzcas.",
        ],
      },
      {
        title: "Qué revisar antes de compartir un informe",
        paragraphs: [
          "Comprueba el periodo, los inmuebles incluidos y el estado de los movimientos. Revisa que no haya duplicados, completa los justificantes y conserva una copia de la exportación que hayas compartido.",
          "Un informe de gestión ayuda a ordenar la información para tu asesor, pero no sustituye una declaración tributaria ni un criterio profesional sobre qué gastos son deducibles.",
        ],
        checklist: [
          "Mismo periodo y criterio de fechas.",
          "Pendientes separados de pagos confirmados.",
          "Gastos asociados al inmueble correcto.",
          "Sin movimientos duplicados.",
          "Justificantes localizables.",
        ],
      },
    ],
  },
];
