(function (global) {
  "use strict";

  var geoCache = null;

  var capitaisPorEstado = {
    AC: "Rio Branco",
    AL: "Maceió",
    AP: "Macapá",
    AM: "Manaus",
    BA: "Salvador",
    CE: "Fortaleza",
    DF: "Brasília",
    ES: "Vitória",
    GO: "Goiânia",
    MA: "São Luís",
    MT: "Cuiabá",
    MS: "Campo Grande",
    MG: "Belo Horizonte",
    PA: "Belém",
    PB: "João Pessoa",
    PR: "Curitiba",
    PE: "Recife",
    PI: "Teresina",
    RJ: "Rio de Janeiro",
    RN: "Natal",
    RS: "Porto Alegre",
    RO: "Porto Velho",
    RR: "Boa Vista",
    SC: "Florianópolis",
    SP: "São Paulo",
    SE: "Aracaju",
    TO: "Palmas"
  };

  function pad(numero) {
    return String(numero).padStart(2, "0");
  }

  function formatDate(data) {
    return data.toLocaleDateString("pt-BR");
  }

  function formatDateTime(data) {
    return formatDate(data) + " " + pad(data.getHours()) + ":" + pad(data.getMinutes());
  }

  function addDays(data, dias) {
    var copia = new Date(data.getTime());
    copia.setDate(copia.getDate() + dias);
    return copia;
  }

  function addHours(data, horas) {
    var copia = new Date(data.getTime());
    copia.setHours(copia.getHours() + horas);
    return copia;
  }

  function normalizeCity(cidade) {
    if (!cidade) return "";
    return cidade
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .split(" ")
      .map(function (parte) {
        return parte.charAt(0).toUpperCase() + parte.slice(1).toLowerCase();
      })
      .join(" ");
  }

  function buildUnidade(cidade, estado) {
    var uf = (estado || "SP").toUpperCase().slice(0, 2);
    var nomeCidade = normalizeCity(cidade) || capitaisPorEstado[uf] || "São Paulo";
    return "Centro de Distribuição de " + nomeCidade + "/" + uf;
  }

  function getFallbackGeo() {
    return {
      city: "São Paulo",
      state: "SP",
      unidade: buildUnidade("São Paulo", "SP")
    };
  }

  function parseGeoResponse(data, source) {
    if (!data) return null;

    if (source === "ipapi") {
      if (data.error) return null;
      return {
        city: data.city || capitaisPorEstado[data.region_code] || "",
        state: data.region_code || "SP",
        unidade: buildUnidade(data.city, data.region_code)
      };
    }

    if (source === "ipwho" && data.success) {
      return {
        city: data.city || "",
        state: data.region_code || "SP",
        unidade: buildUnidade(data.city, data.region_code)
      };
    }

    return null;
  }

  function fetchGeoLocation() {
    if (geoCache) {
      return Promise.resolve(geoCache);
    }

    return fetch("https://ipapi.co/json/", { cache: "no-store" })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        geoCache = parseGeoResponse(data, "ipapi") || getFallbackGeo();
        return geoCache;
      })
      .catch(function () {
        return fetch("https://ipwho.is/", { cache: "no-store" })
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            geoCache = parseGeoResponse(data, "ipwho") || getFallbackGeo();
            return geoCache;
          })
          .catch(function () {
            geoCache = getFallbackGeo();
            return geoCache;
          });
      });
  }

  function getTrackingDates(baseDate) {
    var agora = baseDate ? new Date(baseDate.getTime()) : new Date();

    return {
      prazoFinal: formatDate(addDays(agora, 1)) + " às 23:59",
      dataPrevisao: formatDate(addDays(agora, 5)),
      dataPendencia: formatDateTime(addHours(agora, -2)),
      dataAnalise: formatDateTime(addHours(addDays(agora, -1), -3)),
      dataTransferencia: formatDateTime(addHours(addDays(agora, -2), -5)),
      dataRecebido: formatDateTime(addHours(addDays(agora, -4), -2)),
      dataPostado: formatDateTime(addHours(addDays(agora, -6), -4))
    };
  }

  function setText(id, valor) {
    var elemento = document.getElementById(id);
    if (elemento) {
      elemento.textContent = valor;
    }
  }

  function applyTrackingPage() {
    var datas = getTrackingDates();

    setText("prazoFinal", datas.prazoFinal);
    setText("dataPrevisao", datas.dataPrevisao);
    setText("dataPendencia", datas.dataPendencia);
    setText("dataAnalise", datas.dataAnalise);
    setText("dataTransferencia", datas.dataTransferencia);
    setText("dataRecebido", datas.dataRecebido);
    setText("dataPostado", datas.dataPostado);

    return fetchGeoLocation().then(function (geo) {
      setText("unidadeEntrega", geo.unidade);
      setText("unidadeTimeline", geo.unidade);

      var textoRecebido = document.getElementById("textoRecebidoUnidade");
      if (textoRecebido) {
        var cidade = normalizeCity(geo.city) || "sua região";
        var uf = (geo.state || "BR").toUpperCase().slice(0, 2);
        textoRecebido.textContent =
          "Objeto recebido na unidade de tratamento de " + cidade + "/" + uf + " e encaminhado para triagem.";
      }

      return geo;
    });
  }

  global.CorreiosContext = {
    getTrackingDates: getTrackingDates,
    fetchGeoLocation: fetchGeoLocation,
    applyTrackingPage: applyTrackingPage,
    buildUnidade: buildUnidade
  };
})(window);
